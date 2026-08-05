<?php

declare(strict_types=1);

namespace Atlas\Modules\Plugins\Infrastructure;

use Atlas\Modules\Plugins\Domain\IsolatedPluginExecution;
use Atlas\Modules\Plugins\Domain\IsolatedPluginExecutor;
use Atlas\Modules\Plugins\Domain\PluginOperationResult;
use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;

/** Dispatches to the separately deployed container control plane; never starts a process in Laravel. */
final readonly class ContainerControlPlanePluginExecutor implements IsolatedPluginExecutor
{
    public function __construct(private HttpFactory $http) {}

    public function execute(IsolatedPluginExecution $execution): PluginOperationResult
    {
        $endpoint = (string) config('plugins.runtime.control_plane_url', '');
        if ($endpoint === '') {
            throw new RuntimeException("Executor isolado indisponível para {$execution->pluginId}.");
        }
        $response = $this->http->baseUrl($endpoint)->acceptJson()->timeout($execution->policy->timeoutSeconds)->post('/v1/executions', [
            'plugin_id' => $execution->pluginId,
            'operation' => $execution->operation,
            'context' => ['correlation_id' => $execution->context->correlationId, 'deadline_at' => $execution->context->deadlineAt, 'idempotency_key' => $execution->context->idempotencyKey, 'scope' => $execution->context->scope],
            'input' => $execution->input,
            'sandbox' => ['egress_allowlist' => $execution->policy->egressAllowlist, 'ephemeral_filesystem' => true, 'cpu_millicores' => $execution->policy->cpuMillicores, 'memory_mebibytes' => $execution->policy->memoryMebibytes, 'max_payload_bytes' => $execution->policy->maxPayloadBytes],
            'secret_references' => $execution->secretReferences,
        ]);
        if ($response->failed()) {
            throw new RuntimeException("Executor isolado recusou {$execution->pluginId}: HTTP {$response->status()}.");
        }
        $body = $response->json();
        if (! is_array($body) || ! isset($body['observations'], $body['evidence']) || ! is_array($body['observations']) || ! is_array($body['evidence'])) {
            throw new RuntimeException('Executor isolado retornou resposta inválida.');
        }

        return new PluginOperationResult($body['observations'], $body['evidence']);
    }
}
