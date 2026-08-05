<?php

declare(strict_types=1);

namespace Atlas\Contracts\Port;

/** Stable boundary through which governance asks the Knowledge Engine to curate a revision. */
interface HumanReviewLifecycle
{
    public function publishReviewedRevision(string $revisionId, string $reviewerId, string $reason, string $idempotencyKey, ?string $correlationId = null): void;

    public function retractReviewedRevision(string $revisionId, string $reviewerId, string $reason, string $idempotencyKey, ?string $correlationId = null): void;
}
