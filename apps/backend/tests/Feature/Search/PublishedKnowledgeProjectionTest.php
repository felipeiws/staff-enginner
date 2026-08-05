<?php

declare(strict_types=1);

use Atlas\Modules\Search\Application\PublishedKnowledgeProjector;
use Atlas\Modules\Search\Domain\PublishedKnowledgeIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

final class FakePublishedKnowledgeIndex implements PublishedKnowledgeIndex
{
    /** @var array<string, array<string, array<string, mixed>>> */
    public array $documents = [];

    /** @var array<string, array<string, mixed>> */
    public array $schemas = [];

    /** @var array<string, string> */
    public array $aliases = [];

    public bool $fails = false;

    public function createCollection(string $collection, array $schema): void
    {
        $this->schemas[$collection] = $schema;
    }

    public function upsert(string $collection, array $document): void
    {
        if ($this->fails) {
            throw new RuntimeException('Typesense indisponível');
        } $this->documents[$collection][(string) $document['id']] = $document;
    }

    public function delete(string $collection, string $documentId): void
    {
        unset($this->documents[$collection][$documentId]);
    }

    public function deleteByEntity(string $collection, string $entityId): void
    {
        foreach ($this->documents[$collection] ?? [] as $id => $document) {
            if ($document['entity_id'] === $entityId) {
                unset($this->documents[$collection][$id]);
            }
        }
    }

    public function switchAlias(string $alias, string $collection): void
    {
        $this->aliases[$alias] = $collection;
    }
}

function searchId(int $id): string
{
    return sprintf('81000000-0000-4000-8000-%012d', $id);
}

/** @return array{entity: string, revision: string} */
function publishedSearchRevision(): array
{
    $now = now();
    $source = searchId(1);
    $artifact = searchId(2);
    $sourceRevision = searchId(3);
    $entity = searchId(4);
    $revision = searchId(5);
    DB::table('sources')->insert(['id' => $source, 'type' => 'git', 'scope' => json_encode(['application' => 'atlas', 'module' => 'busca']), 'classification' => 'interno', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
    DB::table('artifacts')->insert(['id' => $artifact, 'source_id' => $source, 'external_identifier' => 'atlas/search', 'uri' => 'https://example.test/search.md', 'content_hash' => 'hash', 'media_type' => 'text/markdown', 'created_at' => $now, 'updated_at' => $now]);
    DB::table('revisions')->insert(['id' => $sourceRevision, 'artifact_id' => $artifact, 'source_revision' => 'abc123', 'observed_at' => $now, 'checksum' => 'checksum', 'metadata' => '{}', 'created_at' => $now, 'updated_at' => $now]);
    DB::table('entities')->insert(['id' => $entity, 'entity_type' => 'Documento', 'family' => 'conhecimento', 'scope' => json_encode(['application' => 'atlas', 'module' => 'busca']), 'external_natural_key' => 'search-document', 'owner_id' => 'human:ana', 'owner_type' => 'human', 'created_at' => $now, 'updated_at' => $now]);
    DB::table('entity_revisions')->insert(['id' => $revision, 'entity_id' => $entity, 'version' => 1, 'lifecycle_state' => 'published', 'title' => 'Busca publicada', 'body' => 'Conhecimento recuperável com proveniência.', 'attributes' => json_encode(['path' => 'docs/search.md', 'aliases' => ['pesquisa'], 'identifiers' => ['ADR-004'], 'method' => 'parser@1', 'processed_at' => $now->toISOString(), 'classification' => 'interno']), 'source_revision_id' => $sourceRevision, 'confidence' => .91, 'published_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
    DB::table('chunks')->insert(['id' => searchId(6), 'revision_id' => $sourceRevision, 'ordinal' => 0, 'start_offset' => 0, 'end_offset' => 12, 'text' => 'Conhecimento', 'tokens' => 1, 'checksum' => 'chunk-checksum', 'created_at' => $now, 'updated_at' => $now]);
    DB::table('chunk_embeddings')->insert(['id' => searchId(7), 'chunk_id' => searchId(6), 'model' => 'fixture', 'model_version' => '1', 'parameters' => '{}', 'chunk_checksum' => 'chunk-checksum', 'embedding' => '[0.1,0.2]', 'dimensions' => 2, 'created_at' => $now, 'updated_at' => $now]);

    return ['entity' => $entity, 'revision' => $revision];
}

/** @param array{entity: string, revision: string} $ids @return array<string, mixed> */
function searchLifecycleEvent(array $ids, string $state, int $number = 99): array
{
    return ['event_id' => searchId($number), 'event_type' => 'KnowledgeRevisionLifecycleChanged', 'entity' => ['entity_id' => $ids['entity'], 'revision_id' => $ids['revision']], 'payload' => ['lifecycle_state' => $state]];
}

beforeEach(function (): void {
    config()->set('search.embedding_dimensions', 2);
    $this->index = new FakePublishedKnowledgeIndex();
    app()->instance(PublishedKnowledgeIndex::class, $this->index);
});

it('projects only published knowledge with provenance-derived searchable fields and lag', function (): void {
    $ids = publishedSearchRevision();
    app(PublishedKnowledgeProjector::class)->consume(searchLifecycleEvent($ids, 'published'));
    $document = $this->index->documents['knowledge_published'][$ids['revision']];
    expect($document)->toMatchArray(['title' => 'Busca publicada', 'path' => 'docs/search.md', 'application' => 'atlas', 'module' => 'busca', 'entity_type' => 'Documento', 'source' => 'git', 'owner' => 'human:ana', 'state' => 'published', 'classification' => 'interno'])
        ->and($document['aliases'])->toBe(['pesquisa'])->and($document['identifiers'])->toBe(['ADR-004'])->and(DB::table('search_projection_metrics')->count())->toBe(1);
});

it('removes a document when its revision is retracted', function (): void {
    $ids = publishedSearchRevision();
    $projector = app(PublishedKnowledgeProjector::class);
    $projector->consume(searchLifecycleEvent($ids, 'published'));
    $projector->consume(searchLifecycleEvent($ids, 'retracted', 100));
    expect($this->index->documents['knowledge_published'] ?? [])->not->toHaveKey($ids['revision']);
});

it('records failures in the projection DLQ without indexing a document', function (): void {
    $ids = publishedSearchRevision();
    $this->index->fails = true;
    app(PublishedKnowledgeProjector::class)->consume(searchLifecycleEvent($ids, 'published'));
    expect(DB::table('search_projection_failures')->where('event_id', searchId(99))->value('attempts'))->toBe(1);
});

it('reindexes published documents and atomically switches the versioned alias', function (): void {
    publishedSearchRevision();
    $this->artisan('search:reindex-published')->assertSuccessful();
    $collection = $this->index->aliases['knowledge_published'];
    expect($collection)->toStartWith('knowledge_published_v2_')->and($this->index->schemas[$collection]['fields'])->toContain(['name' => 'embedding', 'type' => 'float[]', 'num_dim' => 2, 'optional' => true])
        ->and($this->index->documents[$collection])->toHaveCount(1);
});
