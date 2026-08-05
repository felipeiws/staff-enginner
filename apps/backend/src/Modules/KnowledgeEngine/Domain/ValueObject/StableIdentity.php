<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Domain\ValueObject;

use InvalidArgumentException;

/** Natural key that survives revisions of an entity. */
final readonly class StableIdentity
{
    public function __construct(public string $entityType, public string $externalNaturalKey)
    {
        if (trim($entityType) === '' || trim($externalNaturalKey) === '') {
            throw new InvalidArgumentException('Tipo e chave natural estável são obrigatórios.');
        }
    }
}
