<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Domain\Aggregate;

use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Identifier;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Scope;
use InvalidArgumentException;

final readonly class Source
{
    public function __construct(
        public Identifier $id,
        public string $type,
        public ?string $externalSystem,
        public Scope $scope,
        public ?string $credentialReference,
        public string $classification,
        public bool $active = true,
    ) {
        if (trim($type) === '' || trim($classification) === '') {
            throw new InvalidArgumentException('Tipo e classificação da fonte são obrigatórios.');
        }
    }
}
