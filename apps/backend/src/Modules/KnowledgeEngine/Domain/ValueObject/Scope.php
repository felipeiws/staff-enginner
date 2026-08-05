<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Domain\ValueObject;

use InvalidArgumentException;

/** Immutable, namespaced context used before a graph record can be recovered. */
final readonly class Scope
{
    /** @param array<string, mixed> $values */
    public function __construct(public array $values)
    {
        if ($values === []) {
            throw new InvalidArgumentException('Escopo não pode ser vazio.');
        }
        foreach ($values as $key => $value) {
            if (trim($key) === '' || (! is_scalar($value) && ! is_array($value) && $value !== null)) {
                throw new InvalidArgumentException('Escopo contém um valor inválido.');
            }
        }
    }
}
