<?php

declare(strict_types=1);

namespace Atlas\Contracts\Factory;

use Atlas\Contracts\Dto\Actor;
use Atlas\Contracts\Dto\Approval;
use Atlas\Contracts\Dto\Assertion;
use Atlas\Contracts\Dto\AuditEntry;
use Atlas\Contracts\Dto\Command;
use Atlas\Contracts\Dto\ContentReference;
use Atlas\Contracts\Dto\DomainEvent;
use Atlas\Contracts\Dto\EntityReference;
use Atlas\Contracts\Dto\Evidence;
use Atlas\Contracts\Dto\KnowledgeEntity;
use Atlas\Contracts\Dto\KnowledgeRevision;
use Atlas\Contracts\Dto\Offsets;
use Atlas\Contracts\Dto\Provenance;
use Atlas\Contracts\Dto\SourceObservation;
use Atlas\Contracts\Dto\Subject;
use Atlas\Contracts\Enum\GraphPredicate;
use Atlas\Contracts\Enum\KnowledgeLifecycleState;
use Atlas\Contracts\Exception\ContractDeserializationException;
use Atlas\Contracts\Validation\JsonSchemaValidator;

/** Converte arrays validados pelos JSON Schemas v1 em DTOs imutáveis. */
final class ContractFactory
{
    public function __construct(private readonly JsonSchemaValidator $validator = new JsonSchemaValidator()) {}

    /** @param array<string, mixed> $data */
    public function sourceObservation(array $data): SourceObservation
    {
        $this->validate('source-observation', $data);
        return new SourceObservation($this->string($data, 'schema_version'), $this->string($data, 'observation_id'), $this->string($data, 'external_id'), $this->string($data, 'source_type'), $this->string($data, 'uri'), $this->string($data, 'revision'), $this->string($data, 'observed_at'), $this->nullableString($data, 'content'), isset($data['content_ref']) ? $this->contentReference($this->array($data, 'content_ref')) : null, $this->string($data, 'checksum'), $this->array($data, 'metadata'), $this->string($data, 'classification'), $this->command($this->array($data, 'command')), $this->extra($data, ['schema_version', 'observation_id', 'external_id', 'source_type', 'uri', 'revision', 'observed_at', 'content', 'content_ref', 'checksum', 'metadata', 'classification', 'command']));
    }

    /** @param array<string, mixed> $data */
    public function knowledgeEntity(array $data): KnowledgeEntity
    {
        $this->validate('knowledge-entity', $data);
        return new KnowledgeEntity($this->string($data, 'schema_version'), $this->string($data, 'entity_id'), $this->string($data, 'entity_type'), $this->string($data, 'stable_key'), KnowledgeLifecycleState::from($this->string($data, 'lifecycle_state')), $this->provenance($this->array($data, 'provenance')), $this->command($this->array($data, 'command')), $this->extra($data, ['schema_version', 'entity_id', 'entity_type', 'stable_key', 'lifecycle_state', 'provenance', 'command']));
    }

    /** @param array<string, mixed> $data */
    public function knowledgeRevision(array $data): KnowledgeRevision
    {
        $this->validate('knowledge-revision', $data);
        return new KnowledgeRevision($this->string($data, 'schema_version'), $this->string($data, 'revision_id'), $this->string($data, 'entity_id'), $this->string($data, 'source_observation_id'), $this->string($data, 'source_revision'), KnowledgeLifecycleState::from($this->string($data, 'lifecycle_state')), $this->string($data, 'method'), $this->float($data, 'confidence'), $this->string($data, 'processed_at'), $this->string($data, 'checksum'), isset($data['content_ref']) ? $this->array($data, 'content_ref') : null, $this->command($this->array($data, 'command')), $this->extra($data, ['schema_version', 'revision_id', 'entity_id', 'source_observation_id', 'source_revision', 'lifecycle_state', 'method', 'confidence', 'processed_at', 'checksum', 'content_ref', 'command']));
    }

    /** @param array<string, mixed> $data */
    public function assertion(array $data): Assertion
    {
        $this->validate('assertion', $data);
        return new Assertion($this->string($data, 'schema_version'), $this->string($data, 'assertion_id'), $this->entityReference($this->array($data, 'subject')), GraphPredicate::from($this->string($data, 'predicate')), isset($data['object']) ? $this->entityReference($this->array($data, 'object')) : null, $data['value'] ?? null, $this->array($data, 'scope'), $this->float($data, 'confidence'), $this->string($data, 'method'), $this->actor($this->array($data, 'author')), KnowledgeLifecycleState::from($this->string($data, 'lifecycle_state')), $this->provenance($this->array($data, 'provenance')), $this->strings($data['evidence_ids'] ?? []), $this->command($this->array($data, 'command')), $this->extra($data, ['schema_version', 'assertion_id', 'subject', 'predicate', 'object', 'value', 'scope', 'confidence', 'method', 'author', 'lifecycle_state', 'provenance', 'evidence_ids', 'command']));
    }

    /** @param array<string, mixed> $data */
    public function evidence(array $data): Evidence
    {
        $this->validate('evidence', $data);
        return new Evidence($this->string($data, 'schema_version'), $this->string($data, 'evidence_id'), $this->string($data, 'kind'), $this->string($data, 'pointer'), isset($data['offsets']) ? $this->offsets($this->array($data, 'offsets')) : null, $this->string($data, 'checksum'), $this->provenance($this->array($data, 'provenance')), $this->command($this->array($data, 'command')), $this->extra($data, ['schema_version', 'evidence_id', 'kind', 'pointer', 'offsets', 'checksum', 'provenance', 'command']));
    }

    /** @param array<string, mixed> $data */
    public function approval(array $data): Approval
    {
        $this->validate('approval', $data);
        return new Approval($this->string($data, 'schema_version'), $this->string($data, 'approval_id'), $this->subject($this->array($data, 'subject')), $this->string($data, 'decision'), $this->string($data, 'decided_at'), $this->actor($this->array($data, 'decided_by')), $this->string($data, 'reason'), $this->provenance($this->array($data, 'provenance')), $this->command($this->array($data, 'command')), $this->extra($data, ['schema_version', 'approval_id', 'subject', 'decision', 'decided_at', 'decided_by', 'reason', 'provenance', 'command']));
    }

    /** @param array<string, mixed> $data */
    public function auditEntry(array $data): AuditEntry
    {
        $this->validate('audit-entry', $data);
        return new AuditEntry($this->string($data, 'schema_version'), $this->string($data, 'audit_entry_id'), $this->string($data, 'occurred_at'), $this->string($data, 'action'), $this->actor($this->array($data, 'actor')), $this->array($data, 'scope'), $this->subject($this->array($data, 'subject')), $this->string($data, 'reason'), $this->string($data, 'correlation_id'), $this->command($this->array($data, 'command')), $this->extra($data, ['schema_version', 'audit_entry_id', 'occurred_at', 'action', 'actor', 'scope', 'subject', 'reason', 'correlation_id', 'command']));
    }

    /** @param array<string, mixed> $data */
    public function domainEvent(array $data): DomainEvent
    {
        $this->validate('domain-event', $data);
        return new DomainEvent($this->string($data, 'event_id'), $this->string($data, 'event_type'), $this->string($data, 'schema_version'), $this->string($data, 'occurred_at'), $this->string($data, 'correlation_id'), $this->actor($this->array($data, 'actor')), $this->array($data, 'scope'), $this->entityReference($this->array($data, 'entity')), isset($data['payload']) ? $this->array($data, 'payload') : null, $this->command($this->array($data, 'command')), $this->extra($data, ['event_id', 'event_type', 'schema_version', 'occurred_at', 'correlation_id', 'actor', 'scope', 'entity', 'payload', 'command']));
    }

    /** @param array<string, mixed> $data */
    private function validate(string $contract, array $data): void { $this->validator->assertValid($contract, $data); }
    private function actor(mixed $value): Actor { return new Actor($this->string($this->record($value), 'actor_id'), $this->string($this->record($value), 'actor_type'), $this->nullableString($this->record($value), 'display_name')); }
    private function command(mixed $value): Command { $value = $this->record($value); return new Command($this->string($value, 'idempotency_key'), $this->actor($this->array($value, 'actor')), $this->nullableString($value, 'correlation_id')); }
    private function provenance(mixed $value): Provenance { $value = $this->record($value); return new Provenance($this->string($value, 'source_revision'), $this->string($value, 'method'), $this->string($value, 'processed_at'), $this->nullableString($value, 'source_observation_id'), isset($value['confidence']) ? $this->float($value, 'confidence') : null); }
    private function entityReference(mixed $value): EntityReference { $value = $this->record($value); return new EntityReference($this->string($value, 'entity_id'), $this->nullableString($value, 'revision_id')); }
    private function contentReference(mixed $value): ContentReference { $value = $this->record($value); return new ContentReference($this->string($value, 'uri'), $this->string($value, 'checksum'), $this->nullableString($value, 'media_type')); }
    private function subject(mixed $value): Subject { $value = $this->record($value); return new Subject($this->string($value, 'kind'), $this->string($value, 'id'), $this->nullableString($value, 'revision_id')); }
    private function offsets(mixed $value): Offsets { $value = $this->record($value); return new Offsets(isset($value['start']) && is_int($value['start']) ? $value['start'] : null, isset($value['end']) && is_int($value['end']) ? $value['end'] : null, $this->nullableString($value, 'unit')); }
    private function string(mixed $value, string $key): string { $value = $this->record($value); if (!isset($value[$key]) || !is_string($value[$key])) throw new ContractDeserializationException("Campo '$key' ausente ou inválido."); return $value[$key]; }
    private function nullableString(mixed $value, string $key): ?string { $value = $this->record($value); return isset($value[$key]) ? $this->string($value, $key) : null; }
    private function array(mixed $value, string $key): mixed { $value = $this->record($value); if (!isset($value[$key]) || !is_array($value[$key])) throw new ContractDeserializationException("Campo '$key' ausente ou inválido."); return $value[$key]; }
    private function float(mixed $value, string $key): float { $value = $this->record($value); if (!isset($value[$key]) || !is_numeric($value[$key])) throw new ContractDeserializationException("Campo '$key' ausente ou inválido."); return (float) $value[$key]; }
    private function strings(mixed $values): mixed { if (!is_array($values)) throw new ContractDeserializationException('Lista inválida.'); foreach ($values as $value) if (!is_string($value)) throw new ContractDeserializationException('Lista contém valor inválido.'); return array_values($values); }
    private function extra(mixed $value, mixed $known): mixed
    {
        if (!is_array($known)) {
            throw new ContractDeserializationException('Lista de campos conhecidos inválida.');
        }

        $extra = [];
        foreach ($this->record($value) as $key => $item) {
            if (is_string($key) && !in_array($key, $known, true)) {
                $extra[$key] = $item;
            }
        }
        return $extra;
    }

    /** @return array<mixed> */
    private function record(mixed $value): array { if (!is_array($value)) throw new ContractDeserializationException('Objeto aninhado inválido.'); return $value; }
}
