<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Domain\Aggregate;

use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Confidence;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Identifier;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\LifecycleState;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class KnowledgeRevision
{
    /** @param array<string, mixed> $attributes */
    public function __construct(
        public Identifier $id,
        public Identifier $entityId,
        public int $version,
        public LifecycleState $lifecycleState,
        public string $title,
        public ?string $body,
        public array $attributes,
        public Identifier $sourceRevisionId,
        public Confidence $confidence,
        public ?DateTimeImmutable $publishedAt = null,
        public ?Identifier $supersededBy = null,
    ) {
        if ($version < 1 || trim($title) === '') {
            throw new InvalidArgumentException('Versão positiva e título são obrigatórios.');
        }
        if (($lifecycleState === LifecycleState::Superseded) !== ($supersededBy !== null)) {
            throw new InvalidArgumentException('Uma revisão superseded deve referenciar sua sucessora.');
        }
    }

    public function transitionTo(LifecycleState $state, ?DateTimeImmutable $publishedAt = null, ?Identifier $supersededBy = null): self
    {
        $this->lifecycleState->transitionTo($state);

        return new self($this->id, $this->entityId, $this->version, $state, $this->title, $this->body, $this->attributes, $this->sourceRevisionId, $this->confidence, $publishedAt ?? $this->publishedAt, $supersededBy);
    }
}
