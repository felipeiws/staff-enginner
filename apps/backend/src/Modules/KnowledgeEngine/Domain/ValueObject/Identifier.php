<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Domain\ValueObject;

use InvalidArgumentException;

/** Opaque, stable identifier. UUIDs are required at the persistence boundary. */
final readonly class Identifier
{
    public function __construct(public string $value)
    {
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value)) {
            throw new InvalidArgumentException('Identificador deve ser um UUID válido.');
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
