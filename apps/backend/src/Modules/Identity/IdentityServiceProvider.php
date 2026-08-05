<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity;

use Atlas\Modules\Identity\Application\AuthorizationService;
use Atlas\Modules\Identity\Application\MachineTokenService;
use Atlas\Modules\Identity\Application\PrincipalFactory;
use Illuminate\Support\ServiceProvider;

final class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuthorizationService::class);
        $this->app->singleton(MachineTokenService::class);
        $this->app->singleton(PrincipalFactory::class);
    }
}
