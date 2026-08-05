<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Application;

interface EventConsumer
{
    public function name(): string;

    /** @param array<string, mixed> $event */
    public function consume(array $event): void;
}
