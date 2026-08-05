<?php

declare(strict_types=1);

return [
    'host' => env('TYPESENSE_HOST', '127.0.0.1'),
    'port' => (int) env('TYPESENSE_PORT', 8108),
    'protocol' => env('TYPESENSE_PROTOCOL', 'http'),
    'api_key' => env('TYPESENSE_API_KEY', 'atlas_typesense_local_key'),
    'alias' => env('TYPESENSE_KNOWLEDGE_ALIAS', 'knowledge_published'),
    'schema_version' => 2,
    'rrf_k' => (int) env('SEARCH_RRF_K', 60),
    'embedding_dimensions' => (int) env('SEARCH_EMBEDDING_DIMENSIONS', 1536),
    'embedding_model' => env('SEARCH_EMBEDDING_MODEL', 'text-embedding-3-small'),
    'embedding_model_version' => env('SEARCH_EMBEDDING_MODEL_VERSION', '2024-01-25'),
    'evaluation' => [
        'k' => (int) env('SEARCH_EVALUATION_K', 10),
        // Deltas allowed against the committed baseline; p95 is milliseconds.
        'regression_thresholds' => [
            'recall_at_k' => (float) env('SEARCH_EVALUATION_RECALL_DELTA', 0.05),
            'mrr' => (float) env('SEARCH_EVALUATION_MRR_DELTA', 0.05),
            'ndcg_at_k' => (float) env('SEARCH_EVALUATION_NDCG_DELTA', 0.05),
            'citation_coverage' => (float) env('SEARCH_EVALUATION_CITATION_DELTA', 0.05),
            'zero_result_rate' => (float) env('SEARCH_EVALUATION_ZERO_RESULT_DELTA', 0.05),
            'latency_p95_ms' => (float) env('SEARCH_EVALUATION_P95_DELTA_MS', 500),
        ],
    ],
];
