<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Application;

use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;

/** Authoritative read model for the Portal; it never writes to the graph. */
final class ApplicationReadService
{
    private const CLASSIFICATIONS = ['publico' => 0, 'interno' => 1, 'restrito' => 2, 'confidencial' => 3];

    private const CONTEXT_TYPES = ['Organização', 'Aplicação', 'Módulo'];

    public function __construct(private readonly DatabaseManager $database) {}

    /** @return array<string, mixed> */
    public function organization(KnowledgeReadAccess $access, string $organizationId): array
    {
        $rows = $this->accessibleRows($access);
        $organization = $this->first($rows, 'Organização', ['organization' => $organizationId]);
        if ($organization === null) {
            throw new KnowledgeReadAccessDenied('Acesso negado para este escopo ou classificação.');
        }

        $organizationRows = $this->inScope($rows, ['organization' => $organizationId]);

        return [
            'id' => $organizationId,
            'name' => $organization['title'],
            'indexed_entities_count' => count(array_filter($organizationRows, fn (array $row): bool => $row['lifecycle_state'] === 'published')),
            'applications_count' => count(array_filter($organizationRows, fn (array $row): bool => $row['entity_type'] === 'Aplicação' && $row['lifecycle_state'] === 'published')),
            'provenance' => $this->provenance($organization, 'published_entity_aggregation_v1'),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function applications(KnowledgeReadAccess $access): array
    {
        $rows = $this->accessibleRows($access);
        $applications = array_filter($rows, fn (array $row): bool => $row['entity_type'] === 'Aplicação' && $row['lifecycle_state'] === 'published');
        usort($applications, fn (array $left, array $right): int => strcmp($left['title'], $right['title']));

        return array_map(fn (array $application): array => $this->applicationSummary($application, $rows), $applications);
    }

    /** Read-only composition for the Home; every item is filtered before exposure. */
    public function home(KnowledgeReadAccess $access, string $organizationId): array
    {
        $rows = $this->accessibleRows($access);
        $organization = $this->organization($access, $organizationId);
        $organizationRows = $this->inScope($rows, ['organization' => $organizationId]);
        $published = array_values(array_filter($organizationRows, fn (array $row): bool => $row['lifecycle_state'] === 'published'));
        $applications = array_values(array_filter($published, fn (array $row): bool => $row['entity_type'] === 'Aplicação'));
        usort($applications, fn (array $left, array $right): int => strcmp($left['title'], $right['title']));
        $faqs = array_values(array_filter($published, fn (array $row): bool => $row['entity_type'] === 'FAQ'));
        usort($faqs, fn (array $left, array $right): int => strcmp($right['published_at'] ?? '', $left['published_at'] ?? ''));
        $processes = array_values(array_filter($published, fn (array $row): bool => $row['entity_type'] === 'Processo'));
        usort($processes, fn (array $left, array $right): int => ((int) ($right['attributes']['access_count'] ?? 0)) <=> ((int) ($left['attributes']['access_count'] ?? 0)));

        $visibleByEntity = array_column($published, null, 'entity_id');
        $activity = [];
        foreach ($this->database->table('audit_log')->orderByDesc('occurred_at')->limit(30)->get() as $entry) {
            $entityId = $entry->entity_id;
            if ($entityId === null || ! isset($visibleByEntity[$entityId])) continue;
            $activity[] = ['actor' => (string) $entry->actor_id, 'action' => (string) $entry->action, 'occurred_at' => (string) $entry->occurred_at, 'color' => 'var(--acc)', 'provenance' => $this->provenance($visibleByEntity[$entityId], 'audit_trail_v1')];
            if (count($activity) === 5) break;
        }

        return [
            'organization' => $organization,
            'applications' => array_map(fn (array $row): array => $this->applicationSummary($row, $rows), $applications),
            'frequent_questions' => array_map(fn (array $row): array => ['id' => $row['entity_id'], 'question' => $row['title'], 'provenance' => $this->provenance($row, 'published_faq_v1')], array_slice($faqs, 0, 4)),
            'top_processes' => array_map(fn (array $row): array => ['id' => $row['entity_id'], 'name' => $row['title'], 'volume' => (int) ($row['attributes']['access_count'] ?? 0), 'provenance' => $this->provenance($row, 'published_process_access_v1')], array_slice($processes, 0, 5)),
            'activity' => $activity,
        ];
    }

    /** @return array<string, mixed> */
    public function application(KnowledgeReadAccess $access, string $applicationId): array
    {
        $rows = $this->accessibleRows($access);
        $application = $this->first($rows, 'Aplicação', ['application' => $applicationId]);
        if ($application === null || $application['lifecycle_state'] !== 'published') {
            throw new KnowledgeReadAccessDenied('Acesso negado para este escopo ou classificação.');
        }

        return $this->applicationSummary($application, $rows, true);
    }

    /** @return list<array<string, mixed>> */
    public function modules(KnowledgeReadAccess $access, string $applicationId): array
    {
        $rows = $this->accessibleRows($access);
        $this->assertApplicationExists($rows, $applicationId);
        $modules = array_values(array_filter($this->inScope($rows, ['application' => $applicationId]), fn (array $row): bool => $row['entity_type'] === 'Módulo' && $row['lifecycle_state'] === 'published'));
        usort($modules, fn (array $left, array $right): int => strcmp($left['title'], $right['title']));

        return array_map(function (array $module) use ($rows): array {
            $scope = $module['scope'];
            $moduleRows = $this->inScope($rows, ['application' => $scope['application'], 'module' => $scope['module']]);
            $health = $this->health($moduleRows);

            return [
                'id' => $scope['module'],
                'name' => $module['title'],
                'processes_count' => $this->publishedTypeCount($moduleRows, ['Processo']),
                'apis_count' => $this->publishedTypeCount($moduleRows, ['Endpoint']),
                'knowledge_health' => $health,
                'provenance' => $this->provenance($module, 'published_entity_aggregation_v1'),
            ];
        }, $modules);
    }

    /** @return array{nav:string,groups:list<array<string,mixed>>} */
    public function tree(KnowledgeReadAccess $access, string $applicationId, string $nav): array
    {
        $rows = $this->accessibleRows($access);
        $this->assertApplicationExists($rows, $applicationId);
        $applicationRows = $this->inScope($rows, ['application' => $applicationId]);
        $groups = $nav === 'technical'
            ? [
                ['architecture', 'Arquitetura', ['ADR', 'Diagrama']], ['code', 'Código', ['Repositório', 'Controller', 'Service', 'Job']],
                ['apis', 'APIs', ['Endpoint']], ['database', 'Banco', ['Tabela', 'Migration']], ['integrations', 'Integrações', ['Integração']], ['queues_events', 'Filas e Eventos', ['Evento']],
            ]
            : [
                ['processes', 'Processos', ['Processo']], ['flows', 'Fluxos', ['Diagrama']], ['faqs', 'FAQs', ['FAQ']],
                ['troubleshooting', 'Troubleshooting', ['Documento']], ['business_rules', 'Regras de negócio', ['Regra']], ['screens', 'Telas', ['Tela']],
            ];

        return ['nav' => $nav, 'groups' => array_values(array_map(function (array $group) use ($applicationRows): array {
            [$key, $name, $types] = $group;
            $matching = array_values(array_filter($applicationRows, fn (array $row): bool => $row['lifecycle_state'] === 'published' && in_array($row['entity_type'], $types, true)));
            $breakdown = [];
            foreach ($types as $type) {
                $breakdown[$type] = $this->publishedTypeCount($applicationRows, [$type]);
            }

            return ['key' => $key, 'name' => $name, 'count' => count($matching), 'breakdown' => $breakdown, 'provenance' => $this->aggregateProvenance($matching)];
        }, $groups))];
    }

    /**
     * @param  array<string, mixed>  $application
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function applicationSummary(array $application, array $rows, bool $details = false): array
    {
        $scope = $application['scope'];
        $applicationRows = $this->inScope($rows, ['application' => $scope['application']]);
        $health = $this->health($applicationRows);
        $result = [
            'id' => $scope['application'], 'name' => $application['title'],
            'modules_count' => $this->publishedTypeCount($applicationRows, ['Módulo']),
            'screens_count' => $this->publishedTypeCount($applicationRows, ['Tela']),
            'knowledge_health' => $health,
            'provenance' => $this->provenance($application, 'published_entity_aggregation_v1'),
        ];
        if ($details) {
            $result['description'] = (string) ($application['attributes']['description'] ?? $application['body'] ?? '');
            $result['documentation_coverage'] = $health['coverage'];
            $result['pending_approvals'] = $health['pending_approvals'];
        }

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{score:int,status:string,coverage:int,pending_approvals:int,formula_version:string}
     */
    private function health(array $rows): array
    {
        $eligible = array_values(array_filter($rows, fn (array $row): bool => ! in_array($row['entity_type'], self::CONTEXT_TYPES, true) && $row['lifecycle_state'] !== 'retracted'));
        if ($eligible === []) {
            return ['score' => 0, 'status' => 'Cobertura baixa', 'coverage' => 0, 'pending_approvals' => 0, 'formula_version' => 'knowledge_health_v1'];
        }
        $published = array_values(array_filter($eligible, fn (array $row): bool => $row['lifecycle_state'] === 'published'));
        $coverage = (int) round(100 * count($published) / count($eligible));
        $fresh = array_filter($published, fn (array $row): bool => $row['published_at'] !== null && Carbon::parse($row['published_at'])->gte(now()->subDays(90)));
        $freshness = (int) round(100 * count($fresh) / count($eligible));
        $confidence = $published === [] ? 0 : (int) round(array_sum(array_map(fn (array $row): float => (float) $row['confidence'], $published)) / count($published) * 100);
        $proposals = $this->database->table('approvals')->whereIn('entity_revision_id', array_column($eligible, 'revision_id'))->count();
        $pending = $this->database->table('approvals')->whereIn('entity_revision_id', array_column($eligible, 'revision_id'))->whereNull('decision')->count();
        $approval = $proposals === 0 ? 100 : (int) round(100 * (1 - $pending / $proposals));
        $score = (int) round(.50 * $coverage + .25 * $freshness + .15 * $confidence + .10 * $approval);
        $status = $score >= 75 ? 'Saudável' : ($score >= 50 ? 'Atenção' : 'Cobertura baixa');

        return ['score' => $score, 'status' => $status, 'coverage' => $coverage, 'pending_approvals' => $pending, 'formula_version' => 'knowledge_health_v1'];
    }

    /** @return list<array<string,mixed>> */
    private function accessibleRows(KnowledgeReadAccess $access): array
    {
        if (! $this->hasReadGrant($access)) {
            throw new KnowledgeReadAccessDenied('Acesso negado para este escopo ou classificação.');
        }
        $rows = $this->database->table('entities as e')->join('entity_revisions as er', 'er.entity_id', '=', 'e.id')
            ->leftJoin('revisions as r', 'r.id', '=', 'er.source_revision_id')->leftJoin('artifacts as a', 'a.id', '=', 'r.artifact_id')->leftJoin('sources as s', 's.id', '=', 'a.source_id')
            ->whereRaw('er.version = (select max(current_er.version) from entity_revisions as current_er where current_er.entity_id = e.id)')
            ->select(['e.id as entity_id', 'e.entity_type', 'e.scope', 'er.id as revision_id', 'er.lifecycle_state', 'er.title', 'er.body', 'er.attributes', 'er.confidence', 'er.published_at', 'r.source_revision', 'r.observed_at', 'a.uri as origin_uri', 's.type as source_type'])->get()->map(function (object $row): array {
                $row = (array) $row;
                $row['scope'] = json_decode((string) $row['scope'], true, flags: JSON_THROW_ON_ERROR);
                $row['attributes'] = json_decode((string) $row['attributes'], true, flags: JSON_THROW_ON_ERROR);

                return $row;
            })->all();

        return array_values(array_filter($rows, fn (array $row): bool => $this->allowed($access, $row)));
    }

    private function hasReadGrant(KnowledgeReadAccess $access): bool
    {
        foreach ($access->grants as $grant) {
            if (isset($grant['scope']['organization']) && (in_array('knowledge.read', $grant['permissions'], true) || in_array('*', $grant['permissions'], true))) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $row */
    private function allowed(KnowledgeReadAccess $access, array $row): bool
    {
        $classification = (string) ($row['attributes']['classification'] ?? 'interno');
        foreach ($access->grants as $grant) {
            if (! in_array('knowledge.read', $grant['permissions'], true) && ! in_array('*', $grant['permissions'], true)) {
                continue;
            }
            if ((self::CLASSIFICATIONS[$grant['classification_ceiling']] ?? -1) < (self::CLASSIFICATIONS[$classification] ?? PHP_INT_MAX)) {
                continue;
            }
            if ($this->scopeMatches($grant['scope'], $row['scope'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, string>  $grantScope
     * @param  array<string, mixed>  $resourceScope
     */
    private function scopeMatches(array $grantScope, array $resourceScope): bool
    {
        foreach (['organization', 'application', 'module'] as $dimension) {
            if (isset($grantScope[$dimension]) && ($resourceScope[$dimension] ?? null) !== $grantScope[$dimension]) {
                return false;
            }
        }

        return isset($grantScope['organization']);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, string>  $scope
     * @return list<array<string, mixed>>
     */
    private function inScope(array $rows, array $scope): array
    {
        return array_values(array_filter($rows, function (array $row) use ($scope): bool {
            foreach ($scope as $key => $value) {
                if (($row['scope'][$key] ?? null) !== $value) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, string>  $scope
     * @return array<string, mixed>|null
     */
    private function first(array $rows, string $type, array $scope): ?array
    {
        foreach ($this->inScope($rows, $scope) as $row) {
            if ($row['entity_type'] === $type) {
                return $row;
            }
        }

        return null;
    }

    /** @param list<array<string, mixed>> $rows */
    private function assertApplicationExists(array $rows, string $applicationId): void
    {
        if ($this->first($rows, 'Aplicação', ['application' => $applicationId]) === null) {
            throw new KnowledgeReadAccessDenied('Acesso negado para este escopo ou classificação.');
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $types
     */
    private function publishedTypeCount(array $rows, array $types): int
    {
        return count(array_filter($rows, fn (array $row): bool => $row['lifecycle_state'] === 'published' && in_array($row['entity_type'], $types, true)));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function provenance(array $row, string $method): array
    {
        return ['origin' => $row['origin_uri'], 'revision' => $row['source_revision'], 'method' => $method, 'confidence' => (float) $row['confidence'], 'processed_at' => $row['observed_at'] ?? $row['published_at']];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function aggregateProvenance(array $rows): array
    {
        $confidence = $rows === [] ? 0 : array_sum(array_map(fn (array $row): float => (float) $row['confidence'], $rows)) / count($rows);

        return ['origin' => 'published_entity_revisions', 'revision' => null, 'method' => 'published_entity_aggregation_v1', 'confidence' => round($confidence, 3), 'processed_at' => null];
    }
}
