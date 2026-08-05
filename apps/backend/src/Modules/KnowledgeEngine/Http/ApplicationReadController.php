<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Http;

use App\Http\Api\Authorization\KnowledgeReadAccessFactory;
use App\Http\Api\OpenApi\ApiOperation;
use Atlas\Modules\KnowledgeEngine\Application\ApplicationReadService;
use Atlas\Modules\KnowledgeEngine\Application\KnowledgeReadAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[ApiOperation('Consultar organizações, aplicações e módulos publicados', ['Conhecimento'], [200 => 'Modelo de leitura de aplicação', 403 => 'Escopo não autorizado', 422 => 'Navegação inválida'])]
final class ApplicationReadController
{
    public function __construct(private readonly ApplicationReadService $readModel, private readonly KnowledgeReadAccessFactory $accessFactory) {}

    public function organization(Request $request, string $id): JsonResponse
    {
        return response()->json($this->readModel->organization($this->access($request), $id));
    }

    public function applications(Request $request): JsonResponse
    {
        return response()->json(['applications' => $this->readModel->applications($this->access($request))]);
    }

    public function home(Request $request): JsonResponse
    {
        $validated = $request->validate(['organization' => ['required', 'string', 'max:120']]);
        return response()->json($this->readModel->home($this->access($request), $validated['organization']));
    }

    public function application(Request $request, string $id): JsonResponse
    {
        return response()->json($this->readModel->application($this->access($request), $id));
    }

    public function modules(Request $request, string $id): JsonResponse
    {
        return response()->json(['modules' => $this->readModel->modules($this->access($request), $id)]);
    }

    public function tree(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate(['nav' => ['required', 'in:technical,functional']]);

        return response()->json($this->readModel->tree($this->access($request), $id, $validated['nav']));
    }

    private function access(Request $request): KnowledgeReadAccess
    {
        return $this->accessFactory->fromRequest($request);
    }
}
