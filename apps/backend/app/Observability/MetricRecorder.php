<?php

declare(strict_types=1);

namespace App\Observability;

use Illuminate\Database\DatabaseManager;

/** Stores bounded operational samples only; knowledge payloads never enter telemetry. */
final readonly class MetricRecorder
{
    public function __construct(private DatabaseManager $database) {}

    /** @param array<string, scalar> $labels */
    public function observe(string $name, float $value, array $labels = []): void
    {
        $this->database->table('observability_metric_samples')->insert([
            'name' => $name,
            'value' => $value,
            'labels' => json_encode($labels, JSON_THROW_ON_ERROR),
            'observed_at' => now(),
        ]);
    }
}
