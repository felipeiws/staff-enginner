<?php

declare(strict_types=1);

return [
    // The Copilot abstains unless both coverage and average evidence confidence meet these thresholds.
    'minimum_source_count' => (int) env('KNOWLEDGE_GAPS_MINIMUM_SOURCE_COUNT', 1),
    'minimum_evidence_score' => (float) env('KNOWLEDGE_GAPS_MINIMUM_EVIDENCE_SCORE', 0.80),
    'window_days' => (int) env('KNOWLEDGE_GAPS_WINDOW_DAYS', 30),
    'danger_occurrences' => (int) env('KNOWLEDGE_GAPS_DANGER_OCCURRENCES', 25),
];
