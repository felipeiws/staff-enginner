<?php

declare(strict_types=1);

namespace Atlas\Contracts\Port;

use Atlas\Contracts\Dto\AuditHistoryEntry;
use Atlas\Contracts\Dto\AuditRecord;

/** Stable boundary for auditable changes across platform modules. */
interface AuditTrail
{
    public function record(AuditRecord $record): void;

    /** @return list<AuditHistoryEntry> */
    public function historyForEntity(string $entityId): array;
}
