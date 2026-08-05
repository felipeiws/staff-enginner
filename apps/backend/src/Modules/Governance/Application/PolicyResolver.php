<?php

declare(strict_types=1);

namespace Atlas\Modules\Governance\Application;

use DateTimeImmutable;
use DateTimeInterface;

/** Resolves versioned declarative policies with deterministic precedence and an explainable result. */
final class PolicyResolver
{
    /**
     * Legacy boundary used by the lifecycle port.
     *
     * @param  iterable<array<string, mixed>>  $policies
     * @return array<string, mixed>|null
     */
    public function resolve(iterable $policies, string $sourceType, string $entityType, string $classification, DateTimeInterface $at): ?array
    {
        $decision = $this->evaluate($policies, new PolicyCandidate($sourceType, $entityType, $classification, 'unknown', 0.0), $at);

        return $decision['policy_id'] === null ? null : $decision;
    }

    /**
     * @param  iterable<array<string, mixed>>  $policies
     * @return array<string, mixed>
     */
    public function evaluate(iterable $policies, PolicyCandidate $candidate, DateTimeInterface $at): array
    {
        $matched = [];
        foreach ($policies as $policy) {
            if (! ($policy['active'] ?? false) || ! $this->effective($policy, $at)) {
                continue;
            }
            $rules = $this->rules($policy['rules'] ?? []);
            $reasons = $this->matches($rules['applies_to'] ?? [], $candidate);
            if ($reasons !== null) {
                $matched[] = ['policy' => $policy, 'rules' => $rules, 'reasons' => $reasons, 'specificity' => count($reasons)];
            }
        }
        usort($matched, static function (array $left, array $right): int {
            return ($right['policy']['priority'] <=> $left['policy']['priority'])
                ?: ($right['specificity'] <=> $left['specificity'])
                ?: strcmp((string) ($left['policy']['policy_key'] ?? $left['policy']['id']), (string) ($right['policy']['policy_key'] ?? $right['policy']['id']))
                ?: ((int) ($right['policy']['version'] ?? 1) <=> (int) ($left['policy']['version'] ?? 1));
        });
        $selected = $matched[0] ?? null;
        if ($selected === null) {
            return $this->blockedWithoutPolicy();
        }
        $outcome = $selected['rules']['outcome'] ?? $this->legacyOutcome($selected['rules'], $candidate);
        $action = $outcome['action'];
        $created = $candidate->createdAt ?? DateTimeImmutable::createFromInterface($at);
        $afterHours = $outcome['auto_approve_after_hours'] ?? null;
        $autoAt = $afterHours === null ? null : $created->modify('+'.(int) $afterHours.' hours');
        if ($action === 'review' && $autoAt !== null && $at >= $autoAt) {
            $action = 'publish';
        }
        $validity = $outcome['validity_days'] ?? ($selected['rules']['validity_days'] ?? null);
        $validFrom = $candidate->validFrom ?? $created;
        $validUntil = $validity === null ? null : $validFrom->modify('+'.(int) $validity.' days');
        $warningDays = (int) ($outcome['freshness_warning_days'] ?? 7);
        $freshness = $validUntil === null ? 'sem_expiracao' : ($at > $validUntil ? 'expirado' : ($at >= $validUntil->modify('-'.$warningDays.' days') ? 'expira_em_breve' : 'atual'));

        return [
            'id' => (string) $selected['policy']['id'], 'policy_id' => (string) $selected['policy']['id'], 'policy_key' => (string) ($selected['policy']['policy_key'] ?? $selected['policy']['id']),
            'policy_version' => (int) ($selected['policy']['version'] ?? 1), 'action' => $action, 'automatic_publication' => $action === 'publish', 'review_required' => $action === 'review',
            'blocked' => $action === 'block', 'approver_roles' => array_values($outcome['approver_roles'] ?? ($selected['rules']['approver_roles'] ?? [])),
            'validity_days' => $validity === null ? null : (int) $validity, 'valid_until' => $validUntil?->format(DATE_ATOM), 'freshness' => $freshness,
            'automatic_approval_at' => $autoAt?->format(DATE_ATOM), 'retraction_behavior' => (string) ($outcome['retraction_behavior'] ?? ($selected['rules']['retraction_behavior'] ?? 'retain_audit_history')),
            'explanation' => ['matched_rule' => (string) ($selected['policy']['name'] ?? $selected['policy']['id']), 'matched_conditions' => $selected['reasons'], 'precedence' => 'prioridade desc, especificidade desc, policy_key asc, versão desc'],
        ];
    }

    /**
     * @param  array<string, mixed>  $selectors
     * @return list<string>|null
     */
    private function matches(array $selectors, PolicyCandidate $candidate): ?array
    {
        $checks = ['source_types' => $candidate->sourceType, 'entity_types' => $candidate->entityType, 'classifications' => $candidate->classification, 'derivation_methods' => $candidate->derivationMethod, 'impact_levels' => $candidate->impactLevel];
        $reasons = [];
        foreach ($checks as $key => $value) {
            $allowed = $selectors[$key] ?? [];
            if ($allowed !== [] && ! in_array($value, $allowed, true)) {
                return null;
            }
            if ($allowed !== []) {
                $reasons[] = "{$key} contém {$value}";
            }
        }
        $confidence = $selectors['confidence'] ?? [];
        if (($confidence['min'] ?? 0) > $candidate->confidence || ($confidence['max'] ?? 1) < $candidate->confidence) {
            return null;
        }
        if ($confidence !== []) {
            $reasons[] = 'confiança dentro do intervalo declarado';
        }
        foreach (($selectors['scope'] ?? []) as $key => $allowed) {
            $actual = $candidate->scope[$key] ?? null;
            $values = is_array($actual) ? $actual : [$actual];
            if ($actual === null || array_intersect($values, $allowed) === []) {
                return null;
            }
            $reasons[] = "escopo {$key} compatível";
        }
        $owner = $selectors['owner'] ?? [];
        if (($owner['ids'] ?? []) !== [] && array_intersect($candidate->ownerIds, $owner['ids']) === []) {
            return null;
        }
        if (($owner['roles'] ?? []) !== [] && array_intersect($candidate->ownerRoles, $owner['roles']) === []) {
            return null;
        }
        if ($owner !== []) {
            $reasons[] = 'dono compatível';
        }

        return $reasons;
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function legacyOutcome(array $rules, PolicyCandidate $candidate): array
    {
        return ['action' => ((bool) ($rules['automatic_publication'] ?? false) && ! in_array($candidate->classification, $rules['review_required_classifications'] ?? [], true)) ? 'publish' : 'review'];
    }

    /** @return array<string, mixed> */
    private function rules(mixed $rules): array
    {
        return is_string($rules) ? json_decode($rules, true, 512, JSON_THROW_ON_ERROR) : $rules;
    }

    /** @param array<string, mixed> $policy */
    private function effective(array $policy, DateTimeInterface $at): bool
    {
        return ($policy['effective_from'] ?? null) === null || strtotime((string) $policy['effective_from']) <= $at->getTimestamp() ? (($policy['effective_to'] ?? null) === null || strtotime((string) $policy['effective_to']) >= $at->getTimestamp()) : false;
    }

    /** @return array<string, mixed> */
    private function blockedWithoutPolicy(): array
    {
        return ['id' => null, 'policy_id' => null, 'action' => 'block', 'automatic_publication' => false, 'review_required' => false, 'blocked' => true, 'approver_roles' => [], 'validity_days' => null, 'valid_until' => null, 'freshness' => 'sem_expiracao', 'automatic_approval_at' => null, 'retraction_behavior' => 'retain_audit_history', 'explanation' => ['matched_rule' => null, 'matched_conditions' => [], 'precedence' => 'Nenhuma política ativa, vigente e aplicável; bloqueio seguro.']];
    }
}
