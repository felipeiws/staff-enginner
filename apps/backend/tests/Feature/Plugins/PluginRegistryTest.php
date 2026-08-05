<?php

declare(strict_types=1);

use Atlas\Contracts\Exception\ContractViolation;
use Atlas\Modules\Plugins\Application\PluginIncompatible;
use Atlas\Modules\Plugins\Application\PluginPermissionDenied;
use Atlas\Modules\Plugins\Application\PluginRegistry;
use Atlas\Modules\Plugins\Domain\PluginLifecycleTransitionDenied;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/** @return array<string, mixed> */
function pluginManifest(array $overrides = []): array
{
    return array_replace_recursive([
        'schema_version' => '1.0.0', 'id' => 'atlas.code-analyzer', 'version' => '1.0.0', 'platform_compatibility' => '^1.0.0', 'runtime' => 'python_worker',
        'capabilities' => ['code.analyzer'], 'configurations' => ['repository' => ['type' => 'string']], 'permissions' => ['artifact.read'], 'input_schema' => ['type' => 'object'], 'output_schema' => ['type' => 'object'],
        'limits' => ['max_concurrency' => 1, 'timeout_seconds' => 60, 'max_payload_bytes' => 1000000, 'cpu_millicores' => 250, 'memory_mebibytes' => 256], 'retry_policy' => ['max_attempts' => 3, 'backoff_seconds' => 2],
    ], $overrides);
}

function pluginCorrelation(int $number): string
{
    return sprintf('80000000-0000-4000-8000-%012d', $number);
}

it('persists every permitted lifecycle transition, including upgrade', function (): void {
    $registry = app(PluginRegistry::class);
    expect($registry->discover(pluginManifest(), pluginCorrelation(1), 'discover')->state->value)->toBe('discovered');
    expect($registry->validate('atlas.code-analyzer', pluginCorrelation(2), 'validate')->state->value)->toBe('validated');
    expect($registry->install('atlas.code-analyzer', pluginCorrelation(3), 'install')->state->value)->toBe('installed');
    expect($registry->configure('atlas.code-analyzer', ['repository' => 'atlas'], pluginCorrelation(4), 'configure')->state->value)->toBe('configured');
    expect($registry->enable('atlas.code-analyzer', pluginCorrelation(5), 'enable')->state->value)->toBe('enabled');
    expect($registry->disable('atlas.code-analyzer', pluginCorrelation(6), 'disable')->state->value)->toBe('disabled');
    expect($registry->enable('atlas.code-analyzer', pluginCorrelation(7), 'reenable')->state->value)->toBe('enabled');
    expect($registry->upgrade('atlas.code-analyzer', pluginManifest(['version' => '1.1.0']), pluginCorrelation(8), 'upgrade')->version)->toBe('1.1.0');
    expect($registry->revoke('atlas.code-analyzer', pluginCorrelation(9), 'revoke')->state->value)->toBe('revoked')
        ->and(DB::table('plugin_lifecycle_events')->pluck('to_state')->all())->toBe(['discovered', 'validated', 'installed', 'configured', 'enabled', 'disabled', 'enabled', 'upgrading', 'enabled', 'revoked']);
});

it('rejects transitions outside the documented state machine', function (): void {
    $registry = app(PluginRegistry::class);
    $registry->discover(pluginManifest(), pluginCorrelation(1), 'discover');
    expect(fn(): mixed => $registry->enable('atlas.code-analyzer', pluginCorrelation(2), 'enable'))->toThrow(PluginLifecycleTransitionDenied::class);
});

it('rejects an invalid manifest before registering it', function (): void {
    $manifest = pluginManifest();
    unset($manifest['runtime']);
    expect(fn(): mixed => app(PluginRegistry::class)->discover($manifest, pluginCorrelation(1), 'discover'))->toThrow(ContractViolation::class)
        ->and(DB::table('plugin_registrations')->count())->toBe(0);
});

it('rejects an incompatible version during validation and installation', function (): void {
    app(PluginRegistry::class)->discover(pluginManifest(['platform_compatibility' => '^2.0.0']), pluginCorrelation(1), 'discover');
    expect(fn(): mixed => app(PluginRegistry::class)->validate('atlas.code-analyzer', pluginCorrelation(2), 'validate'))->toThrow(PluginIncompatible::class);
});

it('rejects a permission not allowed by the host during installation', function (): void {
    config()->set('plugins.allowed_permissions', ['artifact.read']);
    $registry = app(PluginRegistry::class);
    $registry->discover(pluginManifest(['permissions' => ['network.egress'], 'egress_allowlist' => ['api.github.com']]), pluginCorrelation(1), 'discover');
    $registry->validate('atlas.code-analyzer', pluginCorrelation(2), 'validate');
    expect(fn(): mixed => $registry->install('atlas.code-analyzer', pluginCorrelation(3), 'install'))->toThrow(PluginPermissionDenied::class);
});
