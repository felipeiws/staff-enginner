<?php

declare(strict_types=1);

namespace Atlas\Modules\Governance\Application;

/**
 * Event handler for the idempotent delivery boundary. Its event id is deliberately
 * ignored here because that boundary has already recorded it in processed_events.
 */
final readonly class KnowledgeHealthCacheInvalidator
{
    public function __construct(private KnowledgeHealthService $health) {}

    public function name(): string
    {
        return 'governance.knowledge-health-cache';
    }

    /** @param array<string,mixed> $event */
    public function consume(array $event): void
    {
        $scope = $event['scope'] ?? null;
        if (is_array($scope) && isset($scope['organization'])) {
            $this->health->invalidate($scope);
        }
    }
}
