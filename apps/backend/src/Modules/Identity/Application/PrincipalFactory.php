<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Application;

use App\Models\User;
use Atlas\Modules\Identity\Domain\Principal;
use Illuminate\Support\Facades\DB;

final class PrincipalFactory
{
    public function forUser(User $user): Principal
    {
        $grants = [];
        foreach (DB::table('identity_grants')->where('user_id', $user->getKey())->where('active', true)->get() as $grant) {
            $grants[] = [
                'role' => (string) $grant->role,
                'scope' => $this->scope((string) $grant->scope),
                'permissions' => $this->permissions((string) $grant->role, $this->permissionsFromJson((string) $grant->permissions)),
                'classification_ceiling' => (string) $grant->classification_ceiling,
            ];
        }

        return new Principal((string) $user->getKey(), 'human', $user->name, $this->initials($user->name), $grants);
    }

    /**
     * @param  list<string>  $explicit
     * @return list<string>
     */
    private function permissions(string $role, array $explicit): array
    {
        $permissions = [];
        foreach ([...$this->rolePermissions($role), ...$explicit] as $permission) {
            if (! in_array($permission, $permissions, true)) {
                $permissions[] = $permission;
            }
        }

        return $permissions;
    }

    /** @return list<string> */
    private function rolePermissions(string $role): array
    {
        return match ($role) {
            'leitor' => ['knowledge.read'],
            'contribuidor' => ['knowledge.read', 'knowledge.contribute'],
            'aprovador_de_dominio' => ['knowledge.read', 'knowledge.contribute', 'knowledge.approve'],
            'administrador_de_plataforma' => ['*'],
            default => [],
        };
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return strtoupper(substr((string) ($parts[0] ?? '?'), 0, 1).substr((string) ($parts[count($parts) - 1] ?? ''), 0, 1));
    }

    /** @return array<string, string> */
    private function scope(string $json): array
    {
        $value = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($value)) {
            throw new \UnexpectedValueException('Escopo de identidade inválido.');
        }
        $scope = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && is_string($item)) {
                $scope[$key] = $item;
            }
        }

        return $scope;
    }

    /** @return list<string> */
    private function permissionsFromJson(string $json): array
    {
        $value = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($value)) {
            throw new \UnexpectedValueException('Permissões de identidade inválidas.');
        }
        $permissions = [];
        foreach ($value as $permission) {
            if (is_string($permission)) {
                $permissions[] = $permission;
            }
        }

        return $permissions;
    }
}
