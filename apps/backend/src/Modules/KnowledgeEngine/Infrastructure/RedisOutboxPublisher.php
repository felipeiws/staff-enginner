<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Infrastructure;

use Illuminate\Contracts\Queue\Factory as QueueFactory;

final readonly class RedisOutboxPublisher implements OutboxPublisher
{
    public function __construct(private QueueFactory $queues) {}

    public function publish(array $event): void
    {
        $this->queues->connection('redis')->pushRaw(
            json_encode($event, JSON_THROW_ON_ERROR),
            config('outbox.queue'),
        );
    }
}
