<?php

declare(strict_types=1);

namespace Atlas\Modules\Copilot\Application;

/** Boundary owned by Copilot; its adapter lives at the API composition root. */
interface KnowledgeRetriever
{
    /**
     * @param array{id:string,grants:list<array{role?:string,scope:array<string,string>,permissions:list<string>,classification_ceiling:string}>} $actor
     * @param array{organization:string,application?:string,module?:string} $scope
     * @param list<float|int>|null $embedding
     * @return array{sources:list<array<string,mixed>>,entities:list<array<string,mixed>>,retrieval:array<string,mixed>}
     */
    public function retrieve(array $actor, string $question, array $scope, ?array $embedding): array;
}
