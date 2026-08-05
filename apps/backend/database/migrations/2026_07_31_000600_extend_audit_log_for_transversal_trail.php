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
        Schema::table('audit_log', function (Blueprint $table): void {
            $table->uuid('subject_id')->nullable()->after('subject_kind');
            $table->string('classification')->nullable()->after('source_revision_id');
            $table->index(['entity_id', 'occurred_at'], 'audit_log_entity_history_index');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE audit_log DROP CONSTRAINT audit_log_actor_type_check');
            DB::statement("ALTER TABLE audit_log ADD CONSTRAINT audit_log_actor_type_check CHECK (actor_type IN ('human', 'ci', 'plugin', 'model', 'service', 'worker'))");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE audit_log DROP CONSTRAINT audit_log_actor_type_check');
            DB::statement("ALTER TABLE audit_log ADD CONSTRAINT audit_log_actor_type_check CHECK (actor_type IN ('human', 'service', 'plugin', 'worker'))");
        }
        Schema::table('audit_log', function (Blueprint $table): void {
            $table->dropIndex('audit_log_entity_history_index');
            $table->dropColumn(['subject_id', 'classification']);
        });
    }
};
