<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Http;

use App\Http\Api\OpenApi\ApiOperation;
use Atlas\Modules\Identity\Domain\Principal;
use Illuminate\Http\Request;

#[ApiOperation('Consultar o principal autenticado', ['Identidade'], [200 => 'Principal autenticado'])]
final class CurrentPrincipalController
{
    public function __invoke(Request $request): CurrentPrincipalResource
    {
        /** @var Principal $principal */
        $principal = $request->attributes->get('identity.principal');

        return new CurrentPrincipalResource($principal);
    }
}
