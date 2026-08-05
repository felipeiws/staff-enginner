<?php

declare(strict_types=1);

return [
    'provider' => env('COPILOT_MODEL_PROVIDER', 'anthropic'),
    // Pin the provider model; executions retain this complete snapshot.
    'model' => env('COPILOT_MODEL', 'claude-opus-4-1-20250805'),
    'model_version' => env('COPILOT_MODEL_VERSION', '2025-08-05'),
    'temperature' => (float) env('COPILOT_MODEL_TEMPERATURE', 0.0),
    'max_input_tokens' => (int) env('COPILOT_MAX_INPUT_TOKENS', 16000),
    'max_output_tokens' => (int) env('COPILOT_MAX_OUTPUT_TOKENS', 2048),
    'max_request_tokens' => (int) env('COPILOT_MAX_REQUEST_TOKENS', 18048),
    'period_token_limit' => (int) env('COPILOT_PERIOD_TOKEN_LIMIT', 200000),
    'period_cost_limit_usd' => (float) env('COPILOT_PERIOD_COST_LIMIT_USD', 25),
    'period_hours' => (int) env('COPILOT_PERIOD_HOURS', 24),
    'timeout_seconds' => (int) env('COPILOT_TIMEOUT_SECONDS', 30),
    'retry_attempts' => (int) env('COPILOT_RETRY_ATTEMPTS', 2),
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
        'api_version' => '2023-06-01',
        'cache_system_prompt' => (bool) env('COPILOT_ANTHROPIC_CACHE_SYSTEM_PROMPT', true),
        // USD per million tokens, including Anthropic's 5-minute cache write pricing.
        'pricing' => ['input' => 15.0, 'cache_write' => 18.75, 'cache_read' => 1.5, 'output' => 75.0],
    ],
];
