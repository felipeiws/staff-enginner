<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Domain\Event;

/** Validated contract envelope that must accompany one atomic write. */
final readonly class DomainEvent
{
    /** @param array<string, mixed> $payload */
    public function __construct(public string $aggregate, public array $payload) {}
}
