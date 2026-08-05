<?php

declare(strict_types=1);

use App\Models\User;
use Atlas\Modules\Governance\Domain\DocumentationPullRequestConnector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function approvalApiId(): string
{
    return (string) Str::uuid();
}

function approvalApiGrant(User $user, string $role): void
{
    DB::table('identity_grants')->insert(['id' => approvalApiId(), 'user_id' => $user->id, 'role' => $role, 'scope' => json_encode(['organization' => 'nordika', 'application' => 'erp'], JSON_THROW_ON_ERROR), 'permissions' => '[]', 'classification_ceiling' => 'interno', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
}

/** @return array{id:string,revision_id:string} */
function approvalApiProposal(bool $lowImpact = false): array
{
    $now = now();
    $source = approvalApiId();
    $artifact = approvalApiId();
    $sourceRevision = approvalApiId();
    $entity = approvalApiId();
    $revision = approvalApiId();
    $approval = approvalApiId();
    DB::table('sources')->insert(['id' => $source, 'type' => 'git', 'scope' => json_encode(['organization' => 'nordika', 'application' => 'erp']), 'classification' => 'interno', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
    DB::table('artifacts')->insert(['id' => $artifact, 'source_id' => $source, 'external_identifier' => "approval-api-{$approval}", 'uri' => 'https://git.example.test/erp/docs/compras.md', 'content_hash' => hash('sha256', $approval), 'media_type' => 'text/markdown', 'created_at' => $now, 'updated_at' => $now]);
    DB::table('revisions')->insert(['id' => $sourceRevision, 'artifact_id' => $artifact, 'source_revision' => 'a91f3ce', 'observed_at' => $now, 'checksum' => hash('sha256', $revision), 'metadata' => '{}', 'created_at' => $now, 'updated_at' => $now]);
    DB::table('entities')->insert(['id' => $entity, 'entity_type' => $lowImpact ? 'FAQ' : 'Processo', 'family' => 'conhecimento', 'scope' => json_encode(['organization' => 'nordika', 'application' => 'erp']), 'external_natural_key' => $lowImpact ? 'FAQ-231' : 'PC-0421', 'owner_id' => 'human:ana', 'owner_type' => 'human', 'created_at' => $now, 'updated_at' => $now]);
    DB::table('entity_revisions')->insert(['id' => $revision, 'entity_id' => $entity, 'version' => 1, 'lifecycle_state' => 'review', 'title' => $lowImpact ? 'FAQ-231 · reescrita' : 'Cancelar Pedido de Compra · etapa 4', 'body' => 'A documentação afirmava bloqueio total.', 'attributes' => json_encode(['method' => 'inference@gpt-test', 'processed_at' => $now->toISOString(), 'classification' => 'interno']), 'source_revision_id' => $sourceRevision, 'confidence' => .94, 'created_at' => $now, 'updated_at' => $now]);
    $proposal = ['origin' => ['kind' => 'commit', 'revision' => 'a91f3ce', 'detected_by' => 'CI'], 'ai_analysis' => ['model' => 'gpt-test', 'confidence' => .94, 'text' => 'O código passou a lançar ConflictException e propõe citar o 409.'], 'current_text' => 'A documentação afirmava bloqueio total.', 'proposed_text' => 'Permite faturamento parcial e retorna 409.', 'impacted_entities' => ['RN-142', 'FAQ-231'], 'impact_level' => $lowImpact ? 'baixo' : 'alto', 'auto_approve_after_hours' => $lowImpact ? 48 : null, 'method' => 'inference@gpt-test', 'confidence' => .94, 'processed_at' => $now->toISOString(), 'commit_message' => 'docs(compras): alinha etapa 4 ao 409 (RN-142)'];
    DB::table('approvals')->insert(['id' => $approval, 'subject_kind' => 'entity_revision', 'entity_revision_id' => $revision, 'proposal' => json_encode($proposal, JSON_THROW_ON_ERROR), 'proposed_by_id' => 'worker:ci', 'proposed_by_type' => 'worker', 'proposed_at' => $now->subHours(2), 'correlation_id' => approvalApiId(), 'created_at' => $now, 'updated_at' => $now]);

    return ['id' => $approval, 'revision_id' => $revision];
}

function approvalApiConnector(): void
{
    app()->bind(DocumentationPullRequestConnector::class, fn () => new class implements DocumentationPullRequestConnector
    {
        public function openPullRequest(array $proposal, string $commitMessage, string $idempotencyKey): string
        {
            return 'PR #4127';
        }
    });
}

it('lists the prototype pending proposals and groups low-impact proposals for 48-hour auto approval', function (): void {
    $user = User::factory()->create();
    approvalApiGrant($user, 'aprovador_de_dominio');
    approvalApiProposal();
    approvalApiProposal(true);

    $this->actingAs($user)->getJson('/api/v1/approvals')->assertOk()
        ->assertJsonPath('approvals.0.title', 'Cancelar Pedido de Compra · etapa 4')
        ->assertJsonPath('approvals.0.entity_type_color', 'violet')
        ->assertJsonPath('approvals.0.origin.revision', 'a91f3ce')
        ->assertJsonPath('approvals.0.age', 'há 2h')
        ->assertJsonPath('low_impact_auto_approval.count', 1)
        ->assertJsonPath('low_impact_auto_approval.hours', 48);
});

it('returns AI analysis, confidence, diff, impact and provenance for an approval', function (): void {
    $user = User::factory()->create();
    approvalApiGrant($user, 'aprovador_de_dominio');
    $proposal = approvalApiProposal();

    $this->actingAs($user)->getJson("/api/v1/approvals/{$proposal['id']}")->assertOk()
        ->assertJsonPath('analysis.confidence', 0.94)
        ->assertJsonPath('diff.proposed', 'Permite faturamento parcial e retorna 409.')
        ->assertJsonPath('impact.entities.0', 'RN-142')
        ->assertJsonPath('provenance.revision', 'a91f3ce');
});

it('does not allow a contributor to approve a proposal', function (): void {
    $user = User::factory()->create();
    approvalApiGrant($user, 'contribuidor');
    $proposal = approvalApiProposal();

    $this->actingAs($user)->postJson("/api/v1/approvals/{$proposal['id']}/approve", ['justification' => 'Confirmado pelo domínio.'], ['Idempotency-Key' => 'approval-contributor'])->assertForbidden()
        ->assertJsonPath('code', 'identity.access_denied');
    expect(DB::table('approvals')->where('id', $proposal['id'])->value('decision'))->toBeNull();
});

it('approves with a justification, returns the pull request and replays the idempotent response', function (): void {
    $user = User::factory()->create();
    approvalApiGrant($user, 'aprovador_de_dominio');
    $proposal = approvalApiProposal();
    approvalApiConnector();
    $headers = ['Idempotency-Key' => 'approval-success'];

    $this->actingAs($user)->postJson("/api/v1/approvals/{$proposal['id']}/approve", ['justification' => 'Alinhado com Fiscal em 30/07; 409 confirmado.'], $headers)->assertCreated()
        ->assertJsonPath('pull_request.reference', 'PR #4127')
        ->assertJsonPath('commit', "docs(compras): alinha etapa 4 ao 409 (RN-142)\n\nAlinhado com Fiscal em 30/07; 409 confirmado.")
        ->assertJsonPath('reindexing.target', '40s');
    $this->actingAs($user)->postJson("/api/v1/approvals/{$proposal['id']}/approve", ['justification' => 'Alinhado com Fiscal em 30/07; 409 confirmado.'], $headers)->assertCreated()->assertHeader('X-Idempotent-Replay', 'true');
    expect(DB::table('approvals')->where('id', $proposal['id'])->value('external_pr_reference'))->toBe('PR #4127');
});

it('edits, requests AI revision and rejects through idempotent approval commands', function (): void {
    $user = User::factory()->create();
    approvalApiGrant($user, 'aprovador_de_dominio');
    $proposal = approvalApiProposal();
    $base = "/api/v1/approvals/{$proposal['id']}";

    $this->actingAs($user)->patchJson($base, ['proposed_text' => 'Texto revisado pelo domínio.'], ['Idempotency-Key' => 'approval-edit'])->assertOk()->assertJsonPath('status', 'proposal_text_updated');
    $this->actingAs($user)->postJson("{$base}/request-ai-revision", ['instruction' => 'Inclua a evidência do endpoint 409.'], ['Idempotency-Key' => 'approval-ai'])->assertOk()->assertJsonPath('status', 'ai_revision_requested');
    $this->actingAs($user)->postJson("{$base}/reject", ['reason' => 'A evidência não cobre todos os cenários.'], ['Idempotency-Key' => 'approval-reject'])->assertOk()->assertJsonPath('decision', 'rejected');
});
