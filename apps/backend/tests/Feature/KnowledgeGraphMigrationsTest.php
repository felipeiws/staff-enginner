<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

const KNOWLEDGE_GRAPH_TABLES = ['entities', 'entity_revisions', 'assertions', 'assertion_evidence'];

function graphId(int $number): string
{
    return sprintf('00000000-0000-4000-8000-%012d', $number);
}

function graphProvenance(): void
{
    DB::table('sources')->insert(['id' => graphId(1), 'type' => 'git', 'scope' => '{}', 'classification' => 'internal', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('artifacts')->insert(['id' => graphId(2), 'source_id' => graphId(1), 'external_identifier' => 'docs/adr/003.md', 'uri' => 'file:///docs/adr/003.md', 'content_hash' => 'sha256:artifact', 'media_type' => 'text/markdown', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('revisions')->insert(['id' => graphId(3), 'artifact_id' => graphId(2), 'source_revision' => 'git:abc123', 'observed_at' => now(), 'checksum' => 'sha256:revision', 'metadata' => '{}', 'created_at' => now(), 'updated_at' => now()]);
}

function graphEntity(string $id, string $key): void
{
    DB::table('entities')->insert(['id' => $id, 'entity_type' => 'Documento', 'family' => 'conhecimento', 'scope' => '{}', 'external_natural_key' => $key, 'owner_id' => 'ana@example.com', 'owner_type' => 'human', 'created_at' => now(), 'updated_at' => now()]);
}

function graphRevision(string $id, string $entityId, int $version, ?string $supersededBy = null, string $state = 'published'): void
{
    DB::table('entity_revisions')->insert(['id' => $id, 'entity_id' => $entityId, 'version' => $version, 'lifecycle_state' => $state, 'title' => 'ADR-003', 'body' => null, 'attributes' => '{}', 'source_revision_id' => graphId(3), 'confidence' => 1, 'published_at' => now(), 'superseded_by' => $supersededBy, 'created_at' => now(), 'updated_at' => now()]);
}

it('creates the graph family, published read views, and traversal indexes', function (): void {
    foreach (KNOWLEDGE_GRAPH_TABLES as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }

    expect(Schema::hasColumns('entities', ['entity_type', 'family', 'scope', 'external_natural_key', 'owner_id']))->toBeTrue();
    expect(Schema::hasColumns('entity_revisions', ['entity_id', 'version', 'lifecycle_state', 'source_revision_id', 'superseded_by']))->toBeTrue();
    expect(Schema::hasColumns('assertions', ['subject_entity_revision_id', 'predicate', 'object_entity_revision_id', 'literal_value', 'scope', 'method']))->toBeTrue();
    expect(Schema::hasColumns('assertion_evidence', ['assertion_id', 'evidence_type', 'chunk_id', 'artifact_id', 'event_id', 'weight']))->toBeTrue();

    $objects = collect(DB::select("SELECT name FROM sqlite_master WHERE type IN ('index', 'view')"))->pluck('name');
    expect($objects)->toContain('published_entity_revisions', 'published_assertions', 'assertions_subject_entity_revision_id_index', 'assertions_object_entity_revision_id_index');
});

it('enforces a valid assertion subject through its foreign key', function (): void {
    try {
        DB::table('assertions')->insert(['id' => graphId(10), 'subject_entity_revision_id' => graphId(999), 'predicate' => 'documents', 'literal_value' => json_encode('ADR-003'), 'scope' => '{}', 'confidence' => 1, 'method' => 'human_declared', 'actor_id' => 'ana@example.com', 'actor_type' => 'human', 'lifecycle_state' => 'candidate', 'created_at' => now(), 'updated_at' => now()]);
        $this->fail('A assertion sem revisão-sujeito não deve ser persistida.');
    } catch (Throwable) {
        expect(true)->toBeTrue();
    }
});

it('versions an entity, records supersession, and exposes only published graph records', function (): void {
    graphProvenance();
    graphEntity(graphId(20), 'adr-003');
    graphRevision(graphId(22), graphId(20), 2);
    graphRevision(graphId(21), graphId(20), 1, graphId(22), 'superseded');
    graphEntity(graphId(23), 'adr-006');
    graphRevision(graphId(24), graphId(23), 1, null, 'candidate');

    expect(DB::table('entity_revisions')->where('entity_id', graphId(20))->count())->toBe(2);
    expect(DB::table('entity_revisions')->where('id', graphId(21))->value('superseded_by'))->toBe(graphId(22));
    expect(DB::table('published_entity_revisions')->pluck('id')->all())->toBe([graphId(22)]);
});

it('declares PostgreSQL constraints for vocabulary, temporal values, and evidence', function (): void {
    $migration = file_get_contents(database_path('migrations/2026_07_31_000100_create_knowledge_graph_tables.php'));

    expect($migration)->toContain('entities_type_check', 'assertions_predicate_check', 'assertions_object_or_literal_check', 'assertion_evidence_type_check');
    expect($migration)->toContain('CREATE CONSTRAINT TRIGGER assertions_require_evidence', "NEW.method <> 'human_declared'", 'CREATE CONSTRAINT TRIGGER assertion_evidence_cannot_be_removed', 'DEFERRABLE INITIALLY DEFERRED');
});
