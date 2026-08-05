<?php

declare(strict_types=1);

use Atlas\Modules\Identity\Application\MachineTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function workerResultId(int $id): string
{
    return sprintf('83000000-0000-4000-8000-%012d', $id);
}

function workerResultRevision(): string
{
    $now = now();
    $source = workerResultId(1);
    $artifact = workerResultId(2);
    $revision = workerResultId(3);
    DB::table('sources')->insert(['id' => $source, 'type' => 'git', 'scope' => json_encode(['organization' => 'atlas', 'application' => 'portal']), 'classification' => 'interno', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
    DB::table('artifacts')->insert(['id' => $artifact, 'source_id' => $source, 'external_identifier' => 'worker-fixture', 'uri' => 'https://example.test/worker', 'content_hash' => hash('sha256', 'texto'), 'media_type' => 'text/markdown', 'created_at' => $now, 'updated_at' => $now]);
    DB::table('revisions')->insert(['id' => $revision, 'artifact_id' => $artifact, 'source_revision' => 'worker-r1', 'observed_at' => $now, 'checksum' => hash('sha256', 'worker-r1'), 'metadata' => '{}', 'created_at' => $now, 'updated_at' => $now]);

    return $revision;
}

it('accepts an idempotent worker result through the API and persists its derived projection', function (): void {
    config()->set('search.embedding_dimensions', 2);
    $revision = workerResultRevision();
    $token = app(MachineTokenService::class)->issue('worker-test', 'worker', [['scope' => ['organization' => 'atlas'], 'permissions' => ['knowledge.contribute'], 'classification_ceiling' => 'interno']])['token'];
    $body = ['schema_version' => '1.0.0', 'event_type' => 'EmbeddingGenerated', 'task_id' => workerResultId(4), 'revision_id' => $revision, 'scope' => ['organization' => 'atlas'], 'provenance' => ['source_revision_id' => $revision, 'method' => 'embedding@fixture:1', 'processed_at' => now()->toISOString()], 'chunks' => [[
        'ordinal' => 0, 'start_offset' => 0, 'end_offset' => 14, 'text' => 'texto derivado', 'tokens' => 2, 'checksum' => hash('sha256', 'texto derivado'), 'embedding' => [0.1, 0.2], 'model' => 'fixture', 'model_version' => '1', 'parameters' => ['chunking' => ['max_tokens' => 800]], 'cost' => 0.000001, 'cost_currency' => 'USD',
    ]]];

    $response = $this->withToken($token)->withHeader('Idempotency-Key', 'worker-result-key')->withHeader('X-Correlation-Id', workerResultId(5))->postJson('/api/v1/worker-results/embeddings', $body);

    $response->assertCreated()->assertJson(['revision_id' => $revision, 'status' => 'persisted']);
    expect(DB::table('chunks')->where('revision_id', $revision)->count())->toBe(1)
        ->and(DB::table('chunk_embeddings')->where('model', 'fixture')->value('chunk_checksum'))->toBe(hash('sha256', 'texto derivado'));
});
