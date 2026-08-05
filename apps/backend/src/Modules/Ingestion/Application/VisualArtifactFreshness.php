<?php

declare(strict_types=1);

namespace Atlas\Modules\Ingestion\Application;

use Carbon\CarbonImmutable;

/** Calcula o selo a partir de fatos versionados; não é um estado editável. */
final class VisualArtifactFreshness
{
    /** @return array{status: string, label: string, artifact_at: string, code_changed_at: string} */
    public function calculate(string $artifactAt, string $codeChangedAt, ?CarbonImmutable $now = null): array
    {
        $artifact = CarbonImmutable::parse($artifactAt);
        $changed = CarbonImmutable::parse($codeChangedAt);
        if ($artifact->greaterThanOrEqualTo($changed)) {
            return ['status' => 'atual', 'label' => 'print atual', 'artifact_at' => $artifact->toISOString(), 'code_changed_at' => $changed->toISOString()];
        }
        $reference = $now ?? CarbonImmutable::now();
        $months = max(1, $artifact->diffInMonths($reference));
        $unit = $months === 1 ? 'mês' : 'meses';

        return ['status' => 'desatualizado', 'label' => "print desatualizado há {$months} {$unit}", 'artifact_at' => $artifact->toISOString(), 'code_changed_at' => $changed->toISOString()];
    }
}
