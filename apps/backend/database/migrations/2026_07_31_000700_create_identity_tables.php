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
        Schema::create('identity_grants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->jsonb('scope');
            $table->jsonb('permissions');
            $table->string('classification_ceiling')->default('interno');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['user_id', 'active']);
        });

        Schema::create('machine_credentials', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('actor_type');
            $table->string('token_hash', 64)->unique();
            $table->jsonb('grants');
            $table->timestampTz('expires_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['actor_type', 'expires_at']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE identity_grants ADD CONSTRAINT identity_grants_role_check CHECK (role IN ('leitor', 'contribuidor', 'aprovador_de_dominio', 'administrador_de_plataforma'))");
            DB::statement("ALTER TABLE identity_grants ADD CONSTRAINT identity_grants_classification_check CHECK (classification_ceiling IN ('publico', 'interno', 'restrito', 'confidencial'))");
            DB::statement("ALTER TABLE machine_credentials ADD CONSTRAINT machine_credentials_actor_type_check CHECK (actor_type IN ('ci', 'plugin', 'worker', 'service'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('machine_credentials');
        Schema::dropIfExists('identity_grants');
    }
};
