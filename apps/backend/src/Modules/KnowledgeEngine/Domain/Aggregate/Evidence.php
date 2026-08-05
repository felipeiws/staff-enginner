<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Domain\Aggregate;

use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Confidence;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Identifier;
use InvalidArgumentException;

final readonly class Evidence
{
    public function __construct(public Identifier $id, public Identifier $assertionId, public EvidenceKind $kind, public Identifier $pointerId, public Confidence $weight)
    {
        if ($weight->value === 0.0) {
            throw new InvalidArgumentException('Peso de evidência deve ser maior que zero.');
        }
    }
}
