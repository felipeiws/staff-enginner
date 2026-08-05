<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Infrastructure\Persistence;

use Atlas\Modules\KnowledgeEngine\Domain\Event\DomainEvent;
use Atlas\Modules\KnowledgeEngine\Domain\Repository\UnitOfWork;
use Atlas\Modules\KnowledgeEngine\Infrastructure\OutboxEventStore;
use Illuminate\Database\DatabaseManager;

final class EloquentUnitOfWork implements UnitOfWork
{
    private int $depth = 0;

    public function __construct(private readonly DatabaseManager $database, private readonly OutboxEventStore $outbox) {}

    public function transactional(DomainEvent $event, callable $operation): mixed
    {
        return $this->database->transaction(function () use ($event, $operation): mixed {
            $this->depth++;
            try {
                $result = $operation();
                $this->outbox->append($event->aggregate, $event->payload);

                return $result;
            } finally {
                $this->depth--;
            }
        });
    }

    public function isActive(): bool
    {
        return $this->depth > 0;
    }
}
