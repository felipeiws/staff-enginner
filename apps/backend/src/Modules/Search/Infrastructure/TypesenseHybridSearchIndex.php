<?php

declare(strict_types=1);

namespace Atlas\Modules\Search\Infrastructure;

use Atlas\Modules\Search\Domain\HybridSearchIndex;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Throwable;

final readonly class TypesenseHybridSearchIndex implements HybridSearchIndex
{
    public function __construct(private Factory $http) {}

    public function search(string $query, array $filters, ?array $queryEmbedding, int $limit): array
    {
        $common = ['collection' => (string) config('search.alias'), 'q' => $query, 'query_by' => 'title,body,path,aliases,identifiers', 'filter_by' => $this->filter($filters), 'per_page' => $limit, 'highlight_full_fields' => 'body,title', 'facet_by' => 'application,freshness,owner'];
        $requests = ['lexical' => $common];
        if ($queryEmbedding !== null) {
            $requests['vector'] = [...$common, 'q' => '*', 'query_by' => 'title', 'vector_query' => 'embedding:(['.implode(',', array_map(static fn ($v): string => (string) (float) $v, $queryEmbedding)).'], k:'.$limit.')'];
        }
        $responses = $this->http->pool(fn (Pool $pool) => collect($requests)->mapWithKeys(fn (array $payload, string $name): array => [$name => $pool->as($name)->baseUrl($this->baseUrl())->withHeaders(['X-TYPESENSE-API-KEY' => (string) config('search.api_key')])->acceptJson()->post('multi_search', ['searches' => [$payload]])])->all());
        $lexical = $this->parse($this->response($responses['lexical'])->json('results.0', []));
        $vectorHits = isset($responses['vector']) ? $this->parse($this->response($responses['vector'])->json('results.0', []))['hits'] : [];

        return ['lexical' => $lexical['hits'], 'vector' => $vectorHits, 'facets' => $lexical['facets']];
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array{hits:list<array<string, mixed>>,facets:array<string, list<array{value:string,count:int}>>}
     */
    private function parse(array $response): array
    {
        $hits = array_values(array_map(static function (array $hit): array {
            $document = (array) ($hit['document'] ?? []);
            $document['_score'] = $hit['text_match'] ?? $hit['vector_distance'] ?? null;
            $document['highlight'] = self::highlight($hit['highlights'] ?? []);

            return $document;
        }, (array) ($response['hits'] ?? [])));
        $facets = ['application' => [], 'freshness' => [], 'owner' => []];
        foreach ((array) ($response['facet_counts'] ?? []) as $facet) {
            foreach ((array) ($facet['counts'] ?? []) as $count) {
                $facets[(string) $facet['field_name']][] = ['value' => (string) $count['value'], 'count' => (int) $count['count']];
            }
        }

        return ['hits' => $hits, 'facets' => $facets];
    }

    /** @param list<array<string,mixed>> $highlights */
    private static function highlight(array $highlights): string
    {
        foreach ($highlights as $highlight) {
            if (isset($highlight['snippet'])) {
                return strip_tags((string) $highlight['snippet']);
            }
        }

        return '';
    }

    /** @param array<string,mixed> $filters */
    private function filter(array $filters): string
    {
        $parts = ['state:=published'];
        foreach (['organization', 'application', 'module', 'entity_type', 'freshness', 'owner'] as $field) {
            $value = $filters[$field] ?? null;
            if (is_string($value) && $value !== '') {
                $parts[] = $field.':='.str_replace('`', '', $value);
            } elseif (is_array($value) && $value !== []) {
                $parts[] = $field.':['.implode(',', array_map(static fn ($v): string => '`'.str_replace('`', '', (string) $v).'`', $value)).']';
            }
        } if (($filters['classifications'] ?? []) !== []) {
            $parts[] = 'classification:['.implode(',', array_map(static fn ($v): string => '`'.$v.'`', $filters['classifications'])).']';
        }

        return implode(' && ', $parts);
    }

    private function baseUrl(): string
    {
        return (string) config('search.protocol').'://'.config('search.host').':'.config('search.port');
    }

    private function response(Response|Throwable $response): Response
    {
        if ($response instanceof Throwable) {
            throw $response;
        }

        return $response->throw();
    }
}
