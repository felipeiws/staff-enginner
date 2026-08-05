<?php

declare(strict_types=1);

namespace App\Http\Api\OpenApi;

use Illuminate\Http\JsonResponse;

final class OpenApiController
{
    public function __invoke(OpenApiGenerator $generator): JsonResponse
    {
        return response()->json($generator->generate(), 200, ['Content-Type' => 'application/vnd.oai.openapi+json;version=3.1']);
    }
}
