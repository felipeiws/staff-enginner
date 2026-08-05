<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Infrastructure\Persistence\Repository;

use Atlas\Modules\KnowledgeEngine\Domain\Aggregate\Source;
use Atlas\Modules\KnowledgeEngine\Domain\Repository\SourceRepository;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Identifier;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Scope;
use Atlas\Modules\KnowledgeEngine\Infrastructure\Persistence\Models\SourceModel;

final readonly class EloquentSourceRepository extends EloquentRepository implements SourceRepository
{
    public function save(Source $source): void
    {
        $this->assertWritingTransaction();
        SourceModel::query()->updateOrCreate(['id' => (string) $source->id], ['type' => $source->type, 'external_system' => $source->externalSystem, 'scope' => $source->scope->values, 'credential_reference' => $source->credentialReference, 'classification' => $source->classification, 'active' => $source->active]);
    }

    public function get(Identifier $id): ?Source
    {
        $model = SourceModel::query()->find((string) $id);

        return $model === null ? null : $this->toDomain($model);
    }

    public function findActiveByType(string $type): ?Source
    {
        $model = SourceModel::query()->where('type', $type)->where('active', true)->first();

        return $model === null ? null : $this->toDomain($model);
    }

    private function toDomain(SourceModel $model): Source
    {
        return new Source(new Identifier($this->stringValue($model->id, 'sources.id')), $this->stringValue($model->type, 'sources.type'), is_string($model->external_system) ? $model->external_system : null, new Scope($this->arrayValue($model->scope, 'sources.scope')), is_string($model->credential_reference) ? $model->credential_reference : null, $this->stringValue($model->classification, 'sources.classification'), (bool) $model->active);
    }
}
