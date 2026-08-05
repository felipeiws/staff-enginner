<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Application;

use Illuminate\Database\DatabaseManager;

/** Authoritative, bounded read model for the diagnostic Knowledge Graph. */
final class GraphReadService
{
    private const CLASSIFICATIONS = ['publico' => 0, 'interno' => 1, 'restrito' => 2, 'confidencial' => 3];

    /** @var array<string, string> */
    private const TYPE_COLORS = [
        'Processo' => 'var(--violet)', 'Regra' => 'var(--warn)', 'Tela' => 'var(--info)',
        'Endpoint' => 'var(--acc)', 'Evento' => 'var(--pink)', 'Tabela' => 'var(--sky)',
        'Issue' => 'var(--danger)', 'FAQ' => 'var(--sky)', 'Documento' => 'var(--tx3)',
    ];

    public function __construct(private readonly DatabaseManager $database) {}

    /** @return array<string, mixed> */
    public function neighborhood(KnowledgeReadAccess $access, string $entityId, int $depth, string $groupBy): array
    {
        $focus = $this->publishedEntity($entityId);
        if ($focus === null) {
            throw new KnowledgeReadAccessDenied('Acesso negado para este escopo ou classificação.');
        }
        if (! $this->allowed($access, $focus)) {
            throw new KnowledgeReadAccessDenied('Acesso negado para este escopo ou classificação.');
        }

        $nodes = [$entityId => $focus];
        $edges = [];
        $frontier = [$entityId];
        for ($level = 0; $level < $depth && $frontier !== []; $level++) {
            $rows = $this->edgesFor($frontier);
            $next = [];
            foreach ($rows as $row) {
                $subject = $this->rowEntity($row, 'subject');
                $object = $this->rowEntity($row, 'object');
                if ($subject === null || $object === null) {
                    continue;
                }
                if (! $this->allowed($access, $subject) || ! $this->allowed($access, $object)) {
                    continue;
                }
                $nodes[$subject['entity_id']] = $subject;
                $nodes[$object['entity_id']] = $object;
                $edges[$row['assertion_id']] = [
                    'id' => $row['assertion_id'], 'origin' => $subject['entity_id'], 'destination' => $object['entity_id'],
                    'predicate' => $row['predicate'], 'weight' => (float) $row['confidence'],
                    'provenance' => ['origin' => $row['source_origin_uri'], 'revision' => $row['source_revision'], 'method' => $row['method'], 'confidence' => (float) $row['confidence'], 'processed_at' => $row['created_at']],
                ];
                foreach ([$subject['entity_id'], $object['entity_id']] as $candidate) {
                    if (! isset($nodes[$candidate]['seen']) && $candidate !== $entityId) {
                        $next[$candidate] = $candidate;
                    }
                }
            }
            foreach ($frontier as $seen) {
                if (isset($nodes[$seen])) {
                    $nodes[$seen]['seen'] = true;
                }
            }
            $frontier = array_values($next);
        }

        $degree = array_fill_keys(array_keys($nodes), 0);
        foreach ($edges as $edge) {
            $degree[$edge['origin']]++;
            $degree[$edge['destination']]++;
        }
        $readNodes = array_map(function (array $node) use ($degree, $entityId): array {
            unset($node['seen']);
            $node['color'] = self::TYPE_COLORS[$node['entity_type']] ?? 'var(--tx3)';
            $node['weight'] = $node['entity_id'] === $entityId ? 15 : min(14, 6 + ($degree[$node['entity_id']] * 2));
            $node['provenance'] = $this->provenance($node, 'graph_neighborhood_v1');

            return ['id' => $node['entity_id'], 'type' => $node['entity_type'], 'label' => $node['title'], 'color' => $node['color'], 'weight' => $node['weight'], 'provenance' => $node['provenance']];
        }, $nodes);
        usort($readNodes, fn (array $left, array $right): int => [$left['id'] !== $entityId, $left['type'], $left['label']] <=> [$right['id'] !== $entityId, $right['type'], $right['label']]);

        return [
            'focus' => $entityId, 'depth' => $depth, 'group_by' => $groupBy, 'nodes' => $readNodes,
            'edges' => array_values($edges),
            'provenance' => $this->provenance($focus, 'graph_neighborhood_v1'),
        ];
    }

    /** @return array{entities:list<array<string,mixed>>,provenance:array<string,mixed>} */
    public function orphans(KnowledgeReadAccess $access): array
    {
        $entities = array_values(array_filter($this->publishedEntities(), fn (array $row): bool => $this->allowed($access, $row)));
        $relations = $this->database->table('published_assertions')->get(['id', 'subject_entity_id', 'object_entity_id'])->all();
        $relationIds = array_column($relations, 'id');
        $evidenced = $relationIds === [] ? [] : array_fill_keys($this->database->table('assertion_evidence')->whereIn('assertion_id', $relationIds)->pluck('assertion_id')->all(), true);
        $byEntity = [];
        $visibleIds = array_fill_keys(array_column($entities, 'entity_id'), true);
        foreach ($relations as $relation) {
            if ($relation->object_entity_id === null || ! isset($visibleIds[$relation->subject_entity_id], $visibleIds[$relation->object_entity_id])) {
                continue;
            }
            foreach ([$relation->subject_entity_id, $relation->object_entity_id] as $id) {
                if ($id !== null) {
                    $byEntity[$id][] = $relation->id;
                }
            }
        }
        $result = [];
        foreach ($entities as $entity) {
            $ids = $byEntity[$entity['entity_id']] ?? [];
            $reasons = [];
            if ($ids === []) {
                $reasons[] = 'sem_relacao';
            }
            if (trim((string) $entity['owner_id']) === '') {
                $reasons[] = 'sem_responsavel';
            }
            if ($ids !== [] && array_filter($ids, fn (string $id): bool => isset($evidenced[$id])) === []) {
                $reasons[] = 'sem_evidencia';
            }
            if ($reasons !== []) {
                $result[] = ['id' => $entity['entity_id'], 'type' => $entity['entity_type'], 'label' => $entity['title'], 'reasons' => $reasons, 'provenance' => $this->provenance($entity, 'graph_orphans_v1')];
            }
        }
        usort($result, fn (array $left, array $right): int => [$left['type'], $left['label'], $left['id']] <=> [$right['type'], $right['label'], $right['id']]);

        return ['entities' => $result, 'provenance' => ['origin' => 'published_entity_revisions,published_assertions', 'revision' => null, 'method' => 'graph_orphans_v1', 'confidence' => 1.0, 'processed_at' => now()->toISOString()]];
    }

    /** @return array<string, mixed> */
    public function node(KnowledgeReadAccess $access, string $entityId): array
    {
        $entity = $this->publishedEntity($entityId);
        if ($entity === null || ! $this->allowed($access, $entity)) {
            throw new KnowledgeReadAccessDenied('Acesso negado para este escopo ou classificação.');
        }
        $relations = $this->edgesFor([$entityId]);
        $visible = array_filter($relations, function (array $relation) use ($access): bool {
            $subject = $this->rowEntity($relation, 'subject');
            $object = $this->rowEntity($relation, 'object');

            return $subject !== null && $object !== null && $this->allowed($access, $subject) && $this->allowed($access, $object);
        });

        return ['id' => $entityId, 'type' => $entity['entity_type'], 'label' => $entity['title'], 'description' => (string) ($entity['attributes']['summary'] ?? $entity['body'] ?? ''), 'relations_count' => count($visible), 'owner' => ['id' => $entity['owner_id'], 'type' => $entity['owner_type']], 'updated_at' => $entity['updated_at'], 'provenance' => $this->provenance($entity, 'graph_node_v1')];
    }

    /** @return array<string, mixed> */
    public function diagnosis(KnowledgeReadAccess $access, string $scopeId): array
    {
        $cluster = $this->neighborhood($access, $scopeId, 1, 'type');
        $ids = array_column($cluster['nodes'], 'id');
        $rows = array_values(array_filter($this->publishedEntities($ids), fn (array $row): bool => $this->allowed($access, $row)));
        $problems = [];
        foreach ($rows as $row) {
            if ($row['entity_type'] === 'Regra' && trim((string) $row['owner_id']) === '') {
                $problems[] = ['rule' => 'regra_sem_responsavel', 'entity_id' => $row['entity_id'], 'label' => $row['title'], 'severity' => 'atenção', 'provenance' => $this->provenance($row, 'graph_diagnosis_v1')];
            }
            if ($row['entity_type'] === 'Tela' && empty($row['attributes']['detail']['desktop'])) {
                $problems[] = ['rule' => 'tela_sem_print', 'entity_id' => $row['entity_id'], 'label' => $row['title'], 'severity' => 'atenção', 'provenance' => $this->provenance($row, 'graph_diagnosis_v1')];
            }
        }
        $deadLetters = $this->database->table('outbox_events')->where('status', 'dead_letter')->get(['event_id', 'payload', 'last_error', 'updated_at']);
        foreach ($deadLetters as $event) {
            $payload = $this->decode($event->payload);
            $entityId = $payload['entity']['entity_id'] ?? null;
            if (is_string($entityId) && in_array($entityId, $ids, true)) {
                $problems[] = ['rule' => 'evento_com_dlq_ativa', 'entity_id' => $entityId, 'label' => $this->nodeLabel($rows, $entityId), 'severity' => 'crítico', 'event_id' => $event->event_id, 'provenance' => ['origin' => 'outbox_events', 'revision' => null, 'method' => 'graph_diagnosis_v1', 'confidence' => 1.0, 'processed_at' => $event->updated_at]];
            }
        }

        return ['scope' => $scopeId, 'problems' => $problems, 'summary' => ['total' => count($problems), 'by_rule' => array_count_values(array_column($problems, 'rule'))], 'provenance' => ['origin' => 'published_entity_revisions,published_assertions,outbox_events', 'revision' => null, 'method' => 'graph_diagnosis_v1', 'confidence' => 1.0, 'processed_at' => now()->toISOString()]];
    }

    /** @param list<string> $ids
     * @return list<array<string,mixed>>
     */
    private function edgesFor(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return array_values($this->database->table('published_assertions as pa')
            ->join('entity_revisions as sr', 'sr.id', '=', 'pa.subject_entity_revision_id')->join('entities as se', 'se.id', '=', 'sr.entity_id')
            ->join('revisions as source_revision', 'source_revision.id', '=', 'sr.source_revision_id')->join('artifacts as source_artifact', 'source_artifact.id', '=', 'source_revision.artifact_id')
            ->join('entity_revisions as or', 'or.id', '=', 'pa.object_entity_revision_id')->join('entities as oe', 'oe.id', '=', 'or.entity_id')
            ->where(fn ($query) => $query->whereIn('pa.subject_entity_id', $ids)->orWhereIn('pa.object_entity_id', $ids))
            ->orderBy('pa.id')->get(['pa.id as assertion_id', 'pa.predicate', 'pa.confidence', 'pa.method', 'pa.created_at', 'source_revision.source_revision', 'source_artifact.uri as source_origin_uri', 'se.id as subject_entity_id', 'se.entity_type as subject_entity_type', 'se.scope as subject_scope', 'se.owner_id as subject_owner_id', 'se.owner_type as subject_owner_type', 'sr.title as subject_title', 'sr.body as subject_body', 'sr.attributes as subject_attributes', 'sr.confidence as subject_confidence', 'sr.updated_at as subject_updated_at', 'sr.published_at as subject_published_at', 'or.id as object_revision_id', 'oe.id as object_entity_id', 'oe.entity_type as object_entity_type', 'oe.scope as object_scope', 'oe.owner_id as object_owner_id', 'oe.owner_type as object_owner_type', 'or.title as object_title', 'or.body as object_body', 'or.attributes as object_attributes', 'or.confidence as object_confidence', 'or.updated_at as object_updated_at', 'or.published_at as object_published_at'])->map(fn (object $row): array => (array) $row)->all());
    }

    /** @return array<string,mixed>|null */
    private function publishedEntity(string $id): ?array
    {
        return $this->publishedEntities([$id])[0] ?? null;
    }

    /** @param list<string>|null $ids
     * @return list<array<string,mixed>>
     */
    private function publishedEntities(?array $ids = null): array
    {
        $query = $this->database->table('entities as e')->join('entity_revisions as er', 'er.entity_id', '=', 'e.id')->join('revisions as r', 'r.id', '=', 'er.source_revision_id')->join('artifacts as a', 'a.id', '=', 'r.artifact_id')->join('sources as s', 's.id', '=', 'a.source_id')->where('er.lifecycle_state', 'published')->whereRaw('er.version = (select max(latest.version) from entity_revisions as latest where latest.entity_id = e.id)');
        if ($ids !== null) {
            $query->whereIn('e.id', $ids);
        }

        return array_values($query->orderBy('e.id')->get(['e.id as entity_id', 'e.entity_type', 'e.scope', 'e.owner_id', 'e.owner_type', 'er.title', 'er.body', 'er.attributes', 'er.confidence', 'er.published_at', 'er.updated_at', 'r.source_revision', 'r.observed_at', 'a.uri as origin_uri'])->map(function (object $row): array {
            $value = (array) $row;
            $value['scope'] = $this->decode($value['scope']);
            $value['attributes'] = $this->decode($value['attributes']);

            return $value;
        })->all());
    }

    /** @param array<string,mixed> $row
     * @return array<string,mixed>|null
     */
    private function rowEntity(array $row, string $prefix): ?array
    {
        if ($prefix === 'object' && $row['object_entity_id'] === null) {
            return null;
        }

        return ['entity_id' => $row[$prefix.'_entity_id'], 'entity_type' => $row[$prefix.'_entity_type'], 'scope' => $this->decode($row[$prefix.'_scope']), 'owner_id' => $row[$prefix.'_owner_id'], 'owner_type' => $row[$prefix.'_owner_type'], 'title' => $row[$prefix.'_title'], 'body' => $row[$prefix.'_body'], 'attributes' => $this->decode($row[$prefix.'_attributes']), 'confidence' => $row[$prefix.'_confidence'], 'updated_at' => $row[$prefix.'_updated_at'], 'published_at' => $row[$prefix.'_published_at'], 'origin_uri' => $row['source_origin_uri'], 'source_revision' => $row['source_revision'], 'observed_at' => $row[$prefix.'_published_at']];
    }

    /** @param array<string,mixed> $entity */
    private function allowed(KnowledgeReadAccess $access, array $entity): bool
    {
        $classification = (string) ($entity['attributes']['classification'] ?? 'interno');
        foreach ($access->grants as $grant) {
            if ((! in_array('knowledge.read', $grant['permissions'], true) && ! in_array('*', $grant['permissions'], true)) || (self::CLASSIFICATIONS[$grant['classification_ceiling']] ?? -1) < (self::CLASSIFICATIONS[$classification] ?? PHP_INT_MAX) || ! isset($grant['scope']['organization'])) {
                continue;
            }
            $matches = true;
            foreach (['organization', 'application', 'module'] as $dimension) {
                if (isset($grant['scope'][$dimension]) && ($entity['scope'][$dimension] ?? null) !== $grant['scope'][$dimension]) {
                    $matches = false;
                }
            }
            if ($matches) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string,mixed> $entity
     * @return array<string,mixed>
     */
    private function provenance(array $entity, string $method): array
    {
        return ['origin' => $entity['origin_uri'], 'revision' => $entity['source_revision'], 'method' => $method, 'confidence' => (float) $entity['confidence'], 'processed_at' => $entity['attributes']['processed_at'] ?? $entity['observed_at'] ?? $entity['published_at']];
    }

    /** @return array<string,mixed> */
    private function decode(mixed $value): array
    {
        return is_string($value) ? (json_decode($value, true) ?: []) : (array) $value;
    }

    /** @param list<array<string,mixed>> $rows */
    private function nodeLabel(array $rows, string $id): string
    {
        foreach ($rows as $row) {
            if ($row['entity_id'] === $id) {
                return $row['title'];
            }
        }

        return $id;
    }
}
