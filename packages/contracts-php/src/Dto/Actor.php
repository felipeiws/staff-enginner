<?php

declare(strict_types=1);

namespace Atlas\Contracts\Dto;

final readonly class Actor
{
    public function __construct(public string $actorId, public string $actorType, public ?string $displayName = null) {}
}
