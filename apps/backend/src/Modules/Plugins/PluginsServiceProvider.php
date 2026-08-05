<?php

declare(strict_types=1);

namespace Atlas\Modules\Plugins;

use Atlas\Modules\Plugins\Application\PluginHost;
use Atlas\Modules\Plugins\Application\PluginCircuitBreaker;
use Atlas\Modules\Plugins\Application\PluginWebhookVerifier;
use Atlas\Modules\Plugins\Application\PluginManifestValidator;
use Atlas\Modules\Plugins\Application\PluginRegistry;
use Atlas\Modules\Plugins\Domain\PluginRuntimeResolver;
use Atlas\Modules\Plugins\Domain\IsolatedPluginExecutor;
use Atlas\Modules\Plugins\Infrastructure\ControlledPluginRuntimeResolver;
use Atlas\Modules\Plugins\Infrastructure\ContainerControlPlanePluginExecutor;
use Illuminate\Support\ServiceProvider;

final class PluginsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PluginManifestValidator::class);
        $this->app->singleton(PluginRegistry::class);
        $this->app->singleton(PluginCircuitBreaker::class);
        $this->app->singleton(PluginWebhookVerifier::class);
        $this->app->singleton(PluginHost::class);
        $this->app->bind(IsolatedPluginExecutor::class, ContainerControlPlanePluginExecutor::class);
        $this->app->bind(PluginRuntimeResolver::class, ControlledPluginRuntimeResolver::class);
    }
}
