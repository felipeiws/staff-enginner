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
        Schema::create('entities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('entity_type');
            $table->string('family');
            $table->jsonb('scope');
            $table->string('external_natural_key');
            $table->string('owner_id');
            $table->string('owner_type')->default('human');
            $table->timestamps();

            $table->unique(['entity_type', 'external_natural_key']);
            $table->index(['family', 'entity_type']);
        });

        Schema::create('entity_revisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('entity_id')->constrained('entities')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('lifecycle_state');
            $table->string('title');
            $table->longText('body')->nullable();
            $table->jsonb('attributes');
            $table->foreignUuid('source_revision_id')->constrained('revisions')->restrictOnDelete();
            $table->decimal('confidence', 4, 3);
            $table->timestampTz('published_at')->nullable();
            $table->uuid('superseded_by')->nullable();
            $table->timestamps();

            $table->unique(['entity_id', 'version']);
            $table->index(['entity_id', 'lifecycle_state']);
            $table->index(['lifecycle_state', 'published_at']);
        });

        Schema::table('entity_revisions', function (Blueprint $table): void {
            $table->foreign('superseded_by')->references('id')->on('entity_revisions')->restrictOnDelete();
        });

        Schema::create('assertions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('subject_entity_revision_id')->constrained('entity_revisions')->restrictOnDelete();
            $table->string('predicate');
            $table->uuid('object_entity_revision_id')->nullable();
            $table->jsonb('literal_value')->nullable();
            $table->jsonb('scope');
            $table->decimal('confidence', 4, 3);
            $table->string('method');
            $table->string('actor_id');
            $table->string('actor_type');
            $table->string('lifecycle_state');
            $table->timestampTz('valid_from')->nullable();
            $table->timestampTz('valid_to')->nullable();
            $table->timestamps();

            $table->foreign('object_entity_revision_id')->references('id')->on('entity_revisions')->restrictOnDelete();
            $table->index('subject_entity_revision_id');
            $table->index('object_entity_revision_id');
            $table->index(['lifecycle_state', 'valid_from', 'valid_to']);
        });

        Schema::create('assertion_evidence', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('assertion_id')->constrained('assertions')->cascadeOnDelete();
            $table->string('evidence_type');
            $table->uuid('chunk_id')->nullable();
            $table->uuid('artifact_id')->nullable();
            $table->uuid('event_id')->nullable();
            $table->decimal('weight', 4, 3);
            $table->timestamps();

            $table->foreign('chunk_id')->references('id')->on('chunks')->restrictOnDelete();
            $table->foreign('artifact_id')->references('id')->on('artifacts')->restrictOnDelete();
            $table->foreign('event_id')->references('id')->on('observations')->restrictOnDelete();
            $table->unique(['assertion_id', 'chunk_id']);
            $table->unique(['assertion_id', 'artifact_id']);
            $table->unique(['assertion_id', 'event_id']);
        });

        $this->createPublishedViews();

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            $this->addPostgresConstraintsAndIndexes();
        }
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS published_assertions');
        DB::statement('DROP VIEW IF EXISTS published_entity_revisions');
        Schema::dropIfExists('assertion_evidence');
        Schema::dropIfExists('assertions');
        Schema::dropIfExists('entity_revisions');
        Schema::dropIfExists('entities');

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('DROP FUNCTION IF EXISTS prevent_last_assertion_evidence_removal()');
            DB::statement('DROP FUNCTION IF EXISTS enforce_assertion_evidence()');
        }
    }

    private function createPublishedViews(): void
    {
        DB::statement(<<<'SQL'
            CREATE VIEW published_entity_revisions AS
            SELECT entity_revisions.*, entities.entity_type, entities.family, entities.scope AS entity_scope,
                   entities.external_natural_key, entities.owner_id, entities.owner_type
            FROM entity_revisions
            INNER JOIN entities ON entities.id = entity_revisions.entity_id
            WHERE entity_revisions.lifecycle_state = 'published'
        SQL);

        DB::statement(<<<'SQL'
            CREATE VIEW published_assertions AS
            SELECT assertions.*,
                   subject.entity_id AS subject_entity_id,
                   object.entity_id AS object_entity_id
            FROM assertions
            INNER JOIN entity_revisions AS subject ON subject.id = assertions.subject_entity_revision_id
            LEFT JOIN entity_revisions AS object ON object.id = assertions.object_entity_revision_id
            WHERE assertions.lifecycle_state = 'published'
              AND subject.lifecycle_state = 'published'
              AND (object.id IS NULL OR object.lifecycle_state = 'published')
        SQL);
    }

    private function addPostgresConstraintsAndIndexes(): void
    {
        DB::statement("ALTER TABLE entities ADD CONSTRAINT entities_family_check CHECK (family IN ('contexto', 'software', 'conhecimento', 'proveniencia', 'governanca'))");
        DB::statement("ALTER TABLE entities ADD CONSTRAINT entities_type_check CHECK (entity_type IN ('Organização', 'Espaço', 'Aplicação', 'Módulo', 'Processo', 'Responsável', 'Integração', 'Repositório', 'Endpoint', 'Controller', 'Service', 'Job', 'Evento', 'Tabela', 'Migration', 'Tela', 'Regra', 'Documento', 'ADR', 'FAQ', 'Comentário', 'Issue', 'Release', 'Vídeo', 'Print', 'Diagrama', 'Fonte', 'Artefato', 'Revisão', 'Chunk', 'Evidência', 'Observação', 'Execução', 'Classificação', 'Política', 'Aprovação', 'Auditoria'))");
        DB::statement("ALTER TABLE entity_revisions ADD CONSTRAINT entity_revisions_lifecycle_check CHECK (lifecycle_state IN ('draft', 'candidate', 'review', 'published', 'superseded', 'retracted'))");
        DB::statement('ALTER TABLE entity_revisions ADD CONSTRAINT entity_revisions_confidence_check CHECK (confidence >= 0 AND confidence <= 1)');
        DB::statement("ALTER TABLE entity_revisions ADD CONSTRAINT entity_revisions_supersession_check CHECK ((lifecycle_state = 'superseded') = (superseded_by IS NOT NULL))");
        DB::statement("ALTER TABLE assertions ADD CONSTRAINT assertions_predicate_check CHECK (predicate IN ('contains', 'belongs_to', 'implements', 'calls', 'exposes', 'consumes', 'produces', 'reads', 'writes', 'governs', 'documents', 'depends_on', 'supersedes', 'owned_by', 'related_to', 'contradicts'))");
        DB::statement("ALTER TABLE assertions ADD CONSTRAINT assertions_lifecycle_check CHECK (lifecycle_state IN ('draft', 'candidate', 'review', 'published', 'superseded', 'retracted'))");
        DB::statement('ALTER TABLE assertions ADD CONSTRAINT assertions_confidence_check CHECK (confidence >= 0 AND confidence <= 1)');
        DB::statement('ALTER TABLE assertions ADD CONSTRAINT assertions_object_or_literal_check CHECK ((object_entity_revision_id IS NULL) <> (literal_value IS NULL))');
        DB::statement('ALTER TABLE assertions ADD CONSTRAINT assertions_validity_check CHECK (valid_to IS NULL OR valid_from IS NULL OR valid_to >= valid_from)');
        DB::statement("ALTER TABLE assertion_evidence ADD CONSTRAINT assertion_evidence_type_check CHECK ((evidence_type = 'chunk' AND chunk_id IS NOT NULL AND artifact_id IS NULL AND event_id IS NULL) OR (evidence_type = 'artifact' AND artifact_id IS NOT NULL AND chunk_id IS NULL AND event_id IS NULL) OR (evidence_type = 'event' AND event_id IS NOT NULL AND chunk_id IS NULL AND artifact_id IS NULL))");
        DB::statement('ALTER TABLE assertion_evidence ADD CONSTRAINT assertion_evidence_weight_check CHECK (weight > 0 AND weight <= 1)');
        DB::statement('CREATE INDEX entities_scope_gin ON entities USING GIN (scope)');
        DB::statement('CREATE INDEX entity_revisions_attributes_gin ON entity_revisions USING GIN (attributes)');
        DB::statement('CREATE INDEX assertions_scope_gin ON assertions USING GIN (scope)');
        DB::statement('CREATE INDEX assertions_subject_predicate_index ON assertions (subject_entity_revision_id, predicate)');
        DB::statement('CREATE INDEX assertions_object_predicate_index ON assertions (object_entity_revision_id, predicate) WHERE object_entity_revision_id IS NOT NULL');
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION enforce_assertion_evidence() RETURNS trigger AS $$
            BEGIN
                IF NEW.method <> 'human_declared'
                   AND NOT EXISTS (SELECT 1 FROM assertion_evidence WHERE assertion_id = NEW.id) THEN
                    RAISE EXCEPTION 'assertions created by % require at least one evidence record', NEW.method;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);
        DB::statement(<<<'SQL'
            CREATE CONSTRAINT TRIGGER assertions_require_evidence
            AFTER INSERT OR UPDATE OF method ON assertions
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW EXECUTE FUNCTION enforce_assertion_evidence()
        SQL);
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_last_assertion_evidence_removal() RETURNS trigger AS $$
            BEGIN
                IF EXISTS (
                    SELECT 1 FROM assertions
                    WHERE id = OLD.assertion_id AND method <> 'human_declared'
                ) AND NOT EXISTS (
                    SELECT 1 FROM assertion_evidence WHERE assertion_id = OLD.assertion_id
                ) THEN
                    RAISE EXCEPTION 'assertions created by a non-human method require at least one evidence record';
                END IF;
                RETURN OLD;
            END;
            $$ LANGUAGE plpgsql
        SQL);
        DB::statement(<<<'SQL'
            CREATE CONSTRAINT TRIGGER assertion_evidence_cannot_be_removed
            AFTER DELETE OR UPDATE OF assertion_id ON assertion_evidence
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW EXECUTE FUNCTION prevent_last_assertion_evidence_removal()
        SQL);
    }
};
