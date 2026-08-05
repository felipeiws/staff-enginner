<?php

declare(strict_types=1);

return [
    'inline_content_limit_bytes' => (int) env('INGESTION_INLINE_CONTENT_LIMIT_BYTES', 262_144),
    'object_storage_disk' => env('INGESTION_OBJECT_STORAGE_DISK', 's3'),
    'object_storage_prefix' => env('INGESTION_OBJECT_STORAGE_PREFIX', 'ingestion/content'),
    'visual_object_storage_prefix' => env('INGESTION_VISUAL_OBJECT_STORAGE_PREFIX', 'ingestion/visual'),
    'extractor_name' => 'source-observation-receiver',
    'extractor_version' => '1.0.0',
];
