<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Http;

use App\Http\Api\Problem\ApiProblemResponse;
use Atlas\Modules\Identity\Application\MachineTokenService;
use Atlas\Modules\Identity\Application\PrincipalFactory;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticatePrincipal
{
    public function __construct(private readonly MachineTokenService $tokens, private readonly PrincipalFactory $principals) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $principal = $token !== null ? $this->tokens->authenticate($token) : null;
        if ($principal === null && $request->user() !== null) {
            $principal = $this->principals->forUser($request->user());
        }
        if ($principal === null) {
            if ($request->is('api/v1/*')) {
                return ApiProblemResponse::make(401, 'identity.unauthenticated', 'Autenticação obrigatória para este recurso.', $request, (string) $request->attributes->get('correlation_id'));
            }

            return response()->json(['message' => 'Não autenticado.'], 401);
        }

        $request->attributes->set('identity.principal', $principal);

        return $next($request);
    }
}
