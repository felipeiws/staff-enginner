<?php

declare(strict_types=1);

namespace Atlas\Modules\Governance\Application;

use DateTimeImmutable;

/** Immutable, provenance-bearing input to the declarative policy evaluator. */
final readonly class PolicyCandidate
{
    /**
     * @param  array<string, scalar|list<scalar>>  $scope
     * @param  list<string>  $ownerIds
     * @param  list<string>  $ownerRoles
     */
    public function __construct(
        public string $sourceType,
        public string $entityType,
        public string $classification,
        public string $derivationMethod,
        public float $confidence,
        public array $scope = [],
        public array $ownerIds = [],
        public array $ownerRoles = [],
        public string $impactLevel = 'normal',
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $validFrom = null,
    ) {}
}
