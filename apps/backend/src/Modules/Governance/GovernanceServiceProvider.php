<?php

declare(strict_types=1);

namespace Atlas\Modules\Governance;

use Atlas\Contracts\Port\KnowledgeGapRecorder;
use Atlas\Contracts\Port\LifecyclePolicyEngine;
use Atlas\Modules\Governance\Application\GovernanceLifecyclePolicyEngine;
use Atlas\Modules\Governance\Application\CreateReviewProposal;
use Atlas\Modules\Governance\Application\HomeInsightsService;
use Atlas\Modules\Governance\Application\KnowledgeGapService;
use Atlas\Modules\Governance\Application\KnowledgeHealthCacheInvalidator;
use Atlas\Modules\Governance\Application\KnowledgeHealthService;
use Atlas\Modules\Governance\Application\SemanticQuestionGrouper;
use Atlas\Modules\Governance\Domain\DocumentationPullRequestConnector;
use Atlas\Modules\Governance\Infrastructure\DeterministicDocumentationPullRequestConnector;
use Atlas\Modules\Governance\Infrastructure\UnavailableDocumentationPullRequestConnector;
use Illuminate\Support\ServiceProvider;

final class GovernanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LifecyclePolicyEngine::class, GovernanceLifecyclePolicyEngine::class);
        $this->app->singleton(SemanticQuestionGrouper::class);
        $this->app->singleton(KnowledgeGapService::class);
        $this->app->singleton(KnowledgeHealthService::class);
        $this->app->singleton(KnowledgeHealthCacheInvalidator::class);
        $this->app->singleton(HomeInsightsService::class);
        $this->app->singleton(CreateReviewProposal::class);
        $this->app->bind(KnowledgeGapRecorder::class, KnowledgeGapService::class);
        $this->app->bind(
            DocumentationPullRequestConnector::class,
            $this->app->environment('testing')
                ? DeterministicDocumentationPullRequestConnector::class
                : UnavailableDocumentationPullRequestConnector::class,
        );
    }
}
