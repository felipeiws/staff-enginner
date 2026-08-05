<?php

declare(strict_types=1);

namespace Atlas\Modules\Search\Application;

use Atlas\Contracts\Dto\KnowledgeGapOccurrence;
use Atlas\Contracts\Port\KnowledgeGapRecorder;
use Atlas\Modules\Identity\Application\AuthorizationService;
use Atlas\Modules\Identity\Domain\AuthorizationRequest;
use Atlas\Modules\Identity\Domain\Principal;
use Atlas\Modules\Search\Domain\HybridSearchIndex;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/** Orchestrates authorization, retrieval and presentation; the index remains derived. */
final readonly class HybridSearchService
{
    private const CLASSIFICATIONS = ['publico' => 0, 'interno' => 1, 'restrito' => 2, 'confidencial' => 3];

    public function __construct(private AuthorizationService $authorization, private HybridSearchIndex $index, private KnowledgeGapRecorder $gaps) {}

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<float|int>|null  $embedding
     * @return array<string, mixed>
     */
    public function search(Principal $principal, string $query, array $filters, ?array $embedding = null): array
    {
        $scope = array_filter(['organization' => $filters['organization'] ?? null, 'application' => $filters['application'] ?? null, 'module' => $filters['module'] ?? null], static fn($value): bool => is_string($value) && $value !== '');
        if (! isset($scope['organization'])) {
            throw new InvalidArgumentException('organization é obrigatório.');
        }
        // This happens before a Typesense request, per ADR-010.
        $this->authorization->assertAuthorized($principal, new AuthorizationRequest('knowledge.read', $scope, 'publico'));
        $filters['classifications'] = $this->classificationsFor($principal, $scope);
        if ($filters['classifications'] === []) {
            return $this->empty($query, $scope, $filters);
        }
        if ($embedding !== null && count($embedding) !== (int) config('search.embedding_dimensions')) {
            throw new InvalidArgumentException('A dimensão do embedding da consulta não corresponde ao modelo ativo.');
        }

        $raw = $this->index->search($query, $filters, $embedding, min((int) ($filters['retrieval_limit'] ?? 30), 100));
        // Defense in depth: an index adapter must receive the filter, but a stale
        // projection or faulty adapter must not affect results, counts or facets.
        $fused = array_values(array_filter($this->fuse($raw['lexical'], $raw['vector']), fn (array $row): bool => in_array((string) ($row['classification'] ?? 'interno'), $filters['classifications'], true)));
        $limit = min(max((int) ($filters['page_size'] ?? 20), 1), 100);
        $results = array_map(fn(array $row): array => $this->present($row, $query), array_slice($fused, 0, $limit));
        if ($results === []) {
            Log::info('search.zero_result', ['query_hash' => hash('sha256', $query), 'scope' => $scope, 'correlation_id' => request()->attributes->get('correlation_id')]);
            $this->gaps->record(new KnowledgeGapOccurrence($query, $scope, 'search.zero_result', 'search_zero_result_v1', 1.0, request()->attributes->get('correlation_id')));
        }

        return [
            'query' => $query,
            'results' => $results,
            'type_counts' => $this->typeCounts($fused),
            'facets' => $this->hasInaccessibleCandidate($raw, $filters['classifications']) ? $this->facets($fused) : $raw['facets'],
            'retrieval' => [
                'fusion' => 'rrf', 'rrf_k' => (int) config('search.rrf_k', 60),
                'lexical_candidates' => count($raw['lexical']), 'semantic_candidates' => count($raw['vector']),
                'fused_candidates' => count($fused), 'returned_candidates' => count($results),
                'discarded_by_answer_limit' => max(count($fused) - count($results), 0),
                // Reranking is deliberately not a hidden LLM decision in v1.
                'rerank' => ['applied' => false, 'decision' => 'not_configured'],
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lexical
     * @param  list<array<string, mixed>>  $vector
     * @return list<array<string, mixed>>
     */
    public function fuse(array $lexical, array $vector): array
    {
        $scores = [];
        foreach (['lexical' => $lexical, 'vector' => $vector] as $channel => $rows) {
            foreach ($rows as $rank => $row) {
                $id = (string) $row['id'];
                $scores[$id] ??= ['row' => $row, 'score' => 0.0, 'scores' => []];
                $contribution = 1 / ((int) config('search.rrf_k', 60) + $rank + 1);
                $scores[$id]['score'] += $contribution;
                $scores[$id]['scores'][$channel] = ['rank' => $rank + 1, 'rrf' => $contribution, 'raw' => $row['_score'] ?? null];
            }
        }
        $rows = array_values(array_map(static fn(array $entry): array => [...$entry['row'], 'ranking' => ['rrf' => $entry['score'], 'sources' => $entry['scores']]], $scores));
        usort($rows, static fn(array $a, array $b): int => $b['ranking']['rrf'] <=> $a['ranking']['rrf'] ?: strcmp((string) $a['id'], (string) $b['id']));

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function present(array $row, string $query): array
    {
        $text = (string) ($row['highlight'] ?? $row['body'] ?? $row['title']);
        [$pre, $hit, $post] = $this->highlight($text, $query);

        return [
            'id' => (string) $row['id'], 'entity_id' => (string) ($row['entity_id'] ?? ''),
            'type' => ['name' => (string) ($row['entity_type'] ?? 'Documento'), 'color' => $this->typeColor((string) ($row['entity_type'] ?? ''))],
            'path' => (string) ($row['path'] ?? ''), 'freshness' => (string) ($row['freshness'] ?? 'atual'), 'title' => (string) ($row['title'] ?? ''),
            'excerpt' => ['pre' => $pre, 'hit' => $hit, 'post' => $post],
            'metadata' => (string) ($row['metadata'] ?? $this->metadata($row)), 'related_entities' => array_values((array) ($row['related_entities'] ?? [])),
            'evidence' => ['origin_uri' => (string) ($row['origin_uri'] ?? ''), 'source_revision' => (string) ($row['source_revision'] ?? ''), 'method' => (string) ($row['method'] ?? ''), 'confidence' => (float) ($row['confidence'] ?? 0), 'classification' => (string) ($row['classification'] ?? 'interno'), 'processed_at' => $row['processed_at'] ?? null],
            'ranking' => $row['ranking'],
        ];
    }

    /** @return array{0:string,1:string,2:string} */
    private function highlight(string $text, string $query): array
    {
        $term = trim($query);
        if ($term === '' || preg_match('/' . preg_quote($term, '/') . '/iu', $text, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return [$text, '', ''];
        }
        $offset = $match[0][1];
        $hit = $match[0][0];

        return [mb_substr($text, 0, $offset), $hit, mb_substr($text, $offset + mb_strlen($hit))];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private function typeCounts(array $rows): array
    {
        $counts = ['Tudo' => count($rows)];
        foreach ($rows as $row) {
            $type = (string) ($row['entity_type'] ?? 'Documento');
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }

        return $counts;
    }

    /** @param list<array<string,mixed>> $rows @return array{application:list<array{value:string,count:int}>,freshness:list<array{value:string,count:int}>,owner:list<array{value:string,count:int}>} */
    private function facets(array $rows): array
    {
        $facets = ['application' => [], 'freshness' => [], 'owner' => []];
        foreach (array_keys($facets) as $field) {
            $counts = [];
            foreach ($rows as $row) {
                $value = (string) ($row[$field] ?? '');
                if ($value !== '') { $counts[$value] = ($counts[$value] ?? 0) + 1; }
            }
            ksort($counts);
            foreach ($counts as $value => $count) { $facets[$field][] = ['value' => $value, 'count' => $count]; }
        }
        return $facets;
    }

    /** @param array<string,mixed> $raw @param list<string> $allowed */
    private function hasInaccessibleCandidate(array $raw, array $allowed): bool
    {
        foreach (array_merge((array) ($raw['lexical'] ?? []), (array) ($raw['vector'] ?? [])) as $row) {
            if (! in_array((string) ($row['classification'] ?? 'interno'), $allowed, true)) { return true; }
        }
        return false;
    }

    /** @param array<string,mixed> $row */
    private function metadata(array $row): string
    {
        return trim(implode(' · ', array_filter([(string) ($row['source'] ?? ''), isset($row['confidence']) ? 'confiança ' . (int) round((float) $row['confidence'] * 100) . '%' : '', (string) ($row['owner'] ?? '')])));
    }

    private function typeColor(string $type): string
    {
        return match ($type) {
            'Processo' => 'violet', 'Regra' => 'warn', 'Tela' => 'info', 'API', 'Endpoint' => 'accent', 'FAQ' => 'sky', 'Issue' => 'danger', default => 'neutral',
        };
    }

    /**
     * @param  array<string, string>  $scope
     * @return list<string>
     */
    private function classificationsFor(Principal $principal, array $scope): array
    {
        $allowed = [];
        foreach ($principal->grants as $grant) {
            if (! in_array('knowledge.read', $grant['permissions'], true) && ! in_array('*', $grant['permissions'], true)) {
                continue;
            } foreach ($scope as $key => $value) {
                if (isset($grant['scope'][$key]) && $grant['scope'][$key] !== $value) {
                    continue 2;
                }
            } $ceiling = self::CLASSIFICATIONS[$grant['classification_ceiling']] ?? -1;
            foreach (self::CLASSIFICATIONS as $name => $level) {
                if ($level <= $ceiling) {
                    $allowed[$name] = true;
                }
            }
        }

        return array_keys($allowed);
    }

    /**
     * @param  array<string, string>  $scope
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function empty(string $query, array $scope, array $filters): array
    {
        Log::info('search.zero_result', ['query_hash' => hash('sha256', $query), 'scope' => $scope]);
        $this->gaps->record(new KnowledgeGapOccurrence($query, $scope, 'search.zero_result', 'search_zero_result_v1', 1.0, request()->attributes->get('correlation_id')));

        return ['query' => $query, 'results' => [], 'type_counts' => ['Tudo' => 0], 'facets' => ['application' => [], 'freshness' => [], 'owner' => []], 'retrieval' => ['fusion' => 'rrf', 'rrf_k' => (int) config('search.rrf_k', 60), 'lexical_candidates' => 0, 'semantic_candidates' => 0, 'fused_candidates' => 0, 'returned_candidates' => 0, 'discarded_by_answer_limit' => 0, 'rerank' => ['applied' => false, 'decision' => 'not_configured']]];
    }
}
