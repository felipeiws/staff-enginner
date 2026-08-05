<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Api\OpenApi\OpenApiGenerator;
use App\Http\Api\Copilot\AuthorizedKnowledgeRetriever;
use App\Console\SeedDemoDomain;
use App\Console\CreateInitialAdministrator;
use App\Observability\MetricRecorder;
use App\Observability\PrometheusMetrics;
use Atlas\Modules\Copilot\Application\KnowledgeRetriever;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(OpenApiGenerator::class);
        $this->app->singleton(MetricRecorder::class);
        $this->app->singleton(PrometheusMetrics::class);
        $this->app->bind(KnowledgeRetriever::class, AuthorizedKnowledgeRetriever::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JsonResource::withoutWrapping();
        $this->commands([SeedDemoDomain::class, CreateInitialAdministrator::class]);
    }
}
