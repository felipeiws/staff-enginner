<?php

declare(strict_types=1);

namespace Atlas\Modules\Search\Application;

use Atlas\Modules\Search\Domain\HybridSearchIndex;
use InvalidArgumentException;

/**
 * Calculates vendor-neutral retrieval metrics from a versioned, judged suite.
 *
 * The suite deliberately contains only public synthetic examples. Production
 * callers still query the derived index, never a source-of-truth table.
 */
final readonly class SearchQualityEvaluator
{
    public function __construct(private HybridSearchIndex $index, private HybridSearchService $hybridSearch) {}

    /**
     * @param array{suite_id:string,schema_version:string,queries:list<array<string,mixed>>} $suite
     * @return array<string,mixed>
     */
    public function evaluate(array $suite, int $k): array
    {
        if ($k < 1 || ! isset($suite['queries']) || ! is_array($suite['queries'])) {
            throw new InvalidArgumentException('A suíte de avaliação deve conter queries e k positivo.');
        }

        $totals = ['recall_at_k' => 0.0, 'mrr' => 0.0, 'ndcg_at_k' => 0.0, 'citation_coverage' => 0.0, 'zero_result_rate' => 0.0];
        $latencies = [];
        $details = [];
        foreach ($suite['queries'] as $query) {
            $this->assertQuery($query);
            $startedAt = hrtime(true);
            $raw = $this->index->search((string) $query['query'], (array) $query['filters'], null, $k);
            $results = array_slice($this->hybridSearch->fuse((array) $raw['lexical'], (array) $raw['vector']), 0, $k);
            $latency = (hrtime(true) - $startedAt) / 1_000_000;
            $latencies[] = $latency;

            $judgments = [];
            foreach ($query['judgments'] as $judgment) {
                $judgments[(string) $judgment['id']] = (int) $judgment['grade'];
            }
            $relevantIds = array_keys(array_filter($judgments, static fn(int $grade): bool => $grade > 0));
            $retrievedIds = array_map(static fn(array $result): string => (string) $result['id'], $results);
            $relevantRetrieved = array_values(array_intersect($retrievedIds, $relevantIds));
            $recall = count($relevantIds) === 0 ? 1.0 : count($relevantRetrieved) / count($relevantIds);
            $mrr = $this->reciprocalRank($retrievedIds, $judgments);
            $ndcg = $this->ndcg($retrievedIds, $judgments, $k);
            $citationCoverage = $this->citationCoverage($results, $relevantRetrieved);
            $zeroResult = $results === [] ? 1.0 : 0.0;

            foreach (['recall_at_k' => $recall, 'mrr' => $mrr, 'ndcg_at_k' => $ndcg, 'citation_coverage' => $citationCoverage, 'zero_result_rate' => $zeroResult] as $metric => $value) {
                $totals[$metric] += $value;
            }
            $details[] = ['id' => $query['id'], 'latency_ms' => round($latency, 3), 'result_ids' => $retrievedIds, 'metrics' => ['recall_at_k' => $recall, 'mrr' => $mrr, 'ndcg_at_k' => $ndcg, 'citation_coverage' => $citationCoverage, 'zero_result_rate' => $zeroResult]];
        }

        $count = count($details);
        if ($count === 0) {
            throw new InvalidArgumentException('A suíte de avaliação não pode estar vazia.');
        }
        foreach ($totals as $metric => $total) {
            $totals[$metric] = round($total / $count, 6);
        }

        return ['suite_id' => $suite['suite_id'], 'schema_version' => $suite['schema_version'], 'k' => $k, 'query_count' => $count, 'metrics' => [...$totals, 'latency_p50_ms' => round($this->percentile($latencies, 50), 3), 'latency_p95_ms' => round($this->percentile($latencies, 95), 3)], 'queries' => $details];
    }

    /** @param array<string,mixed> $query */
    private function assertQuery(array $query): void
    {
        foreach (['id', 'query', 'filters', 'judgments'] as $field) {
            if (! isset($query[$field])) {
                throw new InvalidArgumentException("Query de avaliação sem {$field}.");
            }
        }
    }

    /** @param list<string> $ids @param array<string,int> $judgments */
    private function reciprocalRank(array $ids, array $judgments): float
    {
        foreach ($ids as $rank => $id) {
            if (($judgments[$id] ?? 0) > 0) {
                return 1 / ($rank + 1);
            }
        }

        return 0.0;
    }

    /** @param list<string> $ids @param array<string,int> $judgments */
    private function ndcg(array $ids, array $judgments, int $k): float
    {
        $dcg = 0.0;
        foreach (array_slice($ids, 0, $k) as $rank => $id) {
            $dcg += ((2 ** ($judgments[$id] ?? 0)) - 1) / log($rank + 2, 2);
        }
        $grades = array_values($judgments);
        rsort($grades);
        $ideal = 0.0;
        foreach (array_slice($grades, 0, $k) as $rank => $grade) {
            $ideal += ((2 ** $grade) - 1) / log($rank + 2, 2);
        }

        return $ideal === 0.0 ? 1.0 : $dcg / $ideal;
    }

    /** @param list<array<string,mixed>> $results @param list<string> $relevantIds */
    private function citationCoverage(array $results, array $relevantIds): float
    {
        if ($relevantIds === []) {
            return 1.0;
        }
        $cited = 0;
        foreach ($results as $result) {
            if (! in_array((string) $result['id'], $relevantIds, true)) {
                continue;
            }
            if (filled($result['origin_uri'] ?? null) && filled($result['source_revision'] ?? null) && filled($result['method'] ?? null) && isset($result['confidence'])) {
                $cited++;
            }
        }

        return $cited / count($relevantIds);
    }

    /** @param list<float> $values */
    private function percentile(array $values, int $percentile): float
    {
        sort($values);

        return $values[(int) ceil(($percentile / 100) * count($values)) - 1];
    }
}
