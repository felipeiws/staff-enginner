<?php

declare(strict_types=1);

namespace Atlas\Modules\Copilot\Infrastructure;

use Atlas\Modules\Copilot\Domain\ModelProvider;
use Atlas\Modules\Copilot\Domain\ModelRequest;
use Atlas\Modules\Copilot\Domain\ModelResponse;

/** Deterministic adapter reserved for the seeded integration environment. */
final readonly class DeterministicModelProvider implements ModelProvider
{
    public function generate(ModelRequest $request): ModelResponse
    {
        preg_match_all('/\[origem=(?<uri>[^;]+); revisão=(?<revision>[^;]+);/u', $request->messages[0]['content'], $citations, PREG_SET_ORDER);
        $evidence = array_map(fn (array $citation): string => "revision_id: {$citation['revision']}; uri: {$citation['uri']}", $citations);

        return new ModelResponse('A reserva de estoque é liberada de forma assíncrona após o cancelamento. '.implode(' ', $evidence), ['input_tokens' => 0, 'cache_creation_input_tokens' => 0, 'cache_read_input_tokens' => 0, 'output_tokens' => 0], 'end_turn', 'deterministic:seed');
    }
}
