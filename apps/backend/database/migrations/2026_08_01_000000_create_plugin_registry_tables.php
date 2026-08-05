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
        Schema::create('plugin_registrations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('plugin_id')->unique();
            $table->string('version');
            $table->string('lifecycle_state');
            $table->jsonb('manifest');
            $table->jsonb('configuration')->nullable();
            $table->timestamps();
        });
        Schema::create('plugin_lifecycle_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('plugin_registration_id');
            $table->string('from_state')->nullable();
            $table->string('to_state');
            $table->string('correlation_id');
            $table->string('idempotency_key');
            $table->jsonb('details');
            $table->timestamps();
            $table->unique(['plugin_registration_id', 'idempotency_key']);
            $table->index(['plugin_registration_id', 'created_at']);
        });
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE plugin_registrations ADD CONSTRAINT plugin_registrations_state_check CHECK (lifecycle_state IN ('discovered', 'validated', 'installed', 'configured', 'enabled', 'disabled', 'upgrading', 'revoked'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plugin_lifecycle_events');
        Schema::dropIfExists('plugin_registrations');
    }
};
