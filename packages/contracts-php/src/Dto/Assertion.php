<?php

declare(strict_types=1);

namespace Atlas\Contracts\Dto;

use Atlas\Contracts\Enum\GraphPredicate;
use Atlas\Contracts\Enum\KnowledgeLifecycleState;

final readonly class Assertion
{
    public function __construct(
        public string $schemaVersion,
        public string $assertionId,
        public EntityReference $subject,
        public GraphPredicate $predicate,
        public ?EntityReference $object,
        public mixed $value,
        public mixed $scope,
        public float $confidence,
        public string $method,
        public Actor $author,
        public KnowledgeLifecycleState $lifecycleState,
        public Provenance $provenance,
        public mixed $evidenceIds,
        public Command $command,
        public mixed $additionalProperties = [],
    ) {}
}
