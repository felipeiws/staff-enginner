<?php

declare(strict_types=1);

namespace Atlas\Modules\Plugins\Domain;

/** @phpstan-type PluginOperation 'discover'|'fetch_artifact'|'parse'|'health_check'|'shutdown' */
final readonly class IsolatedPluginExecution
{
    /**
     * @param array<string, mixed> $input
     * @param list<string> $secretReferences
     */
    public function __construct(
        public string $pluginId,
        public string $operation,
        public PluginExecutionContext $context,
        public array $input,
        public PluginIsolationPolicy $policy,
        public array $secretReferences,
    ) {}
}
