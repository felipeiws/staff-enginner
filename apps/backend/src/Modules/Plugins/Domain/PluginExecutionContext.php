<?php

declare(strict_types=1);

namespace Atlas\Modules\Plugins\Domain;

/** Context intentionally excludes database handles, credentials and unrestricted scope. */
final readonly class PluginExecutionContext
{
    /** @param array<string, string> $scope */
    public function __construct(public string $correlationId, public string $deadlineAt, public string $idempotencyKey, public array $scope) {}
}
