<?php

declare(strict_types=1);

namespace App\Http\Api\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class PropagateCorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->header('X-Correlation-Id');
        $correlationId = is_string($incoming) && preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,127}$/', $incoming) === 1 ? $incoming : (string) Str::uuid();
        $request->attributes->set('correlation_id', $correlationId);
        $response = $next($request);
        $response->headers->set('X-Correlation-Id', $correlationId);

        return $response;
    }
}
