<?php

declare(strict_types=1);

namespace Atlas\Modules\Search\Http;

use App\Http\Api\OpenApi\ApiOperation;
use Atlas\Modules\Identity\Domain\Principal;
use Atlas\Modules\Search\Application\HybridSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[ApiOperation('Buscar conhecimento publicado com fusão híbrida e facetas', ['Busca'], [200 => 'Resultados e evidências', 403 => 'Escopo não autorizado', 422 => 'Consulta inválida'])]
final class SearchController
{
    public function __invoke(Request $request, HybridSearchService $search): JsonResponse
    {
        $validated = $request->validate(['q' => ['required', 'string', 'max:500'], 'organization' => ['required', 'string', 'max:120'], 'application' => ['nullable', 'string', 'max:120'], 'module' => ['nullable', 'string', 'max:120'], 'entity_type' => ['nullable', 'array'], 'entity_type.*' => ['string', 'max:120'], 'freshness' => ['nullable', 'array'], 'freshness.*' => ['string', 'max:120'], 'owner' => ['nullable', 'array'], 'owner.*' => ['string', 'max:120'], 'page_size' => ['nullable', 'integer', 'min:1', 'max:100'], 'embedding' => ['nullable', 'array'], 'embedding.*' => ['numeric']]);
        /** @var Principal $principal */ $principal = $request->attributes->get('identity.principal');

        return response()->json($search->search($principal, $validated['q'], $validated, $validated['embedding'] ?? null));
    }
}
