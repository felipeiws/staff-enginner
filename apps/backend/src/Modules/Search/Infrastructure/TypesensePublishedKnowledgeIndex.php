<?php

declare(strict_types=1);

namespace Atlas\Modules\Search\Infrastructure;

use Atlas\Modules\Search\Domain\PublishedKnowledgeIndex;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;

final readonly class TypesensePublishedKnowledgeIndex implements PublishedKnowledgeIndex
{
    public function __construct(private Factory $http) {}

    public function createCollection(string $collection, array $schema): void
    {
        $this->client()->post('collections', $schema)->throw();
    }

    public function upsert(string $collection, array $document): void
    {
        $this->client()->post("collections/{$collection}/documents?action=upsert", $document)->throw();
    }

    public function delete(string $collection, string $documentId): void
    {
        $response = $this->client()->delete("collections/{$collection}/documents/{$documentId}");
        if ($response->status() !== 404) {
            $response->throw();
        }
    }

    public function deleteByEntity(string $collection, string $entityId): void
    {
        $this->client()->delete("collections/{$collection}/documents", ['filter_by' => 'entity_id:='.$entityId])->throw();
    }

    public function switchAlias(string $alias, string $collection): void
    {
        $this->client()->put("aliases/{$alias}", ['collection_name' => $collection])->throw();
    }

    private function client(): PendingRequest
    {
        return $this->http->baseUrl((string) config('search.protocol').'://'.config('search.host').':'.config('search.port'))
            ->withHeaders(['X-TYPESENSE-API-KEY' => (string) config('search.api_key')])->acceptJson();
    }
}
