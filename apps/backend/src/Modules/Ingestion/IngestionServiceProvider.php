<?php

declare(strict_types=1);

namespace Atlas\Modules\Ingestion;

use Atlas\Modules\Ingestion\Application\ReceiveSourceObservation;
use Atlas\Modules\Ingestion\Application\ContentClassificationGate;
use Atlas\Modules\Ingestion\Application\IngestVisualArtifact;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;

final class IngestionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ContentClassificationGate::class);
        $this->app->when(ReceiveSourceObservation::class)
            ->needs(Filesystem::class)
            ->give(fn (): Filesystem => Storage::disk((string) config('ingestion.object_storage_disk')));
        $this->app->when(IngestVisualArtifact::class)
            ->needs(Filesystem::class)
            ->give(fn (): Filesystem => Storage::disk((string) config('ingestion.object_storage_disk')));
    }
}
