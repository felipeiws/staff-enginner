<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('knowledge_gaps', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->text('question');
            $table->string('semantic_key', 64);
            $table->jsonb('scope');
            $table->string('scope_key', 64);
            $table->unsignedInteger('occurrence_count')->default(0);
            $table->timestampTz('first_occurred_at');
            $table->timestampTz('window_started_at');
            $table->timestampTz('last_occurred_at');
            $table->string('owner_id')->nullable();
            $table->string('owner_type')->nullable();
            $table->timestampTz('assigned_at')->nullable();
            $table->string('assignment_method')->nullable();
            $table->timestamps();
            $table->unique(['semantic_key', 'scope_key']);
            $table->index(['scope_key', 'last_occurred_at']);
        });

        Schema::create('knowledge_gap_occurrences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('knowledge_gap_id')->constrained('knowledge_gaps')->restrictOnDelete();
            $table->text('question');
            $table->string('origin');
            $table->string('method');
            $table->decimal('confidence', 4, 3);
            $table->uuid('correlation_id')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestamps();
            $table->index(['knowledge_gap_id', 'occurred_at']);
        });

        Schema::create('documentation_tasks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('knowledge_gap_id')->constrained('knowledge_gaps')->restrictOnDelete();
            $table->string('owner_id');
            $table->string('owner_type')->default('human');
            $table->string('status')->default('open');
            $table->jsonb('provenance');
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
            $table->unique(['knowledge_gap_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentation_tasks');
        Schema::dropIfExists('knowledge_gap_occurrences');
        Schema::dropIfExists('knowledge_gaps');
    }
};
