<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::create('observability_metric_samples', function (Blueprint $table): void { $table->id(); $table->string('name', 120); $table->double('value'); $table->jsonb('labels'); $table->timestampTz('observed_at'); $table->index(['name', 'observed_at']); }); }
    public function down(): void { Schema::dropIfExists('observability_metric_samples'); }
};
