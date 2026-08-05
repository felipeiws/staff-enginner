<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\UxDecisionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('returns only authorized published UX decisions with their graph provenance', function (): void {
    $user = User::factory()->create();
    $source = (string) Str::uuid(); $artifact = (string) Str::uuid(); $sourceRevision = (string) Str::uuid(); $entity = (string) Str::uuid(); $entityRevision = (string) Str::uuid();
    DB::table('identity_grants')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'role' => 'leitor', 'scope' => json_encode(['organization' => 'nordika']), 'permissions' => json_encode(['knowledge.read']), 'classification_ceiling' => 'interno', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('sources')->insert(['id' => $source, 'type' => 'git', 'external_system' => 'Atlas', 'scope' => '{}', 'classification' => 'interno', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('artifacts')->insert(['id' => $artifact, 'source_id' => $source, 'external_identifier' => 'prototype', 'uri' => 'docs/design/prototype/atlas-prototype.html#L1397-L1404', 'content_hash' => hash('sha256', 'prototype'), 'media_type' => 'text/html', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('revisions')->insert(['id' => $sourceRevision, 'artifact_id' => $artifact, 'source_revision' => 'prototype-ux-notes', 'observed_at' => now(), 'checksum' => hash('sha256', 'prototype-ux-notes'), 'metadata' => '{}', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('entities')->insert(['id' => $entity, 'entity_type' => 'Documento', 'family' => 'conhecimento', 'scope' => json_encode(['organization' => 'nordika']), 'external_natural_key' => 'ux-decision-search', 'owner_id' => 'produto-atlas', 'owner_type' => 'team', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('entity_revisions')->insert(['id' => $entityRevision, 'entity_id' => $entity, 'version' => 1, 'lifecycle_state' => 'published', 'title' => 'Busca e IA são o mesmo campo', 'body' => 'Um único input decide.', 'attributes' => json_encode(['kind' => 'ux_decision', 'ux_decision_order' => 1, 'outcome' => 'Elimina 1 decisão.', 'classification' => 'interno', 'method' => 'human_declared@1.0.0', 'processed_at' => now()->toISOString()]), 'source_revision_id' => $sourceRevision, 'confidence' => 1, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

    $this->actingAs($user)->getJson('/api/v1/ux-decisions')->assertOk()
        ->assertJsonPath('decisions.0.id', $entity)->assertJsonPath('decisions.0.type', 'Documento')
        ->assertJsonPath('decisions.0.outcome', 'Elimina 1 decisão.')->assertJsonPath('decisions.0.provenance.revision', 'prototype-ux-notes');
});

it('does not expose UX decisions without a matching knowledge grant', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user)->getJson('/api/v1/ux-decisions')->assertOk()->assertJsonCount(0, 'decisions');
});

it('seeds the six prototype decisions as versioned Document entities without duplication', function (): void {
    $this->seed(UxDecisionSeeder::class);
    $this->seed(UxDecisionSeeder::class);

    expect(DB::table('entities')->where('entity_type', 'Documento')->where('external_natural_key', 'like', 'ux-decision-%')->count())->toBe(6)
        ->and(DB::table('entity_revisions')->where('lifecycle_state', 'published')->where('attributes', 'like', '%ux_decision%')->count())->toBe(6);
    expect(DB::table('artifacts')->where('external_identifier', 'atlas-prototype-ux-notes')->value('uri'))->toBe('docs/design/prototype/atlas-prototype.html#L1397-L1404');
});
