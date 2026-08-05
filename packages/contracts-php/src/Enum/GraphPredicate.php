<?php

declare(strict_types=1);

namespace Atlas\Contracts\Enum;

/** Vocabulário de relações definido por Assertion v1. */
enum GraphPredicate: string
{
    case Contains = 'contains';
    case BelongsTo = 'belongs_to';
    case Implements = 'implements';
    case Calls = 'calls';
    case Exposes = 'exposes';
    case Consumes = 'consumes';
    case Produces = 'produces';
    case Reads = 'reads';
    case Writes = 'writes';
    case Governs = 'governs';
    case Documents = 'documents';
    case DependsOn = 'depends_on';
    case Supersedes = 'supersedes';
    case OwnedBy = 'owned_by';
    case RelatedTo = 'related_to';
    case Contradicts = 'contradicts';
}
