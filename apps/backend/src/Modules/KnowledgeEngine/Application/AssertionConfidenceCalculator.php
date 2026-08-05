<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Application;

use DateTimeImmutable;

/** Combines independent evidence using a 30-day exponential freshness half-life. */
final readonly class AssertionConfidenceCalculator
{
    public function __construct(private int $freshnessHalfLifeDays = 30)
    {
        if ($freshnessHalfLifeDays < 1) {
            throw new \InvalidArgumentException('A meia-vida de frescor deve ser positiva.');
        }
    }

    /** @param list<array{weight: float, observed_at: DateTimeImmutable}> $evidence */
    public function calculate(array $evidence, DateTimeImmutable $now): float
    {
        $unconfirmed = 1.0;
        foreach ($evidence as $item) {
            $ageDays = max(0.0, ($now->getTimestamp() - $item['observed_at']->getTimestamp()) / 86400);
            $freshness = exp(-log(2) * $ageDays / $this->freshnessHalfLifeDays);
            $effectiveWeight = min(1.0, max(0.0, $item['weight'])) * $freshness;
            $unconfirmed *= 1.0 - $effectiveWeight;
        }

        return round(1.0 - $unconfirmed, 3);
    }
}
