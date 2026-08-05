<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assertion_relations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('source_assertion_id')->constrained('assertions')->restrictOnDelete();
            $table->foreignUuid('target_assertion_id')->constrained('assertions')->restrictOnDelete();
            $table->string('predicate');
            $table->timestamps();

            $table->unique(['source_assertion_id', 'target_assertion_id', 'predicate']);
            $table->index(['target_assertion_id', 'predicate']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assertion_relations');
    }
};
