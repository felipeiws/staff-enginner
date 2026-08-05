<?php

declare(strict_types=1);

use App\Models\User;
use Atlas\Modules\Identity\Application\AuthorizationService;
use Atlas\Modules\Identity\Application\MachineTokenService;
use Atlas\Modules\Identity\Application\PrincipalFactory;
use Atlas\Modules\Identity\Domain\AccessDenied;
use Atlas\Modules\Identity\Domain\AuthorizationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function identityGrant(User $user, string $role, array $scope, string $classification = 'interno', array $permissions = []): void
{
    DB::table('identity_grants')->insert([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'role' => $role,
        'scope' => json_encode($scope, JSON_THROW_ON_ERROR),
        'permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
        'classification_ceiling' => $classification,
        'active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function protectedRequest(array $scope = ['organization' => 'nordika', 'application' => 'atlas'], string $classification = 'interno'): AuthorizationRequest
{
    return new AuthorizationRequest('knowledge.read', $scope, $classification);
}

it('authenticates a human through a regenerated portal session and presents the prototype avatar data', function (): void {
    $user = User::factory()->create(['name' => 'Rafa Silva', 'email' => 'rafa@nordika.test', 'password' => 'secret-password']);
    identityGrant($user, 'leitor', ['organization' => 'nordika', 'application' => 'atlas']);

    $this->postJson('/auth/session', ['email' => 'rafa@nordika.test', 'password' => 'secret-password'])
        ->assertOk()
        ->assertJsonPath('principal.name', 'Rafa Silva')
        ->assertJsonPath('principal.avatar_initials', 'RS');

    $this->getJson('/api/me')->assertOk()->assertJsonPath('principal.type', 'human');
});

it('denies a human grant outside its application before protected retrieval runs', function (): void {
    $user = User::factory()->create();
    identityGrant($user, 'leitor', ['organization' => 'nordika', 'application' => 'atlas']);
    $principal = app(PrincipalFactory::class)->forUser($user);
    $retrieved = false;

    expect(fn () => app(AuthorizationService::class)->authorizeBeforeRetrieval(
        $principal,
        protectedRequest(['organization' => 'nordika', 'application' => 'pagamentos']),
        function () use (&$retrieved): array {
            $retrieved = true;

            return [];
        },
    ))->toThrow(AccessDenied::class);
    expect($retrieved)->toBeFalse();
});

it('denies content above the classification ceiling before protected retrieval runs', function (): void {
    $user = User::factory()->create();
    identityGrant($user, 'leitor', ['organization' => 'nordika', 'application' => 'atlas'], 'interno');
    $principal = app(PrincipalFactory::class)->forUser($user);
    $retrieved = false;

    expect(fn () => app(AuthorizationService::class)->authorizeBeforeRetrieval(
        $principal,
        protectedRequest(classification: 'confidencial'),
        function () use (&$retrieved): array {
            $retrieved = true;

            return [];
        },
    ))->toThrow(AccessDenied::class);
    expect($retrieved)->toBeFalse();
});

it('does not allow a scoped platform administrator to escalate into another organization', function (): void {
    $user = User::factory()->create();
    identityGrant($user, 'administrador_de_plataforma', ['organization' => 'nordika'], 'confidencial');
    $principal = app(PrincipalFactory::class)->forUser($user);

    expect(fn () => app(AuthorizationService::class)->assertAuthorized(
        $principal,
        protectedRequest(['organization' => 'outra-organizacao', 'application' => 'atlas'], 'confidencial'),
    ))->toThrow(AccessDenied::class);
});

it('authenticates machines only with unexpired explicit-scope short-lived tokens', function (): void {
    $issued = app(MachineTokenService::class)->issue('CI Atlas', 'ci', [[
        'scope' => ['organization' => 'nordika', 'application' => 'atlas'],
        'permissions' => ['knowledge.read'],
        'classification_ceiling' => 'interno',
    ]]);

    expect(DB::table('machine_credentials')->value('token_hash'))->toBe(hash('sha256', $issued['token']));
    $this->withToken($issued['token'])->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('principal.type', 'ci')
        ->assertJsonPath('principal.name', 'CI Atlas');

    app(MachineTokenService::class)->revoke($issued['id']);
    $this->withToken($issued['token'])->getJson('/api/me')->assertUnauthorized();
});
