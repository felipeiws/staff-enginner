<?php

declare(strict_types=1);

namespace Atlas\Modules\Plugins\Domain;

/** This port is implemented by a container/runtime-control-plane adapter, never by the backend process. */
interface IsolatedPluginExecutor
{
    public function execute(IsolatedPluginExecution $execution): PluginOperationResult;
}
