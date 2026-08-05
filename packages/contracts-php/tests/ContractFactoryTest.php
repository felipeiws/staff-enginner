<?php

declare(strict_types=1);

namespace Atlas\Contracts\Tests;

use Atlas\Contracts\Enum\GraphPredicate;
use Atlas\Contracts\Enum\KnowledgeLifecycleState;
use Atlas\Contracts\Exception\ContractViolation;
use Atlas\Contracts\Factory\ContractFactory;
use PHPUnit\Framework\TestCase;

final class ContractFactoryTest extends TestCase
{
    private const ID = '75e7d5be-30e0-4fd9-b948-f821257d0a24';

    public function testItDeserializesAnAssertionWithProvenance(): void
    {
        $assertion = (new ContractFactory())->assertion([
            'schema_version' => '1.0.0', 'assertion_id' => self::ID,
            'subject' => ['entity_id' => self::ID], 'predicate' => 'contains', 'value' => 'compras',
            'scope' => ['application' => 'atlas'], 'confidence' => 0.8, 'method' => 'parser@1.0.0',
            'author' => ['actor_id' => 'ana@example.com', 'actor_type' => 'human'], 'lifecycle_state' => 'candidate',
            'provenance' => ['source_revision' => 'git:abc123', 'method' => 'parser@1.0.0', 'processed_at' => '2026-07-31T12:00:00Z'],
            'evidence_ids' => [self::ID],
            'command' => ['idempotency_key' => 'cmd-001', 'actor' => ['actor_id' => 'ana@example.com', 'actor_type' => 'human']],
            'future_field' => 'preserved for forward compatibility',
        ]);

        self::assertSame(GraphPredicate::Contains, $assertion->predicate);
        self::assertSame(KnowledgeLifecycleState::Candidate, $assertion->lifecycleState);
        self::assertSame('git:abc123', $assertion->provenance->sourceRevision);
        self::assertSame('preserved for forward compatibility', $assertion->additionalProperties['future_field']);
    }

    public function testItRejectsAnInvalidSchemaInstanceWithATypedException(): void
    {
        $this->expectException(ContractViolation::class);
        (new ContractFactory())->knowledgeEntity([
            'schema_version' => '1.0.0', 'entity_id' => 'not-a-uuid', 'entity_type' => 'Processo',
            'stable_key' => 'compras.cancelamento', 'lifecycle_state' => 'candidate',
            'provenance' => ['source_observation_id' => self::ID, 'source_revision' => 'git:abc123', 'method' => 'parser@1.0.0', 'confidence' => 0.9, 'processed_at' => '2026-07-31T12:00:00Z'],
            'command' => ['idempotency_key' => 'cmd-001', 'actor' => ['actor_id' => 'ana@example.com', 'actor_type' => 'human']],
        ]);
    }

    public function testEveryVersionOneContractHasADeserializationFactory(): void
    {
        $factory = new ContractFactory();
        $actor = ['actor_id' => 'ana@example.com', 'actor_type' => 'human'];
        $command = ['idempotency_key' => 'cmd-001', 'actor' => $actor];
        $provenance = ['source_revision' => 'git:abc123', 'method' => 'parser@1.0.0', 'processed_at' => '2026-07-31T12:00:00Z'];
        $reference = ['entity_id' => self::ID];

        self::assertSame(self::ID, $factory->sourceObservation(['schema_version' => '1.0.0', 'observation_id' => self::ID, 'external_id' => 'github:atlas:README', 'source_type' => 'repository_file', 'uri' => 'https://example.test/README.md', 'revision' => 'abc123', 'observed_at' => '2026-07-31T12:00:00Z', 'content' => '# Atlas', 'checksum' => 'sha256:abc', 'metadata' => [], 'classification' => 'internal', 'command' => $command])->observationId);
        self::assertSame(self::ID, $factory->knowledgeEntity(['schema_version' => '1.0.0', 'entity_id' => self::ID, 'entity_type' => 'Processo', 'stable_key' => 'compras.cancelamento', 'lifecycle_state' => 'candidate', 'provenance' => ['source_observation_id' => self::ID, ...$provenance, 'confidence' => 0.9], 'command' => $command])->entityId);
        self::assertSame(self::ID, $factory->knowledgeRevision(['schema_version' => '1.0.0', 'revision_id' => self::ID, 'entity_id' => self::ID, 'source_observation_id' => self::ID, 'source_revision' => 'git:abc123', 'lifecycle_state' => 'candidate', 'method' => 'parser@1.0.0', 'confidence' => 0.9, 'processed_at' => '2026-07-31T12:00:00Z', 'checksum' => 'sha256:abc', 'command' => $command])->revisionId);
        self::assertSame(self::ID, $factory->evidence(['schema_version' => '1.0.0', 'evidence_id' => self::ID, 'kind' => 'chunk', 'pointer' => 's3://atlas/a#1', 'checksum' => 'sha256:abc', 'provenance' => $provenance, 'command' => $command])->evidenceId);
        self::assertSame(self::ID, $factory->approval(['schema_version' => '1.0.0', 'approval_id' => self::ID, 'subject' => ['kind' => 'assertion', 'id' => self::ID], 'decision' => 'approved', 'decided_at' => '2026-07-31T12:00:00Z', 'decided_by' => $actor, 'reason' => 'Evidência suficiente.', 'provenance' => $provenance, 'command' => $command])->approvalId);
        self::assertSame(self::ID, $factory->auditEntry(['schema_version' => '1.0.0', 'audit_entry_id' => self::ID, 'occurred_at' => '2026-07-31T12:00:00Z', 'action' => 'assertion.approved', 'actor' => $actor, 'scope' => [], 'subject' => ['kind' => 'assertion', 'id' => self::ID], 'reason' => 'Evidência suficiente.', 'correlation_id' => self::ID, 'command' => $command])->auditEntryId);
        self::assertSame(self::ID, $factory->domainEvent(['event_id' => self::ID, 'event_type' => 'KnowledgeRevision.Created', 'schema_version' => '1.0.0', 'occurred_at' => '2026-07-31T12:00:00Z', 'correlation_id' => self::ID, 'actor' => $actor, 'scope' => [], 'entity' => $reference, 'command' => $command])->eventId);
    }
}
