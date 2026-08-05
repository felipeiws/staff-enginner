<?php

declare(strict_types=1);

use App\Models\User;
use Atlas\Modules\Governance\Application\KnowledgeHealthCacheInvalidator;
use Atlas\Modules\Governance\Application\KnowledgeHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function healthId(): string
{
    return (string) Str::uuid();
}

function healthSourceRevision(): string
{
    $source = healthId();
    $artifact = healthId();
    $revision = healthId();
    DB::table('sources')->insert(['id' => $source, 'type' => 'git', 'external_system' => 'GitHub', 'scope' => '{}', 'classification' => 'interno', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('artifacts')->insert(['id' => $artifact, 'source_id' => $source, 'external_identifier' => $artifact, 'uri' => 'https://example.test/'.$artifact, 'content_hash' => hash('sha256', $artifact), 'media_type' => 'text/markdown', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('revisions')->insert(['id' => $revision, 'artifact_id' => $artifact, 'source_revision' => 'r1', 'observed_at' => now(), 'checksum' => hash('sha256', $revision), 'metadata' => '{}', 'created_at' => now(), 'updated_at' => now()]);

    return $revision;
}

function healthEntity(string $type, array $scope, string $state = 'published', float $confidence = .8, ?string $publishedAt = null, array $attributes = [], string $owner = 'owner-1'): array
{
    $entity = healthId();
    $revision = healthId();
    DB::table('entities')->insert(['id' => $entity, 'entity_type' => $type, 'family' => 'conhecimento', 'scope' => json_encode($scope, JSON_THROW_ON_ERROR), 'external_natural_key' => $type.'-'.$entity, 'owner_id' => $owner, 'owner_type' => 'human', 'created_at' => now()->subDays(31), 'updated_at' => now()]);
    DB::table('entity_revisions')->insert(['id' => $revision, 'entity_id' => $entity, 'version' => 1, 'lifecycle_state' => $state, 'title' => $type, 'attributes' => json_encode(['method' => 'parser@1', 'processed_at' => now()->toISOString(), ...$attributes], JSON_THROW_ON_ERROR), 'source_revision_id' => healthSourceRevision(), 'confidence' => $confidence, 'published_at' => $state === 'published' ? ($publishedAt ?? now()) : null, 'created_at' => now()->subDays(31), 'updated_at' => now()]);

    return ['entity' => $entity, 'revision' => $revision];
}

function healthFixture(): array
{
    $scope = ['organization' => 'nordika', 'application' => 'erp'];
    $context = healthEntity('Aplicação', $scope);
    $document = healthEntity('Documento', $scope, publishedAt: now()->subDays(91)->toISOString(), confidence: .9);
    healthEntity('Regra', $scope, confidence: .8, owner: 'unassigned');
    healthEntity('Tela', $scope, confidence: .7);
    $candidate = healthEntity('Processo', $scope, 'candidate', .4);
    DB::table('approvals')->insert(['id' => healthId(), 'subject_kind' => 'entity_revision', 'entity_revision_id' => $candidate['revision'], 'proposal' => '{}', 'proposed_by_id' => 'worker', 'proposed_by_type' => 'worker', 'proposed_at' => now()->subHours(10), 'reviewer_id' => 'ana', 'reviewer_type' => 'human', 'decision' => 'approved', 'justification' => 'Revisado.', 'decided_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    DB::table('knowledge_gaps')->insert(['id' => healthId(), 'question' => 'Como estornar o frete?', 'semantic_key' => 'estornar frete', 'scope' => json_encode($scope), 'scope_key' => hash('sha256', json_encode($scope)), 'occurrence_count' => 2, 'first_occurred_at' => now()->subDay(), 'window_started_at' => now()->subDay(), 'last_occurred_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

    return ['scope_id' => $context['entity'], 'scope' => $scope, 'document_id' => $document['entity']];
}

function healthUser(): User
{
    $user = User::factory()->create();
    DB::table('identity_grants')->insert(['id' => healthId(), 'user_id' => $user->id, 'role' => 'leitor', 'scope' => json_encode(['organization' => 'nordika']), 'permissions' => json_encode(['knowledge.read']), 'classification_ceiling' => 'interno', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);

    return $user;
}

it('calcula métricas, dívida, lacunas e proveniência a partir do grafo e auditoria', function (): void {
    $fixture = healthFixture();
    $response = $this->actingAs(healthUser())->getJson('/api/v1/knowledge-health?scope='.$fixture['scope_id'].'&period=30d');
    $response->assertOk()->assertJsonPath('metrics.0.key', 'coverage')->assertJsonPath('metrics.0.value', 75)
        ->assertJsonPath('metrics.1.value', .8)->assertJsonPath('metrics.2.value', 2)->assertJsonPath('metrics.3.value', 10)
        ->assertJsonPath('knowledge_debt.0.value', 1)->assertJsonPath('knowledge_debt.0.reference_total', 1)
        ->assertJsonPath('knowledge_debt.1.value', 1)->assertJsonPath('knowledge_debt.2.value', 1)
        ->assertJsonPath('knowledge_debt.3.value', 1)->assertJsonPath('unanswered_questions.0.question', 'Como estornar o frete?')
        ->assertJsonPath('provenance.method', 'knowledge_health_dashboard_v1')
        ->assertJsonStructure(['metrics' => [['variation', 'trend_color', 'series' => [['at', 'value']], 'provenance']], 'period_insight' => ['support_ticket_share', 'provenance']]);
});

it('invalida a projeção em cache ao consumir evento do escopo', function (): void {
    $fixture = healthFixture();
    $service = app(KnowledgeHealthService::class);
    expect($service->show($fixture['scope_id'], '30d')['metrics'][2]['value'])->toBe(2);
    DB::table('knowledge_gaps')->insert(['id' => healthId(), 'question' => 'Qual o SLA?', 'semantic_key' => 'sla', 'scope' => json_encode($fixture['scope']), 'scope_key' => hash('sha256', json_encode($fixture['scope'])), 'occurrence_count' => 3, 'first_occurred_at' => now(), 'window_started_at' => now(), 'last_occurred_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    app(KnowledgeHealthCacheInvalidator::class)->consume(['event_id' => healthId(), 'scope' => $fixture['scope']]);
    expect($service->show($fixture['scope_id'], '30d')['metrics'][2]['value'])->toBe(5);
});

it('recusa período e escopo sem concessão antes de expor a projeção', function (): void {
    $fixture = healthFixture();
    $this->actingAs(healthUser())->getJson('/api/v1/knowledge-health?scope='.$fixture['scope_id'].'&period=7d')->assertUnprocessable();
    $other = User::factory()->create();
    DB::table('identity_grants')->insert(['id' => healthId(), 'user_id' => $other->id, 'role' => 'leitor', 'scope' => json_encode(['organization' => 'outra']), 'permissions' => json_encode(['knowledge.read']), 'classification_ceiling' => 'interno', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    $this->actingAs($other)->getJson('/api/v1/knowledge-health?scope='.$fixture['scope_id'].'&period=30d')->assertForbidden();
});

it('exclui revisões restritas dos agregados para concessão interna', function (): void {
    $fixture = healthFixture();
    healthEntity('Documento', $fixture['scope'], attributes: ['classification' => 'restrito']);
    $json = $this->actingAs(healthUser())->getJson('/api/v1/knowledge-health?scope='.$fixture['scope_id'].'&period=30d')->assertOk()->json();
    expect($json['metrics'][0]['value'])->toBe(75)->and(json_encode($json))->not->toContain('restrito');
});
