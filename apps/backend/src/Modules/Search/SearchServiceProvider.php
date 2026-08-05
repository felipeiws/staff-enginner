<?php

declare(strict_types=1);

namespace Atlas\Modules\Search;

use Atlas\Modules\Search\Application\EmbeddingPersistenceService;
use Atlas\Modules\Search\Application\HybridSearchService;
use Atlas\Modules\Search\Application\PublishedKnowledgeProjector;
use Atlas\Modules\Search\Application\PublishedKnowledgeSchema;
use Atlas\Modules\Search\Application\SemanticSearchService;
use Atlas\Modules\Search\Domain\HybridSearchIndex;
use Atlas\Modules\Search\Domain\PublishedKnowledgeIndex;
use Atlas\Modules\Search\Infrastructure\Console\EvaluateSearchQuality;
use Atlas\Modules\Search\Infrastructure\Console\ReindexPublishedKnowledge;
use Atlas\Modules\Search\Infrastructure\Console\ReprocessSearchProjectionFailures;
use Atlas\Modules\Search\Infrastructure\TypesenseHybridSearchIndex;
use Atlas\Modules\Search\Infrastructure\TypesensePublishedKnowledgeIndex;
use Illuminate\Support\ServiceProvider;

final class SearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PublishedKnowledgeSchema::class);
        $this->app->singleton(PublishedKnowledgeProjector::class);
        $this->app->singleton(EmbeddingPersistenceService::class);
        $this->app->singleton(SemanticSearchService::class);
        $this->app->singleton(HybridSearchService::class);
        $this->app->bind(PublishedKnowledgeIndex::class, TypesensePublishedKnowledgeIndex::class);
        $this->app->bind(HybridSearchIndex::class, TypesenseHybridSearchIndex::class);
    }

    public function boot(): void
    {
        $this->commands([ReindexPublishedKnowledge::class, ReprocessSearchProjectionFailures::class, EvaluateSearchQuality::class]);
    }
}
