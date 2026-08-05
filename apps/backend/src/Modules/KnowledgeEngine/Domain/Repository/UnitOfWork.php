<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Domain\Repository;

use Atlas\Modules\KnowledgeEngine\Domain\Event\DomainEvent;

interface UnitOfWork
{
    /** @param callable(): mixed $operation */
    public function transactional(DomainEvent $event, callable $operation): mixed;

    public function isActive(): bool;
}
