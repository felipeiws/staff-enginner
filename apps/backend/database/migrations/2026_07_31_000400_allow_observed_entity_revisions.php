<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE entity_revisions DROP CONSTRAINT entity_revisions_lifecycle_check');
            DB::statement("ALTER TABLE entity_revisions ADD CONSTRAINT entity_revisions_lifecycle_check CHECK (lifecycle_state IN ('observed', 'draft', 'candidate', 'review', 'published', 'superseded', 'retracted'))");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("UPDATE entity_revisions SET lifecycle_state = 'candidate' WHERE lifecycle_state = 'observed'");
            DB::statement('ALTER TABLE entity_revisions DROP CONSTRAINT entity_revisions_lifecycle_check');
            DB::statement("ALTER TABLE entity_revisions ADD CONSTRAINT entity_revisions_lifecycle_check CHECK (lifecycle_state IN ('draft', 'candidate', 'review', 'published', 'superseded', 'retracted'))");
        }
    }
};
