<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Http;

use App\Http\Api\Authorization\KnowledgeReadAccessFactory;
use App\Http\Api\OpenApi\ApiOperation;
use Atlas\Modules\KnowledgeEngine\Application\EntityDetailReadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[ApiOperation('Consultar detalhe versionado de uma entidade de conhecimento', ['Conhecimento'], [200 => 'Projeção de detalhe com proveniência e frescor', 403 => 'Escopo não autorizado'])]
final class EntityDetailController
{
    public function __construct(private readonly EntityDetailReadService $readModel, private readonly KnowledgeReadAccessFactory $accessFactory) {}

    public function __invoke(Request $request, string $id): JsonResponse
    {
        return response()->json($this->readModel->entity($this->accessFactory->fromRequest($request), $id));
    }
}
