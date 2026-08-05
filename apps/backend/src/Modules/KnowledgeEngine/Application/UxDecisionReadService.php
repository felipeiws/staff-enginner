<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Application;

use Illuminate\Database\DatabaseManager;

/** Authoritative read model for the published UX decision documents. */
final class UxDecisionReadService
{
    private const CLASSIFICATIONS = ['publico' => 0, 'interno' => 1, 'restrito' => 2, 'confidencial' => 3];

    public function __construct(private readonly DatabaseManager $database) {}

    /** @return array{decisions:list<array<string,mixed>>,provenance:array<string,mixed>} */
    public function all(KnowledgeReadAccess $access): array
    {
        $rows = $this->database->table('entities as e')
            ->join('entity_revisions as er', 'er.entity_id', '=', 'e.id')
            ->join('revisions as r', 'r.id', '=', 'er.source_revision_id')
            ->join('artifacts as a', 'a.id', '=', 'r.artifact_id')
            ->where('er.lifecycle_state', 'published')
            ->whereIn('e.entity_type', ['Documento', 'ADR'])
            ->orderBy('er.attributes->ux_decision_order')
            ->get(['e.id as entity_id', 'e.entity_type', 'e.scope', 'er.title', 'er.body', 'er.attributes', 'er.confidence', 'r.source_revision', 'r.observed_at', 'a.uri as origin_uri']);

        $decisions = [];
        foreach ($rows as $record) {
            $row = (array) $record;
            $attributes = json_decode((string) $row['attributes'], true, flags: JSON_THROW_ON_ERROR);
            if (($attributes['kind'] ?? null) !== 'ux_decision' || ! $this->allowed($access, json_decode((string) $row['scope'], true, flags: JSON_THROW_ON_ERROR), (string) ($attributes['classification'] ?? 'interno'))) {
                continue;
            }
            $decisions[] = [
                'id' => $row['entity_id'],
                'type' => $row['entity_type'],
                'title' => $row['title'],
                'description' => (string) $row['body'],
                'outcome' => (string) ($attributes['outcome'] ?? ''),
                'provenance' => [
                    'origin' => $row['origin_uri'],
                    'revision' => $row['source_revision'],
                    'method' => $attributes['method'] ?? 'human_declared@1.0.0',
                    'confidence' => (float) $row['confidence'],
                    'processed_at' => $attributes['processed_at'] ?? $row['observed_at'],
                ],
            ];
        }

        return ['decisions' => $decisions, 'provenance' => ['origin' => 'published_entity_revisions', 'revision' => null, 'method' => 'ux_decisions_read_v1', 'confidence' => 1.0, 'processed_at' => now()->toISOString()]];
    }

    /** @param array<string,mixed> $scope */
    private function allowed(KnowledgeReadAccess $access, array $scope, string $classification): bool
    {
        foreach ($access->grants as $grant) {
            if ((! in_array('knowledge.read', $grant['permissions'], true) && ! in_array('*', $grant['permissions'], true)) || (self::CLASSIFICATIONS[$grant['classification_ceiling']] ?? -1) < (self::CLASSIFICATIONS[$classification] ?? PHP_INT_MAX)) {
                continue;
            }
            $matches = isset($grant['scope']['organization']);
            foreach (['organization', 'application', 'module'] as $dimension) {
                if (isset($grant['scope'][$dimension]) && ($scope[$dimension] ?? null) !== $grant['scope'][$dimension]) {
                    $matches = false;
                }
            }
            if ($matches) return true;
        }
        return false;
    }
}
