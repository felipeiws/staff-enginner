<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Application;

use Atlas\Contracts\Dto\Actor;
use Atlas\Contracts\Dto\AuditRecord;
use Atlas\Contracts\Enum\GraphPredicate;
use Atlas\Contracts\Port\AuditTrail;
use Atlas\Contracts\Port\LifecyclePolicyEngine;
use Atlas\Modules\KnowledgeEngine\Domain\Aggregate\Assertion;
use Atlas\Modules\KnowledgeEngine\Domain\Aggregate\KnowledgeRevision;
use Atlas\Modules\KnowledgeEngine\Domain\Event\DomainEvent;
use Atlas\Modules\KnowledgeEngine\Domain\Repository\AssertionRepository;
use Atlas\Modules\KnowledgeEngine\Domain\Repository\EvidenceRepository;
use Atlas\Modules\KnowledgeEngine\Domain\Repository\KnowledgeRevisionRepository;
use Atlas\Modules\KnowledgeEngine\Domain\Repository\UnitOfWork;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Identifier;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\LifecycleState;
use DomainException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use RuntimeException;

/** The only application service allowed to change curation lifecycle states. */
final readonly class KnowledgeLifecycleService
{
    public function __construct(
        private KnowledgeRevisionRepository $revisions,
        private AssertionRepository $assertions,
        private EvidenceRepository $evidence,
        private UnitOfWork $unitOfWork,
        private DatabaseManager $database,
        private LifecyclePolicyEngine $policies,
        private AssertionGraphService $assertionGraph,
        private AuditTrail $auditTrail,
    ) {}

    public function transitionRevision(Identifier $id, LifecycleState $target, string $actorId, string $actorType, string $reason, string $idempotencyKey, ?string $correlationId = null, ?Identifier $successorId = null): KnowledgeRevision
    {
        $revision = $this->revisions->get($id) ?? throw new RuntimeException('Revisão de conhecimento não encontrada.');
        $this->assertReasonAndActor($actorId, $actorType, $reason);
        $this->authorize($revision->lifecycleState, $target, $this->policyForRevision($revision), $actorType);
        $event = $this->event('KnowledgeRevisionLifecycleChanged', $revision->entityId, $revision->id, $target, $actorId, $actorType, $idempotencyKey, $correlationId, $this->scopeForRevision($revision));

        return $this->unitOfWork->transactional(new DomainEvent('knowledge_revision', $event), function () use ($revision, $target, $actorId, $actorType, $reason, $correlationId, $successorId): KnowledgeRevision {
            $publishedAt = $target === LifecycleState::Published ? now()->toImmutable() : null;
            if ($target === LifecycleState::Superseded && $successorId === null) {
                throw new DomainException('Supersessão exige a identificação da revisão sucessora.');
            }
            $changed = $revision->transitionTo($target, $publishedAt, $successorId);
            if ($target === LifecycleState::Published) {
                foreach ($this->revisions->publishedForEntity($revision->entityId) as $previous) {
                    if ((string) $previous->id !== (string) $revision->id) {
                        $this->revisions->save($previous->transitionTo(LifecycleState::Superseded, null, $revision->id));
                        $this->assertionGraph->recomputeForSupersededRevision($previous->id, $revision->id);
                        $this->audit('entity_revision', $previous->entityId, $previous, $actorId, $actorType, 'knowledge.lifecycle.superseded', 'superseded', $reason, $correlationId);
                    }
                }
            }
            $this->revisions->save($changed);
            if ($target === LifecycleState::Superseded) {
                $this->assertionGraph->recomputeForSupersededRevision($changed->id, $successorId);
            }
            $this->audit('entity_revision', $changed->entityId, $changed, $actorId, $actorType, 'knowledge.lifecycle.changed', $target->value, $reason, $correlationId);
            if ($target === LifecycleState::Retracted) {
                $this->retractDependentAssertions($changed->id, $actorId, $actorType, $reason, $correlationId);
            }

            return $changed;
        });
    }

    public function transitionAssertion(Identifier $id, LifecycleState $target, string $actorId, string $actorType, string $reason, string $idempotencyKey, ?string $correlationId = null): Assertion
    {
        $assertion = $this->assertions->get($id) ?? throw new RuntimeException('Assertion não encontrada.');
        $this->assertReasonAndActor($actorId, $actorType, $reason);
        $this->requireEvidence($assertion, $target);
        $this->authorize($assertion->lifecycleState, $target, $this->policyForAssertion($assertion), $actorType);
        $event = $this->event('AssertionLifecycleChanged', $assertion->subjectRevisionId, $assertion->id, $target, $actorId, $actorType, $idempotencyKey, $correlationId, $assertion->scope->values);

        return $this->unitOfWork->transactional(new DomainEvent('assertion', $event), function () use ($assertion, $target, $actorId, $actorType, $reason, $correlationId): Assertion {
            $changed = $assertion->transitionTo($target, $target === LifecycleState::Retracted ? now()->toImmutable() : null);
            $this->assertions->save($changed);
            $this->audit('assertion', null, $changed, $actorId, $actorType, 'knowledge.lifecycle.changed', $target->value, $reason, $correlationId);
            if ($target === LifecycleState::Published) {
                $this->registerConflicts($changed);
            }

            return $changed;
        });
    }

    /** @param array{id: string, automatic_publication: bool, review_required: bool, approver_roles: list<string>, validity_days: int|null, retraction_behavior: string}|null $policy */
    private function authorize(LifecycleState $current, LifecycleState $target, ?array $policy, string $actorType): void
    {
        $allowed = match ($current) {
            LifecycleState::Observed => [LifecycleState::Candidate],
            LifecycleState::Candidate => [LifecycleState::Review, LifecycleState::Published],
            LifecycleState::Review => [LifecycleState::Published, LifecycleState::Retracted],
            LifecycleState::Published => [LifecycleState::Superseded, LifecycleState::Retracted],
            default => [],
        };
        if (! in_array($target, $allowed, true)) {
            throw new DomainException("Transição de ciclo de vida não permitida pelo workflow: {$current->value} → {$target->value}.");
        }
        $current->transitionTo($target);
        if ($target === LifecycleState::Published && $current === LifecycleState::Candidate && ! ($policy['automatic_publication'] ?? false)) {
            throw new DomainException('A política exige revisão antes da publicação.');
        }
        if ($current === LifecycleState::Candidate && $target === LifecycleState::Review && ($policy['automatic_publication'] ?? false)) {
            throw new DomainException('A política aplicável permite publicação automática.');
        }
        if ($current === LifecycleState::Review && $target === LifecycleState::Published && $actorType !== 'human') {
            throw new DomainException('Aprovação em revisão exige ator humano.');
        }
    }

    private function requireEvidence(Assertion $assertion, LifecycleState $target): void
    {
        if (in_array($target, [LifecycleState::Review, LifecycleState::Published], true) && ! $assertion->method->isHumanDeclared() && $this->evidence->forAssertion($assertion->id) === []) {
            throw new DomainException('Assertion sem evidência mínima não pode avançar no ciclo de vida.');
        }
    }

    private function retractDependentAssertions(Identifier $revisionId, string $actorId, string $actorType, string $reason, ?string $correlationId): void
    {
        foreach ($this->assertions->dependentsOfRevision($revisionId) as $assertion) {
            if ($assertion->lifecycleState !== LifecycleState::Published) {
                continue;
            }
            $retracted = $assertion->transitionTo(LifecycleState::Retracted, now()->toImmutable());
            $this->assertions->save($retracted);
            $this->audit('assertion', null, $retracted, $actorId, $actorType, 'knowledge.lifecycle.propagated_retraction', 'retracted', $reason, $correlationId);
        }
    }

    private function registerConflicts(Assertion $assertion): void
    {
        if ($assertion->predicate === GraphPredicate::Contradicts) {
            return;
        }
        foreach ($this->assertions->publishedWithSubjectAndPredicate($assertion->subjectRevisionId, $assertion->predicate) as $other) {
            if ((string) $other->id === (string) $assertion->id || $this->sameObject($assertion, $other)) {
                continue;
            }
            [$left, $right] = [(string) $assertion->id, (string) $other->id];
            if ($left > $right) {
                [$left, $right] = [$right, $left];
            }
            $this->database->table('assertion_relations')->insertOrIgnore(['id' => (string) Str::uuid(), 'source_assertion_id' => $left, 'target_assertion_id' => $right, 'predicate' => GraphPredicate::Contradicts->value, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function sameObject(Assertion $left, Assertion $right): bool
    {
        return $left->objectRevisionId !== null && $right->objectRevisionId !== null
            ? (string) $left->objectRevisionId === (string) $right->objectRevisionId
            : $left->literalValue === $right->literalValue;
    }

    /** @return array{id: string, automatic_publication: bool, review_required: bool, approver_roles: list<string>, validity_days: int|null, retraction_behavior: string}|null */
    private function policyForRevision(KnowledgeRevision $revision): ?array
    {
        $source = $this->database->table('entity_revisions')->join('revisions', 'revisions.id', '=', 'entity_revisions.source_revision_id')->join('artifacts', 'artifacts.id', '=', 'revisions.artifact_id')->join('sources', 'sources.id', '=', 'artifacts.source_id')->join('entities', 'entities.id', '=', 'entity_revisions.entity_id')->where('entity_revisions.id', (string) $revision->id)->first(['sources.type as source_type', 'sources.classification', 'entities.entity_type']);

        return $source === null ? null : $this->policies->resolve((string) $source->source_type, (string) $source->entity_type, (string) $source->classification);
    }

    /** @return array{id: string, automatic_publication: bool, review_required: bool, approver_roles: list<string>, validity_days: int|null, retraction_behavior: string}|null */
    private function policyForAssertion(Assertion $assertion): ?array
    {
        $revision = $this->revisions->get($assertion->subjectRevisionId) ?? throw new RuntimeException('Revisão-sujeito da assertion não encontrada.');

        return $this->policyForRevision($revision);
    }

    /** @return array<string, mixed> */
    private function scopeForRevision(KnowledgeRevision $revision): array
    {
        $scope = $this->database->table('entities')->where('id', (string) $revision->entityId)->value('scope');

        return is_string($scope) ? json_decode($scope, true, 512, JSON_THROW_ON_ERROR) : (array) $scope;
    }

    private function assertReasonAndActor(string $actorId, string $actorType, string $reason): void
    {
        if (trim($actorId) === '' || ! in_array($actorType, ['human', 'ci', 'plugin', 'model', 'service', 'worker'], true) || trim($reason) === '') {
            throw new DomainException('Ator válido e motivo são obrigatórios para transições de ciclo de vida.');
        }
    }

    /** @param array<string, mixed> $scope
     * @return array<string, mixed> */
    private function event(string $type, Identifier $entityId, Identifier $revisionId, LifecycleState $target, string $actorId, string $actorType, string $key, ?string $correlationId, array $scope): array
    {
        $actor = ['actor_id' => $actorId, 'actor_type' => $actorType];

        return ['event_id' => (string) Str::uuid(), 'event_type' => $type, 'schema_version' => '1.0.0', 'occurred_at' => now()->toISOString(), 'correlation_id' => $correlationId ?? (string) Str::uuid(), 'actor' => $actor, 'scope' => $scope, 'entity' => ['entity_id' => (string) $entityId, 'revision_id' => (string) $revisionId], 'payload' => ['lifecycle_state' => $target->value], 'command' => ['idempotency_key' => $key, 'actor' => $actor]];
    }

    private function audit(string $kind, ?Identifier $entityId, KnowledgeRevision|Assertion $subject, string $actorId, string $actorType, string $action, string $decision, string $reason, ?string $correlationId): void
    {
        $revision = $subject instanceof KnowledgeRevision ? $subject : $this->revisions->get($subject->subjectRevisionId);
        $this->auditTrail->record(new AuditRecord(new Actor($actorId, $actorType), $action, $kind, (string) $subject->id, $entityId === null ? ($revision === null ? null : (string) $revision->entityId) : (string) $entityId, $subject instanceof KnowledgeRevision ? (string) $subject->id : (string) $subject->subjectRevisionId, $revision === null ? null : (string) $revision->sourceRevisionId, $decision, $reason, $correlationId, [], null, ['lifecycle_state' => $subject->lifecycleState->value], null, $subject instanceof Assertion ? $subject->method->value : 'lifecycle_service', $subject->confidence->value));
    }
}
