<?php

declare(strict_types=1);

namespace Atlas\Modules\Governance\Application;

final readonly class ApprovalAccess
{
    /** @param list<array{scope:array<string,string>,permissions:list<string>,classification_ceiling:string}> $grants */
    public function __construct(public string $actorId, public array $grants) {}
}
