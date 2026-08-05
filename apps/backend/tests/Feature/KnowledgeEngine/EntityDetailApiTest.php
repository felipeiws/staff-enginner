<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function entityDetailId(): string
{
    return (string) Str::uuid();
}

function entityDetailGrant(User $user): void
{
    DB::table('identity_grants')->insert(['id' => entityDetailId(), 'user_id' => $user->id, 'role' => 'leitor', 'scope' => json_encode(['organization' => 'nordika']), 'permissions' => json_encode(['knowledge.read']), 'classification_ceiling' => 'interno', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
}

function entityDetailSource(): string
{
    $source = entityDetailId();
    $artifact = entityDetailId();
    $revision = entityDetailId();
    DB::table('sources')->insert(['id' => $source, 'type' => 'git', 'external_system' => 'GitHub', 'scope' => '{}', 'classification' => 'interno', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('artifacts')->insert(['id' => $artifact, 'source_id' => $source, 'external_identifier' => $artifact, 'uri' => 'https://git.example.test/erp/'.$artifact, 'content_hash' => hash('sha256', $artifact), 'media_type' => 'text/markdown', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('revisions')->insert(['id' => $revision, 'artifact_id' => $artifact, 'source_revision' => 'a91f3ce', 'observed_at' => now(), 'checksum' => hash('sha256', $revision), 'metadata' => '{}', 'created_at' => now(), 'updated_at' => now()]);

    return $revision;
}

/** @param array<string,mixed> $detail */
function entityDetailFixture(string $type, array $detail, string $body = 'Descrição versionada'): string
{
    $entity = entityDetailId();
    $revision = entityDetailId();
    DB::table('entities')->insert(['id' => $entity, 'entity_type' => $type, 'family' => 'software', 'scope' => json_encode(['organization' => 'nordika', 'application' => 'erp']), 'external_natural_key' => $entity, 'owner_id' => 'time-compras', 'owner_type' => 'team', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('entity_revisions')->insert(['id' => $revision, 'entity_id' => $entity, 'version' => 4, 'lifecycle_state' => 'published', 'title' => $type.' de teste', 'body' => $body, 'attributes' => json_encode(['classification' => 'interno', 'method' => 'parser@1.0.0', 'processed_at' => now()->toISOString(), 'detail' => $detail]), 'source_revision_id' => entityDetailSource(), 'confidence' => .92, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

    return $entity;
}

it('returns a process projection with provenance, freshness, owners and all prototype sections', function (): void {
    $user = User::factory()->create();
    entityDetailGrant($user);
    $id = entityDetailFixture('Processo', ['summary' => 'Cancelar pedido', 'flow' => ['steps' => [['name' => 'Validação']], 'deviations' => [['name' => 'Faturamento parcial']], 'known_failures' => [['id' => 'ERP-4821']]], 'rules' => [['id' => 'RN-142']], 'screens' => [['id' => 'PC-0421']], 'technical' => ['endpoints' => ['DELETE /v2/purchase-orders/{id}'], 'services' => ['PurchaseOrderCancelService'], 'tables' => ['purchase_orders'], 'events' => ['purchase_order.canceled']], 'relevant_code' => ['path' => 'PurchaseOrderCancelService.java:214'], 'signals' => ['open_issues' => 2, 'commits_30d' => 17, 'support_tickets' => 41, 'ai_confidence' => .92], 'divergences' => [['description' => 'Retorno 409 não documentado']], 'faqs' => [['id' => 'FAQ-231']]]);
    $this->actingAs($user)->getJson('/api/v1/entities/'.$id)->assertOk()
        ->assertJsonPath('type', 'Processo')->assertJsonPath('projection.kind', 'processo')->assertJsonPath('projection.flow.steps.0.name', 'Validação')
        ->assertJsonPath('projection.technical.events.0', 'purchase_order.canceled')->assertJsonPath('provenance.revision', 'a91f3ce')
        ->assertJsonPath('freshness.status', 'atual')->assertJsonPath('owners.0.id', 'time-compras')->assertJsonStructure(['relations']);
});

it('returns the complete business-rule projection contract', function (): void {
    $user = User::factory()->create();
    entityDetailGrant($user);
    $id = entityDetailFixture('Regra', ['statement' => 'Não cancelar integralmente', 'effective' => ['from' => '2026-03-12'], 'approved_by' => ['name' => 'M. Reis'], 'last_change_reason' => 'Auditoria', 'impact' => ['processes' => 3], 'versions' => [['version' => 'v4']], 'diff' => ['from' => 'v3', 'to' => 'v4', 'before' => ['antigo'], 'after' => ['novo']], 'implementation_code' => [['path' => 'OrderEligibility.java:88']], 'affected_entities' => [['id' => 'processo-1']], 'pinned_comment' => ['text' => 'Validado pelo Fiscal']]);
    $this->actingAs($user)->getJson('/api/v1/entities/'.$id)->assertOk()->assertJsonPath('projection.kind', 'regra_de_negocio')->assertJsonPath('projection.versions.0.version', 'v4')->assertJsonPath('projection.diff.to', 'v4')->assertJsonPath('projection.pinned_comment.text', 'Validado pelo Fiscal')->assertJsonStructure(['provenance', 'freshness']);
});

it('returns the complete screen projection contract', function (): void {
    $user = User::factory()->create();
    entityDetailGrant($user);
    $id = entityDetailFixture('Tela', ['desktop' => ['entity_id' => 'print-desktop'], 'mobile' => ['entity_id' => 'print-mobile'], 'video' => ['entity_id' => 'video-1'], 'fields' => [['name' => 'motivoCancelamento', 'type' => 'select', 'rule' => 'RN-201', 'origin' => 'cancel_reasons']], 'known_issues' => [['id' => 'UX-1']], 'permissions' => ['PC.CANCEL'], 'relationships' => [['id' => 'processo-1']]]);
    $this->actingAs($user)->getJson('/api/v1/entities/'.$id)->assertOk()->assertJsonPath('projection.kind', 'tela')->assertJsonPath('projection.desktop.entity_id', 'print-desktop')->assertJsonPath('projection.fields.0.origin', 'cancel_reasons')->assertJsonPath('projection.permissions.0', 'PC.CANCEL')->assertJsonStructure(['provenance', 'freshness']);
});

it('returns the complete endpoint projection contract and hides inaccessible entities', function (): void {
    $user = User::factory()->create();
    entityDetailGrant($user);
    $id = entityDetailFixture('Endpoint', ['method' => 'DELETE', 'path' => '/v2/purchase-orders/{id}', 'description' => 'Cancela pedido', 'parameters' => [['name' => 'id']], 'responses' => [['status' => 409, 'example_payload' => ['code' => 'PARTIAL_INVOICE']]], 'production_metrics' => ['period' => '7d', 'p95_ms' => 184, 'error_rate' => .004], 'consumers' => [['name' => 'Portal']], 'rules' => [['id' => 'RN-142']]]);
    $this->actingAs($user)->getJson('/api/v1/entities/'.$id)->assertOk()->assertJsonPath('projection.kind', 'endpoint')->assertJsonPath('projection.method', 'DELETE')->assertJsonPath('projection.responses.0.example_payload.code', 'PARTIAL_INVOICE')->assertJsonPath('projection.production_metrics.p95_ms', 184)->assertJsonStructure(['provenance', 'freshness', 'owners', 'relations']);
    $other = User::factory()->create();
    $this->actingAs($other)->getJson('/api/v1/entities/'.$id)->assertForbidden();
});
