<?php

declare(strict_types=1);

namespace App\Http\Api\Middleware;

use App\Observability\MetricRecorder;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/** Emits an OpenTelemetry-compatible W3C trace context without recording request content. */
final class ObserveRequest
{
    public function __construct(private readonly MetricRecorder $metrics) {}

    public function handle(Request $request, Closure $next): Response
    {
        $started = hrtime(true);
        $incoming = (string) $request->header('traceparent', '');
        $traceId = preg_match('/^00-([a-f0-9]{32})-[a-f0-9]{16}-[01]$/i', $incoming, $match) === 1 ? strtolower($match[1]) : bin2hex(random_bytes(16));
        $spanId = bin2hex(random_bytes(8));
        $request->attributes->set('trace_id', $traceId);
        $request->attributes->set('span_id', $spanId);
        $response = $next($request);
        $duration = (hrtime(true) - $started) / 1_000_000;
        $route = $request->route()?->getName() ?? 'unmatched';
        $labels = ['route' => $route, 'status' => (string) $response->getStatusCode()];
        $this->metrics->observe('atlas_http_server_duration_milliseconds', $duration, $labels);
        if ($route === 'api.v1.search.index') {
            $this->metrics->observe('atlas_search_latency_milliseconds', $duration);
        }
        if ($route === 'api.v1.copilot.ask') {
            $this->metrics->observe('atlas_copilot_latency_milliseconds', $duration);
        }
        Log::info('otel.span.completed', ['trace_id' => $traceId, 'span_id' => $spanId, 'correlation_id' => $request->attributes->get('correlation_id'), 'route' => $route, 'duration_ms' => round($duration, 3), 'status_code' => $response->getStatusCode()]);
        $response->headers->set('traceparent', "00-{$traceId}-{$spanId}-01");

        return $response;
    }
}
