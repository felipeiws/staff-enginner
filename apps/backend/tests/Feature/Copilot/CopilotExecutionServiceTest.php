<?php

declare(strict_types=1);

use Atlas\Modules\Copilot\Application\CopilotExecutionService;
use Atlas\Modules\Copilot\Application\CopilotRequest;
use Atlas\Modules\Copilot\Application\ExternalProviderContentDenied;
use Atlas\Modules\Copilot\Domain\ModelProvider;
use Atlas\Modules\Copilot\Domain\ModelRequest;
use Atlas\Modules\Copilot\Domain\ModelResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

final class FakeModelProvider implements ModelProvider
{
    public int $calls = 0;
    public function generate(ModelRequest $request): ModelResponse
    {
        $this->calls++;
        return new ModelResponse('Resposta candidata, sujeita à revisão humana.', ['input_tokens' => 10, 'cache_creation_input_tokens' => 2, 'cache_read_input_tokens' => 3, 'output_tokens' => 5], 'end_turn', 'req_fake_1');
    }
}
function copilotRequest(string $key = 'copilot-test-1', string $classification = 'interno'): CopilotRequest
{
    return new CopilotRequest($key, 'user-1', '40000000-0000-4000-8000-000000000001', 'Você responde somente com base nas evidências.', 'Como funciona o cancelamento?', [['content' => 'O serviço valida a elegibilidade antes da transição.', 'classification' => $classification, 'origin' => 'git://atlas/orders.md', 'revision' => 'a1b2c3', 'method' => 'parser', 'confidence' => 0.91]]);
}
beforeEach(function (): void {
    config()->set('copilot.period_token_limit', 100000);
    config()->set('copilot.period_cost_limit_usd', 10.0);
    foreach ([['40000000-0000-4000-8000-000000000010', 'interno', true], ['40000000-0000-4000-8000-000000000011', 'restrito', false]] as [$id, $level, $external]) {
        DB::table('classifications')->insert(['id' => $id, 'level' => $level, 'search_exposure_allowed' => true, 'display_exposure_allowed' => true, 'external_provider_allowed' => $external, 'exposure_rules' => '{}', 'created_at' => now(), 'updated_at' => now()]);
    }
});
it('registra configuração versionada, uso e custo e torna a repetição idempotente', function (): void {
    $fake = new FakeModelProvider();
    app()->instance(ModelProvider::class, $fake);
    $service = app(CopilotExecutionService::class);
    $first = $service->execute(copilotRequest());
    $second = $service->execute(copilotRequest());
    $execution = DB::table('executions')->where('id', $first['execution_id'])->first();
    $parameters = json_decode((string) $execution->parameters, true, flags: JSON_THROW_ON_ERROR);
    expect($fake->calls)->toBe(1)->and($second['execution_id'])->toBe($first['execution_id'])->and($execution->status)->toBe('completed')->and((float) $execution->cost)->toBe(0.000567)->and($parameters['model'])->toBe('claude-opus-4-1-20250805')->and($parameters['limits']['max_output_tokens'])->toBe(2048)->and(json_encode($parameters))->not->toContain('ANTHROPIC_API_KEY');
});
it('bloqueia classificação antes de montar o prompt ou chamar o provedor', function (): void {
    $fake = new FakeModelProvider();
    app()->instance(ModelProvider::class, $fake);
    expect(fn() => app(CopilotExecutionService::class)->execute(copilotRequest('copilot-test-blocked', 'restrito')))->toThrow(ExternalProviderContentDenied::class);
    expect($fake->calls)->toBe(0)->and(DB::table('executions')->count())->toBe(0);
});
it('permite o provedor determinístico local no ambiente integrado sem liberar conteúdo externo', function (): void {
    config()->set('copilot.provider', 'deterministic');
    $fake = new FakeModelProvider();
    app()->instance(ModelProvider::class, $fake);

    $result = app(CopilotExecutionService::class)->execute(copilotRequest('copilot-local-seed', 'restrito'));

    expect($result['execution_id'])->not->toBe('')->and($fake->calls)->toBe(1);
});
it('respeita o orçamento de tokens do período antes de executar', function (): void {
    config()->set('copilot.period_token_limit', 1);
    $fake = new FakeModelProvider();
    app()->instance(ModelProvider::class, $fake);
    expect(fn() => app(CopilotExecutionService::class)->execute(copilotRequest('copilot-budget')))->toThrow(\Atlas\Modules\Copilot\Application\CopilotBudgetExceeded::class);
    expect($fake->calls)->toBe(0);
});
