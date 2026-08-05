<?php

declare(strict_types=1);

namespace Atlas\Modules\Plugins\Domain;

interface PluginRuntimeResolver
{
    public function forPlugin(string $pluginId): PluginRuntime;
}
