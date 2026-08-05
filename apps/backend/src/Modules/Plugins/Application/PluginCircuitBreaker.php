<?php

declare(strict_types=1);

namespace Atlas\Modules\Plugins\Application;

use App\Observability\MetricRecorder;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Log\Logger;
use RuntimeException;

/** Per-plugin failure containment; it deliberately disables only the offending registration. */
final readonly class PluginCircuitBreaker
{
    public function __construct(private Cache $cache, private PluginRegistry $registry, private Logger $logger, private MetricRecorder $metrics) {}

    public function assertAvailable(string $pluginId): void
    {
        if ($this->cache->get($this->openKey($pluginId), false) === true) {
            throw new RuntimeException("Circuit breaker aberto para {$pluginId}.");
        }
    }

    public function success(string $pluginId): void
    {
        $this->cache->forget($this->failuresKey($pluginId));
    }

    public function failure(string $pluginId, string $correlationId, \Throwable $error): void
    {
        $threshold = (int) config('plugins.circuit_breaker.failure_threshold', 3);
        $failures = (int) $this->cache->increment($this->failuresKey($pluginId));
        $this->cache->put($this->failuresKey($pluginId), $failures, now()->addSeconds((int) config('plugins.circuit_breaker.cooldown_seconds', 300)));
        $this->logger->warning('plugin.execution_failed', ['plugin_id' => $pluginId, 'correlation_id' => $correlationId, 'failure_count' => $failures, 'error_class' => $error::class]);
        $this->metrics->observe('atlas_plugin_errors_total', 1, ['plugin_id' => $pluginId]);
        if ($failures < $threshold) {
            return;
        }
        $this->cache->put($this->openKey($pluginId), true, now()->addSeconds((int) config('plugins.circuit_breaker.cooldown_seconds', 300)));
        $registration = $this->registry->byId($pluginId);
        if ($registration->state->value === 'enabled') {
            $this->registry->disable($pluginId, $correlationId, "circuit-breaker:{$correlationId}");
        }
        $this->logger->error('plugin.circuit_opened', ['plugin_id' => $pluginId, 'correlation_id' => $correlationId, 'metric' => 'plugin_circuit_opened_total']);
    }

    private function failuresKey(string $pluginId): string
    {
        return "plugins:circuit:{$pluginId}:failures";
    }
    private function openKey(string $pluginId): string
    {
        return "plugins:circuit:{$pluginId}:open";
    }
}
