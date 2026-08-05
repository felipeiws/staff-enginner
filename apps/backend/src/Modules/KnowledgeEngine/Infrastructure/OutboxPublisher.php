<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Infrastructure;

interface OutboxPublisher
{
    /** @param array<string, mixed> $event */
    public function publish(array $event): void;
}
