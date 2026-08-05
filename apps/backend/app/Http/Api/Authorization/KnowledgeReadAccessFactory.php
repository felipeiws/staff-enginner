<?php

declare(strict_types=1);

namespace App\Http\Api\Authorization;

use Atlas\Modules\Identity\Domain\Principal;
use Atlas\Modules\KnowledgeEngine\Application\KnowledgeReadAccess;
use Illuminate\Http\Request;

final class KnowledgeReadAccessFactory
{
    public function fromRequest(Request $request): KnowledgeReadAccess
    {
        /** @var Principal $principal */
        $principal = $request->attributes->get('identity.principal');

        return new KnowledgeReadAccess($principal->grants);
    }
}
