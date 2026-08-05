<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Domain\Aggregate;

use Atlas\Contracts\Enum\GraphPredicate;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Confidence;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\DerivationMethod;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Identifier;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\LifecycleState;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Scope;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class Assertion
{
    /** @param array<string, scalar|array<array-key, scalar|null>|null>|null $literalValue */
    public function __construct(
        public Identifier $id,
        public Identifier $subjectRevisionId,
        public GraphPredicate $predicate,
        public ?Identifier $objectRevisionId,
        public ?array $literalValue,
        public Scope $scope,
        public Confidence $confidence,
        public DerivationMethod $method,
        public string $actorId,
        public string $actorType,
        public LifecycleState $lifecycleState,
        public ?DateTimeImmutable $validFrom = null,
        public ?DateTimeImmutable $validTo = null,
    ) {
        if (($objectRevisionId === null) === ($literalValue === null)) {
            throw new InvalidArgumentException('Assertion deve ter exatamente um objeto ou valor literal.');
        }
        if ($validFrom !== null && $validTo !== null && $validTo < $validFrom) {
            throw new InvalidArgumentException('Fim de validade não pode preceder o início.');
        }
        if (trim($actorId) === '' || trim($actorType) === '') {
            throw new InvalidArgumentException('Ator da assertion é obrigatório.');
        }
    }

    public function transitionTo(LifecycleState $state, ?DateTimeImmutable $validTo = null): self
    {
        $this->lifecycleState->transitionTo($state);

        return new self($this->id, $this->subjectRevisionId, $this->predicate, $this->objectRevisionId, $this->literalValue, $this->scope, $this->confidence, $this->method, $this->actorId, $this->actorType, $state, $this->validFrom, $validTo ?? $this->validTo);
    }
}
