<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Application;

/** Read model for the Relacionamentos, Afeta and Regras aplicadas detail sections. */
final readonly class EntityNeighborhood
{
    /** @param array<string, list<array<string, mixed>>> $relationsByPredicate */
    public function __construct(public array $relationsByPredicate) {}
}
