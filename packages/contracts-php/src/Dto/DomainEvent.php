<?php

declare(strict_types=1);

namespace Atlas\Contracts\Dto;

final readonly class DomainEvent
{
    public function __construct(
        public string $eventId,
        public string $eventType,
        public string $schemaVersion,
        public string $occurredAt,
        public string $correlationId,
        public Actor $actor,
        public mixed $scope,
        public EntityReference $entity,
        public mixed $payload,
        public Command $command,
        public mixed $additionalProperties = [],
    ) {}
}
