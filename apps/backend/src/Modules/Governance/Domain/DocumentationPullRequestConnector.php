<?php

declare(strict_types=1);

namespace Atlas\Modules\Governance\Domain;

/**
 * Boundary for the configured documentation-repository connector.
 *
 * Implementations live in a plugin/adapter; governance only receives the
 * reviewed content and never repository credentials.
 */
interface DocumentationPullRequestConnector
{
    /** @param array<string, mixed> $proposal */
    public function openPullRequest(array $proposal, string $commitMessage, string $idempotencyKey): string;
}
