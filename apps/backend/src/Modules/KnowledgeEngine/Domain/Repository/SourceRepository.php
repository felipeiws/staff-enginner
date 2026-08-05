<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Domain\Repository;

use Atlas\Modules\KnowledgeEngine\Domain\Aggregate\Source;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Identifier;

interface SourceRepository
{
    public function save(Source $source): void;

    public function get(Identifier $id): ?Source;

    public function findActiveByType(string $type): ?Source;
}
