<?php

declare(strict_types=1);

namespace Atlas\Modules\Plugins\Domain;

/** Plugin output remains a candidate input: observations and evidence only, never graph writes. */
final readonly class PluginOperationResult
{
    /**
     * @param  list<array<string, mixed>>  $observations
     * @param  list<array<string, mixed>>  $evidence
     */
    public function __construct(public array $observations, public array $evidence) {}
}
