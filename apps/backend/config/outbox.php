<?php

declare(strict_types=1);

return [
    'queue' => env('OUTBOX_QUEUE', 'atlas-events'),
    'batch_size' => (int) env('OUTBOX_BATCH_SIZE', 100),
    'max_attempts' => (int) env('OUTBOX_MAX_ATTEMPTS', 8),
    'base_backoff_seconds' => (int) env('OUTBOX_BASE_BACKOFF_SECONDS', 5),
    'processing_timeout_seconds' => (int) env('OUTBOX_PROCESSING_TIMEOUT_SECONDS', 300),
];
