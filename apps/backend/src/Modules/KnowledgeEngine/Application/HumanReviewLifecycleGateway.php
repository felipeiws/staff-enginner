<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Application;

use Atlas\Contracts\Port\HumanReviewLifecycle;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Identifier;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\LifecycleState;

final readonly class HumanReviewLifecycleGateway implements HumanReviewLifecycle
{
    public function __construct(private KnowledgeLifecycleService $lifecycle) {}

    public function publishReviewedRevision(string $revisionId, string $reviewerId, string $reason, string $idempotencyKey, ?string $correlationId = null): void
    {
        $this->lifecycle->transitionRevision(new Identifier($revisionId), LifecycleState::Published, $reviewerId, 'human', $reason, $idempotencyKey, $correlationId);
    }

    public function retractReviewedRevision(string $revisionId, string $reviewerId, string $reason, string $idempotencyKey, ?string $correlationId = null): void
    {
        $this->lifecycle->transitionRevision(new Identifier($revisionId), LifecycleState::Retracted, $reviewerId, 'human', $reason, $idempotencyKey, $correlationId);
    }
}
