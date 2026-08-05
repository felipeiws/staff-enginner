<?php

declare(strict_types=1);

namespace Atlas\Modules\Copilot\Application;

use DomainException;

final class CopilotTokenLimitExceeded extends DomainException {}
