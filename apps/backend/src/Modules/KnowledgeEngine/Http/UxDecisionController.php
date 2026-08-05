<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Http;

use App\Http\Api\Authorization\KnowledgeReadAccessFactory;
use App\Http\Api\OpenApi\ApiOperation;
use Atlas\Modules\KnowledgeEngine\Application\UxDecisionReadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[ApiOperation('Consultar decisões de UX publicadas no grafo', ['Conhecimento', 'Governança'], [200 => 'Decisões com proveniência', 403 => 'Escopo não autorizado'])]
final class UxDecisionController
{
    public function __construct(private readonly UxDecisionReadService $readModel, private readonly KnowledgeReadAccessFactory $accessFactory) {}

    public function __invoke(Request $request): JsonResponse
    {
        return response()->json($this->readModel->all($this->accessFactory->fromRequest($request)));
    }
}
