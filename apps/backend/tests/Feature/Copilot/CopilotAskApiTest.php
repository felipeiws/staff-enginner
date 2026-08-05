<?php

declare(strict_types=1);

use App\Models\User;
use Atlas\Modules\Copilot\Domain\ModelProvider;
use Atlas\Modules\Copilot\Domain\ModelRequest;
use Atlas\Modules\Copilot\Domain\ModelResponse;
use Atlas\Modules\Search\Domain\HybridSearchIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

final class CopilotAskIndexFixture implements HybridSearchIndex
{
    public bool $called = false;
    public function search(string $query, array $filters, ?array $queryEmbedding, int $limit): array
    {
        $this->called = true;
        $row = ['id' => 'processo-1', 'entity_id' => 'ent-nao-projetada', 'entity_type' => 'Processo', 'title' => 'Cancelar Pedido de Compra', 'body' => 'O cancelamento libera somente o saldo não faturado.', 'freshness' => 'revisado há 3 dias', 'confidence' => .92, 'classification' => 'interno', 'origin_uri' => 'https://git.example/processos/cancelar.md', 'source_revision' => 'a91f3ce', 'method' => 'parser@1', '_score' => 10];
        return ['lexical' => [$row], 'vector' => [$row], 'facets' => ['application' => [], 'freshness' => [], 'owner' => []]];
    }
}
final class CopilotAskModelProvider implements ModelProvider
{
    public function __construct(public bool $cites = true) {}
    public function generate(ModelRequest $request): ModelResponse
    {
        $text = $this->cites ? 'O cancelamento libera somente o saldo não faturado. revision_id: a91f3ce; uri: https://git.example/processos/cancelar.md' : 'O cancelamento libera somente o saldo não faturado.';
        return new ModelResponse($text, ['input_tokens' => 10, 'cache_creation_input_tokens' => 0, 'cache_read_input_tokens' => 0, 'output_tokens' => 10], 'end_turn', 'req-copilot-ask');
    }
}
function copilotAskUser(string $organization = 'nordika'): User
{
    $user = User::factory()->create();
    DB::table('identity_grants')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'role' => 'leitor', 'scope' => json_encode(['organization' => $organization]), 'permissions' => json_encode(['knowledge.read']), 'classification_ceiling' => 'interno', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    return $user;
}
beforeEach(function (): void {
    config()->set('copilot.period_token_limit', 100000);
    config()->set('copilot.period_cost_limit_usd', 10.0);
    DB::table('classifications')->insert(['id' => (string) Str::uuid(), 'level' => 'interno', 'search_exposure_allowed' => true, 'display_exposure_allowed' => true, 'external_provider_allowed' => true, 'exposure_rules' => '{}', 'created_at' => now(), 'updated_at' => now()]);
    $this->copilotIndex = new CopilotAskIndexFixture();
    app()->instance(HybridSearchIndex::class, $this->copilotIndex);
});
it('retorna o contrato da tela com fontes, confiança, ações e citações obrigatórias', function (): void {
    app()->instance(ModelProvider::class, new CopilotAskModelProvider());
    $response = $this->actingAs(copilotAskUser())->postJson('/api/v1/copilot/ask', ['question' => 'Como funciona o cancelamento?', 'organization' => 'nordika'], ['Idempotency-Key' => 'copilot-ask-001']);
    $response->assertOk()->assertJsonPath('confidence', 92)->assertJsonPath('source_count', 1)->assertJsonPath('sources.0.type.name', 'Processo')->assertJsonPath('sources.0.provenance.revision_id', 'a91f3ce')->assertJsonPath('sources.0.provenance.uri', 'https://git.example/processos/cancelar.md')->assertJsonPath('actions.0.label', 'Aprofundar no processo')->assertJsonPath('actions.3.label', 'Reportar erro')->assertJsonPath('reasoning_trace.retrieval.fused_candidates', 1)->assertJsonPath('reasoning_trace.retrieval.selected_candidates', 1)->assertJsonPath('reasoning_trace.retrieval.discarded_candidates', 0)->assertJsonPath('reasoning_trace.rerank.decision', 'not_configured');
    $execution = DB::table('executions')->where('idempotency_key', 'copilot-ask-001')->first();
    $persisted = json_decode((string) $execution->result, true, flags: JSON_THROW_ON_ERROR);
    expect($persisted['reasoning_trace']['retrieval']['fused_candidates'])->toBe(1)
        ->and($persisted['reasoning_trace']['retrieval']['selected_candidates'])->toBe(1)
        ->and($persisted['reasoning_trace']['rerank']['decision'])->toBe('not_configured');
});
it('rejeita resposta do modelo sem citation de revisão e URI', function (): void {
    app()->instance(ModelProvider::class, new CopilotAskModelProvider(false));
    $this->actingAs(copilotAskUser())->postJson('/api/v1/copilot/ask', ['question' => 'Como funciona o cancelamento?', 'organization' => 'nordika'], ['Idempotency-Key' => 'copilot-ask-002'])->assertUnprocessable()->assertJsonPath('code', 'copilot.insufficient_evidence');
});
it('bloqueia evidência abaixo do limiar configurável e registra uma lacuna', function (): void {
    config()->set('knowledge_gaps.minimum_evidence_score', .95);
    app()->instance(ModelProvider::class, new CopilotAskModelProvider());
    $this->actingAs(copilotAskUser())->postJson('/api/v1/copilot/ask', ['question' => 'Como funciona o cancelamento?', 'organization' => 'nordika'], ['Idempotency-Key' => 'copilot-ask-004'])
        ->assertUnprocessable()->assertJsonPath('code', 'copilot.insufficient_evidence');

    expect(DB::table('knowledge_gaps')->where('occurrence_count', 1)->exists())->toBeTrue()
        ->and(DB::table('knowledge_gap_occurrences')->where('origin', 'copilot.abstention')->exists())->toBeTrue();
});
it('respeita o escopo antes de recuperar evidências', function (): void {
    app()->instance(ModelProvider::class, new CopilotAskModelProvider());
    $this->actingAs(copilotAskUser('outra'))->postJson('/api/v1/copilot/ask', ['question' => 'Como funciona o cancelamento?', 'organization' => 'nordika'], ['Idempotency-Key' => 'copilot-ask-003'])->assertForbidden();
    expect($this->copilotIndex->called)->toBeFalse();
});
