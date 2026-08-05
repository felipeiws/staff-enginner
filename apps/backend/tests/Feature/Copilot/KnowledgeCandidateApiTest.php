<?php

declare(strict_types=1);

use App\Models\User;
use Atlas\Modules\Copilot\Domain\ModelProvider;
use Atlas\Modules\Copilot\Domain\ModelRequest;
use Atlas\Modules\Copilot\Domain\ModelResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

final class KnowledgeCandidateModel implements ModelProvider
{
    public function generate(ModelRequest $request): ModelResponse
    {
        return new ModelResponse('FAQ candidata: o cancelamento retorna 409. revision_id: ticket-18; uri: https://tickets.example/18', ['input_tokens' => 10, 'cache_creation_input_tokens' => 0, 'cache_read_input_tokens' => 0, 'output_tokens' => 10], 'end_turn', 'candidate-req');
    }
}

function candidateId(): string
{
    return (string) Str::uuid();
}
function candidateUser(): User
{
    $user = User::factory()->create();
    DB::table('identity_grants')->insert(['id' => candidateId(), 'user_id' => $user->id, 'role' => 'contribuidor', 'scope' => json_encode(['organization' => 'nordika']), 'permissions' => json_encode(['knowledge.read']), 'classification_ceiling' => 'interno', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);

    return $user;
}
function candidateExecution(): string
{
    $source = candidateId();
    $artifact = candidateId();
    $revision = candidateId();
    $execution = candidateId();
    DB::table('sources')->insert(['id' => $source, 'type' => 'ticket', 'scope' => json_encode(['organization' => 'nordika']), 'classification' => 'interno', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('artifacts')->insert(['id' => $artifact, 'source_id' => $source, 'external_identifier' => 'ticket-18', 'uri' => 'https://tickets.example/18', 'content_hash' => hash('sha256', 'ticket-18'), 'media_type' => 'text/plain', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('revisions')->insert(['id' => $revision, 'artifact_id' => $artifact, 'source_revision' => 'ticket-18', 'observed_at' => now(), 'checksum' => hash('sha256', 'ticket-18-r1'), 'metadata' => '{}', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('executions')->insert(['id' => $execution, 'executor_type' => 'model', 'executor_name' => 'fake', 'executor_version' => '1', 'parameters' => '{}', 'idempotency_key' => 'candidate-source-'.$execution, 'status' => 'completed', 'result' => json_encode(['candidate_sources' => [['entity_id' => '', 'entity_type' => 'Issue', 'content' => 'Clientes recebem 409 ao cancelar.', 'scope' => ['organization' => 'nordika'], 'origin_uri' => 'https://tickets.example/18', 'source_revision' => 'ticket-18', 'method' => 'ticket_connector@1', 'confidence' => .9, 'classification' => 'interno']]], JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);

    return $execution;
}
beforeEach(function (): void {
    config()->set('copilot.period_token_limit', 100000);
    config()->set('copilot.period_cost_limit_usd', 10.0);
    DB::table('classifications')->insert(['id' => candidateId(), 'level' => 'interno', 'search_exposure_allowed' => true, 'display_exposure_allowed' => true, 'external_provider_allowed' => true, 'exposure_rules' => '{}', 'created_at' => now(), 'updated_at' => now()]);
    app()->instance(ModelProvider::class, new KnowledgeCandidateModel);
});

it('cria FAQ candidata com todas as fontes citáveis e proposta pendente para aprovação', function (): void {
    $execution = candidateExecution();
    $result = $this->actingAs(candidateUser())->postJson('/api/v1/copilot/knowledge-candidates', ['execution_id' => $execution, 'kind' => 'faq', 'organization' => 'nordika'], ['Idempotency-Key' => 'candidate-faq-001']);
    $result->assertCreated()->assertJsonPath('kind', 'faq')->assertJsonPath('sources.0.revision', 'ticket-18');
    $revision = DB::table('entity_revisions')->where('id', $result->json('revision_id'))->first();
    $proposal = json_decode((string) DB::table('approvals')->where('id', $result->json('approval_id'))->value('proposal'), true, flags: JSON_THROW_ON_ERROR);
    expect(DB::table('entities')->where('id', $result->json('entity_id'))->value('entity_type'))->toBe('FAQ')
        ->and($revision->lifecycle_state)->toBe('review')
        ->and($proposal['source_citations'][0]['uri'])->toBe('https://tickets.example/18')
        ->and(DB::table('audit_log')->where('entity_revision_id', $result->json('revision_id'))->where('action', 'knowledge_candidate.proposed')->exists())->toBeTrue();
});

it('proíbe a criação autônoma de Processo pela IA', function (): void {
    $execution = candidateExecution();
    $this->actingAs(candidateUser())->postJson('/api/v1/copilot/knowledge-candidates', ['execution_id' => $execution, 'kind' => 'process_update', 'organization' => 'nordika'], ['Idempotency-Key' => 'candidate-process-001'])
        ->assertUnprocessable()->assertJsonPath('code', 'knowledge_candidate.invalid');
    expect(DB::table('entities')->where('entity_type', 'Processo')->count())->toBe(0);
});
