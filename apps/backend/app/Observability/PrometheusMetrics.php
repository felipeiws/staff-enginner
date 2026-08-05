<?php

declare(strict_types=1);

namespace App\Observability;

use Illuminate\Database\DatabaseManager;

final readonly class PrometheusMetrics
{
    public function __construct(private DatabaseManager $database) {}

    public function render(): string
    {
        $projectionLag = (float) ($this->database->table('search_projection_metrics')->max('lag_ms') ?? 0);
        $dlq = (int) $this->database->table('outbox_events')->where('status', 'dead_letter')->count();
        $pending = (int) $this->database->table('approvals')->whereNull('decision')->count();
        $durations = $this->database->table('approvals')->whereNotNull('decided_at')->get(['proposed_at', 'decided_at'])->map(static fn (object $approval): float => max(0, strtotime((string) $approval->decided_at) - strtotime((string) $approval->proposed_at)));
        $approvalSeconds = $durations->isEmpty() ? 0.0 : $durations->average();
        $lines = [
            '# HELP atlas_search_projection_lag_milliseconds Maior lag da projeção publicada.',
            '# TYPE atlas_search_projection_lag_milliseconds gauge', "atlas_search_projection_lag_milliseconds {$projectionLag}",
            '# HELP atlas_outbox_dlq_size Eventos em DLQ da outbox.', '# TYPE atlas_outbox_dlq_size gauge', "atlas_outbox_dlq_size {$dlq}",
            '# HELP atlas_approval_proposals_pending Propostas pendentes de revisão humana.', '# TYPE atlas_approval_proposals_pending gauge', "atlas_approval_proposals_pending {$pending}",
            '# HELP atlas_approval_duration_seconds Tempo médio entre proposta e decisão.', '# TYPE atlas_approval_duration_seconds gauge', "atlas_approval_duration_seconds {$approvalSeconds}",
        ];
        foreach ($this->database->table('observability_metric_samples')->where('observed_at', '>=', now()->subDay())->get() as $sample) {
            $labels = json_decode((string) $sample->labels, true, 512, JSON_THROW_ON_ERROR);
            $rendered = $labels === [] ? '' : '{'.implode(',', array_map(static fn (string $key, mixed $value): string => $key.'="'.str_replace('"', '\\"', (string) $value).'"', array_keys($labels), $labels)).'}';
            $name = preg_replace('/[^a-zA-Z0-9_:]/', '_', (string) $sample->name) ?: 'atlas_invalid_metric';
            $lines[] = "{$name}{$rendered} {$sample->value}";
        }

        return implode("\n", $lines)."\n";
    }
}
