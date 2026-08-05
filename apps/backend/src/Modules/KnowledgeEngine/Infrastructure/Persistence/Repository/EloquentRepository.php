<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Infrastructure\Persistence\Repository;

use Atlas\Modules\KnowledgeEngine\Domain\Repository\UnitOfWork;
use DateTimeImmutable;
use DateTimeInterface;
use LogicException;

abstract readonly class EloquentRepository
{
    public function __construct(protected UnitOfWork $unitOfWork) {}

    protected function assertWritingTransaction(): void
    {
        if (! $this->unitOfWork->isActive()) {
            throw new LogicException('Gravações do grafo exigem Unit of Work ativo.');
        }
    }

    /** @return array<string, mixed> */
    protected function arrayValue(mixed $value, string $field): array
    {
        if (! is_array($value)) {
            throw new LogicException("Campo persistido inválido: {$field}.");
        }

        return $value;
    }

    protected function stringValue(mixed $value, string $field): string
    {
        if (! is_string($value)) {
            throw new LogicException("Campo persistido inválido: {$field}.");
        }

        return $value;
    }

    protected function nullableDateValue(mixed $value, string $field): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }
        if (is_string($value)) {
            return new DateTimeImmutable($value);
        }

        throw new LogicException("Campo persistido inválido: {$field}.");
    }
}
