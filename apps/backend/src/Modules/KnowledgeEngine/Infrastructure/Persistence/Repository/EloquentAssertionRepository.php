<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Infrastructure\Persistence\Repository;

use Atlas\Contracts\Enum\GraphPredicate;
use Atlas\Modules\KnowledgeEngine\Domain\Aggregate\Assertion;
use Atlas\Modules\KnowledgeEngine\Domain\Repository\AssertionRepository;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Confidence;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\DerivationMethod;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Identifier;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\LifecycleState;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Scope;
use Atlas\Modules\KnowledgeEngine\Infrastructure\Persistence\Models\AssertionModel;
use Illuminate\Database\Eloquent\Collection;

final readonly class EloquentAssertionRepository extends EloquentRepository implements AssertionRepository
{
    public function save(Assertion $assertion): void
    {
        $this->assertWritingTransaction();
        AssertionModel::query()->updateOrCreate(['id' => (string) $assertion->id], ['subject_entity_revision_id' => (string) $assertion->subjectRevisionId, 'predicate' => $assertion->predicate->value, 'object_entity_revision_id' => $assertion->objectRevisionId === null ? null : (string) $assertion->objectRevisionId, 'literal_value' => $assertion->literalValue, 'scope' => $assertion->scope->values, 'confidence' => $assertion->confidence->value, 'method' => $assertion->method->value, 'actor_id' => $assertion->actorId, 'actor_type' => $assertion->actorType, 'lifecycle_state' => $assertion->lifecycleState->value, 'valid_from' => $assertion->validFrom, 'valid_to' => $assertion->validTo]);
    }

    public function get(Identifier $id): ?Assertion
    {
        $model = AssertionModel::query()->find((string) $id);

        return $model === null ? null : $this->toDomain($model);
    }

    public function dependentsOfRevision(Identifier $revisionId): array
    {
        return $this->map(AssertionModel::query()->where('subject_entity_revision_id', (string) $revisionId)->orWhere('object_entity_revision_id', (string) $revisionId)->get());
    }

    public function publishedWithSubjectAndPredicate(Identifier $subjectRevisionId, GraphPredicate $predicate): array
    {
        return $this->map(AssertionModel::query()->where('subject_entity_revision_id', (string) $subjectRevisionId)->where('predicate', $predicate->value)->where('lifecycle_state', LifecycleState::Published->value)->get());
    }

    /**
     * @param  Collection<int, AssertionModel>  $models
     * @return list<Assertion>
     */
    private function map(Collection $models): array
    {
        return array_values($models->map(fn (AssertionModel $model): Assertion => $this->toDomain($model))->all());
    }

    private function toDomain(AssertionModel $model): Assertion
    {
        return new Assertion(new Identifier($this->stringValue($model->id, 'assertions.id')), new Identifier($this->stringValue($model->subject_entity_revision_id, 'assertions.subject_entity_revision_id')), GraphPredicate::from($this->stringValue($model->predicate, 'assertions.predicate')), is_string($model->object_entity_revision_id) ? new Identifier($model->object_entity_revision_id) : null, $model->literal_value === null ? null : $this->arrayValue($model->literal_value, 'assertions.literal_value'), new Scope($this->arrayValue($model->scope, 'assertions.scope')), new Confidence((float) $model->confidence), new DerivationMethod($this->stringValue($model->method, 'assertions.method')), $this->stringValue($model->actor_id, 'assertions.actor_id'), $this->stringValue($model->actor_type, 'assertions.actor_type'), LifecycleState::from($this->stringValue($model->lifecycle_state, 'assertions.lifecycle_state')), $this->nullableDateValue($model->valid_from, 'assertions.valid_from'), $this->nullableDateValue($model->valid_to, 'assertions.valid_to'));
    }
}
