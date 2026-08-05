<?php

declare(strict_types=1);

namespace Atlas\Modules\Ingestion\Application;

final readonly class VisualArtifactResult
{
    public function __construct(public string $artifactId, public string $revisionId, public string $screenEntityId, public string $screenRevisionId, public bool $replayed) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['artifact_id' => $this->artifactId, 'revision_id' => $this->revisionId, 'screen_entity_id' => $this->screenEntityId, 'screen_revision_id' => $this->screenRevisionId, 'replayed' => $this->replayed];
    }
}
