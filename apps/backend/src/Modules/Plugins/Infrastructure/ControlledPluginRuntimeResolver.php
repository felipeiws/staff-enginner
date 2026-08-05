<?php

declare(strict_types=1);

namespace Atlas\Modules\Plugins\Infrastructure;

use Atlas\Modules\Plugins\Application\PluginRegistry;
use Atlas\Modules\Plugins\Domain\IsolatedPluginExecution;
use Atlas\Modules\Plugins\Domain\IsolatedPluginExecutor;
use Atlas\Modules\Plugins\Domain\PluginExecutionContext;
use Atlas\Modules\Plugins\Domain\PluginIsolationPolicy;
use Atlas\Modules\Plugins\Domain\PluginOperationResult;
use Atlas\Modules\Plugins\Domain\PluginRuntime;
use Atlas\Modules\Plugins\Domain\PluginRuntimeResolver;
use DomainException;

final readonly class ControlledPluginRuntimeResolver implements PluginRuntimeResolver
{
    public function __construct(private PluginRegistry $registry, private IsolatedPluginExecutor $executor) {}

    public function forPlugin(string $pluginId): PluginRuntime
    {
        $registration = $this->registry->byId($pluginId);
        $policy = PluginIsolationPolicy::fromManifest($registration->manifest);
        $references = $this->secretReferences($registration->configuration ?? []);

        return new class ($pluginId, $policy, $references, $this->executor) implements PluginRuntime {
            /** @param list<string> $references */
            public function __construct(private string $pluginId, private PluginIsolationPolicy $policy, private array $references, private IsolatedPluginExecutor $executor) {}
            public function discoverIncremental(PluginExecutionContext $context, array $cursor): PluginOperationResult
            {
                return $this->call('discover', $context, $cursor);
            }
            public function fetchArtifact(PluginExecutionContext $context, array $reference): PluginOperationResult
            {
                return $this->call('fetch_artifact', $context, $reference);
            }
            public function parse(PluginExecutionContext $context, array $artifact): PluginOperationResult
            {
                return $this->call('parse', $context, $artifact);
            }
            public function healthCheck(PluginExecutionContext $context): PluginOperationResult
            {
                return $this->call('health_check', $context, []);
            }
            public function shutdown(PluginExecutionContext $context): void
            {
                $this->call('shutdown', $context, []);
            }
            /** @param array<string, mixed> $input */
            private function call(string $operation, PluginExecutionContext $context, array $input): PluginOperationResult
            {
                $encoded = json_encode($input, JSON_THROW_ON_ERROR);
                if (strlen($encoded) > $this->policy->maxPayloadBytes) {
                    throw new DomainException('Payload excede o limite declarado pelo plugin.');
                }
                return $this->executor->execute(new IsolatedPluginExecution($this->pluginId, $operation, $context, $input, $this->policy, $this->references));
            }
        };
    }

    /**
     * @param array<string, mixed> $configuration
     * @return list<string>
     */
    private function secretReferences(array $configuration): array
    {
        $references = [];
        array_walk_recursive($configuration, function (mixed $value, string $key) use (&$references): void {
            if (preg_match('/(?:secret|token|password|credential|authorization|api[_-]?key)/i', $key) === 1) {
                if (! is_string($value) || ! str_starts_with($value, 'secret://')) {
                    throw new DomainException('Configuração de segredo deve conter somente referência secret://.');
                }
                $references[] = $value;
            }
        });

        return $references;
    }
}
