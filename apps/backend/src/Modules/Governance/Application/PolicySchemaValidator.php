<?php

declare(strict_types=1);

namespace Atlas\Modules\Governance\Application;

use InvalidArgumentException;
use Opis\JsonSchema\Validator;

/** Validates the parsed YAML against the v1 governance-policy contract. */
final class PolicySchemaValidator
{
    /** @param array<string, mixed> $document */
    public function validate(array $document): void
    {
        $schemaPath = dirname(__DIR__, 6).'/packages/contracts/schemas/v1/governance-policy.json';
        $schema = json_decode((string) file_get_contents($schemaPath));
        $instance = json_decode(json_encode($document, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
        $result = (new Validator)->validate($instance, $schema);
        if (! $result->isValid()) {
            $error = $result->error();
            throw new InvalidArgumentException('Documento de políticas não atende ao schema v1: '.($error?->message() ?? 'erro de validação.'));
        }
        if (($document['schema_version'] ?? null) !== '1.0.0' || ! isset($document['policies']) || ! is_array($document['policies']) || $document['policies'] === []) {
            throw new InvalidArgumentException('Documento de políticas inválido: schema_version 1.0.0 e policies não vazio são obrigatórios.');
        }
        foreach ($document['policies'] as $index => $policy) {
            if (! is_array($policy)) {
                throw new InvalidArgumentException("Política #{$index} deve ser um mapa YAML.");
            }
            foreach (['policy_key', 'version', 'name', 'priority', 'applies_to', 'outcome'] as $required) {
                if (! array_key_exists($required, $policy)) {
                    throw new InvalidArgumentException("Política #{$index} não contém {$required}.");
                }
            }
            if (! is_string($policy['policy_key']) || ! is_int($policy['version']) || $policy['version'] < 1 || ! is_string($policy['name']) || ! is_int($policy['priority']) || ! is_array($policy['applies_to']) || ! is_array($policy['outcome'])) {
                throw new InvalidArgumentException("Política #{$index} não atende aos tipos do schema v1.");
            }
            $action = $policy['outcome']['action'] ?? null;
            if (! in_array($action, ['publish', 'review', 'block'], true)) {
                throw new InvalidArgumentException("Política #{$index} deve definir outcome.action como publish, review ou block.");
            }
            $confidence = $policy['applies_to']['confidence'] ?? null;
            if ($confidence !== null && (! is_array($confidence) || (isset($confidence['min']) && (! is_numeric($confidence['min']) || $confidence['min'] < 0 || $confidence['min'] > 1)) || (isset($confidence['max']) && (! is_numeric($confidence['max']) || $confidence['max'] < 0 || $confidence['max'] > 1)) || (isset($confidence['min'], $confidence['max']) && $confidence['min'] > $confidence['max']))) {
                throw new InvalidArgumentException("Política #{$index} possui intervalo de confiança inválido.");
            }
        }
    }
}
