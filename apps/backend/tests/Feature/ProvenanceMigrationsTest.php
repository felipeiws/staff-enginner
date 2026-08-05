<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

const PROVENANCE_TABLES = [
    'sources',
    'artifacts',
    'revisions',
    'chunks',
    'observations',
    'executions',
];

it('creates the provenance family with its required constraints and indexes', function (): void {
    foreach (PROVENANCE_TABLES as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
        expect(Schema::hasColumns($table, ['id', 'retention_until', 'deleted_at', 'created_at', 'updated_at']))->toBeTrue();
    }

    expect(Schema::hasColumns('sources', ['type', 'external_system', 'scope', 'credential_reference', 'classification', 'active']))->toBeTrue();
    expect(Schema::hasColumns('artifacts', ['source_id', 'external_identifier', 'uri', 'content_hash', 'media_type', 'size_bytes', 'object_storage_pointer']))->toBeTrue();
    expect(Schema::hasColumns('revisions', ['artifact_id', 'source_revision', 'observed_at', 'checksum', 'metadata']))->toBeTrue();
    expect(Schema::hasColumns('chunks', ['revision_id', 'ordinal', 'start_offset', 'end_offset', 'text', 'tokens', 'checksum']))->toBeTrue();
    expect(Schema::hasColumns('observations', ['source_id', 'artifact_id', 'payload', 'idempotency_key', 'correlation_id', 'actor_id', 'actor_type', 'processing_status']))->toBeTrue();
    expect(Schema::hasColumns('executions', ['observation_id', 'revision_id', 'executor_type', 'executor_name', 'executor_version', 'parameters', 'duration_ms', 'cost', 'result']))->toBeTrue();

    $indexes = collect(DB::select("SELECT name FROM sqlite_master WHERE type = 'index'"))->pluck('name');

    expect($indexes)->toContain('artifacts_source_id_external_identifier_unique');
    expect($indexes)->toContain('revisions_artifact_id_source_revision_unique');
    expect($indexes)->toContain('chunks_revision_id_ordinal_unique');
    expect($indexes)->toContain('observations_idempotency_key_unique');
    expect($indexes)->toContain('artifacts_content_hash_index');
    expect($indexes)->toContain('revisions_observed_at_index');
});

it('rolls the provenance family back in dependency order', function (): void {
    $migration = require database_path('migrations/2026_07_31_000000_create_provenance_tables.php');

    $migration->down();

    foreach (PROVENANCE_TABLES as $table) {
        expect(Schema::hasTable($table))->toBeFalse();
    }

    $migration->up();

    foreach (PROVENANCE_TABLES as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }
});

it('defines PostgreSQL GIN indexes for JSONB metadata without applying them to SQLite', function (): void {
    $migration = file_get_contents(database_path('migrations/2026_07_31_000000_create_provenance_tables.php'));

    expect($migration)->toContain('USING GIN (metadata)');
    expect($migration)->toContain('USING GIN (payload)');
    expect($migration)->toContain('USING GIN (parameters)');
    expect($migration)->toContain("getDriverName() === 'pgsql'");
});
