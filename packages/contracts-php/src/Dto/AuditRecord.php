<?php

declare(strict_types=1);

namespace Atlas\Contracts\Dto;

/** Command accepted by the append-only audit boundary. Snapshots are sanitized by its implementation. */
final readonly class AuditRecord
{
    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed>|null $beforePayload
     * @param array<string, mixed>|null $afterPayload
     */
    public function __construct(
        public Actor $actor,
        public string $action,
        public string $subjectKind,
        public string $subjectId,
        public ?string $entityId,
        public ?string $entityRevisionId,
        public ?string $sourceRevisionId,
        public ?string $decision,
        public string $reason,
        public ?string $correlationId,
        public array $scope,
        public ?array $beforePayload,
        public ?array $afterPayload,
        public ?string $classification,
        public string $method,
        public ?float $confidence,
    ) {}
}
