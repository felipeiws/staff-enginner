<?php

declare(strict_types=1);

namespace Atlas\Modules\Search\Domain;

interface HybridSearchIndex
{
    /**
     * @param  array<string, mixed>  $filters  Filters already limited to an authorized scope.
     * @param  list<float|int>|null  $queryEmbedding
     * @return array{lexical:list<array<string,mixed>>,vector:list<array<string,mixed>>,facets:array<string,list<array{value:string,count:int}>>}
     */
    public function search(string $query, array $filters, ?array $queryEmbedding, int $limit): array;
}
