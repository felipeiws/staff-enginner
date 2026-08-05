<?php

declare(strict_types=1);

use App\Models\User;
use Atlas\Modules\Search\Domain\HybridSearchIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

final class SearchIndexFixture implements HybridSearchIndex
{
    public bool $called = false;

    /** @param array<string,mixed> $filters */
    public function search(string $query, array $filters, ?array $queryEmbedding, int $limit): array
    {
        $this->called = true;

        return ['lexical' => [['id' => 'regra-1', 'entity_id' => 'ent-2', 'entity_type' => 'Regra', 'title' => 'RN-142', 'body' => 'Não pode cancelar após faturamento.', '_score' => 30], ['id' => 'processo-1', 'entity_id' => 'ent-1', 'entity_type' => 'Processo', 'title' => 'Cancelar pedido', 'body' => 'O faturamento parcial impede cancelar o pedido.', 'path' => 'ERP Financeiro › Compras › Processos', 'freshness' => 'Atualizado há 3 dias', 'owner' => 'Time Suprimentos', 'confidence' => .91, 'origin_uri' => 'https://git.example/processo.md', 'source_revision' => 'a91f3ce', 'method' => 'parser@1', 'related_entities' => ['RN-142'], '_score' => 42]], 'vector' => [['id' => 'regra-1', 'entity_id' => 'ent-2', 'entity_type' => 'Regra', 'title' => 'RN-142', 'body' => 'Não pode cancelar após faturamento.', '_score' => .8], ['id' => 'processo-1', 'entity_id' => 'ent-1', 'entity_type' => 'Processo', 'title' => 'Cancelar pedido', 'body' => 'O faturamento parcial impede cancelar o pedido.', '_score' => .7]], 'facets' => ['application' => [['value' => 'ERP Financeiro', 'count' => 2]], 'freshness' => [['value' => 'Últimos 7 dias', 'count' => 1]], 'owner' => [['value' => 'Time Suprimentos', 'count' => 1]]]];
    }
}

function searchUser(string $organization = 'nordika'): User
{
    $user = User::factory()->create();
    DB::table('identity_grants')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'role' => 'leitor', 'scope' => json_encode(['organization' => $organization]), 'permissions' => json_encode(['knowledge.read']), 'classification_ceiling' => 'interno', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);

    return $user;
}

beforeEach(function (): void {
    $this->fixtureIndex = new SearchIndexFixture;
    app()->instance(HybridSearchIndex::class, $this->fixtureIndex);
});

it('fuses ranks, preserves score provenance, presents prototype fields and facets', function (): void {
    $response = $this->actingAs(searchUser())->getJson('/api/v1/search?q=faturamento&organization=nordika');
    $response->assertOk()->assertJsonPath('results.0.id', 'regra-1')->assertJsonPath('results.0.type.name', 'Regra')->assertJsonPath('results.0.type.color', 'warn')->assertJsonPath('results.0.excerpt.hit', 'faturamento')->assertJsonPath('type_counts.Tudo', 2)->assertJsonPath('type_counts.Processo', 1)->assertJsonPath('facets.application.0.value', 'ERP Financeiro')->assertJsonPath('results.0.ranking.sources.lexical.rank', 1)->assertJsonPath('results.0.ranking.sources.vector.rank', 1);
});

it('authorizes scope before any index retrieval', function (): void {
    $this->actingAs(searchUser('outra'))->getJson('/api/v1/search?q=faturamento&organization=nordika')->assertForbidden();
    expect($this->fixtureIndex->called)->toBeFalse();
});

it('records a zero-result signal', function (): void {
    app()->instance(HybridSearchIndex::class, new class implements HybridSearchIndex
    {
        public function search(string $query, array $filters, ?array $queryEmbedding, int $limit): array
        {
            return ['lexical' => [], 'vector' => [], 'facets' => ['application' => [], 'freshness' => [], 'owner' => []]];
        }
    });
    Log::spy();
    $this->actingAs(searchUser())->getJson('/api/v1/search?q=ausente&organization=nordika')->assertOk()->assertJsonPath('type_counts.Tudo', 0);
    Log::shouldHaveReceived('info')->with('search.zero_result', Mockery::type('array'))->once();
});

it('does not expose restricted results, counts, or facets when an adapter returns them', function (): void {
    app()->instance(HybridSearchIndex::class, new class implements HybridSearchIndex {
        public function search(string $query, array $filters, ?array $queryEmbedding, int $limit): array { return ['lexical' => [['id' => 'public', 'entity_type' => 'Documento', 'classification' => 'interno', 'title' => 'Visível'], ['id' => 'restricted', 'entity_type' => 'Regra', 'classification' => 'restrito', 'title' => 'NÃO VAZAR']], 'vector' => [], 'facets' => ['application' => [['value' => 'secreto', 'count' => 1]], 'freshness' => [], 'owner' => []]]; }
    });
    $json = $this->actingAs(searchUser())->getJson('/api/v1/search?q=teste&organization=nordika')->assertOk()->json();
    expect(json_encode($json))->not->toContain('NÃO VAZAR')->and($json['type_counts'])->toBe(['Tudo' => 1, 'Documento' => 1])->and($json['facets']['application'])->toBe([]);
});
