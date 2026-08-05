<?php

declare(strict_types=1);

namespace Atlas\Modules\Governance\Application;

use Atlas\Contracts\Dto\Actor;
use Atlas\Contracts\Dto\AuditRecord;
use Atlas\Contracts\Port\AuditTrail;
use Atlas\Contracts\Port\HumanReviewLifecycle;
use Atlas\Modules\Governance\Domain\DocumentationPullRequestConnector;
use DomainException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use RuntimeException;

/** Coordinates a human decision; publication remains owned by the lifecycle service. */
/** @phpstan-type ApprovalRow array{id: string, subject_kind: string, entity_revision_id: string|null, proposal: string, correlation_id: string|null, decision: null} */
final readonly class ApprovalReviewService
{
    public function __construct(
        private DatabaseManager $database,
        private HumanReviewLifecycle $lifecycle,
        private DocumentationPullRequestConnector $pullRequests,
        private AuditTrail $auditTrail,
    ) {}

    public function approve(string $approvalId, string $reviewerId, string $justification, string $idempotencyKey): string
    {
        $approval = $this->pendingApproval($approvalId);
        $this->humanReason($reviewerId, $justification);
        $proposal = $this->proposal($approval);
        $commit = $this->commitMessage($proposal, $justification);

        // The external side effect is intentionally attempted before publication.
        // A connector failure therefore leaves the candidate, approval and audit intact.
        $prReference = $this->pullRequests->openPullRequest($proposal, $commit, $idempotencyKey);
        if (trim($prReference) === '') {
            throw new RuntimeException('O conector não retornou a referência do Pull Request.');
        }

        $this->database->transaction(function () use ($approval, $proposal, $reviewerId, $justification, $idempotencyKey, $prReference, $commit): void {
            $this->publishSubject($approval, $reviewerId, $justification, $idempotencyKey);
            $this->database->table('approvals')->where('id', $approval['id'])->update([
                'reviewer_id' => $reviewerId, 'reviewer_type' => 'human', 'decision' => 'approved',
                'justification' => $justification, 'decided_at' => now(), 'external_pr_reference' => $prReference, 'updated_at' => now(),
            ]);
            $this->audit($approval, $reviewerId, 'governance.approval.approved', 'approved', $justification, $proposal + ['commit_message' => $commit, 'external_pr_reference' => $prReference]);
            $this->markDependentsForReview($approval, $proposal, $reviewerId);
        });

        return $prReference;
    }

    public function reject(string $approvalId, string $reviewerId, string $justification, string $idempotencyKey): void
    {
        $approval = $this->pendingApproval($approvalId);
        $this->humanReason($reviewerId, $justification);
        $this->database->transaction(function () use ($approval, $reviewerId, $justification, $idempotencyKey): void {
            $this->publishSubject($approval, $reviewerId, $justification, $idempotencyKey, true);
            $this->database->table('approvals')->where('id', $approval['id'])->update(['reviewer_id' => $reviewerId, 'reviewer_type' => 'human', 'decision' => 'rejected', 'justification' => $justification, 'decided_at' => now(), 'updated_at' => now()]);
            $this->audit($approval, $reviewerId, 'governance.approval.rejected', 'rejected', $justification, $this->proposal($approval));
        });
    }

    /** Stores the reviewer edit in the proposal and preserves the previous value in append-only audit. */
    public function editText(string $approvalId, string $reviewerId, string $text): void
    {
        $approval = $this->pendingApproval($approvalId);
        $this->humanReason($reviewerId, $text);
        $proposal = $this->proposal($approval);
        $before = $proposal;
        $proposal['proposed_text'] = $text;
        $this->database->transaction(function () use ($approval, $reviewerId, $proposal, $before): void {
            $this->database->table('approvals')->where('id', $approval['id'])->update(['proposal' => json_encode($proposal, JSON_THROW_ON_ERROR), 'updated_at' => now()]);
            $this->audit($approval, $reviewerId, 'governance.approval.text_edited', null, 'Texto proposto editado pelo revisor.', ['before' => $before, 'after' => $proposal]);
        });
    }

    public function requestAiAdjustment(string $approvalId, string $reviewerId, string $instruction): void
    {
        $approval = $this->pendingApproval($approvalId);
        $this->humanReason($reviewerId, $instruction);
        $this->audit($approval, $reviewerId, 'governance.approval.ai_adjustment_requested', null, $instruction, $this->proposal($approval));
    }

    /** @param ApprovalRow $approval */
    private function publishSubject(array $approval, string $reviewerId, string $reason, string $key, bool $rejected = false): void
    {
        if ($approval['subject_kind'] !== 'entity_revision' || $approval['entity_revision_id'] === null) {
            throw new DomainException('A revisão humana de assertions ainda não possui publicação por Pull Request.');
        }
        $correlationId = $approval['correlation_id'];
        if ($rejected) {
            $this->lifecycle->retractReviewedRevision($approval['entity_revision_id'], $reviewerId, $reason, $key, $correlationId);

            return;
        }
        $this->lifecycle->publishReviewedRevision($approval['entity_revision_id'], $reviewerId, $reason, $key, $correlationId);
    }

    /** @param array<string, mixed> $proposal */
    /** @param ApprovalRow $approval
     * @param array<string, mixed> $proposal */
    private function markDependentsForReview(array $approval, array $proposal, string $reviewerId): void
    {
        if ($approval['entity_revision_id'] === null) {
            return;
        }
        $dependentRevisionIds = $this->database->table('assertions')
            ->where('lifecycle_state', 'published')
            ->where('object_entity_revision_id', $approval['entity_revision_id'])
            ->pluck('subject_entity_revision_id')->unique();
        foreach ($dependentRevisionIds as $revisionId) {
            if ($revisionId === $approval['entity_revision_id'] || $this->database->table('approvals')->where('entity_revision_id', $revisionId)->whereNull('decision')->exists()) {
                continue;
            }
            $id = (string) Str::uuid();
            $dependencyProposal = ['kind' => 'automatic_dependency_review', 'target_entity_revision_id' => $revisionId, 'triggering_approval_id' => $approval['id'], 'origin' => $proposal['origin'] ?? null, 'method' => 'lifecycle_service', 'confidence' => $proposal['confidence'] ?? null, 'processed_at' => now()->toISOString()];
            $this->database->table('approvals')->insert(['id' => $id, 'subject_kind' => 'entity_revision', 'entity_revision_id' => $revisionId, 'proposal' => json_encode($dependencyProposal, JSON_THROW_ON_ERROR), 'proposed_by_id' => 'service:lifecycle', 'proposed_by_type' => 'service', 'proposed_at' => now(), 'correlation_id' => $approval['correlation_id'], 'created_at' => now(), 'updated_at' => now()]);
            $this->auditForRevision($revisionId, $approval['correlation_id'], $reviewerId, 'governance.approval.dependent_review_required', null, 'Entidade dependente marcada para revisão automática.', $dependencyProposal);
        }
    }

    /** @return ApprovalRow */
    private function pendingApproval(string $id): array
    {
        $approval = $this->database->table('approvals')->where('id', $id)->first();
        if ($approval === null || $approval->decision !== null) {
            throw new RuntimeException('Proposta de aprovação pendente não encontrada.');
        }

        return [
            'id' => (string) $approval->id,
            'subject_kind' => (string) $approval->subject_kind,
            'entity_revision_id' => is_string($approval->entity_revision_id) ? $approval->entity_revision_id : null,
            'proposal' => (string) $approval->proposal,
            'correlation_id' => is_string($approval->correlation_id) ? $approval->correlation_id : null,
            'decision' => null,
        ];
    }

    /** @param ApprovalRow $approval
     * @return array<string, mixed> */
    private function proposal(array $approval): array
    {
        return json_decode($approval['proposal'], true, 512, JSON_THROW_ON_ERROR);
    }

    private function humanReason(string $reviewer, string $reason): void
    {
        if (trim($reviewer) === '' || trim($reason) === '') {
            throw new DomainException('Revisor humano e justificativa são obrigatórios.');
        }
    }

    /** @param array<string,mixed> $proposal */
    private function commitMessage(array $proposal, string $justification): string
    {
        return (string) ($proposal['commit_message'] ?? 'docs(knowledge): revisão humana aprovada')."\n\n".$justification;
    }

    /** @param array<string,mixed>|null $after */
    /** @param ApprovalRow $approval
     * @param array<string, mixed>|null $after */
    private function audit(array $approval, string $actor, string $action, ?string $decision, string $reason, ?array $after): void
    {
        $this->auditForRevision($approval['entity_revision_id'], $approval['correlation_id'], $actor, $action, $decision, $reason, $after);
    }

    /** @param array<string, mixed>|null $after */
    private function auditForRevision(?string $revision, ?string $correlationId, string $actor, string $action, ?string $decision, string $reason, ?array $after): void
    {
        $sourceRevision = $revision === null ? null : $this->database->table('entity_revisions')->where('id', $revision)->value('source_revision_id');
        $entityId = $revision === null ? null : $this->database->table('entity_revisions')->where('id', $revision)->value('entity_id');
        $this->auditTrail->record(new AuditRecord(new Actor($actor, 'human'), $action, 'approval', $revision ?? (string) Str::uuid(), is_string($entityId) ? $entityId : null, $revision, is_string($sourceRevision) ? $sourceRevision : null, $decision, $reason, $correlationId, [], null, $after, null, 'human_review', null));
    }
}
