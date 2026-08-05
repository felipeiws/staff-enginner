<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Application;

use Atlas\Contracts\Enum\GraphPredicate;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Identifier;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Scope;

/** Command boundary for assertion creation; assertions are always introduced as candidates. */
final readonly class CreateAssertion
{
    /**
     * @param  array<string, scalar|array<array-key, scalar|null>|null>|null  $literalValue
     * @param  list<AssertionEvidenceInput>  $evidence
     */
    public function __construct(
        public Identifier $id,
        public RevisionReference $subject,
        public GraphPredicate $predicate,
        public ?RevisionReference $object,
        public ?array $literalValue,
        public Scope $scope,
        public string $method,
        public string $actorId,
        public string $actorType,
        public array $evidence,
        public float $declaredConfidence,
        public string $idempotencyKey,
        public ?string $correlationId = null,
    ) {}
}
