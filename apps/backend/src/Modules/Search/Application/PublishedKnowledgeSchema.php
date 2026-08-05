<?php

declare(strict_types=1);

namespace Atlas\Modules\Search\Application;

final readonly class PublishedKnowledgeSchema
{
    public function version(): int
    {
        return (int) config('search.schema_version');
    }

    public function alias(): string
    {
        return (string) config('search.alias');
    }

    public function collectionName(?string $suffix = null): string
    {
        return $this->alias().'_v'.$this->version().($suffix === null ? '' : '_'.$suffix);
    }

    /** @return array<string, mixed> */
    public function definition(string $name): array
    {
        return ['name' => $name, 'fields' => [
            ['name' => 'id', 'type' => 'string'],
            ['name' => 'entity_id', 'type' => 'string', 'facet' => false],
            ['name' => 'title', 'type' => 'string'], ['name' => 'body', 'type' => 'string', 'optional' => true],
            ['name' => 'path', 'type' => 'string', 'optional' => true], ['name' => 'aliases', 'type' => 'string[]', 'optional' => true],
            ['name' => 'identifiers', 'type' => 'string[]', 'optional' => true],
            ['name' => 'application', 'type' => 'string', 'facet' => true, 'optional' => true],
            ['name' => 'module', 'type' => 'string', 'facet' => true, 'optional' => true],
            ['name' => 'entity_type', 'type' => 'string', 'facet' => true], ['name' => 'source', 'type' => 'string', 'facet' => true],
            ['name' => 'owner', 'type' => 'string', 'facet' => true], ['name' => 'state', 'type' => 'string', 'facet' => true],
            ['name' => 'classification', 'type' => 'string', 'facet' => true], ['name' => 'freshness', 'type' => 'string', 'facet' => true],
            ['name' => 'organization', 'type' => 'string', 'facet' => true], ['name' => 'related_entities', 'type' => 'string[]', 'optional' => true],
            ['name' => 'method', 'type' => 'string', 'optional' => true], ['name' => 'source_revision', 'type' => 'string', 'optional' => true], ['name' => 'origin_uri', 'type' => 'string', 'optional' => true], ['name' => 'processed_at', 'type' => 'string', 'optional' => true],
            ['name' => 'relevance', 'type' => 'float', 'sort' => true], ['name' => 'updated_at', 'type' => 'int64', 'sort' => true],
            ['name' => 'access_count', 'type' => 'int64', 'sort' => true],
            ['name' => 'embedding', 'type' => 'float[]', 'num_dim' => (int) config('search.embedding_dimensions'), 'optional' => true],
        ]];
    }
}
