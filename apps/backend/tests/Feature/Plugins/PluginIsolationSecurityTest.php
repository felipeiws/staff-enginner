<?php

declare(strict_types=1);

use Atlas\Modules\Plugins\Application\PluginHost;
use Atlas\Modules\Plugins\Application\PluginRegistry;
use Atlas\Modules\Plugins\Application\PluginWebhookVerifier;
use Atlas\Modules\Plugins\Domain\IsolatedPluginExecution;
use Atlas\Modules\Plugins\Domain\IsolatedPluginExecutor;
use Atlas\Modules\Plugins\Domain\PluginExecutionContext;
use Atlas\Modules\Plugins\Domain\PluginOperationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function enabledPlugin(array $manifest = []): PluginRegistry
{
    $registry = app(PluginRegistry::class);
    $full = array_replace_recursive(pluginManifest(['id' => 'atlas.isolated', 'permissions' => ['artifact.read']]), $manifest);
    $registry->discover($full, pluginCorrelation(10), 'discover');
    $registry->validate('atlas.isolated', pluginCorrelation(11), 'validate');
    $registry->install('atlas.isolated', pluginCorrelation(12), 'install');
    $registry->configure('atlas.isolated', [], pluginCorrelation(13), 'configure');
    $registry->enable('atlas.isolated', pluginCorrelation(14), 'enable');
    return $registry;
}

function pluginContext(): PluginExecutionContext
{
    return new PluginExecutionContext(pluginCorrelation(15), now()->addMinute()->toIso8601String(), 'execution-1', ['organization' => 'atlas']);
}

it('gives the isolated executor no internal store access and enforces an ephemeral sandbox', function (): void {
    enabledPlugin();
    $seen = null;
    app()->bind(IsolatedPluginExecutor::class, function () use (&$seen): IsolatedPluginExecutor {
        return new class ($seen) implements IsolatedPluginExecutor {
            public function __construct(private mixed &$seen) {}
            public function execute(IsolatedPluginExecution $execution): PluginOperationResult
            {
                $this->seen = $execution;
                return new PluginOperationResult([], []);
            }
        };
    });
    app(PluginHost::class)->parse('atlas.isolated', pluginContext(), ['artifact' => 'safe']);
    expect($seen->policy->ephemeralFilesystem)->toBeTrue()
        ->and($seen->policy->egressAllowlist)->toBe([])
        ->and($seen->policy->mayReadSecretReferences)->toBeFalse()
        ->and($seen->input)->not->toHaveKeys(['postgresql', 'redis', 'typesense', 'database_url']);
});

it('rejects egress without an explicit allowlist and inline secrets', function (): void {
    expect(fn(): mixed => app(PluginRegistry::class)->discover(pluginManifest(['permissions' => ['network.egress']]), pluginCorrelation(16), 'bad-egress'))
        ->toThrow(DomainException::class);
    expect(fn(): mixed => app(PluginRegistry::class)->discover(pluginManifest(['configurations' => ['api_token' => 'plaintext']]), pluginCorrelation(17), 'bad-secret'))
        ->toThrow(DomainException::class);
});

it('opens a per-plugin circuit and disables only the failing registration', function (): void {
    config()->set('plugins.circuit_breaker.failure_threshold', 2);
    $registry = enabledPlugin();
    app()->bind(IsolatedPluginExecutor::class, fn(): IsolatedPluginExecutor => new class implements IsolatedPluginExecutor {
        public function execute(IsolatedPluginExecution $execution): PluginOperationResult
        {
            throw new RuntimeException('isolated runtime failed');
        }
    });
    $host = app(PluginHost::class);
    expect(fn(): mixed => $host->healthCheck('atlas.isolated', pluginContext()))->toThrow(RuntimeException::class);
    expect(fn(): mixed => $host->healthCheck('atlas.isolated', pluginContext()))->toThrow(RuntimeException::class)
        ->and($registry->byId('atlas.isolated')->state->value)->toBe('disabled');
});

it('validates webhook signatures before allowing the connector and rate limits callers', function (): void {
    config()->set('plugins.webhooks.requests_per_minute', 1);
    $verifier = app(PluginWebhookVerifier::class);
    $body = '{"revision":"abc"}';
    $signature = 'sha256=' . hash_hmac('sha256', $body, 'short-lived-secret');
    $verifier->assertValid('atlas.isolated', $body, $signature, 'short-lived-secret', '203.0.113.1');
    expect(fn(): mixed => $verifier->assertValid('atlas.isolated', $body, $signature, 'short-lived-secret', '203.0.113.1'))->toThrow(RuntimeException::class);
});
