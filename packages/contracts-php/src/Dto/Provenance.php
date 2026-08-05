<?php

declare(strict_types=1);

namespace Atlas\Contracts\Dto;

final readonly class Provenance
{
    public function __construct(
        public string $sourceRevision,
        public string $method,
        public string $processedAt,
        public ?string $sourceObservationId = null,
        public ?float $confidence = null,
    ) {}
}
