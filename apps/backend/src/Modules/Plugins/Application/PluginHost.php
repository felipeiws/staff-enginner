<?php

declare(strict_types=1);

namespace Atlas\Modules\Plugins\Application;

use Atlas\Modules\Plugins\Domain\PluginExecutionContext;
use Atlas\Modules\Plugins\Domain\PluginLifecycleState;
use Atlas\Modules\Plugins\Domain\PluginOperationResult;
use Atlas\Modules\Plugins\Domain\PluginRuntime;
use Atlas\Modules\Plugins\Domain\PluginRuntimeResolver;
use RuntimeException;

final readonly class PluginHost
{
    public function __construct(private PluginRegistry $registry, private PluginRuntimeResolver $runtimes, private PluginCircuitBreaker $circuit) {}

    /** @param array<string, mixed> $cursor */
    public function discoverIncremental(string $pluginId, PluginExecutionContext $context, array $cursor): PluginOperationResult
    {
        return $this->runtime($pluginId)->discoverIncremental($context, $cursor);
    }

    /** @param array<string, mixed> $reference */
    public function fetchArtifact(string $pluginId, PluginExecutionContext $context, array $reference): PluginOperationResult
    {
        return $this->runtime($pluginId)->fetchArtifact($context, $reference);
    }

    /** @param array<string, mixed> $artifact */
    public function parse(string $pluginId, PluginExecutionContext $context, array $artifact): PluginOperationResult
    {
        return $this->runtime($pluginId)->parse($context, $artifact);
    }

    public function healthCheck(string $pluginId, PluginExecutionContext $context): PluginOperationResult
    {
        return $this->runtime($pluginId)->healthCheck($context);
    }

    public function shutdown(string $pluginId, PluginExecutionContext $context): void
    {
        $this->runtime($pluginId)->shutdown($context);
    }

    private function runtime(string $pluginId): PluginRuntime
    {
        if ($this->registry->byId($pluginId)->state !== PluginLifecycleState::Enabled) {
            throw new RuntimeException('A execução exige plugin habilitado.');
        }

        $this->circuit->assertAvailable($pluginId);
        return new class ($this->runtimes->forPlugin($pluginId), $this->circuit, $pluginId) implements PluginRuntime {
            public function __construct(private PluginRuntime $runtime, private PluginCircuitBreaker $circuit, private string $pluginId) {}
            public function discoverIncremental(PluginExecutionContext $context, array $cursor): PluginOperationResult
            {
                return $this->call($context, fn(): PluginOperationResult => $this->runtime->discoverIncremental($context, $cursor));
            }
            public function fetchArtifact(PluginExecutionContext $context, array $reference): PluginOperationResult
            {
                return $this->call($context, fn(): PluginOperationResult => $this->runtime->fetchArtifact($context, $reference));
            }
            public function parse(PluginExecutionContext $context, array $artifact): PluginOperationResult
            {
                return $this->call($context, fn(): PluginOperationResult => $this->runtime->parse($context, $artifact));
            }
            public function healthCheck(PluginExecutionContext $context): PluginOperationResult
            {
                return $this->call($context, fn(): PluginOperationResult => $this->runtime->healthCheck($context));
            }
            public function shutdown(PluginExecutionContext $context): void
            {
                $this->call($context, function () use ($context): PluginOperationResult {
                    $this->runtime->shutdown($context);
                    return new PluginOperationResult([], []);
                });
            }
            /** @param callable(): PluginOperationResult $call */
            private function call(PluginExecutionContext $context, callable $call): PluginOperationResult
            {
                try {
                    $result = $call();
                    $this->circuit->success($this->pluginId);
                    return $result;
                } catch (\Throwable $error) {
                    $this->circuit->failure($this->pluginId, $context->correlationId, $error);
                    throw $error;
                }
            }
        };
    }
}
