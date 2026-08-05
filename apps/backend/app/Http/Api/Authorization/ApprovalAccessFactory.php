<?php

declare(strict_types=1);

namespace App\Http\Api\Authorization;

use Atlas\Modules\Governance\Application\ApprovalAccess;
use Atlas\Modules\Identity\Domain\Principal;
use Illuminate\Http\Request;

final class ApprovalAccessFactory
{
    public function fromRequest(Request $request): ApprovalAccess
    {
        /** @var Principal $principal */
        $principal = $request->attributes->get('identity.principal');

        return new ApprovalAccess($principal->id, $principal->grants);
    }
}
