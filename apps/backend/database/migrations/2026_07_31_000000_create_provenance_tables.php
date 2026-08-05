<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('external_system')->nullable();
            $table->jsonb('scope');
            $table->string('credential_reference')->nullable();
            $table->string('classification');
            $table->boolean('active')->default(true);
            $this->retentionColumns($table);
            $table->timestamps();

            $table->index(['external_system', 'active']);
        });

        Schema::create('artifacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('source_id')->constrained('sources')->restrictOnDelete();
            $table->string('external_identifier');
            $table->text('uri');
            $table->string('content_hash');
            $table->string('media_type');
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->text('object_storage_pointer')->nullable();
            $this->retentionColumns($table);
            $table->timestamps();

            $table->unique(['source_id', 'external_identifier']);
            $table->index('source_id');
            $table->index('content_hash');
        });

        Schema::create('revisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('artifact_id')->constrained('artifacts')->restrictOnDelete();
            $table->string('source_revision');
            $table->timestampTz('observed_at');
            $table->string('checksum');
            $table->jsonb('metadata');
            $this->retentionColumns($table);
            $table->timestamps();

            $table->unique(['artifact_id', 'source_revision']);
            $table->index('artifact_id');
            $table->index('observed_at');
        });

        Schema::create('chunks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('revision_id')->constrained('revisions')->restrictOnDelete();
            $table->unsignedInteger('ordinal');
            $table->unsignedBigInteger('start_offset');
            $table->unsignedBigInteger('end_offset');
            $table->longText('text');
            $table->unsignedInteger('tokens');
            $table->string('checksum');
            $this->retentionColumns($table);
            $table->timestamps();

            $table->unique(['revision_id', 'ordinal']);
            $table->index('revision_id');
        });

        Schema::create('observations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('source_id')->constrained('sources')->restrictOnDelete();
            $table->uuid('artifact_id')->nullable();
            $table->jsonb('payload');
            $table->string('idempotency_key')->unique();
            $table->uuid('correlation_id')->nullable();
            $table->string('actor_id');
            $table->string('actor_type');
            $table->string('processing_status')->default('received');
            $table->timestampTz('processed_at')->nullable();
            $this->retentionColumns($table);
            $table->timestamps();

            $table->foreign('artifact_id')->references('id')->on('artifacts')->restrictOnDelete();
            $table->index('source_id');
            $table->index('correlation_id');
            $table->index('processing_status');
        });

        Schema::create('executions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('observation_id')->nullable()->constrained('observations')->restrictOnDelete();
            $table->uuid('revision_id')->nullable();
            $table->string('executor_type');
            $table->string('executor_name');
            $table->string('executor_version');
            $table->jsonb('parameters');
            $table->unsignedInteger('duration_ms')->nullable();
            $table->decimal('cost', 14, 6)->nullable();
            $table->string('cost_currency', 3)->nullable();
            $table->string('status');
            $table->jsonb('result')->nullable();
            $this->retentionColumns($table);
            $table->timestamps();

            $table->foreign('revision_id')->references('id')->on('revisions')->restrictOnDelete();
            $table->index('observation_id');
            $table->index('revision_id');
            $table->index(['executor_name', 'executor_version']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX revisions_metadata_gin ON revisions USING GIN (metadata)');
            DB::statement('CREATE INDEX observations_payload_gin ON observations USING GIN (payload)');
            DB::statement('CREATE INDEX executions_parameters_gin ON executions USING GIN (parameters)');
            DB::statement('CREATE INDEX executions_result_gin ON executions USING GIN (result)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('executions');
        Schema::dropIfExists('observations');
        Schema::dropIfExists('chunks');
        Schema::dropIfExists('revisions');
        Schema::dropIfExists('artifacts');
        Schema::dropIfExists('sources');
    }

    private function retentionColumns(Blueprint $table): void
    {
        $table->timestampTz('retention_until')->nullable();
        $table->timestampTz('deleted_at')->nullable();
    }
};
