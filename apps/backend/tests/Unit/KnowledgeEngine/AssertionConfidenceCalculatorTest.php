<?php

declare(strict_types=1);

use Atlas\Modules\KnowledgeEngine\Application\AssertionConfidenceCalculator;

it('aggregates independent evidence weights and decays them with freshness', function (): void {
    $calculator = new AssertionConfidenceCalculator(30);
    $now = new DateTimeImmutable('2026-07-31T12:00:00+00:00');

    expect($calculator->calculate([
        ['weight' => 0.8, 'observed_at' => $now->modify('-30 days')],
    ], $now))->toBe(0.4)
        ->and($calculator->calculate([
            ['weight' => 0.5, 'observed_at' => $now],
            ['weight' => 0.5, 'observed_at' => $now],
        ], $now))->toBe(0.75);
});
