<?php

declare(strict_types=1);

use Atlas\Contracts\Enum\GraphPredicate;
use Atlas\Modules\KnowledgeEngine\Domain\Aggregate\Assertion;
use Atlas\Modules\KnowledgeEngine\Domain\Aggregate\Evidence;
use Atlas\Modules\KnowledgeEngine\Domain\Aggregate\EvidenceKind;
use Atlas\Modules\KnowledgeEngine\Domain\Aggregate\KnowledgeEntity;
use Atlas\Modules\KnowledgeEngine\Domain\Aggregate\KnowledgeRevision;
use Atlas\Modules\KnowledgeEngine\Domain\Aggregate\Source;
use Atlas\Modules\KnowledgeEngine\Domain\Event\DomainEvent;
use Atlas\Modules\KnowledgeEngine\Domain\Repository\AssertionRepository;
use Atlas\Modules\KnowledgeEngine\Domain\Repository\EvidenceRepository;
use Atlas\Modules\KnowledgeEngine\Domain\Repository\KnowledgeEntityRepository;
use Atlas\Modules\KnowledgeEngine\Domain\Repository\KnowledgeRevisionRepository;
use Atlas\Modules\KnowledgeEngine\Domain\Repository\SourceRepository;
use Atlas\Modules\KnowledgeEngine\Domain\Repository\UnitOfWork;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Confidence;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\DerivationMethod;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Identifier;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\LifecycleState;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Scope;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\StableIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function repositoryId(int $number): Identifier
{
    return new Identifier(sprintf('20000000-0000-4000-8000-%012d', $number));
}

/** @return array<string, mixed> */
function repositoryEvent(Identifier $entityId, int $number = 99): array
{
    $actor = ['actor_id' => 'service:knowledge-engine', 'actor_type' => 'service'];

    return ['event_id' => sprintf('20000000-0000-4000-8000-%012d', $number), 'event_type' => 'KnowledgeEntity.Created', 'schema_version' => '1.0.0', 'occurred_at' => now()->toISOString(), 'correlation_id' => sprintf('20000000-0000-4000-8000-%012d', $number + 100), 'actor' => $actor, 'scope' => ['application' => 'atlas'], 'entity' => ['entity_id' => (string) $entityId], 'payload' => ['method' => 'human_declared', 'confidence' => 1.0], 'command' => ['idempotency_key' => "repository-test-entity-{$number}", 'actor' => $actor]];
}

it('maps domain aggregates explicitly and appends the event in the same unit of work', function (): void {
    $source = new Source(repositoryId(1), 'git_repository', 'github', new Scope(['organization' => 'atlas']), 'vault://atlas/github', 'internal');
    $entity = new KnowledgeEntity(repositoryId(2), new StableIdentity('Endpoint', 'atlas/api/health'), 'software', new Scope(['application' => 'atlas']), 'team:platform');
    $sources = app(SourceRepository::class);
    $entities = app(KnowledgeEntityRepository::class);

    app(UnitOfWork::class)->transactional(new DomainEvent('knowledge_entity', repositoryEvent($entity->id)), function () use ($sources, $entities, $source, $entity): void {
        $sources->save($source);
        $entities->save($entity);
    });

    expect($sources->get($source->id))->toEqual($source)
        ->and($entities->get($entity->id))->toEqual($entity)
        ->and(DB::table('outbox_events')->where('event_id', '20000000-0000-4000-8000-000000000099')->value('aggregate'))->toBe('knowledge_entity');
});

it('round-trips revisions, assertions and evidence through explicit mappers', function (): void {
    $source = new Source(repositoryId(10), 'git_repository', 'github', new Scope(['organization' => 'atlas']), null, 'internal');
    $entity = new KnowledgeEntity(repositoryId(11), new StableIdentity('Endpoint', 'atlas/api/graph'), 'software', new Scope(['application' => 'atlas']), 'team:platform');
    $artifactId = repositoryId(12);
    $sourceRevisionId = repositoryId(13);
    $revision = new KnowledgeRevision(repositoryId(14), $entity->id, 1, LifecycleState::Candidate, 'Grafo', 'Descrição', ['path' => 'routes/api.php'], $sourceRevisionId, new Confidence(0.91));
    $assertion = new Assertion(repositoryId(15), $revision->id, GraphPredicate::Documents, null, ['uri' => 'https://atlas.example/graph'], new Scope(['application' => 'atlas']), new Confidence(1.0), new DerivationMethod('human_declared'), 'human:ana', 'human', LifecycleState::Candidate);
    $evidence = new Evidence(repositoryId(16), $assertion->id, EvidenceKind::Artifact, $artifactId, new Confidence(0.8));

    app(UnitOfWork::class)->transactional(new DomainEvent('knowledge_entity', repositoryEvent($entity->id, 120)), function () use ($source, $entity): void {
        app(SourceRepository::class)->save($source);
        app(KnowledgeEntityRepository::class)->save($entity);
    });
    DB::table('artifacts')->insert(['id' => (string) $artifactId, 'source_id' => (string) $source->id, 'external_identifier' => 'routes/api.php', 'uri' => 'file:///routes/api.php', 'content_hash' => 'hash', 'media_type' => 'text/plain', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('revisions')->insert(['id' => (string) $sourceRevisionId, 'artifact_id' => (string) $artifactId, 'source_revision' => 'git:abc123', 'observed_at' => now(), 'checksum' => 'checksum', 'metadata' => json_encode([]), 'created_at' => now(), 'updated_at' => now()]);

    app(UnitOfWork::class)->transactional(new DomainEvent('assertion', repositoryEvent($entity->id, 121)), function () use ($revision, $assertion, $evidence): void {
        app(KnowledgeRevisionRepository::class)->save($revision);
        app(AssertionRepository::class)->save($assertion);
        app(EvidenceRepository::class)->save($evidence);
    });

    expect(app(KnowledgeRevisionRepository::class)->get($revision->id))->toEqual($revision)
        ->and(app(AssertionRepository::class)->get($assertion->id))->toEqual($assertion)
        ->and(app(EvidenceRepository::class)->forAssertion($assertion->id))->toEqual([$evidence]);
});

it('refuses graph writes outside a unit of work', function (): void {
    $entity = new KnowledgeEntity(repositoryId(3), new StableIdentity('Endpoint', 'atlas/api/ready'), 'software', new Scope(['application' => 'atlas']), 'team:platform');
    expect(fn () => app(KnowledgeEntityRepository::class)->save($entity))->toThrow(LogicException::class);
});
