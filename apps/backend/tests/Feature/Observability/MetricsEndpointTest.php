<?php

declare(strict_types=1);

use App\Observability\MetricRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('expõe métricas Prometheus sem exigir autenticação', function (): void {
    app(MetricRecorder::class)->observe('atlas_search_latency_milliseconds', 42.5, ['route' => 'search']);

    $this->get('/metrics')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8')
        ->assertSee('atlas_search_projection_lag_milliseconds', false)
        ->assertSee('atlas_search_latency_milliseconds{route="search"} 42.5', false);
});
