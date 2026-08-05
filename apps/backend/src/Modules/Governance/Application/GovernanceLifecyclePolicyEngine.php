<?php

declare(strict_types=1);

namespace Atlas\Modules\Governance\Application;

use Atlas\Contracts\Port\LifecyclePolicyEngine;
use Illuminate\Database\DatabaseManager;

final readonly class GovernanceLifecyclePolicyEngine implements LifecyclePolicyEngine
{
    public function __construct(private DatabaseManager $database, private PolicyResolver $resolver) {}

    public function resolve(string $sourceType, string $entityType, string $classification): ?array
    {
        $policies = [];
        foreach ($this->database->table('policies')->get() as $policy) {
            $policies[] = ['id' => (string) $policy->id, 'policy_key' => $policy->policy_key, 'version' => (int) $policy->version, 'name' => (string) $policy->name, 'active' => (bool) $policy->active, 'priority' => (int) $policy->priority, 'rules' => (string) $policy->rules, 'effective_from' => $policy->effective_from, 'effective_to' => $policy->effective_to];
        }

        $decision = $this->resolver->resolve($policies, $sourceType, $entityType, $classification, now());
        if ($decision === null) {
            return null;
        }

        return [
            'id' => (string) $decision['id'],
            'automatic_publication' => (bool) $decision['automatic_publication'],
            'review_required' => (bool) $decision['review_required'],
            'approver_roles' => array_values($decision['approver_roles']),
            'validity_days' => $decision['validity_days'] === null ? null : (int) $decision['validity_days'],
            'retraction_behavior' => (string) $decision['retraction_behavior'],
        ];
    }
}
