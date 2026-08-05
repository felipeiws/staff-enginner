<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Application;

/** Removes secrets and bounds snapshots before they can enter an immutable record. */
final class AuditPayloadRedactor
{
    private const MAX_DEPTH = 6;

    private const MAX_ITEMS = 50;

    private const MAX_STRING_LENGTH = 1024;

    public function redact(mixed $payload): mixed
    {
        return $this->sanitize($payload, 0, null);
    }

    private function sanitize(mixed $value, int $depth, ?string $key): mixed
    {
        if ($key !== null && preg_match('/secret|password|token|api[_-]?key|authorization|credential|cookie|private[_-]?key/i', $key) === 1) {
            return '[REDACTED]';
        }
        if ($key !== null && preg_match('/(^|_)(content|body|text|prompt|message|instruction)(_|$)/i', $key) === 1) {
            return is_string($value) ? '[OMITTED: content of '.strlen($value).' bytes]' : '[OMITTED: content]';
        }
        if ($depth >= self::MAX_DEPTH) {
            return '[TRUNCATED: maximum depth]';
        }
        if (is_string($value)) {
            return strlen($value) <= self::MAX_STRING_LENGTH ? $value : substr($value, 0, self::MAX_STRING_LENGTH).'[TRUNCATED: '.strlen($value).' bytes]';
        }
        if (! is_array($value)) {
            return $value;
        }

        $result = [];
        foreach (array_slice($value, 0, self::MAX_ITEMS, true) as $childKey => $childValue) {
            $result[$childKey] = $this->sanitize($childValue, $depth + 1, is_string($childKey) ? $childKey : null);
        }
        if (count($value) > self::MAX_ITEMS) {
            $result['__truncated__'] = '[TRUNCATED: '.(count($value) - self::MAX_ITEMS).' items]';
        }

        return $result;
    }
}
