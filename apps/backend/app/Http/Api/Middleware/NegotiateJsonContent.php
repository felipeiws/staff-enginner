<?php

declare(strict_types=1);

namespace App\Http\Api\Middleware;

use App\Http\Api\Problem\ApiProblemResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class NegotiateJsonContent
{
    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = (string) $request->attributes->get('correlation_id');
        if (! $request->acceptsJson() && ! $request->acceptsAnyContentType()) {
            return ApiProblemResponse::make(406, 'request.not_acceptable', 'A API aceita somente representações JSON.', $request, $correlationId);
        }
        if (in_array($request->getMethod(), ['POST', 'PUT', 'PATCH'], true) && $request->getContent() !== '' && ! $request->isJson()) {
            return ApiProblemResponse::make(415, 'request.unsupported_media_type', 'Comandos devem usar Content-Type application/json.', $request, $correlationId);
        }

        return $next($request);
    }
}
