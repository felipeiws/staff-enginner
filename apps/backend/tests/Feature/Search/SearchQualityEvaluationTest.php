<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('calculates the judged retrieval metrics and persists a comparable history entry', function (): void {
    $history = storage_path('framework/testing/search-quality-history.jsonl');
    File::delete($history);

    $this->artisan('search:evaluate', [
        '--suite' => base_path('../../docs/search-engine/evaluation/queries.v1.json'),
        '--results' => base_path('../../docs/search-engine/evaluation/results.v1.json'),
        '--baseline' => base_path('../../docs/search-engine/evaluation/baseline.v1.json'),
        '--history' => $history,
    ])->assertSuccessful();

    $entry = json_decode(trim((string) File::get($history)), true, flags: JSON_THROW_ON_ERROR);
    expect($entry['metrics'])->toMatchArray([
        'recall_at_k' => 1.0,
        'mrr' => 1.0,
        'ndcg_at_k' => 0.966798,
        'citation_coverage' => 1.0,
        'zero_result_rate' => 0.0,
    ])->and($entry['queries'])->toHaveCount(5)->and($entry['regressions'])->toBe([]);
});

it('fails only when a quality metric regresses beyond the configured threshold', function (): void {
    $fixture = storage_path('framework/testing/search-quality-regression.json');
    File::put($fixture, json_encode(['queries' => []], JSON_THROW_ON_ERROR));

    $this->artisan('search:evaluate', [
        '--suite' => base_path('../../docs/search-engine/evaluation/queries.v1.json'),
        '--results' => $fixture,
        '--baseline' => base_path('../../docs/search-engine/evaluation/baseline.v1.json'),
        '--history' => storage_path('framework/testing/search-quality-regression-history.jsonl'),
    ])->assertFailed();
});
