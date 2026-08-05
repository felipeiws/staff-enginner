<?php

declare(strict_types=1);

use Atlas\Modules\KnowledgeEngine\Application\HealthCheckService;

it('reports all configured dependencies as healthy', function (): void {
    $healthCheck = Mockery::mock(HealthCheckService::class);
    $healthCheck->shouldReceive('check')->once()->andReturn([
        'status' => 'ok',
        'checks' => [
            'database' => 'ok',
            'redis' => 'ok',
            'storage' => 'ok',
        ],
    ]);
    app()->instance(HealthCheckService::class, $healthCheck);

    $this->getJson('/health')
        ->assertOk()
        ->assertExactJson([
            'status' => 'ok',
            'checks' => [
                'database' => 'ok',
                'redis' => 'ok',
                'storage' => 'ok',
            ],
        ]);
});

it('returns service unavailable when a dependency is unavailable', function (): void {
    $healthCheck = Mockery::mock(HealthCheckService::class);
    $healthCheck->shouldReceive('check')->once()->andReturn([
        'status' => 'degraded',
        'checks' => [
            'database' => 'ok',
            'redis' => 'failed',
            'storage' => 'ok',
        ],
    ]);
    app()->instance(HealthCheckService::class, $healthCheck);

    $this->getJson('/health')
        ->assertServiceUnavailable()
        ->assertJsonPath('checks.redis', 'failed');
});
