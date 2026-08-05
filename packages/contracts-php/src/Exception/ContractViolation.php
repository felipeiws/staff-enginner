<?php

declare(strict_types=1);

namespace Atlas\Contracts\Exception;

use RuntimeException;

final class ContractViolation extends RuntimeException
{
    /** @param list<ContractViolationDetail> $violations */
    public function __construct(
        public readonly string $contract,
        public readonly array $violations,
    ) {
        parent::__construct(sprintf('O contrato "%s" é inválido.', $contract));
    }
}
