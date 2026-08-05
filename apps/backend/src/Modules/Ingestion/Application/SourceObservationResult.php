<?php

declare(strict_types=1);

namespace Atlas\Modules\Ingestion\Application;

final readonly class SourceObservationResult
{
    public function __construct(
        public string $observationId,
        public string $artifactId,
        public string $sourceRevisionId,
        public string $entityId,
        public string $entityRevisionId,
        public bool $replayed,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'observation_id' => $this->observationId,
            'artifact_id' => $this->artifactId,
            'source_revision_id' => $this->sourceRevisionId,
            'entity_id' => $this->entityId,
            'entity_revision_id' => $this->entityRevisionId,
        ];
    }
}
