<?php

declare(strict_types=1);

namespace Atlas\Modules\Copilot\Domain;

final readonly class ModelResponse
{
    /** @param array{input_tokens:int,cache_creation_input_tokens:int,cache_read_input_tokens:int,output_tokens:int} $usage */
    public function __construct(public string $text, public array $usage, public string $stopReason, public string $providerRequestId) {}
}
