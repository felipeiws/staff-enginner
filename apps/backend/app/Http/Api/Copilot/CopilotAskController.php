<?php

declare(strict_types=1);

namespace App\Http\Api\Copilot;

use App\Http\Api\OpenApi\ApiOperation;
use Atlas\Contracts\Dto\KnowledgeGapOccurrence;
use Atlas\Contracts\Port\KnowledgeGapRecorder;
use Atlas\Modules\Copilot\Application\CopilotAskService;
use Atlas\Modules\Copilot\Application\CopilotInsufficientEvidence;
use Atlas\Modules\Identity\Domain\Principal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[ApiOperation('Perguntar ao Copiloto com evidências citáveis', ['Copiloto'], [200 => 'Resposta candidata com fontes', 403 => 'Escopo não autorizado', 422 => 'Evidências insuficientes ou resposta sem citações'])]
final class CopilotAskController
{
    public function __invoke(Request $request, CopilotAskService $copilot, KnowledgeGapRecorder $gaps): JsonResponse
    {
        $data = $request->validate(['question' => ['required', 'string', 'max:2000'], 'organization' => ['required', 'string', 'max:120'], 'application' => ['nullable', 'string', 'max:120'], 'module' => ['nullable', 'string', 'max:120'], 'embedding' => ['nullable', 'array'], 'embedding.*' => ['numeric']]);
        /** @var Principal $principal */ $principal = $request->attributes->get('identity.principal');
        $scope = $this->scope($data);
        try {
            return response()->json($copilot->ask(['id' => $principal->id, 'grants' => $principal->grants], (string) $request->header('Idempotency-Key'), (string) $request->attributes->get('correlation_id'), (string) $data['question'], $scope, $data['embedding'] ?? null));
        } catch (CopilotInsufficientEvidence $exception) {
            $gaps->record(new KnowledgeGapOccurrence((string) $data['question'], $scope, 'copilot.abstention', 'copilot_evidence_threshold_v1', 1.0, (string) $request->attributes->get('correlation_id')));
            return response()->json(['code' => 'copilot.insufficient_evidence', 'detail' => $exception->getMessage(), 'correlation_id' => $request->attributes->get('correlation_id')], 422);
        }
    }

    /**
     * @param array<string,mixed> $data
     * @return array{organization:string,application?:string,module?:string}
     */
    private function scope(array $data): array
    {
        $scope = ['organization' => (string) $data['organization']];
        foreach (['application', 'module'] as $dimension) {
            if (isset($data[$dimension]) && is_string($data[$dimension]) && $data[$dimension] !== '') {
                $scope[$dimension] = $data[$dimension];
            }
        }

        return $scope;
    }
}
