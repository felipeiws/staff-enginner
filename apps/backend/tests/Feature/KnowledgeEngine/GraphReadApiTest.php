<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function graphReadId(): string
{
    return (string) Str::uuid();
}

function graphReadGrant(User $user): void
{
    DB::table('identity_grants')->insert(['id' => graphReadId(), 'user_id' => $user->id, 'role' => 'leitor', 'scope' => json_encode(['organization' => 'nordika']), 'permissions' => json_encode(['knowledge.read']), 'classification_ceiling' => 'interno', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
}

function graphReadSource(): string
{
    $source = graphReadId();
    $artifact = graphReadId();
    $revision = graphReadId();
    DB::table('sources')->insert(['id' => $source, 'type' => 'git', 'external_system' => 'GitHub', 'scope' => '{}', 'classification' => 'interno', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('artifacts')->insert(['id' => $artifact, 'source_id' => $source, 'external_identifier' => $artifact, 'uri' => 'https://git.example.test/erp/'.$artifact, 'content_hash' => hash('sha256', $artifact), 'media_type' => 'text/markdown', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('revisions')->insert(['id' => $revision, 'artifact_id' => $artifact, 'source_revision' => 'graph-a91f3ce', 'observed_at' => now(), 'checksum' => hash('sha256', $revision), 'metadata' => '{}', 'created_at' => now(), 'updated_at' => now()]);

    return $revision;
}

/** @param array<string,mixed> $attributes */
function graphReadEntity(string $sourceRevision, string $type, string $title, string $owner = 'time-compras', array $attributes = []): array
{
    $entity = graphReadId();
    $revision = graphReadId();
    DB::table('entities')->insert(['id' => $entity, 'entity_type' => $type, 'family' => 'software', 'scope' => json_encode(['organization' => 'nordika', 'application' => 'erp']), 'external_natural_key' => $entity, 'owner_id' => $owner, 'owner_type' => 'team', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('entity_revisions')->insert(['id' => $revision, 'entity_id' => $entity, 'version' => 1, 'lifecycle_state' => 'published', 'title' => $title, 'body' => 'Descrição de '.$title, 'attributes' => json_encode(array_replace(['classification' => 'interno', 'processed_at' => now()->toISOString()], $attributes)), 'source_revision_id' => $sourceRevision, 'confidence' => .91, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

    return compact('entity', 'revision');
}

function graphReadRelation(array $origin, array $destination, string $predicate = 'related_to'): string
{
    $assertion = graphReadId();
    DB::table('assertions')->insert(['id' => $assertion, 'subject_entity_revision_id' => $origin['revision'], 'predicate' => $predicate, 'object_entity_revision_id' => $destination['revision'], 'scope' => json_encode(['organization' => 'nordika', 'application' => 'erp']), 'confidence' => .85, 'method' => 'human_declared', 'actor_id' => 'human:ana', 'actor_type' => 'human', 'lifecycle_state' => 'published', 'valid_from' => now(), 'created_at' => now(), 'updated_at' => now()]);

    return $assertion;
}

function graphReadFixture(): array
{
    $source = graphReadSource();
    $process = graphReadEntity($source, 'Processo', 'Cancelar Pedido de Compra');
    $screen = graphReadEntity($source, 'Tela', 'PC-0421');
    $endpoint = graphReadEntity($source, 'Endpoint', 'DELETE /purchase-orders');
    graphReadRelation($process, $screen, 'contains');
    graphReadRelation($screen, $endpoint, 'calls');

    return compact('source', 'process', 'screen', 'endpoint');
}

it('returns a focused depth-one neighborhood without layout coordinates and expands only on request', function (): void {
    $user = User::factory()->create();
    graphReadGrant($user);
    $fixture = graphReadFixture();

    $this->actingAs($user)->getJson('/api/v1/graph/neighborhood?entity='.$fixture['process']['entity'])
        ->assertOk()->assertJsonPath('focus', $fixture['process']['entity'])->assertJsonPath('depth', 1)
        ->assertJsonCount(2, 'nodes')->assertJsonCount(1, 'edges')->assertJsonPath('nodes.0.color', 'var(--violet)')
        ->assertJsonStructure(['nodes' => [['id', 'type', 'label', 'color', 'weight', 'provenance']], 'edges' => [['origin', 'destination', 'predicate', 'weight', 'provenance']]]);

    $this->actingAs($user)->getJson('/api/v1/graph/neighborhood?entity='.$fixture['process']['entity'].'&depth=2&group_by=type')
        ->assertOk()->assertJsonPath('depth', 2)->assertJsonCount(3, 'nodes')->assertJsonCount(2, 'edges');
    $this->actingAs($user)->getJson('/api/v1/graph/neighborhood?entity='.$fixture['process']['entity'].'&depth=4')
        ->assertUnprocessable()->assertJsonPath('code', 'request.validation_failed');
});

it('finds entities without relations, owners or assertion evidence and protects the node panel', function (): void {
    $user = User::factory()->create();
    graphReadGrant($user);
    $fixture = graphReadFixture();
    $isolated = graphReadEntity($fixture['source'], 'Documento', 'ADR sem relação');
    $unowned = graphReadEntity($fixture['source'], 'Regra', 'RN-233', '');
    graphReadRelation($fixture['process'], $unowned);

    $this->actingAs($user)->getJson('/api/v1/graph/orphans')->assertOk()
        ->assertJsonFragment(['id' => $isolated['entity'], 'reasons' => ['sem_relacao']])
        ->assertJsonFragment(['id' => $unowned['entity'], 'reasons' => ['sem_responsavel', 'sem_evidencia']]);
    $this->actingAs($user)->getJson('/api/v1/graph/nodes/'.$fixture['process']['entity'])->assertOk()
        ->assertJsonPath('relations_count', 2)->assertJsonPath('owner.id', 'time-compras')->assertJsonStructure(['description', 'updated_at', 'provenance']);
    $other = User::factory()->create();
    $this->actingAs($other)->getJson('/api/v1/graph/nodes/'.$fixture['process']['entity'])->assertForbidden();
});

it('diagnoses an unowned rule, a screen without print and a cluster event in the DLQ', function (): void {
    $user = User::factory()->create();
    graphReadGrant($user);
    $fixture = graphReadFixture();
    $rule = graphReadEntity($fixture['source'], 'Regra', 'RN-233', '');
    $event = graphReadEntity($fixture['source'], 'Evento', 'purchase_order.canceled');
    graphReadRelation($fixture['process'], $rule);
    graphReadRelation($fixture['process'], $event);
    DB::table('outbox_events')->insert(['event_id' => graphReadId(), 'aggregate' => 'entity', 'aggregate_id' => $event['entity'], 'tipo' => 'KnowledgeRevisionCreated', 'schema_version' => '1.0.0', 'payload' => json_encode(['entity' => ['entity_id' => $event['entity']]]), 'occurred_at' => now(), 'correlation_id' => graphReadId(), 'ator' => '{}', 'status' => 'dead_letter', 'tentativas' => 3, 'available_at' => now(), 'last_error' => 'broker indisponível', 'created_at' => now(), 'updated_at' => now()]);

    $this->actingAs($user)->getJson('/api/v1/graph/diagnosis?scope='.$fixture['process']['entity'])->assertOk()
        ->assertJsonPath('summary.total', 3)->assertJsonPath('summary.by_rule.regra_sem_responsavel', 1)
        ->assertJsonPath('summary.by_rule.tela_sem_print', 1)->assertJsonPath('summary.by_rule.evento_com_dlq_ativa', 1);
});

it('keeps a depth-one traversal bounded and responsive with a large direct neighborhood', function (): void {
    $user = User::factory()->create();
    graphReadGrant($user);
    $source = graphReadSource();
    $focus = graphReadEntity($source, 'Processo', 'Processo central');
    for ($index = 0; $index < 300; $index++) {
        $neighbor = graphReadEntity($source, 'Tela', 'Tela '.$index);
        graphReadRelation($focus, $neighbor);
    }

    $startedAt = microtime(true);
    $response = $this->actingAs($user)->getJson('/api/v1/graph/neighborhood?entity='.$focus['entity']);
    $elapsed = microtime(true) - $startedAt;

    $response->assertOk()->assertJsonCount(301, 'nodes')->assertJsonCount(300, 'edges');
    expect($elapsed)->toBeLessThan(1.5);
});
