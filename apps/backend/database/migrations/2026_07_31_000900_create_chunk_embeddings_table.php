<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chunk_embeddings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('chunk_id')->constrained('chunks')->restrictOnDelete();
            $table->string('model');
            $table->string('model_version');
            $table->jsonb('parameters');
            $table->string('chunk_checksum');
            // SQLite is used by fast tests; PostgreSQL replaces this with pgvector below.
            $table->jsonb('embedding')->nullable();
            $table->unsignedInteger('dimensions');
            $table->decimal('cost', 14, 6)->nullable();
            $table->string('cost_currency', 3)->nullable();
            $table->foreignUuid('execution_id')->nullable()->constrained('executions')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['chunk_id', 'model', 'model_version']);
            $table->index(['model', 'model_version', 'chunk_checksum']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
            DB::statement('ALTER TABLE chunk_embeddings ALTER COLUMN embedding TYPE vector(' . ((int) config('search.embedding_dimensions', 1536)) . ') USING embedding::text::vector');
            DB::statement('CREATE INDEX chunk_embeddings_embedding_hnsw ON chunk_embeddings USING hnsw (embedding vector_cosine_ops)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chunk_embeddings');
    }
};
