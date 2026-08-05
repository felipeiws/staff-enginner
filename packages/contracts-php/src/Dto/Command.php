<?php

declare(strict_types=1);

namespace Atlas\Contracts\Dto;

final readonly class Command
{
    public function __construct(public string $idempotencyKey, public Actor $actor, public ?string $correlationId = null) {}
}
