<?php

declare(strict_types=1);

use Atlas\Contracts\Exception\ContractViolation;
use Atlas\Modules\Ingestion\Application\ReceiveSourceObservation;
use Atlas\Modules\Ingestion\Application\SourceObservationConflict;
use Atlas\Modules\Ingestion\Application\UnknownSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function ingestionUuid(int $number): string
{
    return sprintf('70000000-0000-4000-8000-%012d', $number);
}

function knownIngestionSource(): void
{
    DB::table('sources')->insert(['id' => ingestionUuid(1), 'type' => 'git_repository', 'scope' => json_encode(['application' => 'atlas']), 'classification' => 'internal', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
}

/** @return array<string, mixed> */
function sourceObservation(array $overrides = []): array
{
    return array_replace_recursive([
        'schema_version' => '1.0.0', 'observation_id' => ingestionUuid(2), 'external_id' => 'docs/adr/006.md', 'source_type' => 'git_repository', 'uri' => 'file:///docs/adr/006.md', 'revision' => 'git:abc123', 'observed_at' => '2026-07-31T12:00:00Z', 'content' => '# ADR-006', 'checksum' => 'sha256:abc123', 'metadata' => ['title' => 'ADR-006'], 'classification' => 'internal', 'command' => ['idempotency_key' => 'ingestion-command-1', 'actor' => ['actor_id' => 'plugin:git', 'actor_type' => 'plugin'], 'correlation_id' => ingestionUuid(3)],
    ], $overrides);
}

beforeEach(function (): void {
    config()->set('ingestion.object_storage_disk', 'ingestion');
    config()->set('ingestion.inline_content_limit_bytes', 32);
    Storage::fake('ingestion');
});

it('is idempotent and records provenance, execution, observed revision, and outbox event', function (): void {
    knownIngestionSource();
    $receiver = app(ReceiveSourceObservation::class);
    $payload = sourceObservation();

    $first = $receiver->handle($payload);
    $second = $receiver->handle($payload);

    expect($first->toArray())->toBe($second->toArray())
        ->and($second->replayed)->toBeTrue()
        ->and(DB::table('observations')->count())->toBe(1)
        ->and(DB::table('revisions')->count())->toBe(1)
        ->and(DB::table('entity_revisions')->value('lifecycle_state'))->toBe('observed')
        ->and(DB::table('executions')->where('executor_name', 'source-observation-receiver')->where('executor_version', '1.0.0')->count())->toBe(1)
        ->and(DB::table('outbox_events')->where('tipo', 'KnowledgeRevisionCreated')->count())->toBe(1);
});

it('rejects different content for an existing source revision instead of overwriting it', function (): void {
    knownIngestionSource();
    $receiver = app(ReceiveSourceObservation::class);
    $receiver->handle(sourceObservation());

    expect(fn (): mixed => $receiver->handle(sourceObservation(['observation_id' => ingestionUuid(4), 'content' => '# conteúdo alterado', 'checksum' => 'sha256:different', 'command' => ['idempotency_key' => 'ingestion-command-2', 'actor' => ['actor_id' => 'plugin:git', 'actor_type' => 'plugin'], 'correlation_id' => ingestionUuid(5)]])))->toThrow(SourceObservationConflict::class)
        ->and(DB::table('revisions')->count())->toBe(1);
});

it('stores large raw content under its content hash in object storage', function (): void {
    knownIngestionSource();
    $content = str_repeat('conteúdo grande ', 10);
    app(ReceiveSourceObservation::class)->handle(sourceObservation(['content' => $content]));

    $hash = hash('sha256', $content);
    Storage::disk('ingestion')->assertExists("ingestion/content/{$hash}");
    expect(DB::table('artifacts')->value('object_storage_pointer'))->toBe("ingestion/content/{$hash}")
        ->and(DB::table('entity_revisions')->value('body'))->toBeNull();
});

it('quarantines and redacts an accidental secret before it can reach events, storage or alerts', function (): void {
    knownIngestionSource();
    $secret = 'sk_abcdefghijklmnopqrstuvwxyz123456';
    app(ReceiveSourceObservation::class)->handle(sourceObservation(['content' => "API_KEY={$secret}", 'command' => ['idempotency_key' => 'ingestion-secret-1', 'actor' => ['actor_id' => 'plugin:git', 'actor_type' => 'plugin'], 'correlation_id' => ingestionUuid(8)]]));

    expect(DB::table('observations')->value('processing_status'))->toBe('quarantined')
        ->and((string) DB::table('observations')->value('payload'))->not->toContain($secret)
        ->and((string) DB::table('entity_revisions')->value('body'))->not->toContain($secret)
        ->and(DB::table('outbox_events')->count())->toBe(0)
        ->and(DB::table('content_classifications')->value('quarantined'))->toBe(1)
        ->and((string) DB::table('security_alerts')->value('details'))->not->toContain($secret);
});

it('rejects an observation from an unknown source', function (): void {
    expect(fn (): mixed => app(ReceiveSourceObservation::class)->handle(sourceObservation()))->toThrow(UnknownSource::class)
        ->and(DB::table('observations')->count())->toBe(0);
});

it('validates the payload against the SourceObservation schema before writing', function (): void {
    knownIngestionSource();
    $payload = sourceObservation();
    unset($payload['command']['actor']);

    expect(fn (): mixed => app(ReceiveSourceObservation::class)->handle($payload))->toThrow(ContractViolation::class)
        ->and(DB::table('observations')->count())->toBe(0);
});
