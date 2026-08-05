<?php

declare(strict_types=1);

use App\Http\Api\Middleware\EnforceIdempotency;
use App\Http\Api\Middleware\NegotiateJsonContent;
use App\Http\Api\Middleware\ObserveRequest;
use App\Http\Api\Middleware\PropagateCorrelationId;
use App\Http\Api\Middleware\RateLimitByActorAndScope;
use App\Http\Api\Problem\ApiProblemResponse;
use App\Http\Api\Problem\DomainProblem;
use Atlas\Modules\Governance\Application\ApprovalAccessDenied;
use Atlas\Modules\Identity\Domain\AccessDenied;
use Atlas\Modules\Identity\Http\AuthenticatePrincipal;
use Atlas\Modules\KnowledgeEngine\Application\KnowledgeReadAccessDenied;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'identity.principal' => AuthenticatePrincipal::class,
            'api.correlation' => PropagateCorrelationId::class,
            'api.observe' => ObserveRequest::class,
            'api.negotiate' => NegotiateJsonContent::class,
            'api.rate-limit' => RateLimitByActorAndScope::class,
            'api.idempotency' => EnforceIdempotency::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/v1/*')) {
                return null;
            }

            $correlationId = (string) $request->attributes->get('correlation_id', Str::uuid());
            if ($exception instanceof DomainProblem) {
                return ApiProblemResponse::make($exception->status, $exception->domainCode, $exception->getMessage(), $request, $correlationId, $exception->errors);
            }
            if ($exception instanceof ValidationException) {
                return ApiProblemResponse::make(422, 'request.validation_failed', 'A requisição não passou na validação.', $request, $correlationId, $exception->errors());
            }
            if ($exception instanceof AuthenticationException) {
                return ApiProblemResponse::make(401, 'identity.unauthenticated', 'Autenticação obrigatória para este recurso.', $request, $correlationId);
            }
            if ($exception instanceof AccessDenied) {
                return ApiProblemResponse::make(403, 'identity.access_denied', 'O principal não possui acesso a este recurso.', $request, $correlationId);
            }
            if ($exception instanceof KnowledgeReadAccessDenied) {
                return ApiProblemResponse::make(403, 'identity.access_denied', 'O principal não possui acesso a este recurso.', $request, $correlationId);
            }
            if ($exception instanceof ApprovalAccessDenied) {
                return ApiProblemResponse::make(403, 'identity.access_denied', 'O principal não possui acesso a este recurso.', $request, $correlationId);
            }

            return ApiProblemResponse::make(500, 'internal.unexpected_error', 'Ocorreu um erro inesperado.', $request, $correlationId);
        });
    })->create();
