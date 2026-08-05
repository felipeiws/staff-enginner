<?php

declare(strict_types=1);

namespace Atlas\Modules\Copilot\Application;

use App\Observability\MetricRecorder;
use Atlas\Modules\Copilot\Domain\ModelProvider;
use Atlas\Modules\Copilot\Domain\ModelRequest;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use Throwable;

final readonly class CopilotExecutionService
{
    public function __construct(private DatabaseManager $database, private ModelProvider $provider, private ExternalProviderContentPolicy $contentPolicy, private MetricRecorder $metrics) {}

    /** @return array{execution_id:string,text:string,usage:array<string,int>,cost:float,cost_currency:string,model:string,model_version:string,stop_reason:string,reasoning_trace:array<string,mixed>} */
    public function execute(CopilotRequest $request): array
    {
        // Security invariant: this is deliberately before prompt assembly and provider selection.
        // The deterministic provider is local to the seeded integration environment;
        // it never transmits context to an external model provider.
        if (config('copilot.provider') !== 'deterministic') {
            $this->contentPolicy->assertAllowed($request->context);
        }
        $existing = $this->database->table('executions')->where('idempotency_key', $request->idempotencyKey)->first();
        if ($existing !== null) {
            $row = (array) $existing;

            return $this->completedResult(['id' => $row['id'], 'status' => $row['status'], 'result' => $row['result'], 'parameters' => $row['parameters'], 'cost' => $row['cost'], 'cost_currency' => $row['cost_currency']]);
        }

        $config = config('copilot');
        $maxOutput = min($request->maxOutputTokens ?? $config['max_output_tokens'], $config['max_output_tokens']);
        $input = $this->inputFor($request);
        // A Unicode character is a conservative upper bound for tokens, unlike an optimistic byte/4 estimate.
        $inputUpperBound = mb_strlen($request->systemPrompt.$input, 'UTF-8');
        if ($inputUpperBound > $config['max_input_tokens'] || $inputUpperBound + $maxOutput > $config['max_request_tokens']) {
            throw new CopilotTokenLimitExceeded('A solicitação excede o limite de tokens configurado.');
        }
        $this->assertPeriodBudget($config, $maxOutput, $inputUpperBound);

        $executionId = (string) Str::uuid();
        $snapshot = ['provider' => $config['provider'], 'model' => $config['model'], 'model_version' => $config['model_version'], 'temperature' => $config['temperature'], 'limits' => ['max_input_tokens' => $config['max_input_tokens'], 'max_output_tokens' => $maxOutput, 'max_request_tokens' => $config['max_request_tokens']], 'timeout_seconds' => $config['timeout_seconds'], 'retry_attempts' => $config['retry_attempts'], 'pricing_usd_per_mtok' => $config['anthropic']['pricing']];
        $this->database->table('executions')->insert(['id' => $executionId, 'revision_id' => $request->revisionId, 'executor_type' => 'model', 'executor_name' => $config['provider'], 'executor_version' => $config['model_version'], 'parameters' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'idempotency_key' => $request->idempotencyKey, 'status' => 'running', 'created_at' => now(), 'updated_at' => now()]);
        $startedAt = hrtime(true);
        try {
            $response = $this->provider->generate(new ModelRequest($executionId, $request->idempotencyKey, $config['model'], $config['model_version'], $config['temperature'], $maxOutput, $request->systemPrompt, [['role' => 'user', 'content' => $input]], $config['timeout_seconds'], $config['retry_attempts']));
            $cost = $this->cost($response->usage, ['input' => (float) $config['anthropic']['pricing']['input'], 'cache_write' => (float) $config['anthropic']['pricing']['cache_write'], 'cache_read' => (float) $config['anthropic']['pricing']['cache_read'], 'output' => (float) $config['anthropic']['pricing']['output']]);
            // This trace is assembled by deterministic pipeline stages before the
            // model call. It is retained with the execution, never inferred from
            // the generated answer.
            $result = ['text' => $response->text, 'usage' => $response->usage, 'stop_reason' => $response->stopReason, 'provider_request_id' => $response->providerRequestId, 'reasoning_trace' => $request->reasoningTrace, 'candidate_sources' => $request->candidateSources];
            $this->database->table('executions')->where('id', $executionId)->update(['status' => 'completed', 'duration_ms' => (int) ((hrtime(true) - $startedAt) / 1000000), 'cost' => $cost, 'cost_currency' => 'USD', 'result' => json_encode($result, JSON_THROW_ON_ERROR), 'updated_at' => now()]);
            $this->metrics->observe('atlas_copilot_cost_usd', $cost, ['model' => $config['model']]);
            $this->metrics->observe('atlas_copilot_tokens_total', (float) array_sum($response->usage), ['model' => $config['model']]);

            return ['execution_id' => $executionId, 'text' => $response->text, 'usage' => $response->usage, 'cost' => $cost, 'cost_currency' => 'USD', 'model' => $config['model'], 'model_version' => $config['model_version'], 'stop_reason' => $response->stopReason, 'reasoning_trace' => $request->reasoningTrace];
        } catch (Throwable $exception) {
            $this->database->table('executions')->where('id', $executionId)->update(['status' => 'failed', 'duration_ms' => (int) ((hrtime(true) - $startedAt) / 1000000), 'result' => json_encode(['error' => $exception::class], JSON_THROW_ON_ERROR), 'updated_at' => now()]);
            throw new CopilotExecutionFailed('A execução do Copiloto falhou.', previous: $exception);
        }
    }

    private function inputFor(CopilotRequest $request): string
    {
        return "Pergunta:\n{$request->question}\n\nContexto com proveniência:\n".implode("\n\n", array_map(static fn (array $item): string => "[origem={$item['origin']}; revisão={$item['revision']}; método={$item['method']}; confiança={$item['confidence']}]\n{$item['content']}", $request->context));
    }

    /** @param array<string,mixed> $config */
    private function assertPeriodBudget(array $config, int $maxOutput, int $inputUpperBound): void
    {
        $from = now()->subHours($config['period_hours']);
        $usage = $this->database->table('executions')->where('executor_name', $config['provider'])->where('created_at', '>=', $from)->where('status', 'completed')->get(['result', 'cost']);
        $tokens = 0;
        $cost = 0.0;
        foreach ($usage as $item) {
            $result = json_decode((string) ($item->result ?? '{}'), true) ?: [];
            $tokens += array_sum((array) ($result['usage'] ?? []));
            $cost += (float) $item->cost;
        } $estimatedCost = (($inputUpperBound * $config['anthropic']['pricing']['input']) + ($maxOutput * $config['anthropic']['pricing']['output'])) / 1000000;
        if ($tokens + $inputUpperBound + $maxOutput > $config['period_token_limit'] || $cost + $estimatedCost > $config['period_cost_limit_usd']) {
            throw new CopilotBudgetExceeded('O orçamento do período para o Copiloto foi atingido.');
        }
    }

    /**
     * @param  array{input_tokens:int,cache_creation_input_tokens:int,cache_read_input_tokens:int,output_tokens:int}  $usage
     * @param  array{input:float,cache_write:float,cache_read:float,output:float}  $pricing
     */
    private function cost(array $usage, array $pricing): float
    {
        return round(($usage['input_tokens'] * $pricing['input'] + $usage['cache_creation_input_tokens'] * $pricing['cache_write'] + $usage['cache_read_input_tokens'] * $pricing['cache_read'] + $usage['output_tokens'] * $pricing['output']) / 1000000, 6);
    }

    /**
     * @param  array{id:mixed,status:mixed,result:mixed,parameters:mixed,cost:mixed,cost_currency:mixed}  $execution
     * @return array{execution_id:string,text:string,usage:array<string,int>,cost:float,cost_currency:string,model:string,model_version:string,stop_reason:string,reasoning_trace:array<string,mixed>}
     */
    private function completedResult(array $execution): array
    {
        if ($execution['status'] !== 'completed') {
            throw new CopilotExecutionFailed('Já existe uma execução não concluída para esta chave de idempotência.');
        } $result = json_decode((string) $execution['result'], true, flags: JSON_THROW_ON_ERROR);
        $parameters = json_decode((string) $execution['parameters'], true, flags: JSON_THROW_ON_ERROR);

        return ['execution_id' => (string) $execution['id'], 'text' => (string) $result['text'], 'usage' => $result['usage'], 'cost' => (float) $execution['cost'], 'cost_currency' => (string) $execution['cost_currency'], 'model' => (string) $parameters['model'], 'model_version' => (string) $parameters['model_version'], 'stop_reason' => (string) $result['stop_reason'], 'reasoning_trace' => $result['reasoning_trace'] ?? []];
    }
}
