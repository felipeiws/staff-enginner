<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Application;

use Atlas\Modules\KnowledgeEngine\Domain\Aggregate\EvidenceKind;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Identifier;

final readonly class AssertionEvidenceInput
{
    public function __construct(public Identifier $id, public EvidenceKind $kind, public Identifier $pointerId, public float $weight) {}
}
