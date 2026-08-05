<?php

declare(strict_types=1);

namespace Atlas\Modules\Search\Application;

use Atlas\Modules\Search\Domain\PublishedKnowledgeIndex;
use Carbon\CarbonImmutable;
use Illuminate\Database\DatabaseManager;
use Throwable;

/** Projects only published revisions; Typesense remains fully rebuildable. */
final readonly class PublishedKnowledgeProjector
{
    public function __construct(private DatabaseManager $database, private PublishedKnowledgeIndex $index, private PublishedKnowledgeSchema $schema) {}

    public function name(): string
    {
        return 'search.published-knowledge-v' . $this->schema->version();
    }

    /** @param array<string, mixed> $event */
    public function consume(array $event): void
    {
        if (($event['event_type'] ?? null) !== 'KnowledgeRevisionLifecycleChanged') {
            return;
        }
        try {
            $this->project($event, $this->schema->alias());
        } catch (Throwable $exception) {
            $this->deadLetter($event, $exception);
        }
    }

    /** @param array<string, mixed> $event */
    public function project(array $event, string $collection): void
    {
        $revisionId = (string) data_get($event, 'entity.revision_id');
        $entityId = (string) data_get($event, 'entity.entity_id');
        $state = (string) data_get($event, 'payload.lifecycle_state');
        if ($revisionId === '' || $entityId === '') {
            return;
        }
        if ($state !== 'published') {
            $this->index->delete($collection, $revisionId);

            return;
        }
        $document = $this->document($revisionId);
        if ($document === null) {
            $this->index->delete($collection, $revisionId);

            return;
        }
        $this->index->deleteByEntity($collection, $entityId);
        $this->index->upsert($collection, $document);
        $availableAt = now();
        $publishedAt = CarbonImmutable::parse((string) $document['published_at']);
        $this->database->table('search_projection_metrics')->updateOrInsert(['event_id' => (string) $event['event_id']], ['entity_revision_id' => $revisionId, 'published_at' => $publishedAt, 'available_at' => $availableAt, 'lag_ms' => max(0, $availableAt->diffInMilliseconds($publishedAt)), 'created_at' => $availableAt, 'updated_at' => $availableAt]);
    }

    /** @return list<array<string, mixed>> */
    public function publishedDocuments(): array
    {
        return array_values(array_filter($this->database->table('published_entity_revisions')->orderBy('id')->pluck('id')->map(fn(string $id) => $this->document($id))->all()));
    }

    /** @return array<string, mixed>|null */
    private function document(string $revisionId): ?array
    {
        $row = $this->database->table('published_entity_revisions as er')->join('revisions as r', 'r.id', '=', 'er.source_revision_id')->join('artifacts as a', 'a.id', '=', 'r.artifact_id')->join('sources as s', 's.id', '=', 'a.source_id')->where('er.id', $revisionId)->select(['er.*', 's.type as source_type', 's.classification as source_classification', 'r.source_revision', 'a.uri as origin_uri'])->first();
        if ($row === null) {
            return null;
        }
        $attributes = is_array($row->attributes) ? $row->attributes : json_decode((string) $row->attributes, true, 512, JSON_THROW_ON_ERROR);
        if (($attributes['quarantined'] ?? false) === true || ! $this->searchExposureAllowed((string) ($attributes['classification'] ?? $row->source_classification))) {
            return null;
        }
        $scope = is_array($row->entity_scope) ? $row->entity_scope : json_decode((string) $row->entity_scope, true, 512, JSON_THROW_ON_ERROR);
        $freshness = $attributes['freshness'] ?? $this->freshness($attributes['valid_until'] ?? null);
        $document = ['id' => (string) $row->id, 'entity_id' => (string) $row->entity_id, 'title' => (string) $row->title, 'body' => (string) ($row->body ?? ''), 'path' => (string) ($attributes['path'] ?? ''), 'aliases' => array_values((array) ($attributes['aliases'] ?? [])), 'identifiers' => array_values((array) ($attributes['identifiers'] ?? [])), 'organization' => (string) ($scope['organization'] ?? ''), 'application' => (string) ($scope['application'] ?? ''), 'module' => (string) ($scope['module'] ?? ''), 'entity_type' => (string) $row->entity_type, 'source' => (string) $row->source_type, 'owner' => (string) $row->owner_id, 'state' => 'published', 'classification' => (string) ($attributes['classification'] ?? $row->source_classification), 'freshness' => $freshness, 'related_entities' => array_values((array) ($attributes['related_entities'] ?? [])), 'method' => (string) ($attributes['method'] ?? ''), 'source_revision' => (string) $row->source_revision, 'origin_uri' => (string) $row->origin_uri, 'processed_at' => $attributes['processed_at'] ?? null, 'confidence' => (float) $row->confidence, 'relevance' => (float) $row->confidence, 'updated_at' => CarbonImmutable::parse((string) $row->updated_at)->getTimestamp(), 'access_count' => (int) ($attributes['access_count'] ?? 0), 'published_at' => (string) $row->published_at];
        $embedding = $this->database->table('chunks as c')->join('chunk_embeddings as ce', 'ce.chunk_id', '=', 'c.id')
            ->where('c.revision_id', $row->source_revision_id)->orderBy('c.ordinal')->value('ce.embedding');
        if ($embedding !== null) {
            $document['embedding'] = is_array($embedding) ? array_map('floatval', $embedding) : array_map('floatval', json_decode((string) $embedding, true, 512, JSON_THROW_ON_ERROR));
        }

        return $document;
    }

    private function searchExposureAllowed(string $classification): bool
    {
        $configured = $this->database->table('classifications')->where('level', $classification)->value('search_exposure_allowed');

        return $configured === null || (bool) $configured;
    }

    private function freshness(mixed $validUntil): string
    {
        if (! is_string($validUntil) || $validUntil === '') {
            return 'atual';
        }
        $date = CarbonImmutable::parse($validUntil);

        return $date->isPast() ? 'expirado' : ($date->lessThanOrEqualTo(now()->addDays(7)) ? 'expira_em_breve' : 'atual');
    }

    /** @param array<string, mixed> $event */
    private function deadLetter(array $event, Throwable $exception): void
    {
        $now = now();
        $id = (string) ($event['event_id'] ?? '');
        if ($id === '') {
            throw $exception;
        }
        $current = $this->database->table('search_projection_failures')->where('event_id', $id)->value('attempts');
        $this->database->table('search_projection_failures')->updateOrInsert(['event_id' => $id], ['event' => json_encode($event, JSON_THROW_ON_ERROR), 'attempts' => (int) $current + 1, 'last_error' => mb_substr($exception->getMessage(), 0, 4000), 'failed_at' => $now, 'available_at' => $now, 'resolved_at' => null, 'created_at' => $now, 'updated_at' => $now]);
    }
}
