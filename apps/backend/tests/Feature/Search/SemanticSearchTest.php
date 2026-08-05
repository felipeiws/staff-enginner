<?php

declare(strict_types=1);

use Atlas\Modules\Search\Application\EmbeddingPersistenceService;
use Atlas\Modules\Search\Application\SemanticSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function semanticId(int $id): string
{
    return sprintf('82000000-0000-4000-8000-%012d', $id);
}

function semanticFixture(int $number, string $organization, string $classification, string $text): string
{
    $now = now();
    $source = semanticId($number * 10 + 1);
    $artifact = semanticId($number * 10 + 2);
    $revision = semanticId($number * 10 + 3);
    DB::table('sources')->insert(['id' => $source, 'type' => 'git', 'scope' => json_encode(['organization' => $organization, 'application' => 'atlas']), 'classification' => $classification, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
    DB::table('artifacts')->insert(['id' => $artifact, 'source_id' => $source, 'external_identifier' => "semantic-{$number}", 'uri' => "https://example.test/{$number}", 'content_hash' => hash('sha256', $text), 'media_type' => 'text/markdown', 'created_at' => $now, 'updated_at' => $now]);
    DB::table('revisions')->insert(['id' => $revision, 'artifact_id' => $artifact, 'source_revision' => "r{$number}", 'observed_at' => $now, 'checksum' => hash('sha256', "r{$number}"), 'metadata' => '{}', 'created_at' => $now, 'updated_at' => $now]);

    return $revision;
}

beforeEach(function (): void {
    config()->set('search.embedding_dimensions', 2);
});

it('persists a provenance-complete worker result and retrieves only pre-filtered scope and classification', function (): void {
    $allowed = semanticFixture(1, 'nordika', 'interno', 'recall permitido');
    $otherScope = semanticFixture(2, 'outra-org', 'interno', 'escopo proibido');
    $restricted = semanticFixture(3, 'nordika', 'restrito', 'classificação proibida');
    $service = app(EmbeddingPersistenceService::class);
    foreach ([[$allowed, 'recall permitido'], [$otherScope, 'escopo proibido'], [$restricted, 'classificação proibida']] as [$revision, $text]) {
        $service->persistGenerated(['revision_id' => $revision, 'chunks' => [[
            'ordinal' => 0, 'start_offset' => 0, 'end_offset' => strlen($text), 'text' => $text, 'tokens' => 2,
            'checksum' => hash('sha256', $text), 'embedding' => [0.1, 0.2], 'model' => 'fixture', 'model_version' => '1',
            'parameters' => ['chunking' => ['max_tokens' => 800]], 'cost' => 0.000001, 'cost_currency' => 'USD',
        ]]]);
    }

    $results = app(SemanticSearchService::class)->search(['organization' => 'nordika', 'application' => 'atlas'], ['publico', 'interno'], [0.1, 0.2]);
    expect($results)->toHaveCount(1)->and($results[0]['revision_id'])->toBe($allowed)
        ->and(DB::table('chunk_embeddings')->where('chunk_checksum', hash('sha256', 'recall permitido'))->value('model_version'))->toBe('1');
});

it('achieves recall on the small semantic fixture set', function (): void {
    $revision = semanticFixture(4, 'nordika', 'interno', 'cancelamento de pedido de compra');
    app(EmbeddingPersistenceService::class)->persistGenerated(['revision_id' => $revision, 'chunks' => [[
        'ordinal' => 0, 'start_offset' => 0, 'end_offset' => 30, 'text' => 'cancelamento de pedido de compra', 'tokens' => 5,
        'checksum' => hash('sha256', 'cancelamento de pedido de compra'), 'embedding' => [0.9, 0.1], 'model' => 'fixture', 'model_version' => '1', 'parameters' => [],
    ]]]);

    $results = app(SemanticSearchService::class)->search(['organization' => 'nordika', 'application' => 'atlas'], ['interno'], [0.9, 0.1]);
    expect($results)->toHaveCount(1)->and($results[0]['revision_id'])->toBe($revision);
});
