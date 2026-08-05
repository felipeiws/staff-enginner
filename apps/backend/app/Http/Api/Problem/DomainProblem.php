<?php

declare(strict_types=1);

namespace App\Http\Api\Problem;

use RuntimeException;

final class DomainProblem extends RuntimeException
{
    /** @param array<string, mixed> $errors */
    public function __construct(public readonly int $status, public readonly string $domainCode, string $detail, public readonly array $errors = [])
    {
        parent::__construct($detail);
    }
}
