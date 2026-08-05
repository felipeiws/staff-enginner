<?php

declare(strict_types=1);

namespace Atlas\Contracts\Exception;

final readonly class ContractViolationDetail
{
    public function __construct(
        public string $keyword,
        public string $message,
        public string $dataPath,
        public mixed $arguments = [],
    ) {}
}
