<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Http;

use App\Http\Api\Authorization\KnowledgeReadAccessFactory;
use App\Http\Api\OpenApi\ApiOperation;
use Atlas\Modules\KnowledgeEngine\Application\GraphReadService;
use Atlas\Modules\KnowledgeEngine\Application\KnowledgeReadAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[ApiOperation('Consultar o Knowledge Graph diagnóstico e suas lacunas', ['Conhecimento', 'Knowledge Graph'], [200 => 'Modelo de leitura do grafo com proveniência', 403 => 'Escopo não autorizado', 422 => 'Parâmetros inválidos'])]
final class GraphReadController
{
    public function __construct(private readonly GraphReadService $readModel, private readonly KnowledgeReadAccessFactory $accessFactory) {}

    public function neighborhood(Request $request): JsonResponse
    {
        $query = $request->validate(['entity' => ['required', 'uuid'], 'depth' => ['sometimes', 'integer', 'min:1', 'max:3'], 'group_by' => ['sometimes', 'in:type']]);

        return response()->json($this->readModel->neighborhood($this->access($request), $query['entity'], (int) ($query['depth'] ?? 1), $query['group_by'] ?? 'type'));
    }

    public function orphans(Request $request): JsonResponse
    {
        return response()->json($this->readModel->orphans($this->access($request)));
    }

    public function node(Request $request, string $id): JsonResponse
    {
        return response()->json($this->readModel->node($this->access($request), $id));
    }

    public function diagnosis(Request $request): JsonResponse
    {
        $query = $request->validate(['scope' => ['required', 'uuid']]);

        return response()->json($this->readModel->diagnosis($this->access($request), $query['scope']));
    }

    private function access(Request $request): KnowledgeReadAccess
    {
        return $this->accessFactory->fromRequest($request);
    }
}
