<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Infrastructure\Persistence\Repository;

use Atlas\Modules\KnowledgeEngine\Domain\Aggregate\KnowledgeEntity;
use Atlas\Modules\KnowledgeEngine\Domain\Repository\KnowledgeEntityRepository;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Identifier;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Scope;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\StableIdentity;
use Atlas\Modules\KnowledgeEngine\Infrastructure\Persistence\Models\KnowledgeEntityModel;

final readonly class EloquentKnowledgeEntityRepository extends EloquentRepository implements KnowledgeEntityRepository
{
    public function save(KnowledgeEntity $entity): void
    {
        $this->assertWritingTransaction();
        KnowledgeEntityModel::query()->updateOrCreate(['id' => (string) $entity->id], ['entity_type' => $entity->identity->entityType, 'family' => $entity->family, 'scope' => $entity->scope->values, 'external_natural_key' => $entity->identity->externalNaturalKey, 'owner_id' => $entity->ownerId, 'owner_type' => $entity->ownerType]);
    }

    public function get(Identifier $id): ?KnowledgeEntity
    {
        $model = KnowledgeEntityModel::query()->find((string) $id);

        return $model === null ? null : $this->toDomain($model);
    }

    public function findByIdentity(StableIdentity $identity): ?KnowledgeEntity
    {
        $model = KnowledgeEntityModel::query()
            ->where('entity_type', $identity->entityType)
            ->where('external_natural_key', $identity->externalNaturalKey)
            ->first();

        return $model === null ? null : $this->toDomain($model);
    }

    private function toDomain(KnowledgeEntityModel $model): KnowledgeEntity
    {
        return new KnowledgeEntity(new Identifier($this->stringValue($model->id, 'entities.id')), new StableIdentity($this->stringValue($model->entity_type, 'entities.entity_type'), $this->stringValue($model->external_natural_key, 'entities.external_natural_key')), $this->stringValue($model->family, 'entities.family'), new Scope($this->arrayValue($model->scope, 'entities.scope')), $this->stringValue($model->owner_id, 'entities.owner_id'), $this->stringValue($model->owner_type, 'entities.owner_type'));
    }
}
