<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Application;

final readonly class KnowledgeReadAccess
{
    /** @param list<array{scope:array<string,string>,permissions:list<string>,classification_ceiling:string}> $grants */
    public function __construct(public array $grants) {}
}
