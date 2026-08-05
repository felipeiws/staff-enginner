<?php

declare(strict_types=1);

namespace Atlas\Contracts\Dto;

final readonly class SourceObservation
{
    public function __construct(
        public string $schemaVersion,
        public string $observationId,
        public string $externalId,
        public string $sourceType,
        public string $uri,
        public string $revision,
        public string $observedAt,
        public ?string $content,
        public ?ContentReference $contentRef,
        public string $checksum,
        public mixed $metadata,
        public string $classification,
        public Command $command,
        public mixed $additionalProperties = [],
    ) {}
}
