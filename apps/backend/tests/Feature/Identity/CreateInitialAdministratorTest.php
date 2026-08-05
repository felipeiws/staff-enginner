<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('creates the initial administrator idempotently with a bounded organization scope', function (): void {
    config()->set('app.env', 'testing');
    putenv('ATLAS_ADMIN_NAME=Admin Atlas');
    putenv('ATLAS_ADMIN_EMAIL=admin@example.test');
    putenv('ATLAS_ADMIN_PASSWORD=a-password-with-at-least-16-characters');
    putenv('ATLAS_ADMIN_ORGANIZATION=nordika');

    $this->artisan('atlas:create-initial-admin')->assertSuccessful();
    $this->artisan('atlas:create-initial-admin')->assertSuccessful();

    $user = User::query()->where('email', 'admin@example.test')->firstOrFail();
    expect($user->name)->toBe('Admin Atlas');
    expect(DB::table('identity_grants')->where('user_id', $user->id)->count())->toBe(1);
    expect(DB::table('identity_grants')->where('user_id', $user->id)->value('role'))->toBe('administrador_de_plataforma');
    expect(DB::table('identity_grants')->where('user_id', $user->id)->value('scope'))->toContain('nordika');
});
