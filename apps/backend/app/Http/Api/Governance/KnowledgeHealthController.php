<?php

declare(strict_types=1);

namespace App\Http\Api\Governance;

use App\Http\Api\OpenApi\ApiOperation;
use Atlas\Modules\Governance\Application\KnowledgeHealthService;
use Atlas\Modules\Identity\Application\AuthorizationService;
use Atlas\Modules\Identity\Domain\AuthorizationRequest;
use Atlas\Modules\Identity\Domain\Principal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class KnowledgeHealthController
{
    public function __construct(private readonly KnowledgeHealthService $health, private readonly AuthorizationService $authorization) {}

    #[ApiOperation('Consultar saúde do conhecimento calculada', ['Governança'], [200 => 'Métricas, dívida, lacunas e insight', 403 => 'Escopo não autorizado'])]
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate(['scope' => ['required', 'uuid'], 'period' => ['required', 'in:30d']]);
        $scope = $this->health->scopeFor($data['scope']);
        if ($scope === null) {
            abort(404);
        }
        /** @var Principal $principal */ $principal = $request->attributes->get('identity.principal');
        $this->authorization->assertAuthorized($principal, new AuthorizationRequest('knowledge.read', $scope, 'interno'));

        return response()->json($this->health->show($data['scope'], $data['period'], $principal));
    }
}
