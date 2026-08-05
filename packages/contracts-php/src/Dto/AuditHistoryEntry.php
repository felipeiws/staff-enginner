<?php

declare(strict_types=1);

namespace Atlas\Contracts\Dto;

/** Read model used by an entity's Histórico tab. */
final readonly class AuditHistoryEntry
{
    public function __construct(
        public string $occurredAt,
        public string $author,
        public string $description,
        public string $actorType,
        public string $color,
    ) {}
}
