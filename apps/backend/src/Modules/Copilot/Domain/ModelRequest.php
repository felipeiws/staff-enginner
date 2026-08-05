<?php

declare(strict_types=1);

namespace Atlas\Modules\Copilot\Domain;

final readonly class ModelRequest
{
    /** @param list<array{role:string,content:string}> $messages */
    public function __construct(
        public string $executionId,
        public string $idempotencyKey,
        public string $model,
        public string $modelVersion,
        public float $temperature,
        public int $maxTokens,
        public string $systemPrompt,
        public array $messages,
        public int $timeoutSeconds,
        public int $retryAttempts,
    ) {}
}
