<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Application;

use Atlas\Contracts\Dto\AuditHistoryEntry;
use Atlas\Contracts\Dto\AuditRecord;
use Atlas\Contracts\Port\AuditTrail;
use DomainException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;

/** PostgreSQL-backed, append-only implementation of the cross-platform audit boundary. */
final readonly class AuditTrailService implements AuditTrail
{
    public function __construct(private DatabaseManager $database, private AuditPayloadRedactor $redactor) {}

    public function record(AuditRecord $record): void
    {
        if (trim($record->actor->actorId) === '' || trim($record->action) === '' || trim($record->reason) === '' || trim($record->subjectKind) === '' || trim($record->subjectId) === '') {
            throw new DomainException('Ator, ação, sujeito e motivo são obrigatórios para auditoria.');
        }
        if (! in_array($record->actor->actorType, ['human', 'ci', 'plugin', 'model', 'service', 'worker'], true)) {
            throw new DomainException('Tipo de ator inválido para auditoria.');
        }

        $this->database->table('audit_log')->insert([
            'id' => (string) Str::uuid(), 'occurred_at' => now(),
            'actor_id' => $record->actor->actorId, 'actor_type' => $record->actor->actorType,
            'action' => $record->action, 'subject_kind' => $record->subjectKind, 'subject_id' => $record->subjectId,
            'entity_id' => $record->entityId, 'entity_revision_id' => $record->entityRevisionId,
            'decision' => $record->decision, 'reason' => $record->reason, 'correlation_id' => $record->correlationId ?? (string) Str::uuid(),
            'before_payload' => $record->beforePayload === null ? null : json_encode($this->redactor->redact($record->beforePayload), JSON_THROW_ON_ERROR),
            'after_payload' => $record->afterPayload === null ? null : json_encode($this->redactor->redact($record->afterPayload), JSON_THROW_ON_ERROR),
            'source_revision_id' => $record->sourceRevisionId, 'classification' => $record->classification,
            'method' => $record->method, 'confidence' => $record->confidence,
        ]);
    }

    /** @return list<AuditHistoryEntry> */
    public function historyForEntity(string $entityId): array
    {
        return array_values($this->database->table('audit_log')->where('entity_id', $entityId)->orderByDesc('occurred_at')->orderByDesc('id')->get(['occurred_at', 'actor_id', 'actor_type', 'reason'])->map(fn (object $row): AuditHistoryEntry => new AuditHistoryEntry((string) $row->occurred_at, (string) $row->actor_id, (string) $row->reason, (string) $row->actor_type, $this->colorFor((string) $row->actor_type)))->all());
    }

    private function colorFor(string $actorType): string
    {
        return match ($actorType) {
            'model' => 'var(--violet)', 'human' => 'var(--warn)', 'ci' => 'var(--info)',
            'plugin' => 'var(--acc)', default => 'var(--tx3)',
        };
    }
}
