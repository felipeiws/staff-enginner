<?php

declare(strict_types=1);

namespace Atlas\Modules\Plugins\Infrastructure;

use Atlas\Modules\Plugins\Domain\PluginRuntime;
use Atlas\Modules\Plugins\Domain\PluginRuntimeResolver;
use RuntimeException;

final class UnavailablePluginRuntimeResolver implements PluginRuntimeResolver
{
    public function forPlugin(string $pluginId): PluginRuntime
    {
        throw new RuntimeException("Runtime isolado indisponível para {$pluginId}.");
    }
}
