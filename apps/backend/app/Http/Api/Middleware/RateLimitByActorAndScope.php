<?php

declare(strict_types=1);

namespace App\Http\Api\Middleware;

use App\Http\Api\Problem\ApiProblemResponse;
use Atlas\Modules\Identity\Domain\Principal;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

final class RateLimitByActorAndScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $principal = $request->attributes->get('identity.principal');
        $actor = $principal instanceof Principal ? $principal->id : 'anonymous:'.$request->ip();
        $scope = $principal instanceof Principal ? json_encode($principal->grants[0]['scope'] ?? [], JSON_THROW_ON_ERROR) : 'public';
        $key = 'api:v1:'.hash('sha256', $actor.'|'.$scope);
        $maxAttempts = 120;
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);

            return ApiProblemResponse::make(429, 'request.rate_limited', 'O limite de requisições deste ator e escopo foi excedido.', $request, (string) $request->attributes->get('correlation_id'))
                ->header('Retry-After', (string) $seconds);
        }
        RateLimiter::hit($key, 60);
        $response = $next($request);
        $response->headers->set('X-RateLimit-Limit', (string) $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', (string) max(0, $maxAttempts - RateLimiter::attempts($key)));

        return $response;
    }
}
