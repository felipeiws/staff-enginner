<?php

declare(strict_types=1);

return [
    'platform_version' => env('PLUGINS_PLATFORM_VERSION', '1.0.0'),
    'allowed_permissions' => ['artifact.read', 'network.egress', 'secrets.read', 'webhook.receive'],
    'circuit_breaker' => ['failure_threshold' => (int) env('PLUGINS_FAILURE_THRESHOLD', 3), 'cooldown_seconds' => (int) env('PLUGINS_CIRCUIT_COOLDOWN_SECONDS', 300)],
    'webhooks' => ['requests_per_minute' => (int) env('PLUGINS_WEBHOOKS_PER_MINUTE', 60)],
    'runtime' => ['control_plane_url' => env('PLUGINS_CONTROL_PLANE_URL', '')],
];
