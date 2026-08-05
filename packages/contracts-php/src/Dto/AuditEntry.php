<?php

declare(strict_types=1);

namespace Atlas\Contracts\Dto;

final readonly class AuditEntry
{
    public function __construct(
        public string $schemaVersion,
        public string $auditEntryId,
        public string $occurredAt,
        public string $action,
        public Actor $actor,
        public mixed $scope,
        public Subject $subject,
        public string $reason,
        public string $correlationId,
        public Command $command,
        public mixed $additionalProperties = [],
    ) {}
}
