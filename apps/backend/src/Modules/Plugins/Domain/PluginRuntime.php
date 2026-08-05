<?php

declare(strict_types=1);

namespace Atlas\Modules\Plugins\Domain;

interface PluginRuntime
{
    /** @param array<string, mixed> $cursor */
    public function discoverIncremental(PluginExecutionContext $context, array $cursor): PluginOperationResult;

    /** @param array<string, mixed> $reference */
    public function fetchArtifact(PluginExecutionContext $context, array $reference): PluginOperationResult;

    /** @param array<string, mixed> $artifact */
    public function parse(PluginExecutionContext $context, array $artifact): PluginOperationResult;

    public function healthCheck(PluginExecutionContext $context): PluginOperationResult;

    public function shutdown(PluginExecutionContext $context): void;
}
