<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Application;

use Atlas\Contracts\Dto\Actor;
use Atlas\Contracts\Dto\AuditRecord;
use Atlas\Contracts\Enum\GraphPredicate;
use Atlas\Contracts\Port\AuditTrail;
use Atlas\Modules\KnowledgeEngine\Domain\Aggregate\Assertion;
use Atlas\Modules\KnowledgeEngine\Domain\Aggregate\Evidence;
use Atlas\Modules\KnowledgeEngine\Domain\Aggregate\EvidenceKind;
use Atlas\Modules\KnowledgeEngine\Domain\Event\DomainEvent;
use Atlas\Modules\KnowledgeEngine\Domain\Repository\AssertionRepository;
use Atlas\Modules\KnowledgeEngine\Domain\Repository\EvidenceRepository;
use Atlas\Modules\KnowledgeEngine\Domain\Repository\KnowledgeRevisionRepository;
use Atlas\Modules\KnowledgeEngine\Domain\Repository\UnitOfWork;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Confidence;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\DerivationMethod;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Identifier;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\LifecycleState;
use DateTimeImmutable;
use DomainException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class AssertionGraphService
{
    public function __construct(
        private AssertionRepository $assertions,
        private EvidenceRepository $evidence,
        private KnowledgeRevisionRepository $revisions,
        private UnitOfWork $unitOfWork,
        private DatabaseManager $database,
        private AssertionConfidenceCalculator $confidence,
        private AuditTrail $auditTrail,
    ) {}

    public function create(CreateAssertion $command): Assertion
    {
        if (($command->object === null) === ($command->literalValue === null)) {
            throw new DomainException('Assertion deve ter exatamente um objeto ou valor literal.');
        }
        if (! in_array($command->actorType, ['human', 'ci', 'plugin', 'model', 'service', 'worker'], true)) {
            throw new DomainException('Tipo de ator inválido para assertion.');
        }
        $method = new DerivationMethod($command->method);
        if (! $method->isHumanDeclared() && $command->evidence === []) {
            throw new DomainException('parser, analyzer e inference exigem ao menos uma evidência.');
        }
        $subject = $this->resolve($command->subject);
        $object = $command->object === null ? null : $this->resolve($command->object);
        $evidence = $this->validatedEvidence($command->evidence);
        $score = $evidence === [] ? $command->declaredConfidence : $this->confidence->calculate($evidence, now()->toImmutable());
        $assertion = new Assertion($command->id, $subject, $command->predicate, $object, $command->literalValue, $command->scope, new Confidence($score), $method, $command->actorId, $command->actorType, LifecycleState::Candidate, now()->toImmutable());

        return $this->unitOfWork->transactional(new DomainEvent('assertion', $this->event('AssertionCreated', $assertion, $command->idempotencyKey, $command->correlationId)), function () use ($assertion, $command): Assertion {
            $this->assertions->save($assertion);
            foreach ($command->evidence as $item) {
                $this->evidence->save(new Evidence($item->id, $assertion->id, $item->kind, $item->pointerId, new Confidence($item->weight)));
            }
            $this->audit($assertion, 'knowledge.assertion.created', 'candidate', $command->correlationId);

            return $assertion;
        });
    }

    public function neighborhood(Identifier $entityId): EntityNeighborhood
    {
        $rows = $this->database->table('published_assertions as a')
            ->join('entity_revisions as subject_revision', 'subject_revision.id', '=', 'a.subject_entity_revision_id')
            ->join('entities as subject', 'subject.id', '=', 'subject_revision.entity_id')
            ->leftJoin('entity_revisions as object_revision', 'object_revision.id', '=', 'a.object_entity_revision_id')
            ->leftJoin('entities as object', 'object.id', '=', 'object_revision.entity_id')
            ->where(function ($query) use ($entityId): void {
                $query->where('subject.id', (string) $entityId)->orWhere('object.id', (string) $entityId);
            })
            ->orderBy('a.predicate')->orderBy('a.id')
            ->get(['a.id', 'a.predicate', 'a.confidence', 'a.method', 'a.scope', 'a.subject_entity_revision_id', 'a.object_entity_revision_id', 'a.literal_value', 'subject.id as subject_entity_id', 'subject.entity_type as subject_type', 'subject_revision.title as subject_title', 'object.id as object_entity_id', 'object.entity_type as object_type', 'object_revision.title as object_title']);
        $result = [];
        foreach ($rows as $row) {
            $predicate = GraphPredicate::from((string) $row->predicate);
            $outgoing = (string) $row->subject_entity_id === (string) $entityId;
            [$label, $inverted] = $this->labelFor($predicate, ! $outgoing);
            $counterpart = $outgoing ? ['entity_id' => $row->object_entity_id, 'revision_id' => $row->object_entity_revision_id, 'type' => $row->object_type, 'title' => $row->object_title] : ['entity_id' => $row->subject_entity_id, 'revision_id' => $row->subject_entity_revision_id, 'type' => $row->subject_type, 'title' => $row->subject_title];
            $result[$label][] = ['assertion_id' => $row->id, 'predicate' => $predicate->value, 'inverted' => $inverted, 'counterpart' => $counterpart, 'literal_value' => $row->literal_value, 'confidence' => (float) $row->confidence, 'method' => $row->method, 'scope' => $this->json($row->scope), 'evidence' => $this->evidenceReadModel((string) $row->id)];
        }

        return new EntityNeighborhood($result);
    }

    /** Replaces published assertion endpoints with the concrete successor and returns candidates for reapproval. */
    public function recomputeForSupersededRevision(Identifier $supersededId, Identifier $successorId): void
    {
        foreach ($this->assertions->dependentsOfRevision($supersededId) as $old) {
            if ($old->lifecycleState !== LifecycleState::Published) {
                continue;
            }
            $replacement = new Assertion(new Identifier((string) Str::uuid()), (string) $old->subjectRevisionId === (string) $supersededId ? $successorId : $old->subjectRevisionId, $old->predicate, $old->objectRevisionId !== null && (string) $old->objectRevisionId === (string) $supersededId ? $successorId : $old->objectRevisionId, $old->literalValue, $old->scope, new Confidence($this->confidenceFromStoredEvidence($old)), $old->method, $old->actorId, $old->actorType, LifecycleState::Candidate, now()->toImmutable());
            $this->unitOfWork->transactional(new DomainEvent('assertion', $this->event('AssertionRecomputed', $replacement, 'supersession:'.(string) $supersededId, null)), function () use ($old, $replacement): void {
                $this->assertions->save($old->transitionTo(LifecycleState::Superseded));
                $this->assertions->save($replacement);
                foreach ($this->evidence->forAssertion($old->id) as $item) {
                    $this->evidence->save(new Evidence(new Identifier((string) Str::uuid()), $replacement->id, $item->kind, $item->pointerId, $item->weight));
                }
                $this->audit($replacement, 'knowledge.assertion.recomputed', 'candidate', null);
            });
        }
    }

    private function resolve(RevisionReference $reference): Identifier
    {
        $query = $this->database->table('entity_revisions')->where('entity_id', (string) $reference->entityId);
        if ($reference->revisionId !== null) {
            $query->where('id', (string) $reference->revisionId);
        } else {
            $query->whereNotIn('lifecycle_state', [LifecycleState::Superseded->value, LifecycleState::Retracted->value])->orderByDesc('version');
        }
        $revision = $query->first(['id', 'lifecycle_state']);
        if ($revision === null || in_array($revision->lifecycle_state, [LifecycleState::Superseded->value, LifecycleState::Retracted->value], true)) {
            throw new RuntimeException('Não foi possível resolver a referência para uma revisão concreta e ativa.');
        }

        return new Identifier((string) $revision->id);
    }

    /** @param list<AssertionEvidenceInput> $items
     * @return list<array{weight: float, observed_at: DateTimeImmutable}> */
    private function validatedEvidence(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            $table = match ($item->kind) {
                EvidenceKind::Chunk => 'chunks', EvidenceKind::Artifact => 'artifacts', EvidenceKind::Event => 'observations',
            };
            $row = $this->database->table($table)->where('id', (string) $item->pointerId)->first(['created_at']);
            if ($row === null || $item->weight <= 0 || $item->weight > 1) {
                throw new DomainException('Evidência inválida, inexistente ou com peso fora do intervalo permitido.');
            }
            $result[] = ['weight' => $item->weight, 'observed_at' => new DateTimeImmutable((string) $row->created_at)];
        }

        return $result;
    }

    /** @return list<array<string, mixed>> */
    private function evidenceReadModel(string $assertionId): array
    {
        return array_values($this->database->table('assertion_evidence')->where('assertion_id', $assertionId)->orderBy('id')->get()->map(fn ($item): array => ['id' => $item->id, 'kind' => $item->evidence_type, 'pointer_id' => $item->{$item->evidence_type.'_id'}, 'weight' => (float) $item->weight])->all());
    }

    private function confidenceFromStoredEvidence(Assertion $assertion): float
    {
        $items = [];
        foreach ($this->evidence->forAssertion($assertion->id) as $item) {
            $items[] = ['weight' => $item->weight->value, 'observed_at' => $this->evidenceObservedAt($item->kind, $item->pointerId)];
        }

        return $items === [] ? $assertion->confidence->value : $this->confidence->calculate($items, now()->toImmutable());
    }

    private function evidenceObservedAt(EvidenceKind $kind, Identifier $pointer): DateTimeImmutable
    {
        $table = match ($kind) {
            EvidenceKind::Chunk => 'chunks', EvidenceKind::Artifact => 'artifacts', EvidenceKind::Event => 'observations',
        };
        $value = $this->database->table($table)->where('id', (string) $pointer)->value('created_at');

        return new DateTimeImmutable((string) $value);
    }

    /** @return array{0: string, 1: bool} */
    private function labelFor(GraphPredicate $predicate, bool $inverse): array
    {
        $inversePredicate = match ($predicate) {
            GraphPredicate::Contains => GraphPredicate::BelongsTo,
            GraphPredicate::BelongsTo => GraphPredicate::Contains,
            GraphPredicate::Exposes => GraphPredicate::Implements,
            GraphPredicate::Implements => GraphPredicate::Exposes,
            GraphPredicate::Produces => GraphPredicate::Consumes,
            GraphPredicate::Consumes => GraphPredicate::Produces,
            default => $predicate,
        };

        return [($inverse ? $inversePredicate : $predicate)->value, $inverse];
    }

    /** @return array<string, mixed> */
    private function event(string $type, Assertion $assertion, string $key, ?string $correlation): array
    {
        $actor = ['actor_id' => $assertion->actorId, 'actor_type' => $assertion->actorType];
        $subjectRevision = $this->revisions->get($assertion->subjectRevisionId);
        $entityId = $subjectRevision === null ? $assertion->subjectRevisionId : $subjectRevision->entityId;

        return ['event_id' => (string) Str::uuid(), 'event_type' => $type, 'schema_version' => '1.0.0', 'occurred_at' => now()->toISOString(), 'correlation_id' => $correlation ?? (string) Str::uuid(), 'actor' => $actor, 'scope' => $assertion->scope->values, 'entity' => ['entity_id' => (string) $entityId, 'revision_id' => (string) $assertion->subjectRevisionId], 'payload' => ['assertion_id' => (string) $assertion->id, 'predicate' => $assertion->predicate->value, 'confidence' => $assertion->confidence->value, 'method' => $assertion->method->value], 'command' => ['idempotency_key' => $key, 'actor' => $actor]];
    }

    private function audit(Assertion $assertion, string $action, string $decision, ?string $correlation): void
    {
        $revision = $this->revisions->get($assertion->subjectRevisionId);
        $this->auditTrail->record(new AuditRecord(new Actor($assertion->actorId, $assertion->actorType), $action, 'assertion', (string) $assertion->id, $revision === null ? null : (string) $revision->entityId, (string) $assertion->subjectRevisionId, $revision === null ? null : (string) $revision->sourceRevisionId, $decision, 'Mutação rastreável de assertion.', $correlation, $assertion->scope->values, null, ['assertion_id' => (string) $assertion->id, 'confidence' => $assertion->confidence->value], null, $assertion->method->value, $assertion->confidence->value));
    }

    /** @return array<string, mixed> */
    private function json(mixed $value): array
    {
        return is_string($value) ? json_decode($value, true, 512, JSON_THROW_ON_ERROR) : (array) $value;
    }
}
