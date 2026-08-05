<?php

declare(strict_types=1);

namespace Atlas\Contracts\Dto;

final readonly class Offsets
{
    public function __construct(public ?int $start = null, public ?int $end = null, public ?string $unit = null) {}
}
