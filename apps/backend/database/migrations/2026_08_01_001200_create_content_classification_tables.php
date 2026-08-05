<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('content_classifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('entity_revision_id')->nullable()->unique();
            $table->uuid('source_revision_id')->nullable()->index();
            $table->string('classification');
            $table->string('source_classification');
            $table->string('decision_method');
            $table->decimal('confidence', 4, 3);
            $table->boolean('contains_personal_data')->default(false);
            $table->boolean('quarantined')->default(false);
            $table->jsonb('findings');
            $table->jsonb('provenance');
            $table->timestamps();
        });
        Schema::create('security_alerts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('entity_revision_id')->nullable()->index();
            $table->string('recipient_id');
            $table->string('recipient_type');
            $table->string('kind');
            $table->string('status')->default('open');
            $table->jsonb('details');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('security_alerts'); Schema::dropIfExists('content_classifications'); }
};
