<?php

declare(strict_types=1);

namespace Atlas\Modules\Search\Domain;

interface PublishedKnowledgeIndex
{
    /** @param array<string, mixed> $schema */
    public function createCollection(string $collection, array $schema): void;

    /** @param array<string, mixed> $document */
    public function upsert(string $collection, array $document): void;

    public function delete(string $collection, string $documentId): void;

    public function deleteByEntity(string $collection, string $entityId): void;

    public function switchAlias(string $alias, string $collection): void;
}
