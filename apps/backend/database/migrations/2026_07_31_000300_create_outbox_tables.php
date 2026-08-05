<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_events', function (Blueprint $table): void {
            $table->uuid('event_id')->primary();
            $table->string('aggregate');
            $table->uuid('aggregate_id');
            $table->string('tipo');
            $table->string('schema_version', 32);
            $table->jsonb('payload');
            $table->timestampTz('occurred_at');
            $table->uuid('correlation_id');
            $table->jsonb('ator');
            $table->string('status')->default('pending');
            $table->unsignedInteger('tentativas')->default(0);
            $table->timestampTz('available_at');
            $table->text('last_error')->nullable();
            $table->timestampTz('locked_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'available_at']);
            $table->index(['aggregate', 'aggregate_id', 'occurred_at', 'event_id']);
        });

        Schema::create('processed_events', function (Blueprint $table): void {
            $table->string('consumer');
            $table->uuid('event_id');
            $table->timestampTz('processed_at');
            $table->timestamps();

            $table->primary(['consumer', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processed_events');
        Schema::dropIfExists('outbox_events');
    }
};
