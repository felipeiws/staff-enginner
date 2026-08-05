<?php

declare(strict_types=1);

namespace Atlas\Modules\Governance\Application;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;
use Atlas\Modules\Identity\Domain\Principal;

/**
 * Read-only, reproducible health projection backed by the graph and audit trail.
 * Nothing in this class persists a score: every value is recalculated from source records.
 */
final readonly class KnowledgeHealthService
{
    private const CONTEXT_TYPES = ['Organização', 'Aplicação', 'Módulo'];

    public function __construct(private DatabaseManager $database, private CacheRepository $cache) {}

    /** @return array<string,mixed> */
    public function show(string $scopeId, string $period, ?Principal $principal = null): array
    {
        $scope = $this->scope($scopeId);
        $key = $this->cacheKey($scope, $period);

        // A principal-specific aggregate is intentionally not shared through the
        // scope cache; this prevents a privileged aggregate from being reused.
        if ($principal !== null) {
            return $this->calculate($scopeId, $scope, $period, $principal);
        }
        return $this->cache->remember($key, now()->addMinutes(5), fn (): array => $this->calculate($scopeId, $scope, $period, null));
    }

    /** @return array{organization:string,application?:string,module?:string}|null */
    public function scopeFor(string $scopeId): ?array
    {
        try {
            return $this->scope($scopeId);
        } catch (KnowledgeHealthScopeNotFound) {
            return null;
        }
    }

    /** @param array<string,string> $scope */
    public function invalidate(array $scope): void
    {
        $this->cache->forget($this->cacheKey($scope, '30d'));
    }

    /** @return array{organization:string,application?:string,module?:string} */
    private function scope(string $scopeId): array
    {
        $row = $this->database->table('entities')->where('id', $scopeId)
            ->whereIn('entity_type', self::CONTEXT_TYPES)->first(['scope']);
        if ($row === null) {
            throw new KnowledgeHealthScopeNotFound;
        }

        /** @var array{organization:string,application?:string,module?:string} $scope */
        $scope = json_decode((string) $row->scope, true, 512, JSON_THROW_ON_ERROR);

        return $scope;
    }

    /** @param array<string,string> $scope
     * @return array<string,mixed> */
    private function calculate(string $scopeId, array $scope, string $period, ?Principal $principal): array
    {
        $end = now();
        $start = $end->copy()->subDays(30);
        $rows = $this->rows($scope, $principal);
        $series = $this->series($rows, $scope, $start, $end);
        $current = $this->metrics($rows, $scope, $start, $end);

        return [
            'scope_id' => $scopeId,
            'period' => $period,
            'metrics' => [
                $this->metric('Cobertura documental', 'coverage', $current['coverage'], '%', $series['coverage'], false),
                $this->metric('Índice de confiança da IA', 'ai_confidence', $current['ai_confidence'], 'decimal', $series['ai_confidence'], false),
                $this->metric('Perguntas sem resposta', 'unanswered_questions', $current['unanswered_questions'], 'count', $series['unanswered_questions'], true),
                $this->metric('Tempo médio de aprovação', 'approval_time_hours', $current['approval_time_hours'], 'hours', $series['approval_time_hours'], true),
            ],
            'knowledge_debt' => $this->debt($rows, $scope, $start),
            'unanswered_questions' => $this->unanswered($scope),
            'period_insight' => $this->insight($scope),
            'provenance' => [
                'origin' => 'knowledge_graph + audit_log', 'revision' => null,
                'method' => 'knowledge_health_dashboard_v1', 'confidence' => $current['ai_confidence'],
                'processed_at' => $end->toISOString(), 'formula_version' => 'knowledge_health_v1',
            ],
        ];
    }

    /** @param list<array<string,mixed>> $rows
     * @param array<string,string> $scope
     * @return array{coverage:int,ai_confidence:float,unanswered_questions:int,approval_time_hours:float} */
    private function metrics(array $rows, array $scope, Carbon $start, Carbon $end): array
    {
        $eligible = array_values(array_filter($rows, fn (array $row): bool => ! in_array($row['entity_type'], self::CONTEXT_TYPES, true) && $row['lifecycle_state'] !== 'retracted'));
        $published = array_values(array_filter($eligible, fn (array $row): bool => $row['lifecycle_state'] === 'published'));
        $coverage = $eligible === [] ? 0 : (int) round(100 * count($published) / count($eligible));
        $confidence = $published === [] ? 0.0 : round(array_sum(array_column($published, 'confidence')) / count($published), 2);
        $approvalHours = [];
        $revisionIds = array_column($eligible, 'revision_id');
        if ($revisionIds !== []) {
            foreach ($this->database->table('approvals')->whereIn('entity_revision_id', $revisionIds)->whereNotNull('decision')->whereBetween('decided_at', [$start, $end])->get(['proposed_at', 'decided_at']) as $approval) {
                $approvalHours[] = Carbon::parse((string) $approval->proposed_at)->diffInMinutes(Carbon::parse((string) $approval->decided_at)) / 60;
            }
        }

        return ['coverage' => $coverage, 'ai_confidence' => $confidence, 'unanswered_questions' => $this->unansweredCount($scope, $start, $end), 'approval_time_hours' => $approvalHours === [] ? 0.0 : round(array_sum($approvalHours) / count($approvalHours), 1)];
    }

    /** @param list<array<string,mixed>> $rows
     * @param array<string,string> $scope
     * @return array<string,list<array{at:string,value:int|float}>> */
    private function series(array $rows, array $scope, Carbon $start, Carbon $end): array
    {
        $result = ['coverage' => [], 'ai_confidence' => [], 'unanswered_questions' => [], 'approval_time_hours' => []];
        for ($bucket = 0; $bucket < 9; $bucket++) {
            $at = $start->copy()->addSeconds((int) floor($start->diffInSeconds($end) * $bucket / 8));
            $snapshot = array_values(array_filter($rows, fn (array $row): bool => Carbon::parse($row['created_at'])->lte($at)));
            $values = $this->metrics($snapshot, $scope, $start, $at);
            foreach ($result as $key => $unused) {
                $result[$key][] = ['at' => $at->toISOString() ?? $at->format(DATE_ATOM), 'value' => $values[$key]];
            }
        }

        return $result;
    }

    /** @param list<array<string,mixed>> $series
     * @return array<string,mixed> */
    private function metric(string $label, string $key, int|float $value, string $unit, array $series, bool $inverse): array
    {
        $first = $series[0]['value'];
        $variation = round($value - $first, 2);
        $improved = $inverse ? $variation <= 0 : $variation >= 0;
        $color = $improved ? 'var(--acc)' : ($inverse ? 'var(--danger)' : 'var(--warn)');

        return ['key' => $key, 'label' => $label, 'value' => $value, 'unit' => $unit, 'variation' => $variation, 'trend_color' => $color, 'series' => $series, 'provenance' => ['method' => 'knowledge_health_dashboard_v1', 'confidence' => 1.0]];
    }

    /** @param list<array<string,mixed>> $rows
     * @param array<string,string> $scope
     * @return list<array<string,mixed>> */
    private function debt(array $rows, array $scope, Carbon $staleBefore): array
    {
        $published = array_values(array_filter($rows, fn (array $row): bool => $row['lifecycle_state'] === 'published'));
        $documents = array_values(array_filter($published, fn (array $row): bool => $row['entity_type'] === 'Documento'));
        $rules = array_values(array_filter($published, fn (array $row): bool => $row['entity_type'] === 'Regra'));
        $screens = array_values(array_filter($published, fn (array $row): bool => $row['entity_type'] === 'Tela'));
        $stale = array_values(array_filter($documents, fn (array $row): bool => $row['published_at'] === null || Carbon::parse($row['published_at'])->lt($staleBefore)));
        $ownerless = array_values(array_filter($rules, fn (array $row): bool => ! $this->hasOwner($row)));
        $withoutPrint = array_values(array_filter($screens, fn (array $row): bool => empty($row['attributes']['visual_artifact']) && empty($row['attributes']['detail']['desktop'])));
        $accessed = $this->database->table('audit_log')->whereIn('action', ['knowledge.accessed', 'knowledge.read'])->whereNotNull('entity_id')->pluck('entity_id')->all();
        $neverAccessed = array_values(array_filter($documents, fn (array $row): bool => ! in_array($row['entity_id'], $accessed, true)));

        return [
            $this->debtRow('Documentações desatualizadas', count($stale), count($documents), 'var(--warn)'),
            $this->debtRow('Regras sem responsável', count($ownerless), count($rules), 'var(--danger)'),
            $this->debtRow('Telas sem print', count($withoutPrint), count($screens), 'var(--warn)'),
            $this->debtRow('Documentos nunca acessados', count($neverAccessed), count($documents), 'var(--tx3)'),
        ];
    }

    /** @return array<string,mixed> */
    private function debtRow(string $label, int $value, int $total, string $color): array
    {
        return ['label' => $label, 'value' => $value, 'reference_total' => $total, 'percentage' => $total === 0 ? 0 : (int) round(100 * $value / $total), 'color' => $color, 'provenance' => ['method' => 'knowledge_health_debt_v1', 'confidence' => 1.0]];
    }

    /** @param array<string,mixed> $row */
    private function hasOwner(array $row): bool
    {
        if (trim((string) $row['owner_id']) !== '' && ! in_array($row['owner_id'], ['unassigned', 'unknown'], true)) {
            return true;
        }

        return $this->database->table('owners')->where('scope_type', 'entity')->where('scope_identifier', $row['entity_id'])->where(function ($query): void {
            $query->whereNull('effective_to')->orWhere('effective_to', '>=', now());
        })->exists();
    }

    /** @param array<string,string> $scope
     * @return list<array<string,mixed>> */
    private function unanswered(array $scope): array
    {
        $gaps = collect($this->database->table('knowledge_gaps')->orderByDesc('occurrence_count')->orderByDesc('last_occurred_at')->get())->map(function (object $gap) use ($scope): ?array {
            $gapScope = json_decode((string) $gap->scope, true, 512, JSON_THROW_ON_ERROR);
            if (! $this->scopeMatches($scope, $gapScope)) {
                return null;
            }
            $started = Carbon::parse((string) $gap->window_started_at);
            $last = Carbon::parse((string) $gap->last_occurred_at);
            $count = (int) $gap->occurrence_count;

            return ['id' => (string) $gap->id, 'question' => (string) $gap->question, 'occurrence_count' => $count, 'period' => ['started_at' => $started->toISOString() ?? $started->format(DATE_ATOM), 'ended_at' => $last->toISOString() ?? $last->format(DATE_ATOM), 'days' => max(1, $started->diffInDays($last) + 1)], 'color' => $count >= 25 ? 'var(--danger)' : 'var(--warn)', 'provenance' => ['method' => 'unanswered_question_v1', 'confidence' => 1.0]];
        })->filter()->take(3)->values()->all();

        return array_values($gaps);
    }

    /** @param array<string,string> $scope */
    private function unansweredCount(array $scope, Carbon $start, Carbon $end): int
    {
        return (int) $this->database->table('knowledge_gaps')->whereBetween('last_occurred_at', [$start, $end])->get()->sum(function (object $gap) use ($scope): int {
            $gapScope = json_decode((string) $gap->scope, true, 512, JSON_THROW_ON_ERROR);

            return $this->scopeMatches($scope, $gapScope) ? (int) $gap->occurrence_count : 0;
        });
    }

    /** @param array<string,string> $scope
     * @return array<string,mixed> */
    private function insight(array $scope): array
    {
        $rows = $this->rows($scope);
        $modules = [];
        foreach ($rows as $row) {
            if (isset($row['scope']['module'])) {
                $modules[$row['scope']['module']][] = $row;
            }
        }
        $allTickets = 0;
        $lowTickets = 0;
        foreach ($modules as $moduleRows) {
            $eligible = array_filter($moduleRows, fn (array $r): bool => ! in_array($r['entity_type'], self::CONTEXT_TYPES, true) && $r['lifecycle_state'] !== 'retracted');
            $published = array_filter($eligible, fn (array $r): bool => $r['lifecycle_state'] === 'published');
            $coverage = $eligible === [] ? 0 : 100 * count($published) / count($eligible);
            $tickets = count(array_filter($moduleRows, fn (array $r): bool => $r['entity_type'] === 'Issue' && (($r['attributes']['kind'] ?? null) === 'support_ticket')));
            $allTickets += $tickets;
            if ($coverage < 50) {
                $lowTickets += $tickets;
            }
        }
        $share = $allTickets === 0 ? 0 : (int) round(100 * $lowTickets / $allTickets);

        return ['low_coverage_threshold' => 50, 'support_tickets_in_low_coverage_modules' => $lowTickets, 'support_tickets_total' => $allTickets, 'support_ticket_share' => $share, 'text' => $allTickets === 0 ? 'Não há tickets de suporte com proveniência no escopo para correlacionar com a cobertura.' : "Módulos com cobertura abaixo de 50% concentram {$share}% dos tickets de suporte.", 'provenance' => ['method' => 'knowledge_health_ticket_correlation_v1', 'confidence' => 1.0]];
    }

    /** @param array<string,string> $scope
     * @return list<array<string,mixed>> */
    private function rows(array $scope, ?Principal $principal = null): array
    {
        $rows = $this->database->table('entities as e')->join('entity_revisions as er', 'er.entity_id', '=', 'e.id')
            ->whereRaw('er.version = (select max(current_er.version) from entity_revisions as current_er where current_er.entity_id = e.id)')
            ->select(['e.id as entity_id', 'e.entity_type', 'e.owner_id', 'e.scope', 'er.id as revision_id', 'er.lifecycle_state', 'er.confidence', 'er.published_at', 'er.attributes', 'er.created_at'])->get()->map(function (object $row) use ($scope, $principal): ?array {
                $item = (array) $row;
                $item['scope'] = json_decode((string) $item['scope'], true, 512, JSON_THROW_ON_ERROR);
                $item['attributes'] = json_decode((string) $item['attributes'], true, 512, JSON_THROW_ON_ERROR);

                return $this->scopeMatches($scope, $item['scope']) && $this->visibleTo($item, $principal) ? $item : null;
            })->filter()->values()->all();

        return array_values($rows);
    }

    /** @param array<string,mixed> $row */
    private function visibleTo(array $row, ?Principal $principal): bool
    {
        if ($principal === null) { return true; }
        $levels = ['publico' => 0, 'interno' => 1, 'restrito' => 2, 'confidencial' => 3];
        $classification = (string) ($row['attributes']['classification'] ?? 'interno');
        foreach ($principal->grants as $grant) {
            if ((! in_array('knowledge.read', $grant['permissions'], true) && ! in_array('*', $grant['permissions'], true)) || ($levels[$grant['classification_ceiling']] ?? -1) < ($levels[$classification] ?? PHP_INT_MAX)) { continue; }
            if ($this->scopeMatches((array) $grant['scope'], (array) $row['scope'])) { return true; }
        }
        return false;
    }

    /** @param array<string,string> $required
     * @param array<string,mixed> $actual */
    private function scopeMatches(array $required, array $actual): bool
    {
        foreach ($required as $key => $value) {
            if (($actual[$key] ?? null) !== $value) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string,string> $scope */
    private function cacheKey(array $scope, string $period): string
    {
        return 'knowledge-health:'.hash('sha256', json_encode($scope, JSON_THROW_ON_ERROR)).':'.$period;
    }
}

final class KnowledgeHealthScopeNotFound extends \RuntimeException {}
