<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Http;

use Atlas\Modules\KnowledgeEngine\Application\HealthCheckService;
use Illuminate\Http\JsonResponse;

final readonly class HealthController
{
    public function __construct(private HealthCheckService $healthCheck) {}

    public function __invoke(): JsonResponse
    {
        $health = $this->healthCheck->check();

        return response()->json($health, $health['status'] === 'ok' ? 200 : 503);
    }
}
