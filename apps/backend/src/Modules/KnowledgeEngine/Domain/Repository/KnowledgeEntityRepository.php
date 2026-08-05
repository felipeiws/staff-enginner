<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Domain\Repository;

use Atlas\Modules\KnowledgeEngine\Domain\Aggregate\KnowledgeEntity;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Identifier;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\StableIdentity;

interface KnowledgeEntityRepository
{
    public function save(KnowledgeEntity $entity): void;

    public function get(Identifier $id): ?KnowledgeEntity;

    public function findByIdentity(StableIdentity $identity): ?KnowledgeEntity;
}
