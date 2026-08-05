<?php

declare(strict_types=1);

namespace App\Http\Api\Governance;

use App\Http\Api\OpenApi\ApiOperation;
use Atlas\Modules\Governance\Application\KnowledgeGapService;
use Atlas\Modules\Identity\Application\AuthorizationService;
use Atlas\Modules\Identity\Domain\AuthorizationRequest;
use Atlas\Modules\Identity\Domain\Principal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class KnowledgeGapController
{
    public function __construct(private readonly KnowledgeGapService $gaps, private readonly AuthorizationService $authorization) {}

    #[ApiOperation('Listar perguntas sem resposta agrupadas', ['Governança'], [200 => 'Lacunas com período e severidade por cor', 403 => 'Escopo não autorizado'])]
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['organization' => ['required', 'string', 'max:120'], 'application' => ['nullable', 'string', 'max:120'], 'module' => ['nullable', 'string', 'max:120']]);
        $scope = $this->scope($data);
        /** @var Principal $principal */ $principal = $request->attributes->get('identity.principal');
        $this->authorization->assertAuthorized($principal, new AuthorizationRequest('knowledge.read', $scope, 'publico'));

        return response()->json(['data' => $this->gaps->listed($scope)]);
    }

    #[ApiOperation('Atribuir lacuna e abrir tarefa de documentação', ['Governança'], [201 => 'Tarefa de documentação aberta', 403 => 'Escopo não autorizado', 404 => 'Lacuna inexistente'])]
    public function assign(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['owner_id' => ['required', 'string', 'max:255'], 'organization' => ['required', 'string', 'max:120'], 'application' => ['nullable', 'string', 'max:120'], 'module' => ['nullable', 'string', 'max:120']]);
        $scope = $this->scope($data);
        /** @var Principal $principal */ $principal = $request->attributes->get('identity.principal');
        $this->authorization->assertAuthorized($principal, new AuthorizationRequest('knowledge.approve', $scope, 'interno'));

        return response()->json($this->gaps->assign($id, $data['owner_id'], $principal->id, (string) $request->attributes->get('correlation_id')), 201);
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
