<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Domain;

final readonly class AuthorizationRequest
{
    /** @param array{organization:string, application?:string, module?:string} $scope */
    public function __construct(public string $permission, public array $scope, public string $classification) {}
}
