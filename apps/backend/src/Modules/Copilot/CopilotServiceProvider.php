<?php

declare(strict_types=1);

namespace Atlas\Modules\Copilot;

use Atlas\Modules\Copilot\Application\CopilotExecutionService;
use Atlas\Modules\Copilot\Application\ExternalProviderContentPolicy;
use Atlas\Modules\Copilot\Application\KnowledgeCandidateService;
use Atlas\Modules\Copilot\Domain\ModelProvider;
use Atlas\Modules\Copilot\Infrastructure\AnthropicModelProvider;
use Atlas\Modules\Copilot\Infrastructure\DeterministicModelProvider;
use Illuminate\Support\ServiceProvider;

final class CopilotServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ExternalProviderContentPolicy::class);
        $this->app->singleton(CopilotExecutionService::class);
        $this->app->singleton(KnowledgeCandidateService::class);
        $this->app->bind(ModelProvider::class, config('copilot.provider') === 'deterministic' ? DeterministicModelProvider::class : AnthropicModelProvider::class);
    }
}
