<?php

declare(strict_types=1);

namespace Atlas\Modules\Copilot\Application;

use Illuminate\Database\DatabaseManager;

/** Checks classification before any caller concatenates content into a provider prompt. */
final readonly class ExternalProviderContentPolicy
{
    public function __construct(private DatabaseManager $database) {}

    /** @param list<array{classification:string}> $context */
    public function assertAllowed(array $context): void
    {
        $levels = array_values(array_unique(array_map(static fn(array $item): string => $item['classification'], $context)));
        if ($levels === []) {
            return;
        }
        $allowed = $this->database->table('classifications')->whereIn('level', $levels)->where('external_provider_allowed', true)->pluck('level')->all();
        if (count($allowed) !== count($levels)) {
            throw new ExternalProviderContentDenied('A classificação do conteúdo não permite envio a provedor externo.');
        }
    }
}
