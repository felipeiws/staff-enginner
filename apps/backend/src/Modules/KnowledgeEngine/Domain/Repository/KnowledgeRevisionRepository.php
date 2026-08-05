<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Domain\Repository;

use Atlas\Modules\KnowledgeEngine\Domain\Aggregate\KnowledgeRevision;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Identifier;

interface KnowledgeRevisionRepository
{
    public function save(KnowledgeRevision $revision): void;

    public function get(Identifier $id): ?KnowledgeRevision;

    /** @return list<KnowledgeRevision> */
    public function publishedForEntity(Identifier $entityId): array;
}
