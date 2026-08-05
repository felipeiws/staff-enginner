<?php

declare(strict_types=1);

namespace Atlas\Contracts\Dto;

final readonly class EntityReference
{
    public function __construct(public string $entityId, public ?string $revisionId = null) {}
}
