<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Domain;

final readonly class Principal
{
    /** @param list<array{role?:string,scope:array<string,string>,permissions:list<string>,classification_ceiling:string}> $grants */
    public function __construct(
        public string $id,
        public string $type,
        public string $displayName,
        public string $avatarInitials,
        public array $grants,
    ) {}
}
