<?php

declare(strict_types=1);

namespace Atlas\Contracts\Dto;

final readonly class ContentReference
{
    public function __construct(public string $uri, public string $checksum, public ?string $mediaType = null) {}
}
