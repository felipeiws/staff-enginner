<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Domain\ValueObject;

use InvalidArgumentException;

final readonly class Confidence
{
    public function __construct(public float $value)
    {
        if ($value < 0 || $value > 1) {
            throw new InvalidArgumentException('Confiança deve estar entre 0 e 1.');
        }
    }
}
