<?php

declare(strict_types=1);

namespace Atlas\Contracts\Dto;

final readonly class Evidence
{
    public function __construct(
        public string $schemaVersion,
        public string $evidenceId,
        public string $kind,
        public string $pointer,
        public ?Offsets $offsets,
        public string $checksum,
        public Provenance $provenance,
        public Command $command,
        public mixed $additionalProperties = [],
    ) {}
}
