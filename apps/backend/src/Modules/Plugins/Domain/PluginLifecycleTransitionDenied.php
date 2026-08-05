<?php

declare(strict_types=1);

namespace Atlas\Modules\Plugins\Domain;

use DomainException;

final class PluginLifecycleTransitionDenied extends DomainException {}
