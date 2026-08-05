<?php

declare(strict_types=1);

namespace Atlas\Modules\Copilot\Infrastructure;

use Atlas\Modules\Copilot\Application\CopilotExecutionFailed;
use Atlas\Modules\Copilot\Domain\ModelProvider;
use Atlas\Modules\Copilot\Domain\ModelRequest;
use Atlas\Modules\Copilot\Domain\ModelResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;
use Throwable;

/** Anthropic Messages API adapter. It never logs request bodies or credentials. */
final readonly class AnthropicModelProvider implements ModelProvider
{
    public function __construct(private HttpFactory $http) {}

    public function generate(ModelRequest $request): ModelResponse
    {
        $config = config('copilot.anthropic');
        $key = $config['api_key'];
        if (! is_string($key) || $key === '') {
            throw new CopilotExecutionFailed('ANTHROPIC_API_KEY não está configurada.');
        }
        $system = ['type' => 'text', 'text' => $request->systemPrompt];
        if ($config['cache_system_prompt']) {
            $system['cache_control'] = ['type' => 'ephemeral'];
        }
        try {
            $response = $this->http->baseUrl(rtrim((string) $config['base_url'], '/'))->acceptJson()->asJson()->withHeaders(['x-api-key' => $key, 'anthropic-version' => $config['api_version'], 'X-Atlas-Idempotency-Key' => $request->idempotencyKey])->timeout($request->timeoutSeconds)->retry($request->retryAttempts, 200, static function (Throwable $exception): bool {
                return $exception instanceof ConnectionException || ($exception instanceof RequestException && ($exception->response->status() === 429 || $exception->response->serverError()));
            })->post('/v1/messages', ['model' => $request->model, 'max_tokens' => $request->maxTokens, 'temperature' => $request->temperature, 'system' => [$system], 'messages' => $request->messages]);
            $response->throw();
        } catch (ConnectionException|RequestException $exception) {
            throw new CopilotExecutionFailed('A API Anthropic não respondeu à execução.', previous: $exception);
        }
        $body = $response->json();
        $text = '';
        foreach ((array) ($body['content'] ?? []) as $block) {
            if (($block['type'] ?? null) === 'text') {
                $text .= (string) ($block['text'] ?? '');
            }
        }
        $usage = (array) ($body['usage'] ?? []);
        return new ModelResponse($text, ['input_tokens' => (int) ($usage['input_tokens'] ?? 0), 'cache_creation_input_tokens' => (int) ($usage['cache_creation_input_tokens'] ?? 0), 'cache_read_input_tokens' => (int) ($usage['cache_read_input_tokens'] ?? 0), 'output_tokens' => (int) ($usage['output_tokens'] ?? 0)], (string) ($body['stop_reason'] ?? 'unknown'), (string) $response->header('request-id'));
    }
}
