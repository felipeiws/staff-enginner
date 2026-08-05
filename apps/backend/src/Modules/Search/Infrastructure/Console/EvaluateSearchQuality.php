<?php

declare(strict_types=1);

namespace Atlas\Modules\Search\Infrastructure\Console;

use Atlas\Modules\Search\Application\SearchQualityEvaluator;
use Atlas\Modules\Search\Application\HybridSearchService;
use Atlas\Modules\Search\Domain\HybridSearchIndex;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

final class EvaluateSearchQuality extends Command
{
    protected $signature = 'search:evaluate
        {--suite= : Caminho da suíte de queries julgadas.}
        {--results= : Fixture JSON de respostas do índice para execução determinística.}
        {--baseline= : Baseline JSON para detectar regressões.}
        {--history= : Arquivo JSONL da série temporal de resultados.}
        {--k= : Quantidade de resultados avaliada.}';

    protected $description = 'Avalia qualidade da busca híbrida e registra uma série temporal comparável.';

    public function handle(): int
    {
        $suite = $this->readJson((string) ($this->option('suite') ?: base_path('../../docs/search-engine/evaluation/queries.v1.json')));
        $k = (int) ($this->option('k') ?: config('search.evaluation.k', 10));
        if ($this->option('results')) {
            app()->instance(HybridSearchIndex::class, new EvaluationFixtureSearchIndex($this->readJson((string) $this->option('results'))));
            app()->forgetInstance(HybridSearchService::class);
            app()->forgetInstance(SearchQualityEvaluator::class);
        }
        $evaluator = app(SearchQualityEvaluator::class);
        $result = $evaluator->evaluate($suite, $k);
        $result['executed_at'] = now()->toIso8601String();
        $result['configuration'] = ['embedding_model' => config('search.embedding_model'), 'embedding_model_version' => config('search.embedding_model_version'), 'rrf_k' => config('search.rrf_k')];
        $baselinePath = (string) ($this->option('baseline') ?: base_path('../../docs/search-engine/evaluation/baseline.v1.json'));
        $baseline = is_file($baselinePath) ? $this->readJson($baselinePath) : null;
        $regressions = $baseline === null ? [] : $this->regressions((array) $result['metrics'], (array) ($baseline['metrics'] ?? []));
        $result['baseline'] = $baselinePath;
        $result['regressions'] = $regressions;
        $history = (string) ($this->option('history') ?: storage_path('app/search-evaluations/history.jsonl'));
        File::ensureDirectoryExists(dirname($history));
        File::append($history, json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        if ($regressions !== []) {
            $this->error('Regressão além do limiar: ' . implode('; ', $regressions));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** @return array<string,mixed> */
    private function readJson(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Arquivo de avaliação não encontrado: {$path}");
        }

        $decoded = json_decode((string) File::get($path), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new RuntimeException("Arquivo de avaliação inválido: {$path}");
        }

        return $decoded;
    }

    /** @param array<string,float|int> $metrics @param array<string,mixed> $baseline @return list<string> */
    private function regressions(array $metrics, array $baseline): array
    {
        $thresholds = (array) config('search.evaluation.regression_thresholds', []);
        $regressions = [];
        foreach (['recall_at_k', 'mrr', 'ndcg_at_k', 'citation_coverage'] as $metric) {
            if (isset($baseline[$metric]) && (($baseline[$metric] - $metrics[$metric]) > ($thresholds[$metric] ?? 0))) {
                $regressions[] = "{$metric}: {$metrics[$metric]} < {$baseline[$metric]}";
            }
        }
        if (isset($baseline['zero_result_rate']) && (($metrics['zero_result_rate'] - $baseline['zero_result_rate']) > ($thresholds['zero_result_rate'] ?? 0))) {
            $regressions[] = "zero_result_rate: {$metrics['zero_result_rate']} > {$baseline['zero_result_rate']}";
        }
        if (isset($baseline['latency_p95_ms']) && (($metrics['latency_p95_ms'] - $baseline['latency_p95_ms']) > ($thresholds['latency_p95_ms'] ?? 0))) {
            $regressions[] = "latency_p95_ms: {$metrics['latency_p95_ms']} > {$baseline['latency_p95_ms']}";
        }

        return $regressions;
    }
}

/** Deterministic adapter used only by CI and local evaluation fixtures. */
final readonly class EvaluationFixtureSearchIndex implements HybridSearchIndex
{
    /** @param array<string,mixed> $results */
    public function __construct(private array $results) {}

    public function search(string $query, array $filters, ?array $queryEmbedding, int $limit): array
    {
        $entry = (array) ($this->results['queries'][$query] ?? []);

        return ['lexical' => array_slice((array) ($entry['lexical'] ?? []), 0, $limit), 'vector' => array_slice((array) ($entry['vector'] ?? []), 0, $limit), 'facets' => ['application' => [], 'freshness' => [], 'owner' => []]];
    }
}
