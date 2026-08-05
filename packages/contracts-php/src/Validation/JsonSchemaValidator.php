<?php

declare(strict_types=1);

namespace Atlas\Contracts\Validation;

use Atlas\Contracts\Exception\ContractViolation;
use Atlas\Contracts\Exception\ContractViolationDetail;
use JsonException;
use Opis\JsonSchema\Validator;

final class JsonSchemaValidator
{
    private readonly Validator $validator;

    public function __construct(?Validator $validator = null)
    {
        $this->validator = $validator ?? new Validator();
    }

    /** @param array<string, mixed> $data @throws ContractViolation */
    public function assertValid(string $contract, array $data): void
    {
        try {
            /** Opis trabalha com objetos JSON; a conversão preserva arrays aninhados. */
            $instance = json_decode(json_encode($this->normalizeObjectFields($contract, $data), JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
            $schema = json_decode(
                file_get_contents($this->schemaPath($contract)) ?: throw new JsonException('Schema ausente.'),
                false,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new ContractViolation($contract, [new ContractViolationDetail('schema', $exception->getMessage(), '')]);
        }

        if (!is_object($schema)) {
            throw new ContractViolation($contract, [new ContractViolationDetail('schema', 'Schema inválido.', '')]);
        }

        $result = $this->validator->validate($instance, $schema);
        if ($result->isValid()) {
            return;
        }

        $error = $result->error();
        throw new ContractViolation($contract, [new ContractViolationDetail(
            $error?->keyword() ?? 'validation',
            $error?->message() ?? 'Instância inválida.',
            $error === null ? '' : '/' . implode('/', $error->data()->fullPath()),
            $error === null ? [] : $this->arguments($error->args()),
        )]);
    }

    private function schemaPath(string $contract): string
    {
        return dirname(__DIR__, 2) . '/resources/schemas/v1/' . $contract . '.json';
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeObjectFields(string $contract, array $data): array
    {
        $objectFields = match ($contract) {
            'source-observation' => ['metadata'],
            'assertion', 'audit-entry', 'domain-event' => ['scope', 'payload'],
            'plugin-manifest' => ['configurations', 'input_schema', 'output_schema'],
            default => [],
        };
        foreach ($objectFields as $field) {
            if (($data[$field] ?? null) === []) {
                $data[$field] = (object) [];
            }
        }
        return $data;
    }

    private function arguments(mixed $arguments): mixed
    {
        if (!is_array($arguments)) {
            return [];
        }
        return array_filter($arguments, static fn (mixed $key): bool => is_string($key), ARRAY_FILTER_USE_KEY);
    }
}
