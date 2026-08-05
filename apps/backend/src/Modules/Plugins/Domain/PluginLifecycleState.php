<?php

declare(strict_types=1);

namespace Atlas\Modules\Plugins\Domain;

enum PluginLifecycleState: string
{
    case Discovered = 'discovered';
    case Validated = 'validated';
    case Installed = 'installed';
    case Configured = 'configured';
    case Enabled = 'enabled';
    case Disabled = 'disabled';
    case Upgrading = 'upgrading';
    case Revoked = 'revoked';
}
