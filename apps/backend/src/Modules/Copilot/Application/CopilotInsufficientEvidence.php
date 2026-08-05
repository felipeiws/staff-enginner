<?php

declare(strict_types=1);

namespace Atlas\Modules\Copilot\Application;

use RuntimeException;

/** The Copilot must abstain instead of turning an uncited answer into knowledge. */
final class CopilotInsufficientEvidence extends RuntimeException {}
