<?php

declare(strict_types=1);

namespace Atlas\Modules\Copilot\Application;

final readonly class CopilotRequest
{
    /**
     * @param  list<array{content:string,classification:string,origin:string,revision:string,method:string,confidence:float}>  $context
     * @param  array<string,mixed>  $reasoningTrace
     * @param  list<array<string,mixed>>  $candidateSources
     */
    public function __construct(
        public string $idempotencyKey,
        public string $actorId,
        public string $correlationId,
        public string $systemPrompt,
        public string $question,
        public array $context,
        public ?int $maxOutputTokens = null,
        public ?string $revisionId = null,
        public array $reasoningTrace = [],
        public array $candidateSources = [],
    ) {}
}
