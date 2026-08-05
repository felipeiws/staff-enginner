<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Application;

use Atlas\Modules\Identity\Domain\Principal;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class MachineTokenService
{
    /**
     * @param  list<array{scope:array<string,string>,permissions:list<string>,classification_ceiling:string}>  $grants
     * @return array{id:string,token:string,expires_at:DateTimeImmutable}
     */
    public function issue(string $name, string $actorType, array $grants, ?DateTimeImmutable $expiresAt = null): array
    {
        if (! in_array($actorType, ['ci', 'plugin', 'worker', 'service'], true)) {
            throw new \InvalidArgumentException('Tipo de ator de máquina inválido.');
        }
        $maximumExpiry = now()->addMinutes((int) config('identity.machine_token_ttl_minutes', 15));
        $expiresAt ??= DateTimeImmutable::createFromMutable($maximumExpiry);
        if ($expiresAt > DateTimeImmutable::createFromMutable($maximumExpiry)) {
            throw new \InvalidArgumentException('Token de máquina excede a duração máxima permitida.');
        }

        $token = Str::random(80);
        $id = (string) Str::uuid();
        DB::table('machine_credentials')->insert([
            'id' => $id, 'name' => $name, 'actor_type' => $actorType, 'token_hash' => hash('sha256', $token),
            'grants' => json_encode($grants, JSON_THROW_ON_ERROR), 'expires_at' => $expiresAt,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['id' => $id, 'token' => $token, 'expires_at' => $expiresAt];
    }

    public function authenticate(string $token): ?Principal
    {
        $credential = DB::table('machine_credentials')->where('token_hash', hash('sha256', $token))->whereNull('revoked_at')->where('expires_at', '>', now())->first();
        if ($credential === null) {
            return null;
        }

        $grants = $this->grants((string) $credential->grants);

        return new Principal((string) $credential->id, $credential->actor_type, $credential->name, '••', $grants);
    }

    public function revoke(string $id): void
    {
        DB::table('machine_credentials')->where('id', $id)->whereNull('revoked_at')->update(['revoked_at' => now(), 'updated_at' => now()]);
    }

    /** @return list<array{scope:array<string,string>,permissions:list<string>,classification_ceiling:string}> */
    private function grants(string $json): array
    {
        $value = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($value)) {
            throw new \UnexpectedValueException('Concessões de máquina inválidas.');
        }
        $grants = [];
        foreach ($value as $grant) {
            if (! is_array($grant) || ! isset($grant['scope'], $grant['permissions'], $grant['classification_ceiling']) || ! is_array($grant['scope']) || ! is_array($grant['permissions']) || ! is_string($grant['classification_ceiling'])) {
                throw new \UnexpectedValueException('Concessão de máquina inválida.');
            }
            $scope = [];
            foreach ($grant['scope'] as $key => $item) {
                if (is_string($key) && is_string($item)) {
                    $scope[$key] = $item;
                }
            }
            $permissions = [];
            foreach ($grant['permissions'] as $permission) {
                if (is_string($permission)) {
                    $permissions[] = $permission;
                }
            }
            $grants[] = ['scope' => $scope, 'permissions' => $permissions, 'classification_ceiling' => $grant['classification_ceiling']];
        }

        return $grants;
    }
}
