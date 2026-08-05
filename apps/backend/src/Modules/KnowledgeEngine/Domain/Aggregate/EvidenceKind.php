<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Domain\Aggregate;

enum EvidenceKind: string
{
    case Chunk = 'chunk';
    case Artifact = 'artifact';
    case Event = 'event';
}
