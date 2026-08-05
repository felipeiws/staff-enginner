<?php

declare(strict_types=1);

namespace App\Http\Api\Middleware;

use App\Http\Api\Problem\ApiProblemResponse;
use Atlas\Modules\Identity\Domain\Principal;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

final class EnforceIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }
        $correlationId = (string) $request->attributes->get('correlation_id');
        $key = $request->header('Idempotency-Key');
        if (! is_string($key) || trim($key) === '' || strlen($key) > 255) {
            return ApiProblemResponse::make(400, 'command.idempotency_key_required', 'O header Idempotency-Key é obrigatório para comandos.', $request, $correlationId);
        }
        $principal = $request->attributes->get('identity.principal');
        $actor = $principal instanceof Principal ? $principal->id : 'anonymous:'.$request->ip();
        $cacheKey = 'api-idempotency:'.hash('sha256', $actor.'|'.$request->getMethod().'|'.$request->path().'|'.$key);
        $fingerprint = hash('sha256', $request->getMethod().'|'.$request->fullUrl().'|'.$request->getContent());
        $existing = Cache::get($cacheKey);
        if (is_array($existing)) {
            if (($existing['fingerprint'] ?? null) !== $fingerprint) {
                return ApiProblemResponse::make(409, 'command.idempotency_key_reused', 'A chave de idempotência já foi usada com outra requisição.', $request, $correlationId);
            }
            if (($existing['state'] ?? null) === 'pending') {
                return ApiProblemResponse::make(409, 'command.idempotency_in_progress', 'Já existe um comando em processamento com esta chave.', $request, $correlationId);
            }
            $response = response((string) $existing['body'], (int) $existing['status'], (array) $existing['headers']);
            $response->headers->set('X-Idempotent-Replay', 'true');

            return $response;
        }
        Cache::put($cacheKey, ['state' => 'pending', 'fingerprint' => $fingerprint], now()->addHours(24));
        $response = $next($request);
        if ($response->getStatusCode() < 500) {
            Cache::put($cacheKey, ['state' => 'completed', 'fingerprint' => $fingerprint, 'status' => $response->getStatusCode(), 'body' => $response->getContent(), 'headers' => ['Content-Type' => $response->headers->get('Content-Type', 'application/json'), 'X-Correlation-Id' => $correlationId]], now()->addHours(24));
        } else {
            Cache::forget($cacheKey);
        }

        return $response;
    }
}
