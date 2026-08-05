<?php

declare(strict_types=1);

use Atlas\Modules\Governance\Application\PolicyCandidate;
use Atlas\Modules\Governance\Application\PolicyResolver;
use Atlas\Modules\Governance\Application\YamlPolicyLoader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function declarativePolicy(string $id, string $key, int $priority, array $rules, int $version = 1): array
{
    return ['id' => $id, 'policy_key' => $key, 'version' => $version, 'name' => $key, 'active' => true, 'priority' => $priority, 'rules' => $rules];
}

it('evaluates declarative policy cases for precedence, deterministic ties, and no policy', function (): void {
    $resolver = new PolicyResolver;
    $candidate = new PolicyCandidate('git', 'Documento', 'interno', 'parser', 0.94, ['application' => 'atlas'], ['human:ana'], ['technical'], 'baixo', now()->subHours(49)->toImmutable());
    $policies = [
        declarativePolicy('a', 'base', 10, ['applies_to' => ['source_types' => ['git']], 'outcome' => ['action' => 'review', 'approver_roles' => ['technical']]]),
        declarativePolicy('b', 'ci-baixo-impacto', 20, ['applies_to' => ['source_types' => ['git'], 'entity_types' => ['Documento'], 'classifications' => ['interno'], 'derivation_methods' => ['parser'], 'confidence' => ['min' => 0.9], 'scope' => ['application' => ['atlas']], 'owner' => ['roles' => ['technical']], 'impact_levels' => ['baixo']], 'outcome' => ['action' => 'review', 'approver_roles' => ['technical'], 'auto_approve_after_hours' => 48, 'validity_days' => 90]]),
    ];
    $decision = $resolver->evaluate($policies, $candidate, now());
    expect($decision)->toMatchArray(['policy_id' => 'b', 'action' => 'publish', 'automatic_publication' => true, 'freshness' => 'atual'])
        ->and($decision['explanation']['matched_conditions'])->toContain('source_types contém git');

    $tie = $resolver->evaluate([declarativePolicy('z', 'zeta', 30, ['applies_to' => [], 'outcome' => ['action' => 'block']]), declarativePolicy('a', 'alfa', 30, ['applies_to' => [], 'outcome' => ['action' => 'review']])], $candidate, now());
    expect($tie['policy_id'])->toBe('a')->and($tie['action'])->toBe('review');

    $none = $resolver->evaluate([], $candidate, now());
    expect($none)->toMatchArray(['action' => 'block', 'blocked' => true, 'policy_id' => null]);
});

it('loads schema-validated YAML as immutable policy versions and emits freshness data', function (): void {
    $yaml = <<<'YAML'
schema_version: 1.0.0
policies:
  - policy_key: validade-curta
    version: 1
    name: Validade curta
    priority: 5
    applies_to:
      classifications: [interno]
    outcome:
      action: publish
      validity_days: 1
      freshness_warning_days: 1
YAML;
    $loader = app(YamlPolicyLoader::class);
    $ids = $loader->load($yaml, 'human:ana');
    expect($ids)->toHaveCount(1)->and(DB::table('policies')->where('id', $ids[0])->value('schema_version'))->toBe('1.0.0');
    expect(fn () => $loader->load($yaml, 'human:ana'))->toThrow(DomainException::class);
    expect(fn () => $loader->load("schema_version: 1.0.0\npolicies: []", 'human:ana'))->toThrow(InvalidArgumentException::class);

    $row = (array) DB::table('policies')->where('id', $ids[0])->first();
    $decision = (new PolicyResolver)->evaluate([$row], new PolicyCandidate('git', 'Documento', 'interno', 'parser', 0.8, createdAt: now()->subDays(2)->toImmutable()), now());
    expect($decision['freshness'])->toBe('expirado')->and($decision['valid_until'])->not->toBeNull();
});
