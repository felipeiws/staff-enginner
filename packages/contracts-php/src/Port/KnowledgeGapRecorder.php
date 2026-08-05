<?php

declare(strict_types=1);

namespace Atlas\Contracts\Port;

use Atlas\Contracts\Dto\KnowledgeGapOccurrence;

/** Stable boundary for recording unanswered questions from independent consumers. */
interface KnowledgeGapRecorder
{
    public function record(KnowledgeGapOccurrence $occurrence): string;
}
