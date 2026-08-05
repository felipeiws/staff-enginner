<?php

declare(strict_types=1);

use Atlas\Contracts\Dto\Actor;
use Atlas\Modules\Ingestion\Application\IngestVisualArtifact;
use Atlas\Modules\Ingestion\Application\VisualArtifactFreshness;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function visualSource(): void
{
    DB::table('sources')->insert(['id' => '70000000-0000-4000-8000-000000000050', 'type' => 'visual_capture', 'scope' => json_encode(['application' => 'atlas']), 'classification' => 'internal', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
}

/** @return array<string, mixed> */
function visualArtifact(array $overrides = []): array
{
    $content = 'fixture visual: botão Cancelar';
    return array_replace_recursive(['screen_key' => 'PC-0421', 'screen_title' => 'Detalhe do Pedido de Compra', 'variant' => 'desktop', 'kind' => 'print', 'content_base64' => base64_encode($content), 'content_hash' => 'sha256:'.hash('sha256', $content), 'media_type' => 'image/png', 'source_type' => 'visual_capture', 'source_revision' => 'git:a91f3ce', 'artifact_at' => '2026-02-01T12:00:00Z', 'code_changed_at' => '2026-03-01T12:00:00Z', 'scope' => ['application' => 'atlas'], 'components' => ['Cancelar'], 'relationships' => ['RN-142'], 'provenance' => ['source_revision' => 'git:a91f3ce', 'method' => 'artifact.reader@1.0.0', 'processed_at' => '2026-02-01T12:01:00Z'], 'ocr_evidence' => ['text' => 'Cancelar pedido', 'method' => 'tesseract@5', 'confidence' => 0.94, 'processed_at' => '2026-02-01T12:01:00Z']], $overrides);
}

beforeEach(function (): void {
    config()->set('ingestion.object_storage_disk', 'ingestion');
    Storage::fake('ingestion');
    visualSource();
    CarbonImmutable::setTestNow('2026-07-01T12:00:00Z');
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('ingests a visual upload with OCR evidence and a stale-screen seal', function (): void {
    $result = app(IngestVisualArtifact::class)->handle(visualArtifact(), new Actor('worker:artifact-reader', 'worker'));

    expect($result->replayed)->toBeFalse()
        ->and(DB::table('entities')->where('entity_type', 'Tela')->count())->toBe(1)
        ->and(DB::table('chunks')->value('text'))->toBe('Cancelar pedido')
        ->and(DB::table('entity_revisions')->value('lifecycle_state'))->toBe('observed');
    $attributes = json_decode((string) DB::table('entity_revisions')->value('attributes'), true, 512, JSON_THROW_ON_ERROR);
    expect($attributes['visual_artifact']['variant'])->toBe('desktop')
        ->and($attributes['visual_artifact']['freshness']['label'])->toBe('print desatualizado há 5 meses');
    Storage::disk('ingestion')->assertExists('ingestion/visual/'.substr((string) DB::table('artifacts')->value('content_hash'), 7));
});

it('deduplicates equal content by hash while retaining each Tela revision', function (): void {
    $ingestion = app(IngestVisualArtifact::class);
    $first = $ingestion->handle(visualArtifact(), new Actor('worker:artifact-reader', 'worker'));
    $second = $ingestion->handle(visualArtifact(['screen_key' => 'PC-0433', 'screen_title' => 'Motivo do Cancelamento']), new Actor('worker:artifact-reader', 'worker'));

    expect($first->artifactId)->toBe($second->artifactId)
        ->and(DB::table('artifacts')->count())->toBe(1)
        ->and(DB::table('entities')->where('entity_type', 'Tela')->count())->toBe(2)
        ->and(DB::table('chunks')->count())->toBe(1);
});

it('calculates the prototype stale label from artifact and code dates', function (): void {
    $seal = app(VisualArtifactFreshness::class)->calculate('2026-02-01T12:00:00Z', '2026-03-01T12:00:00Z');

    expect($seal['status'])->toBe('desatualizado')->and($seal['label'])->toBe('print desatualizado há 5 meses');
});
