<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Application;

use Atlas\Modules\Identity\Domain\AccessDenied;
use Atlas\Modules\Identity\Domain\AuthorizationRequest;
use Atlas\Modules\Identity\Domain\Principal;

final class AuthorizationService
{
    private const CLASSIFICATIONS = ['publico' => 0, 'interno' => 1, 'restrito' => 2, 'confidencial' => 3];

    /** @param callable(): mixed $retrieval */
    public function authorizeBeforeRetrieval(Principal $principal, AuthorizationRequest $request, callable $retrieval): mixed
    {
        $this->assertAuthorized($principal, $request);

        return $retrieval();
    }

    public function assertAuthorized(Principal $principal, AuthorizationRequest $request): void
    {
        foreach ($principal->grants as $grant) {
            if (! $this->allowsClassification($grant['classification_ceiling'], $request->classification)) {
                continue;
            }
            if (! $this->matchesScope($grant['scope'], $request->scope)) {
                continue;
            }
            if (in_array('*', $grant['permissions'], true) || in_array($request->permission, $grant['permissions'], true)) {
                return;
            }
        }

        throw new AccessDenied('Acesso negado para este escopo ou classificação.');
    }

    /**
     * @param  array<string, string>  $grantScope
     * @param  array<string, string>  $resourceScope
     */
    private function matchesScope(array $grantScope, array $resourceScope): bool
    {
        foreach (['organization', 'application', 'module'] as $dimension) {
            if (! isset($grantScope[$dimension])) {
                continue;
            }
            if (($resourceScope[$dimension] ?? null) !== $grantScope[$dimension]) {
                return false;
            }
        }

        return isset($grantScope['organization']);
    }

    private function allowsClassification(string $ceiling, string $requested): bool
    {
        return isset(self::CLASSIFICATIONS[$ceiling], self::CLASSIFICATIONS[$requested])
            && self::CLASSIFICATIONS[$ceiling] >= self::CLASSIFICATIONS[$requested];
    }
}
