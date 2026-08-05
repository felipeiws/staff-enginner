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
        Schema::create('classifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('level')->unique();
            $table->boolean('search_exposure_allowed')->default(false);
            $table->boolean('display_exposure_allowed')->default(false);
            $table->boolean('external_provider_allowed')->default(false);
            $table->jsonb('exposure_rules');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('policies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('policy_key')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->string('schema_version')->default('1.0.0');
            $table->string('definition_checksum', 64)->nullable();
            $table->string('origin_reference')->nullable();
            $table->string('origin_revision')->nullable();
            $table->string('derivation_method')->default('human_declared');
            $table->decimal('confidence', 4, 3)->default(1);
            $table->string('name')->unique();
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('active')->default(true);
            $table->jsonb('rules');
            $table->timestampTz('effective_from')->nullable();
            $table->timestampTz('effective_to')->nullable();
            $table->string('created_by_id');
            $table->string('created_by_type')->default('human');
            $table->timestamps();

            $table->index(['active', 'priority']);
            $table->index(['effective_from', 'effective_to']);
            $table->unique(['policy_key', 'version']);
        });

        Schema::create('owners', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('scope_type');
            $table->string('scope_identifier');
            $table->jsonb('scope');
            $table->string('responsibility_type');
            $table->string('owner_id');
            $table->string('owner_type')->default('human');
            $table->timestampTz('effective_from')->nullable();
            $table->timestampTz('effective_to')->nullable();
            $table->timestamps();

            $table->unique(['scope_type', 'scope_identifier', 'responsibility_type', 'owner_id'], 'owners_scope_responsibility_owner_unique');
            $table->index(['scope_type', 'scope_identifier']);
        });

        Schema::create('retention_rules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('active')->default(true);
            $table->jsonb('applies_to');
            $table->string('disposition');
            $table->unsignedInteger('retain_for_days')->nullable();
            $table->boolean('legal_hold_allowed')->default(true);
            $table->jsonb('rules');
            $table->string('created_by_id');
            $table->string('created_by_type')->default('human');
            $table->timestamps();

            $table->index(['active', 'priority']);
        });

        Schema::create('approvals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('subject_kind');
            $table->uuid('entity_revision_id')->nullable();
            $table->uuid('assertion_id')->nullable();
            $table->foreignUuid('policy_id')->nullable()->constrained('policies')->restrictOnDelete();
            $table->jsonb('proposal');
            $table->string('proposed_by_id');
            $table->string('proposed_by_type');
            $table->timestampTz('proposed_at');
            $table->string('reviewer_id')->nullable();
            $table->string('reviewer_type')->nullable();
            $table->string('decision')->nullable();
            $table->text('justification')->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->string('external_pr_reference')->nullable();
            $table->uuid('correlation_id')->nullable();
            $table->timestamps();

            $table->foreign('entity_revision_id')->references('id')->on('entity_revisions')->restrictOnDelete();
            $table->foreign('assertion_id')->references('id')->on('assertions')->restrictOnDelete();
            $table->index(['subject_kind', 'entity_revision_id']);
            $table->index(['subject_kind', 'assertion_id']);
            $table->index(['decision', 'decided_at']);
            $table->index('correlation_id');
        });

        Schema::create('audit_log', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->timestampTz('occurred_at');
            $table->string('actor_id');
            $table->string('actor_type');
            $table->string('action');
            $table->string('subject_kind');
            $table->uuid('entity_id')->nullable();
            $table->uuid('entity_revision_id')->nullable();
            $table->string('decision')->nullable();
            $table->text('reason');
            $table->uuid('correlation_id')->nullable();
            $table->jsonb('before_payload')->nullable();
            $table->jsonb('after_payload')->nullable();
            $table->uuid('source_revision_id')->nullable();
            $table->string('method');
            $table->decimal('confidence', 4, 3)->nullable();

            $table->foreign('entity_id')->references('id')->on('entities')->restrictOnDelete();
            $table->foreign('entity_revision_id')->references('id')->on('entity_revisions')->restrictOnDelete();
            $table->foreign('source_revision_id')->references('id')->on('revisions')->restrictOnDelete();
            $table->index(['subject_kind', 'entity_id']);
            $table->index(['subject_kind', 'entity_revision_id']);
            $table->index('correlation_id');
            $table->index('occurred_at');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            $this->addPostgresConstraintsAndTriggers();
        } else {
            $this->addSqliteAuditTriggers();
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('DROP FUNCTION IF EXISTS prevent_audit_log_mutation()');
        } else {
            DB::statement('DROP TRIGGER IF EXISTS audit_log_immutable_update');
            DB::statement('DROP TRIGGER IF EXISTS audit_log_immutable_delete');
        }

        Schema::dropIfExists('audit_log');
        Schema::dropIfExists('approvals');
        Schema::dropIfExists('retention_rules');
        Schema::dropIfExists('owners');
        Schema::dropIfExists('policies');
        Schema::dropIfExists('classifications');
    }

    private function addSqliteAuditTriggers(): void
    {
        DB::statement("CREATE TRIGGER audit_log_immutable_update BEFORE UPDATE ON audit_log BEGIN SELECT RAISE(ABORT, 'audit_log is immutable'); END");
        DB::statement("CREATE TRIGGER audit_log_immutable_delete BEFORE DELETE ON audit_log BEGIN SELECT RAISE(ABORT, 'audit_log is immutable'); END");
    }

    private function addPostgresConstraintsAndTriggers(): void
    {
        DB::statement("ALTER TABLE classifications ADD CONSTRAINT classifications_level_check CHECK (level IN ('publico', 'interno', 'restrito', 'confidencial'))");
        DB::statement("ALTER TABLE owners ADD CONSTRAINT owners_responsibility_type_check CHECK (responsibility_type IN ('technical', 'business'))");
        DB::statement("ALTER TABLE retention_rules ADD CONSTRAINT retention_rules_disposition_check CHECK (disposition IN ('retain', 'anonymize', 'delete'))");
        DB::statement("ALTER TABLE approvals ADD CONSTRAINT approvals_subject_check CHECK ((subject_kind = 'entity_revision' AND entity_revision_id IS NOT NULL AND assertion_id IS NULL) OR (subject_kind = 'assertion' AND assertion_id IS NOT NULL AND entity_revision_id IS NULL))");
        DB::statement("ALTER TABLE approvals ADD CONSTRAINT approvals_decision_check CHECK (decision IS NULL OR decision IN ('approved', 'rejected'))");
        DB::statement("ALTER TABLE approvals ADD CONSTRAINT approvals_reviewer_check CHECK ((decision IS NULL AND reviewer_id IS NULL AND reviewer_type IS NULL AND decided_at IS NULL) OR (decision IS NOT NULL AND reviewer_id IS NOT NULL AND reviewer_type = 'human' AND decided_at IS NOT NULL AND length(trim(justification)) > 0))");
        DB::statement("ALTER TABLE audit_log ADD CONSTRAINT audit_log_actor_type_check CHECK (actor_type IN ('human', 'service', 'plugin', 'worker'))");
        DB::statement('ALTER TABLE audit_log ADD CONSTRAINT audit_log_confidence_check CHECK (confidence IS NULL OR (confidence >= 0 AND confidence <= 1))');
        DB::statement('CREATE INDEX classifications_exposure_index ON classifications (search_exposure_allowed, display_exposure_allowed, external_provider_allowed)');
        DB::statement('CREATE INDEX policies_rules_gin ON policies USING GIN (rules)');
        DB::statement('CREATE INDEX owners_scope_gin ON owners USING GIN (scope)');
        DB::statement('CREATE INDEX retention_rules_applies_to_gin ON retention_rules USING GIN (applies_to)');
        DB::statement('CREATE INDEX retention_rules_rules_gin ON retention_rules USING GIN (rules)');
        DB::statement('CREATE INDEX approvals_proposal_gin ON approvals USING GIN (proposal)');
        DB::statement('CREATE INDEX audit_log_before_payload_gin ON audit_log USING GIN (before_payload)');
        DB::statement('CREATE INDEX audit_log_after_payload_gin ON audit_log USING GIN (after_payload)');
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_audit_log_mutation() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'audit_log is immutable';
            END;
            $$ LANGUAGE plpgsql
        SQL);
        DB::statement('CREATE TRIGGER audit_log_immutable_update BEFORE UPDATE ON audit_log FOR EACH ROW EXECUTE FUNCTION prevent_audit_log_mutation()');
        DB::statement('CREATE TRIGGER audit_log_immutable_delete BEFORE DELETE ON audit_log FOR EACH ROW EXECUTE FUNCTION prevent_audit_log_mutation()');
    }
};
