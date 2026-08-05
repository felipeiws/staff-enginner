<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Domain\Repository;

use Atlas\Contracts\Enum\GraphPredicate;
use Atlas\Modules\KnowledgeEngine\Domain\Aggregate\Assertion;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Identifier;

interface AssertionRepository
{
    public function save(Assertion $assertion): void;

    public function get(Identifier $id): ?Assertion;

    /** @return list<Assertion> */
    public function dependentsOfRevision(Identifier $revisionId): array;

    /** @return list<Assertion> */
    public function publishedWithSubjectAndPredicate(Identifier $subjectRevisionId, GraphPredicate $predicate): array;
}
