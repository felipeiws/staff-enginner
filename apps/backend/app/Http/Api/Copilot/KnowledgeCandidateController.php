<?php

declare(strict_types=1);

namespace App\Http\Api\Copilot;

use App\Http\Api\OpenApi\ApiOperation;
use Atlas\Modules\Copilot\Application\KnowledgeCandidateService;
use Atlas\Modules\Identity\Domain\Principal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[ApiOperation('Criar proposta de FAQ ou atualização de Processo a partir de fontes citadas', ['Copiloto', 'Governança'], [201 => 'Proposta candidata para revisão humana', 403 => 'Escopo não autorizado', 422 => 'Fontes ou alvo inválidos'])]
final class KnowledgeCandidateController
{
    public function __invoke(Request $request, KnowledgeCandidateService $candidates): JsonResponse
    {
        $data = $request->validate(['execution_id' => ['required', 'uuid'], 'kind' => ['required', 'in:faq,process_update'], 'organization' => ['required', 'string', 'max:120'], 'application' => ['nullable', 'string', 'max:120'], 'module' => ['nullable', 'string', 'max:120'], 'process_entity_id' => ['nullable', 'uuid']]);
        /** @var Principal $principal */
        $principal = $request->attributes->get('identity.principal');
        $scope = array_filter(['organization' => $data['organization'], 'application' => $data['application'] ?? null, 'module' => $data['module'] ?? null], static fn(mixed $value): bool => is_string($value) && $value !== '');
        try {
            return response()->json($candidates->propose(['id' => $principal->id, 'grants' => $principal->grants], (string) $request->header('Idempotency-Key'), (string) $request->attributes->get('correlation_id'), $data['execution_id'], $data['kind'], $scope, $data['process_entity_id'] ?? null), 201);
        } catch (\DomainException $exception) {
            return response()->json(['code' => 'knowledge_candidate.invalid', 'detail' => $exception->getMessage(), 'correlation_id' => $request->attributes->get('correlation_id')], 422);
        }
    }
}
