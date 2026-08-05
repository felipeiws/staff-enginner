<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Observability\PrometheusMetrics;
use Symfony\Component\HttpFoundation\Response;

final class MetricsController
{
    public function __invoke(PrometheusMetrics $metrics): Response
    {
        return response($metrics->render(), 200, ['Content-Type' => 'text/plain; version=0.0.4; charset=utf-8']);
    }
}
