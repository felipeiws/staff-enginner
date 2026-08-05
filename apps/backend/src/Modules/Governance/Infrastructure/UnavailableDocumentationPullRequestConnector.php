<?php

declare(strict_types=1);

namespace Atlas\Modules\Governance\Infrastructure;

use Atlas\Modules\Governance\Domain\DocumentationPullRequestConnector;
use RuntimeException;

final class UnavailableDocumentationPullRequestConnector implements DocumentationPullRequestConnector
{
    public function openPullRequest(array $proposal, string $commitMessage, string $idempotencyKey): string
    {
        throw new RuntimeException('Conector do repositório de documentação não está configurado.');
    }
}
