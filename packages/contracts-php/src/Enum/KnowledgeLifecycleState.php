<?php

declare(strict_types=1);

namespace Atlas\Contracts\Enum;

/** Estados de curadoria definidos pelos contratos do Knowledge Engine. */
enum KnowledgeLifecycleState: string
{
    case Observed = 'observed';
    case Draft = 'draft';
    case Candidate = 'candidate';
    case Review = 'review';
    case Published = 'published';
    case Superseded = 'superseded';
    case Retracted = 'retracted';
}
