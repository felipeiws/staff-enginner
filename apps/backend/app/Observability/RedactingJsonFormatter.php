<?php

declare(strict_types=1);

namespace App\Observability;

use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;

final class RedactingJsonFormatter extends JsonFormatter
{
    public function format(LogRecord $record): string
    {
        $context = $this->redact($record->context);
        $record = $record->with(context: $context);

        return parent::format($record);
    }

    /** @param array<string, mixed> $context @return array<string, mixed> */
    private function redact(array $context): array
    {
        foreach ($context as $key => $value) {
            if (preg_match('/(authorization|token|secret|password|api[_-]?key|content|body|prompt)/i', (string) $key) === 1) {
                $context[$key] = '[REDACTED]';
            } elseif (is_string($value) && mb_strlen($value) > 512) {
                $context[$key] = mb_substr($value, 0, 512).'…[truncated]';
            } elseif (is_array($value)) {
                $context[$key] = $this->redact($value);
            }
        }

        return $context;
    }
}
