<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine;

use Atlas\Contracts\Port\AuditTrail;
use Atlas\Contracts\Port\HumanReviewLifecycle;
use Atlas\Modules\KnowledgeEngine\Application\ApplicationReadService;
use Atlas\Modules\KnowledgeEngine\Application\AssertionConfidenceCalculator;
use Atlas\Modules\KnowledgeEngine\Application\AuditTrailService;
use Atlas\Modules\KnowledgeEngine\Application\EntityDetailReadService;
use Atlas\Modules\KnowledgeEngine\Application\GraphReadService;
use Atlas\Modules\KnowledgeEngine\Application\HealthCheckService;
use Atlas\Modules\KnowledgeEngine\Application\UxDecisionReadService;
use Atlas\Modules\KnowledgeEngine\Application\HumanReviewLifecycleGateway;
use Atlas\Modules\KnowledgeEngine\Domain\Repository\AssertionRepository;
use Atlas\Modules\KnowledgeEngine\Domain\Repository\EvidenceRepository;
use Atlas\Modules\KnowledgeEngine\Domain\Repository\KnowledgeEntityRepository;
use Atlas\Modules\KnowledgeEngine\Domain\Repository\KnowledgeRevisionRepository;
use Atlas\Modules\KnowledgeEngine\Domain\Repository\SourceRepository;
use Atlas\Modules\KnowledgeEngine\Domain\Repository\UnitOfWork;
use Atlas\Modules\KnowledgeEngine\Infrastructure\Console\ReprocessOutboxDeadLetters;
use Atlas\Modules\KnowledgeEngine\Infrastructure\OutboxPublisher;
use Atlas\Modules\KnowledgeEngine\Infrastructure\Persistence\EloquentUnitOfWork;
use Atlas\Modules\KnowledgeEngine\Infrastructure\Persistence\Repository\EloquentAssertionRepository;
use Atlas\Modules\KnowledgeEngine\Infrastructure\Persistence\Repository\EloquentEvidenceRepository;
use Atlas\Modules\KnowledgeEngine\Infrastructure\Persistence\Repository\EloquentKnowledgeEntityRepository;
use Atlas\Modules\KnowledgeEngine\Infrastructure\Persistence\Repository\EloquentKnowledgeRevisionRepository;
use Atlas\Modules\KnowledgeEngine\Infrastructure\Persistence\Repository\EloquentSourceRepository;
use Atlas\Modules\KnowledgeEngine\Infrastructure\RedisOutboxPublisher;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;

final class KnowledgeEngineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AssertionConfidenceCalculator::class);
        $this->app->singleton(ApplicationReadService::class);
        $this->app->singleton(EntityDetailReadService::class);
        $this->app->singleton(GraphReadService::class);
        $this->app->singleton(UxDecisionReadService::class);
        $this->app->singleton(AuditTrailService::class);
        $this->app->bind(AuditTrail::class, AuditTrailService::class);
        $this->app->bind(OutboxPublisher::class, RedisOutboxPublisher::class);
        $this->app->singleton(UnitOfWork::class, EloquentUnitOfWork::class);
        $this->app->bind(SourceRepository::class, EloquentSourceRepository::class);
        $this->app->bind(KnowledgeEntityRepository::class, EloquentKnowledgeEntityRepository::class);
        $this->app->bind(KnowledgeRevisionRepository::class, EloquentKnowledgeRevisionRepository::class);
        $this->app->bind(AssertionRepository::class, EloquentAssertionRepository::class);
        $this->app->bind(EvidenceRepository::class, EloquentEvidenceRepository::class);
        $this->app->bind(HumanReviewLifecycle::class, HumanReviewLifecycleGateway::class);
        $this->app->when(HealthCheckService::class)
            ->needs(Filesystem::class)
            ->give(fn (): Filesystem => Storage::disk('s3'));
    }

    public function boot(): void
    {
        $this->commands([ReprocessOutboxDeadLetters::class]);
    }
}
