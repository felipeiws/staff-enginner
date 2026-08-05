<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Application;

use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Identifier;

final readonly class RevisionReference
{
    public function __construct(public Identifier $entityId, public ?Identifier $revisionId = null) {}
}
