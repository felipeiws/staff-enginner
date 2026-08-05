<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Domain\Repository;

use Atlas\Modules\KnowledgeEngine\Domain\Aggregate\Evidence;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Identifier;

interface EvidenceRepository
{
    public function save(Evidence $evidence): void;

    /** @return list<Evidence> */
    public function forAssertion(Identifier $assertionId): array;
}
