<?php

declare(strict_types=1);

namespace Atlas\Contracts\Dto;

final readonly class Subject
{
    public function __construct(public string $kind, public string $id, public ?string $revisionId = null) {}
}
