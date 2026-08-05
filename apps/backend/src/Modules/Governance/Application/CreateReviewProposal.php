<?php

declare(strict_types=1);

namespace Atlas\Modules\Governance\Application;

use Atlas\Contracts\Dto\Actor;
use Atlas\Contracts\Dto\AuditRecord;
use Atlas\Contracts\Port\AuditTrail;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;

/** Creates an auditable, still-undecided human-review proposal for a review revision. */
final readonly class CreateReviewProposal
{
    public function __construct(private DatabaseManager $database, private AuditTrail $auditTrail) {}

    /** @param array<string, mixed> $proposal */
    public function create(string $revisionId, array $proposal, string $proposedById, string $proposedByType, string $correlationId): string
    {
        $revision = $this->database->table('entity_revisions')->where('id', $revisionId)->where('lifecycle_state', 'review')->first();
        if ($revision === null) {
            throw new \RuntimeException('Proposta pendente exige uma revisão no estado review.');
        }
        $id = (string) Str::uuid();
        $now = now();
        $this->database->transaction(function () use ($id, $revisionId, $proposal, $proposedById, $proposedByType, $correlationId, $revision, $now): void {
            $this->database->table('approvals')->insert(['id' => $id, 'subject_kind' => 'entity_revision', 'entity_revision_id' => $revisionId, 'proposal' => json_encode($proposal, JSON_THROW_ON_ERROR), 'proposed_by_id' => $proposedById, 'proposed_by_type' => $proposedByType, 'proposed_at' => $now, 'correlation_id' => $correlationId, 'created_at' => $now, 'updated_at' => $now]);
            $this->auditTrail->record(new AuditRecord(new Actor($proposedById, $proposedByType), 'governance.approval.proposed', 'approval', $id, (string) $revision->entity_id, $revisionId, (string) $revision->source_revision_id, null, 'Proposta criada para revisão humana; nenhum conteúdo foi publicado.', $correlationId, [], null, ['proposal_kind' => $proposal['kind'] ?? 'knowledge_change'], 'interno', (string) ($proposal['method'] ?? 'human_declared'), (float) ($proposal['confidence'] ?? 1.0)));
        });

        return $id;
    }
}
