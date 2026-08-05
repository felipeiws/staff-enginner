<?php

declare(strict_types=1);

use Atlas\Modules\Governance\Application\ApprovalReviewService;
use Atlas\Modules\Governance\Domain\DocumentationPullRequestConnector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function approvalReviewId(int $number): string
{
    return sprintf('50000000-0000-4000-8000-%012d', $number);
}

/** @return array{entity_id:string, revision_id:string} */
function approvalReviewRevision(int $number, string $state = 'review'): array
{
    $now = now();
    $source = approvalReviewId($number * 10 + 1);
    $artifact = approvalReviewId($number * 10 + 2);
    $sourceRevision = approvalReviewId($number * 10 + 3);
    $entity = approvalReviewId($number * 10 + 4);
    $revision = approvalReviewId($number * 10 + 5);
    DB::table('sources')->insert(['id' => $source, 'type' => 'git_repository', 'scope' => json_encode(['application' => 'atlas']), 'classification' => 'interno', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
    DB::table('artifacts')->insert(['id' => $artifact, 'source_id' => $source, 'external_identifier' => "approval-doc-{$number}", 'uri' => "file:///approval-doc-{$number}", 'content_hash' => "approval-hash-{$number}", 'media_type' => 'text/markdown', 'created_at' => $now, 'updated_at' => $now]);
    DB::table('revisions')->insert(['id' => $sourceRevision, 'artifact_id' => $artifact, 'source_revision' => "commit:{$number}", 'observed_at' => $now, 'checksum' => "approval-checksum-{$number}", 'metadata' => json_encode([]), 'created_at' => $now, 'updated_at' => $now]);
    DB::table('entities')->insert(['id' => $entity, 'entity_type' => $number === 2 ? 'FAQ' : 'Processo', 'family' => 'conhecimento', 'scope' => json_encode(['application' => 'atlas']), 'external_natural_key' => $number === 2 ? 'FAQ-231' : "processo-{$number}", 'owner_id' => 'human:ana', 'owner_type' => 'human', 'created_at' => $now, 'updated_at' => $now]);
    DB::table('entity_revisions')->insert(['id' => $revision, 'entity_id' => $entity, 'version' => 1, 'lifecycle_state' => $state, 'title' => "Revisão {$number}", 'body' => 'Texto atual', 'attributes' => json_encode(['method' => 'inference@gpt', 'processed_at' => $now->toISOString()]), 'source_revision_id' => $sourceRevision, 'confidence' => .94, 'published_at' => $state === 'published' ? $now : null, 'created_at' => $now, 'updated_at' => $now]);

    return ['entity_id' => $entity, 'revision_id' => $revision];
}

function approvalReviewProposal(string $revisionId, int $number = 1): string
{
    $id = approvalReviewId(100 + $number);
    DB::table('approvals')->insert(['id' => $id, 'subject_kind' => 'entity_revision', 'entity_revision_id' => $revisionId, 'proposal' => json_encode(['target_entity_revision_id' => $revisionId, 'origin' => ['kind' => 'commit', 'revision' => 'a91f3ce', 'detected_by' => 'CI'], 'ai_analysis' => ['model' => 'gpt-test', 'confidence' => .94], 'current_text' => 'Bloqueio total.', 'proposed_text' => 'Permite faturamento parcial e retorna 409.', 'impacted_entities' => ['RN-142', 'FAQ-231'], 'method' => 'inference@gpt', 'confidence' => .94, 'processed_at' => now()->toISOString(), 'commit_message' => 'docs(compras): alinha etapa 4 ao 409 (RN-142)'], JSON_THROW_ON_ERROR), 'proposed_by_id' => 'worker:ci', 'proposed_by_type' => 'worker', 'proposed_at' => now(), 'correlation_id' => approvalReviewId(900 + $number), 'created_at' => now(), 'updated_at' => now()]);

    return $id;
}

function approvalReviewConnector(string $reference = 'PR #4127'): void
{
    app()->bind(DocumentationPullRequestConnector::class, fn () => new class($reference) implements DocumentationPullRequestConnector
    {
        public function __construct(private string $reference) {}

        public function openPullRequest(array $proposal, string $commitMessage, string $idempotencyKey): string
        {
            return $this->reference;
        }
    });
}

it('approves, publishes, audits and opens a pull request with the reviewer justification in the commit', function (): void {
    $target = approvalReviewRevision(1);
    $approvalId = approvalReviewProposal($target['revision_id']);
    approvalReviewConnector();

    $reference = app(ApprovalReviewService::class)->approve($approvalId, 'human:bia', 'Alinhado com Fiscal em 30/07; 409 confirmado.', 'approval-1');

    expect($reference)->toBe('PR #4127')
        ->and(DB::table('entity_revisions')->where('id', $target['revision_id'])->value('lifecycle_state'))->toBe('published')
        ->and(DB::table('approvals')->where('id', $approvalId)->value('decision'))->toBe('approved')
        ->and(DB::table('approvals')->where('id', $approvalId)->value('external_pr_reference'))->toBe('PR #4127')
        ->and(DB::table('audit_log')->where('action', 'governance.approval.approved')->where('reason', 'Alinhado com Fiscal em 30/07; 409 confirmado.')->exists())->toBeTrue();
});

it('rejects a proposal, retracts the candidate and preserves its decision history', function (): void {
    $target = approvalReviewRevision(3);
    $approvalId = approvalReviewProposal($target['revision_id'], 3);

    app(ApprovalReviewService::class)->reject($approvalId, 'human:bia', 'A evidência não cobre todos os cenários.', 'approval-3');

    expect(DB::table('entity_revisions')->where('id', $target['revision_id'])->value('lifecycle_state'))->toBe('retracted')
        ->and(DB::table('approvals')->where('id', $approvalId)->value('decision'))->toBe('rejected')
        ->and(DB::table('audit_log')->where('action', 'governance.approval.rejected')->exists())->toBeTrue();
});

it('does not publish or decide when opening the pull request fails', function (): void {
    $target = approvalReviewRevision(4);
    $approvalId = approvalReviewProposal($target['revision_id'], 4);
    app()->bind(DocumentationPullRequestConnector::class, fn () => new class implements DocumentationPullRequestConnector
    {
        public function openPullRequest(array $proposal, string $commitMessage, string $idempotencyKey): string
        {
            throw new RuntimeException('Git indisponível');
        }
    });

    expect(fn () => app(ApprovalReviewService::class)->approve($approvalId, 'human:bia', 'Pode publicar.', 'approval-4'))->toThrow(RuntimeException::class, 'Git indisponível');
    expect(DB::table('entity_revisions')->where('id', $target['revision_id'])->value('lifecycle_state'))->toBe('review')
        ->and(DB::table('approvals')->where('id', $approvalId)->value('decision'))->toBeNull()
        ->and(DB::table('audit_log')->where('action', 'governance.approval.approved')->count())->toBe(0);
});

it('marks dependent entities for automatic review after approval', function (): void {
    $target = approvalReviewRevision(5);
    $dependent = approvalReviewRevision(2, 'published');
    DB::table('assertions')->insert(['id' => approvalReviewId(777), 'subject_entity_revision_id' => $dependent['revision_id'], 'predicate' => 'depends_on', 'object_entity_revision_id' => $target['revision_id'], 'scope' => json_encode(['application' => 'atlas']), 'confidence' => .9, 'method' => 'human_declared', 'actor_id' => 'human:ana', 'actor_type' => 'human', 'lifecycle_state' => 'published', 'valid_from' => now(), 'created_at' => now(), 'updated_at' => now()]);
    $approvalId = approvalReviewProposal($target['revision_id'], 5);
    approvalReviewConnector('PR #4128');

    app(ApprovalReviewService::class)->approve($approvalId, 'human:bia', 'Dependência revisada.', 'approval-5');

    expect(DB::table('approvals')->where('entity_revision_id', $dependent['revision_id'])->whereNull('decision')->where('proposed_by_id', 'service:lifecycle')->exists())->toBeTrue()
        ->and(DB::table('audit_log')->where('action', 'governance.approval.dependent_review_required')->exists())->toBeTrue();
});
