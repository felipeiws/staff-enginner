<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Domain\Aggregate;

use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Identifier;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Scope;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\StableIdentity;
use InvalidArgumentException;

final readonly class KnowledgeEntity
{
    public function __construct(
        public Identifier $id,
        public StableIdentity $identity,
        public string $family,
        public Scope $scope,
        public string $ownerId,
        public string $ownerType = 'human',
    ) {
        if (trim($family) === '' || trim($ownerId) === '' || trim($ownerType) === '') {
            throw new InvalidArgumentException('Família e proprietário da entidade são obrigatórios.');
        }
    }
}
