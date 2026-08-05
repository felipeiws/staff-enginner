<?php

declare(strict_types=1);

namespace Atlas\Modules\Plugins\Application;

use Illuminate\Cache\RateLimiter;
use RuntimeException;

/** Verifies a replay-resistant HMAC before a webhook is admitted to a plugin runtime. */
final readonly class PluginWebhookVerifier
{
    public function __construct(private RateLimiter $rateLimiter) {}

    public function assertValid(string $pluginId, string $body, string $signature, string $secret, string $remoteAddress): void
    {
        $key = "plugin-webhook:{$pluginId}:{$remoteAddress}";
        $max = (int) config('plugins.webhooks.requests_per_minute', 60);
        if ($this->rateLimiter->tooManyAttempts($key, $max)) {
            throw new RuntimeException('Webhook excedeu o limite de requisições.');
        }
        $this->rateLimiter->hit($key, 60);
        $expected = 'sha256=' . hash_hmac('sha256', $body, $secret);
        if (! hash_equals($expected, $signature)) {
            throw new RuntimeException('Assinatura de webhook inválida.');
        }
    }
}
