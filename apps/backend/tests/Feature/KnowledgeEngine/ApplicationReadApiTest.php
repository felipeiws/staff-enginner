<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function applicationReadId(): string
{
    return (string) Str::uuid();
}

function applicationReadGrant(User $user, array $scope, string $classification = 'interno'): void
{
    DB::table('identity_grants')->insert(['id' => applicationReadId(), 'user_id' => $user->id, 'role' => 'leitor', 'scope' => json_encode($scope, JSON_THROW_ON_ERROR), 'permissions' => '[]', 'classification_ceiling' => $classification, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
}

function applicationReadSourceRevision(): string
{
    $source = applicationReadId();
    $artifact = applicationReadId();
    $revision = applicationReadId();
    DB::table('sources')->insert(['id' => $source, 'type' => 'git', 'external_system' => 'GitHub', 'scope' => '{}', 'classification' => 'interno', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('artifacts')->insert(['id' => $artifact, 'source_id' => $source, 'external_identifier' => 'atlas-read-fixture-'.$artifact, 'uri' => 'https://git.example.test/atlas/'.$artifact, 'content_hash' => hash('sha256', $artifact), 'media_type' => 'text/markdown', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('revisions')->insert(['id' => $revision, 'artifact_id' => $artifact, 'source_revision' => 'a91f3ce', 'observed_at' => now(), 'checksum' => hash('sha256', $revision), 'metadata' => '{}', 'created_at' => now(), 'updated_at' => now()]);

    return $revision;
}

function applicationReadEntity(string $type, array $scope, string $state = 'published', float $confidence = .8, ?string $publishedAt = null, array $attributes = [], ?string $body = null): string
{
    $entity = applicationReadId();
    $revision = applicationReadId();
    DB::table('entities')->insert(['id' => $entity, 'entity_type' => $type, 'family' => 'contexto', 'scope' => json_encode($scope, JSON_THROW_ON_ERROR), 'external_natural_key' => $type.'-'.$entity, 'owner_id' => 'owner-1', 'owner_type' => 'human', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('entity_revisions')->insert(['id' => $revision, 'entity_id' => $entity, 'version' => 1, 'lifecycle_state' => $state, 'title' => $type.' '.($scope['module'] ?? $scope['application'] ?? $scope['organization']), 'body' => $body, 'attributes' => json_encode(['method' => 'parser@1', 'processed_at' => now()->toISOString(), ...$attributes], JSON_THROW_ON_ERROR), 'source_revision_id' => applicationReadSourceRevision(), 'confidence' => $confidence, 'published_at' => $state === 'published' ? ($publishedAt ?? now()) : null, 'created_at' => now(), 'updated_at' => now()]);

    return $revision;
}

function applicationReadFixture(): void
{
    applicationReadEntity('Organização', ['organization' => 'nordika']);
    applicationReadEntity('Aplicação', ['organization' => 'nordika', 'application' => 'erp'], body: 'Núcleo financeiro da Nordika.');
    applicationReadEntity('Módulo', ['organization' => 'nordika', 'application' => 'erp', 'module' => 'compras']);
    applicationReadEntity('Processo', ['organization' => 'nordika', 'application' => 'erp', 'module' => 'compras'], confidence: .8);
    applicationReadEntity('Tela', ['organization' => 'nordika', 'application' => 'erp', 'module' => 'compras'], confidence: .8, publishedAt: now()->subDays(91)->toISOString());
    $pending = applicationReadEntity('Endpoint', ['organization' => 'nordika', 'application' => 'erp', 'module' => 'compras'], state: 'candidate', confidence: .6);
    DB::table('approvals')->insert(['id' => applicationReadId(), 'subject_kind' => 'entity_revision', 'entity_revision_id' => $pending, 'proposal' => '{}', 'proposed_by_id' => 'worker-1', 'proposed_by_type' => 'worker', 'proposed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
}

it('returns Home organization totals and application cards with calculated health', function (): void {
    $user = User::factory()->create();
    applicationReadGrant($user, ['organization' => 'nordika']);
    applicationReadFixture();

    $this->actingAs($user)->getJson('/api/v1/organizations/nordika')
        ->assertOk()->assertJsonPath('indexed_entities_count', 5)->assertJsonPath('applications_count', 1)
        ->assertJsonPath('provenance.method', 'published_entity_aggregation_v1');
    $this->actingAs($user)->getJson('/api/v1/applications')
        ->assertOk()->assertJsonPath('applications.0.id', 'erp')->assertJsonPath('applications.0.modules_count', 1)
        ->assertJsonPath('applications.0.screens_count', 1)->assertJsonPath('applications.0.knowledge_health.score', 54)
        ->assertJsonPath('applications.0.knowledge_health.status', 'Atenção');
});

it('returns the Home composition only from published, authorized read models', function (): void {
    $user = User::factory()->create();
    applicationReadGrant($user, ['organization' => 'nordika']);
    applicationReadFixture();
    applicationReadEntity('FAQ', ['organization' => 'nordika', 'application' => 'erp'], attributes: ['access_count' => 3]);
    applicationReadEntity('Processo', ['organization' => 'nordika', 'application' => 'erp'], attributes: ['access_count' => 12]);

    $this->actingAs($user)->getJson('/api/v1/home?organization=nordika')
        ->assertOk()->assertJsonPath('organization.id', 'nordika')
        ->assertJsonPath('applications.0.id', 'erp')
        ->assertJsonPath('frequent_questions.0.provenance.method', 'published_faq_v1')
        ->assertJsonPath('top_processes.0.volume', 12)
        ->assertJsonPath('top_processes.0.provenance.method', 'published_process_access_v1');
});

it('returns application overview, modules and both prototype navigation trees', function (): void {
    $user = User::factory()->create();
    applicationReadGrant($user, ['organization' => 'nordika', 'application' => 'erp']);
    applicationReadFixture();

    $this->actingAs($user)->getJson('/api/v1/applications/erp')
        ->assertOk()->assertJsonPath('description', 'Núcleo financeiro da Nordika.')
        ->assertJsonPath('documentation_coverage', 67)->assertJsonPath('pending_approvals', 1)
        ->assertJsonPath('knowledge_health.formula_version', 'knowledge_health_v1');
    $this->actingAs($user)->getJson('/api/v1/applications/erp/modules')
        ->assertOk()->assertJsonPath('modules.0.id', 'compras')->assertJsonPath('modules.0.processes_count', 1)
        ->assertJsonPath('modules.0.apis_count', 0);
    $this->actingAs($user)->getJson('/api/v1/applications/erp/tree?nav=technical')
        ->assertOk()->assertJsonPath('nav', 'technical')->assertJsonPath('groups.2.name', 'APIs')->assertJsonPath('groups.2.count', 0)
        ->assertJsonPath('groups.5.name', 'Filas e Eventos');
    $this->actingAs($user)->getJson('/api/v1/applications/erp/tree?nav=functional')
        ->assertOk()->assertJsonPath('groups.0.name', 'Processos')->assertJsonPath('groups.0.count', 1)
        ->assertJsonPath('groups.5.name', 'Telas')->assertJsonPath('groups.5.count', 1);
});

it('denies unrelated scopes before exposing application data and validates navigation', function (): void {
    $user = User::factory()->create();
    applicationReadGrant($user, ['organization' => 'outra']);
    applicationReadFixture();

    $this->actingAs($user)->getJson('/api/v1/applications/erp')->assertForbidden();
    $allowed = User::factory()->create();
    applicationReadGrant($allowed, ['organization' => 'nordika', 'application' => 'erp']);
    $this->actingAs($allowed)->getJson('/api/v1/applications/erp/tree?nav=invalid')->assertUnprocessable()->assertJsonPath('code', 'request.validation_failed');
});
