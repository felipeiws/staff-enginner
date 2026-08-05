<?php

declare(strict_types=1);

namespace Atlas\Contracts\Dto;

use Atlas\Contracts\Enum\KnowledgeLifecycleState;

final readonly class KnowledgeEntity
{
    public function __construct(
        public string $schemaVersion,
        public string $entityId,
        public string $entityType,
        public string $stableKey,
        public KnowledgeLifecycleState $lifecycleState,
        public Provenance $provenance,
        public Command $command,
        public mixed $additionalProperties = [],
    ) {}
}
