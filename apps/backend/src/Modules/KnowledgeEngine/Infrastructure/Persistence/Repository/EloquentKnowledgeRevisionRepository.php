<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Infrastructure\Persistence\Repository;

use Atlas\Modules\KnowledgeEngine\Domain\Aggregate\KnowledgeRevision;
use Atlas\Modules\KnowledgeEngine\Domain\Repository\KnowledgeRevisionRepository;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Confidence;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Identifier;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\LifecycleState;
use Atlas\Modules\KnowledgeEngine\Infrastructure\Persistence\Models\KnowledgeRevisionModel;

final readonly class EloquentKnowledgeRevisionRepository extends EloquentRepository implements KnowledgeRevisionRepository
{
    public function save(KnowledgeRevision $revision): void
    {
        $this->assertWritingTransaction();
        KnowledgeRevisionModel::query()->updateOrCreate(['id' => (string) $revision->id], ['entity_id' => (string) $revision->entityId, 'version' => $revision->version, 'lifecycle_state' => $revision->lifecycleState->value, 'title' => $revision->title, 'body' => $revision->body, 'attributes' => $revision->attributes, 'source_revision_id' => (string) $revision->sourceRevisionId, 'confidence' => $revision->confidence->value, 'published_at' => $revision->publishedAt, 'superseded_by' => $revision->supersededBy === null ? null : (string) $revision->supersededBy]);
    }

    public function get(Identifier $id): ?KnowledgeRevision
    {
        $model = KnowledgeRevisionModel::query()->find((string) $id);

        return $model === null ? null : $this->toDomain($model);
    }

    public function publishedForEntity(Identifier $entityId): array
    {
        return array_values(KnowledgeRevisionModel::query()->where('entity_id', (string) $entityId)->where('lifecycle_state', LifecycleState::Published->value)->orderBy('version')->get()->map(fn (KnowledgeRevisionModel $model): KnowledgeRevision => $this->toDomain($model))->all());
    }

    private function toDomain(KnowledgeRevisionModel $model): KnowledgeRevision
    {
        return new KnowledgeRevision(new Identifier($this->stringValue($model->id, 'entity_revisions.id')), new Identifier($this->stringValue($model->entity_id, 'entity_revisions.entity_id')), (int) $model->version, LifecycleState::from($this->stringValue($model->lifecycle_state, 'entity_revisions.lifecycle_state')), $this->stringValue($model->title, 'entity_revisions.title'), is_string($model->body) ? $model->body : null, $this->arrayValue($model->attributes, 'entity_revisions.attributes'), new Identifier($this->stringValue($model->source_revision_id, 'entity_revisions.source_revision_id')), new Confidence((float) $model->confidence), $this->nullableDateValue($model->published_at, 'entity_revisions.published_at'), is_string($model->superseded_by) ? new Identifier($model->superseded_by) : null);
    }
}
