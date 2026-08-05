<?php

declare(strict_types=1);

namespace Atlas\Modules\Plugins\Application;

use Atlas\Modules\Plugins\Domain\PluginLifecycleState;

final readonly class PluginRegistration
{
    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, mixed>|null  $configuration
     */
    public function __construct(public string $id, public string $pluginId, public string $version, public PluginLifecycleState $state, public array $manifest, public ?array $configuration) {}
}
