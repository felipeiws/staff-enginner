<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Domain\ValueObject;

use InvalidArgumentException;

final readonly class DerivationMethod
{
    public function __construct(public string $value)
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException('Método de derivação é obrigatório.');
        }
    }

    public function isHumanDeclared(): bool
    {
        return $this->value === 'human_declared';
    }
}
