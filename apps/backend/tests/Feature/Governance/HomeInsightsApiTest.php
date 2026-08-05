<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('retorna os campos visuais de divergência, lacuna e sugestão para a Home', function (): void {
    $user = User::factory()->create();
    DB::table('identity_grants')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'role' => 'leitor', 'scope' => json_encode(['organization' => 'nordika']), 'permissions' => json_encode(['knowledge.read']), 'classification_ceiling' => 'interno', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('knowledge_gaps')->insert(['id' => (string) Str::uuid(), 'question' => 'estorno de frete', 'semantic_key' => 'frete', 'scope' => json_encode(['organization' => 'nordika']), 'scope_key' => hash('sha256', json_encode(['organization' => 'nordika'])), 'occurrence_count' => 31, 'first_occurred_at' => now(), 'window_started_at' => now(), 'last_occurred_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    $this->actingAs($user)->getJson('/api/v1/insights')->assertOk()->assertJsonStructure(['insights' => [['type', 'color', 'title', 'description', 'action']]])->assertJsonPath('insights.0.type', 'Lacuna')->assertJsonPath('insights.0.color', 'var(--warn)');
});
