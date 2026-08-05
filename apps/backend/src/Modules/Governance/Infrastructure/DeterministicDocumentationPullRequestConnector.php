<?php

declare(strict_types=1);

namespace Atlas\Modules\Governance\Infrastructure;

use Atlas\Modules\Governance\Domain\DocumentationPullRequestConnector;

/** Adaptador local para o seed e2e; nunca chama um provedor de repositório. */
final class DeterministicDocumentationPullRequestConnector implements DocumentationPullRequestConnector
{
    /** @param array<string, mixed> $proposal */
    public function openPullRequest(array $proposal, string $commitMessage, string $idempotencyKey): string
    {
        return 'PR #'.(100 + (crc32($idempotencyKey) % 900));
    }
}
