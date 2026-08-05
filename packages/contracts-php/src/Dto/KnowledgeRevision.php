<?php

declare(strict_types=1);

namespace Atlas\Contracts\Dto;

use Atlas\Contracts\Enum\KnowledgeLifecycleState;

final readonly class KnowledgeRevision
{
    public function __construct(
        public string $schemaVersion,
        public string $revisionId,
        public string $entityId,
        public string $sourceObservationId,
        public string $sourceRevision,
        public KnowledgeLifecycleState $lifecycleState,
        public string $method,
        public float $confidence,
        public string $processedAt,
        public string $checksum,
        public mixed $contentRef,
        public Command $command,
        public mixed $additionalProperties = [],
    ) {}
}
