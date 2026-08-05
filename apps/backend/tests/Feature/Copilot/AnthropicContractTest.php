<?php

declare(strict_types=1);

use Atlas\Modules\Copilot\Domain\ModelRequest;
use Atlas\Modules\Copilot\Infrastructure\AnthropicModelProvider;

it('calls the Anthropic Messages API when explicitly enabled', function (): void {
    $response = app(AnthropicModelProvider::class)->generate(new ModelRequest('contract-execution', 'contract-idempotency', (string) config('copilot.model'), (string) config('copilot.model_version'), 0.0, 16, 'Responda concisamente.', [['role' => 'user', 'content' => 'Diga apenas: ok']], 30, 0));
    expect($response->text)->not->toBe('')->and($response->usage['output_tokens'])->toBeGreaterThan(0);
})->group('optional', 'anthropic')->skip(fn(): bool => env('ANTHROPIC_CONTRACT_TESTS') !== '1' || ! is_string(env('ANTHROPIC_API_KEY')) || env('ANTHROPIC_API_KEY') === '', 'Defina ANTHROPIC_CONTRACT_TESTS=1 e ANTHROPIC_API_KEY para consumir a API real.');
