<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Application;

use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;

/** Authoritative, read-only projection for the entity-detail screens. */
final class EntityDetailReadService
{
    private const CLASSIFICATIONS = ['publico' => 0, 'interno' => 1, 'restrito' => 2, 'confidencial' => 3];

    public function __construct(private readonly DatabaseManager $database) {}

    /** @return array<string, mixed> */
    public function entity(KnowledgeReadAccess $access, string $entityId): array
    {
        $row = $this->publishedEntity($entityId);
        if ($row === null || ! $this->allowed($access, $row)) {
            throw new KnowledgeReadAccessDenied('Acesso negado para este escopo ou classificação.');
        }

        $provenance = $this->provenance($row, (string) ($row['attributes']['method'] ?? 'published_entity_revision_v1'));

        return [
            'id' => $row['entity_id'],
            'type' => $row['entity_type'],
            'title' => $row['title'],
            'summary' => (string) ($row['attributes']['summary'] ?? $row['body'] ?? ''),
            'scope' => $row['scope'],
            'revision' => ['id' => $row['revision_id'], 'version' => (int) $row['version'], 'published_at' => $row['published_at']],
            'provenance' => $provenance,
            'freshness' => $this->freshness($row),
            'owners' => $this->owners($row),
            'relations' => $this->relations($row),
            'projection' => $this->projection($row),
        ];
    }

    /** @return array<string, mixed>|null */
    private function publishedEntity(string $entityId): ?array
    {
        $record = $this->database->table('entities as e')->join('entity_revisions as er', 'er.entity_id', '=', 'e.id')
            ->join('revisions as r', 'r.id', '=', 'er.source_revision_id')->join('artifacts as a', 'a.id', '=', 'r.artifact_id')->join('sources as s', 's.id', '=', 'a.source_id')
            ->where('e.id', $entityId)->where('er.lifecycle_state', 'published')->orderByDesc('er.version')
            ->select(['e.id as entity_id', 'e.entity_type', 'e.scope', 'e.owner_id', 'e.owner_type', 'er.id as revision_id', 'er.version', 'er.title', 'er.body', 'er.attributes', 'er.confidence', 'er.published_at', 'r.source_revision', 'r.observed_at', 'a.uri as origin_uri', 's.type as source_type'])->first();
        if ($record === null) {
            return null;
        }
        $row = (array) $record;
        $row['scope'] = json_decode((string) $row['scope'], true, flags: JSON_THROW_ON_ERROR);
        $row['attributes'] = json_decode((string) $row['attributes'], true, flags: JSON_THROW_ON_ERROR);

        return $row;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function projection(array $row): array
    {
        $detail = is_array($row['attributes']['detail'] ?? null) ? $row['attributes']['detail'] : [];

        return match ($row['entity_type']) {
            'Processo' => ['kind' => 'processo', 'summary' => (string) ($detail['summary'] ?? $row['body'] ?? ''), 'flow' => $this->section($detail, 'flow', ['steps' => [], 'deviations' => [], 'known_failures' => []]), 'rules' => $detail['rules'] ?? [], 'screens' => $detail['screens'] ?? [], 'technical' => $this->section($detail, 'technical', ['endpoints' => [], 'services' => [], 'tables' => [], 'events' => []]), 'relevant_code' => $detail['relevant_code'] ?? null, 'history' => $this->history($row), 'signals' => $this->section($detail, 'signals', ['open_issues' => 0, 'commits_30d' => 0, 'support_tickets' => 0, 'ai_confidence' => null]), 'divergences' => $detail['divergences'] ?? [], 'faqs' => $detail['faqs'] ?? []],
            'Regra' => ['kind' => 'regra_de_negocio', 'statement' => (string) ($detail['statement'] ?? $row['body'] ?? ''), 'effective' => $detail['effective'] ?? null, 'approved_by' => $detail['approved_by'] ?? null, 'last_change_reason' => $detail['last_change_reason'] ?? null, 'impact' => $detail['impact'] ?? [], 'versions' => $detail['versions'] ?? [], 'diff' => $this->section($detail, 'diff', ['from' => null, 'to' => null, 'before' => [], 'after' => []]), 'implementation_code' => $detail['implementation_code'] ?? [], 'affected_entities' => $detail['affected_entities'] ?? [], 'pinned_comment' => $detail['pinned_comment'] ?? null],
            'Tela' => ['kind' => 'tela', 'desktop' => $detail['desktop'] ?? null, 'mobile' => $detail['mobile'] ?? null, 'video' => $detail['video'] ?? null, 'fields' => $detail['fields'] ?? [], 'known_issues' => $detail['known_issues'] ?? [], 'permissions' => $detail['permissions'] ?? [], 'relationships' => $detail['relationships'] ?? []],
            'Endpoint' => ['kind' => 'endpoint', 'method' => $detail['method'] ?? null, 'path' => $detail['path'] ?? null, 'description' => (string) ($detail['description'] ?? $row['body'] ?? ''), 'parameters' => $detail['parameters'] ?? [], 'responses' => $detail['responses'] ?? [], 'production_metrics' => $this->section($detail, 'production_metrics', ['period' => null, 'p95_ms' => null, 'error_rate' => null]), 'consumers' => $detail['consumers'] ?? [], 'rules' => $detail['rules'] ?? []],
            default => ['kind' => 'generic', 'attributes' => $detail],
        };
    }

    /**
     * @param  array<string, mixed>  $detail
     * @param  array<string, mixed>  $default
     * @return array<string, mixed>
     */
    private function section(array $detail, string $key, array $default): array
    {
        return array_replace($default, is_array($detail[$key] ?? null) ? $detail[$key] : []);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<array<string, mixed>>
     */
    private function owners(array $row): array
    {
        $owners = [['id' => $row['owner_id'], 'type' => $row['owner_type'], 'responsibility' => 'entity_owner']];
        foreach ($this->database->table('owners')->get() as $owner) {
            $candidateScope = json_decode((string) $owner->scope, true, flags: JSON_THROW_ON_ERROR);
            if ($candidateScope === $row['scope']) {
                $owners[] = ['id' => $owner->owner_id, 'type' => $owner->owner_type, 'responsibility' => $owner->responsibility_type];
            }
        }

        return $owners;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<array<string, mixed>>
     */
    private function relations(array $row): array
    {
        $assertions = $this->database->table('published_assertions as pa')->leftJoin('entity_revisions as counterpart', function ($join) use ($row): void {
            $join->on('counterpart.id', '=', 'pa.object_entity_revision_id')->where('pa.subject_entity_revision_id', '=', $row['revision_id'])
                ->orOn('counterpart.id', '=', 'pa.subject_entity_revision_id')->where('pa.object_entity_revision_id', '=', $row['revision_id']);
        })->leftJoin('entities as entity', 'entity.id', '=', 'counterpart.entity_id')->where(function ($query) use ($row): void {
            $query->where('pa.subject_entity_revision_id', $row['revision_id'])->orWhere('pa.object_entity_revision_id', $row['revision_id']);
        })->select(['pa.id', 'pa.predicate', 'pa.confidence', 'pa.method', 'pa.created_at', 'counterpart.id as revision_id', 'counterpart.title', 'entity.id as entity_id', 'entity.entity_type'])->get();

        return array_values($assertions->map(fn (object $relation): array => ['id' => $relation->id, 'predicate' => $relation->predicate, 'entity' => $relation->entity_id === null ? null : ['id' => $relation->entity_id, 'revision_id' => $relation->revision_id, 'type' => $relation->entity_type, 'title' => $relation->title], 'provenance' => ['origin' => $row['origin_uri'], 'revision' => $row['source_revision'], 'method' => $relation->method, 'confidence' => (float) $relation->confidence, 'processed_at' => $relation->created_at], 'freshness' => $this->freshness($row)])->all());
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<array<string, mixed>>
     */
    private function history(array $row): array
    {
        return array_values($this->database->table('audit_log')->where('entity_id', $row['entity_id'])->orderByDesc('occurred_at')->get()->map(fn (object $entry): array => ['occurred_at' => $entry->occurred_at, 'actor' => ['id' => $entry->actor_id, 'type' => $entry->actor_type], 'action' => $entry->action, 'reason' => $entry->reason, 'decision' => $entry->decision, 'provenance' => $this->provenance($row, 'audit_trail_v1'), 'freshness' => $this->freshness($row)])->all());
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function provenance(array $row, string $method): array
    {
        return ['origin' => $row['origin_uri'], 'revision' => $row['source_revision'], 'method' => $method, 'confidence' => (float) $row['confidence'], 'processed_at' => $row['attributes']['processed_at'] ?? $row['observed_at']];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function freshness(array $row): array
    {
        $validUntil = $row['attributes']['valid_until'] ?? null;
        $reference = $validUntil === null ? Carbon::parse((string) $row['published_at'])->addDays(90) : Carbon::parse((string) $validUntil);
        $status = $reference->isPast() ? 'expirado' : ($reference->lte(now()->addDays(7)) ? 'expira_em_breve' : 'atual');

        return ['status' => $status, 'checked_at' => $row['attributes']['processed_at'] ?? $row['observed_at'], 'valid_until' => $validUntil, 'label' => $status === 'atual' ? 'atual' : $status];
    }

    /** @param array<string,mixed> $row */
    private function allowed(KnowledgeReadAccess $access, array $row): bool
    {
        $classification = (string) ($row['attributes']['classification'] ?? 'interno');
        foreach ($access->grants as $grant) {
            if ((! in_array('knowledge.read', $grant['permissions'], true) && ! in_array('*', $grant['permissions'], true)) || (self::CLASSIFICATIONS[$grant['classification_ceiling']] ?? -1) < (self::CLASSIFICATIONS[$classification] ?? PHP_INT_MAX)) {
                continue;
            }
            $matches = isset($grant['scope']['organization']);
            foreach (['organization', 'application', 'module'] as $dimension) {
                if (isset($grant['scope'][$dimension]) && ($row['scope'][$dimension] ?? null) !== $grant['scope'][$dimension]) {
                    $matches = false;
                }
            }
            if ($matches) {
                return true;
            }
        }

        return false;
    }
}
