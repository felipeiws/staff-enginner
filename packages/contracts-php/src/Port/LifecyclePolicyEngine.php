<?php

declare(strict_types=1);

namespace Atlas\Contracts\Port;

/** Stable boundary for policy decisions consumed by the Knowledge Engine. */
interface LifecyclePolicyEngine
{
    /** @return array{id: string, automatic_publication: bool, review_required: bool, approver_roles: list<string>, validity_days: int|null, retraction_behavior: string}|null */
    public function resolve(string $sourceType, string $entityType, string $classification): ?array;
}
