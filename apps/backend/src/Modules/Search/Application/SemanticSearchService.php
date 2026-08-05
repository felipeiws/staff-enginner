<?php

declare(strict_types=1);

namespace Atlas\Modules\Search\Application;

use Illuminate\Database\DatabaseManager;
use InvalidArgumentException;

/** Searches only an already-authorized scope and classification ceiling. */
final readonly class SemanticSearchService
{
    public function __construct(private DatabaseManager $database) {}

    /**
     * @param array{organization:string,application?:string,module?:string} $scope
     * @param list<string> $classifications
     * @param list<float|int> $queryEmbedding
     * @return list<array<string,mixed>>
     */
    public function search(array $scope, array $classifications, array $queryEmbedding, int $limit = 10): array
    {
        if ($scope['organization'] === '' || $classifications === [] || $queryEmbedding === []) {
            return [];
        }
        if (count($queryEmbedding) !== (int) config('search.embedding_dimensions')) {
            throw new InvalidArgumentException('A dimensão da consulta não corresponde ao modelo ativo.');
        }
        $query = $this->database->table('chunk_embeddings as ce')
            ->join('chunks as c', 'c.id', '=', 'ce.chunk_id')
            ->join('revisions as r', 'r.id', '=', 'c.revision_id')
            ->join('artifacts as a', 'a.id', '=', 'r.artifact_id')
            ->join('sources as s', 's.id', '=', 'a.source_id')
            ->whereIn('s.classification', $classifications)
            ->where('s.scope->organization', $scope['organization']);
        foreach (['application', 'module'] as $dimension) {
            if (isset($scope[$dimension])) {
                $query->where("s.scope->{$dimension}", $scope[$dimension]);
            }
        }
        if ($this->database->getDriverName() === 'pgsql') {
            $vector = '[' . implode(',', array_map(static fn($value): string => (string) (float) $value, $queryEmbedding)) . ']';
            $query->selectRaw('c.id as chunk_id, c.revision_id, c.text, ce.model, ce.model_version, ce.chunk_checksum, 1 - (ce.embedding <=> ?::vector) as similarity', [$vector])
                ->orderByRaw('ce.embedding <=> ?::vector', [$vector]);
        } else {
            // The lightweight test database has no pgvector; preserve filter-before-retrieval semantics.
            $query->select(['c.id as chunk_id', 'c.revision_id', 'c.text', 'ce.model', 'ce.model_version', 'ce.chunk_checksum'])->orderBy('c.id');
        }

        return array_values($query->limit(min(max($limit, 1), 100))->get()->map(static fn(object $row): array => (array) $row)->all());
    }
}
