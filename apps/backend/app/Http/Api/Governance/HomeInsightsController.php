<?php

declare(strict_types=1);

namespace App\Http\Api\Governance;

use App\Http\Api\Authorization\ApprovalAccessFactory;
use App\Http\Api\OpenApi\ApiOperation;
use Atlas\Modules\Governance\Application\HomeInsightsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[ApiOperation('Consultar insights de divergência, lacuna e sugestão da Home', ['Governança'], [200 => 'Insights da Home'])]
final class HomeInsightsController
{
    public function __invoke(Request $request, HomeInsightsService $insights, ApprovalAccessFactory $access): JsonResponse
    {
        return response()->json($insights->list($access->fromRequest($request)));
    }
}
