<?php

declare(strict_types=1);

namespace Atlas\Contracts\Dto;

/** An observed unanswered question; the question itself is retained as provenance. */
final readonly class KnowledgeGapOccurrence
{
    /** @param array<string,string> $scope */
    public function __construct(
        public string $question,
        public array $scope,
        public string $origin,
        public string $method,
        public float $confidence,
        public ?string $correlationId = null,
    ) {}
}
