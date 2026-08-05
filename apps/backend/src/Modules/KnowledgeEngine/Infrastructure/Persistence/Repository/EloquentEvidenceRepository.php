<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Infrastructure\Persistence\Repository;

use Atlas\Modules\KnowledgeEngine\Domain\Aggregate\Evidence;
use Atlas\Modules\KnowledgeEngine\Domain\Aggregate\EvidenceKind;
use Atlas\Modules\KnowledgeEngine\Domain\Repository\EvidenceRepository;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Confidence;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Identifier;
use Atlas\Modules\KnowledgeEngine\Infrastructure\Persistence\Models\EvidenceModel;

final readonly class EloquentEvidenceRepository extends EloquentRepository implements EvidenceRepository
{
    public function save(Evidence $evidence): void
    {
        $this->assertWritingTransaction();
        $pointers = ['chunk_id' => null, 'artifact_id' => null, 'event_id' => null];
        $pointers[$evidence->kind->value.'_id'] = (string) $evidence->pointerId;
        EvidenceModel::query()->updateOrCreate(['id' => (string) $evidence->id], ['assertion_id' => (string) $evidence->assertionId, 'evidence_type' => $evidence->kind->value, ...$pointers, 'weight' => $evidence->weight->value]);
    }

    public function forAssertion(Identifier $assertionId): array
    {
        return array_values(EvidenceModel::query()->where('assertion_id', (string) $assertionId)->orderBy('id')->get()->map(function (EvidenceModel $model): Evidence {
            $kind = EvidenceKind::from($model->evidence_type);
            $pointer = $model->{$kind->value.'_id'};

            return new Evidence(new Identifier($this->stringValue($model->id, 'assertion_evidence.id')), new Identifier($this->stringValue($model->assertion_id, 'assertion_evidence.assertion_id')), $kind, new Identifier($this->stringValue($pointer, 'assertion_evidence.pointer')), new Confidence((float) $model->weight));
        })->all());
    }
}
