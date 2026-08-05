<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_projection_failures', function (Blueprint $table): void {
            $table->uuid('event_id')->primary();
            $table->jsonb('event');
            $table->unsignedInteger('attempts')->default(1);
            $table->text('last_error');
            $table->timestampTz('failed_at');
            $table->timestampTz('available_at');
            $table->timestampTz('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['resolved_at', 'available_at']);
        });

        Schema::create('search_projection_metrics', function (Blueprint $table): void {
            $table->uuid('event_id')->primary();
            $table->uuid('entity_revision_id');
            $table->timestampTz('published_at');
            $table->timestampTz('available_at');
            $table->unsignedBigInteger('lag_ms');
            $table->timestamps();
            $table->index('available_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_projection_metrics');
        Schema::dropIfExists('search_projection_failures');
    }
};
