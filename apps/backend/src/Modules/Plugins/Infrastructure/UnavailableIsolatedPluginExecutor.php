<?php

declare(strict_types=1);

namespace Atlas\Modules\Plugins\Infrastructure;

use Atlas\Modules\Plugins\Domain\IsolatedPluginExecution;
use Atlas\Modules\Plugins\Domain\IsolatedPluginExecutor;
use Atlas\Modules\Plugins\Domain\PluginOperationResult;
use RuntimeException;

/** Fail closed until a container control plane is configured; it can never fall back to in-process execution. */
final class UnavailableIsolatedPluginExecutor implements IsolatedPluginExecutor
{
    public function execute(IsolatedPluginExecution $execution): PluginOperationResult
    {
        throw new RuntimeException("Executor isolado indisponível para {$execution->pluginId}.");
    }
}
