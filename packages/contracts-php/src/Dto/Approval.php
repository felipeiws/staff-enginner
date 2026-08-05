<?php

declare(strict_types=1);

namespace Atlas\Contracts\Dto;

final readonly class Approval
{
    public function __construct(
        public string $schemaVersion,
        public string $approvalId,
        public Subject $subject,
        public string $decision,
        public string $decidedAt,
        public Actor $decidedBy,
        public string $reason,
        public Provenance $provenance,
        public Command $command,
        public mixed $additionalProperties = [],
    ) {}
}
