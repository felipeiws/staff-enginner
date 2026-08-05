<?php

declare(strict_types=1);

use Atlas\Modules\Governance\Application\PolicyResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

const GOVERNANCE_TABLES = ['classifications', 'policies', 'owners', 'approvals', 'audit_log', 'retention_rules'];

function governanceId(int $number): string
{
    return sprintf('10000000-0000-4000-8000-%012d', $number);
}

it('creates the governance family with evaluable policies and decision records', function (): void {
    foreach (GOVERNANCE_TABLES as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }

    expect(Schema::hasColumns('classifications', ['level', 'search_exposure_allowed', 'display_exposure_allowed', 'external_provider_allowed', 'exposure_rules']))->toBeTrue();
    expect(Schema::hasColumns('policies', ['policy_key', 'version', 'schema_version', 'definition_checksum', 'origin_reference', 'origin_revision', 'derivation_method', 'confidence', 'priority', 'active', 'rules', 'effective_from', 'effective_to']))->toBeTrue();
    expect(Schema::hasColumns('owners', ['scope_type', 'scope_identifier', 'responsibility_type', 'owner_id']))->toBeTrue();
    expect(Schema::hasColumns('approvals', ['proposal', 'reviewer_id', 'decision', 'justification', 'decided_at', 'external_pr_reference']))->toBeTrue();
    expect(Schema::hasColumns('audit_log', ['actor_id', 'action', 'entity_id', 'entity_revision_id', 'decision', 'reason', 'correlation_id', 'before_payload', 'after_payload']))->toBeTrue();
    expect(Schema::hasColumns('retention_rules', ['applies_to', 'disposition', 'retain_for_days', 'legal_hold_allowed']))->toBeTrue();
});

it('makes audit records immutable through database triggers', function (): void {
    DB::table('audit_log')->insert([
        'id' => governanceId(1),
        'occurred_at' => now(),
        'actor_id' => 'ana@example.com',
        'actor_type' => 'human',
        'action' => 'policy.created',
        'subject_kind' => 'policy',
        'reason' => 'Criação aprovada pelo responsável técnico.',
        'correlation_id' => governanceId(2),
        'after_payload' => json_encode(['name' => 'publicacao-git']),
        'method' => 'human_declared',
        'confidence' => 1,
    ]);

    try {
        DB::table('audit_log')->where('id', governanceId(1))->update(['reason' => 'alterado']);
        $this->fail('Um registro de auditoria não pode ser atualizado.');
    } catch (Throwable $exception) {
        expect($exception->getMessage())->toContain('audit_log is immutable');
    }

    try {
        DB::table('audit_log')->where('id', governanceId(1))->delete();
        $this->fail('Um registro de auditoria não pode ser removido.');
    } catch (Throwable $exception) {
        expect($exception->getMessage())->toContain('audit_log is immutable');
    }
    expect(DB::table('audit_log')->where('id', governanceId(1))->value('reason'))->toBe('Criação aprovada pelo responsável técnico.');
});

it('resolves representative declarative policies by scope, priority, and classification', function (): void {
    $resolver = new PolicyResolver;
    $at = now();
    $policies = [
        [
            'id' => governanceId(10),
            'active' => true,
            'priority' => 10,
            'rules' => [
                'applies_to' => [],
                'automatic_publication' => false,
                'review_required_classifications' => ['restrito', 'confidencial'],
                'approver_roles' => ['technical', 'business'],
                'validity_days' => 30,
                'retraction_behavior' => 'remove_from_search_and_notify',
            ],
        ],
        [
            'id' => governanceId(11),
            'active' => true,
            'priority' => 20,
            'rules' => [
                'applies_to' => ['source_types' => ['git'], 'entity_types' => ['Documento'], 'classifications' => ['interno']],
                'automatic_publication' => true,
                'review_required_classifications' => [],
                'approver_roles' => ['technical'],
                'validity_days' => 90,
                'retraction_behavior' => 'retain_audit_history',
            ],
        ],
    ];

    expect($resolver->resolve($policies, 'git', 'Documento', 'interno', $at))->toMatchArray([
        'id' => governanceId(11),
        'automatic_publication' => true,
        'review_required' => false,
        'approver_roles' => ['technical'],
        'validity_days' => 90,
    ]);
    expect($resolver->resolve($policies, 'git', 'Documento', 'confidencial', $at))->toMatchArray([
        'id' => governanceId(10),
        'automatic_publication' => false,
        'review_required' => true,
        'approver_roles' => ['technical', 'business'],
        'retraction_behavior' => 'remove_from_search_and_notify',
    ]);
});

it('declares PostgreSQL audit immutability, JSON indexes, and approval invariants', function (): void {
    $migration = file_get_contents(database_path('migrations/2026_07_31_000200_create_governance_tables.php'));

    expect($migration)->toContain('CREATE FUNCTION prevent_audit_log_mutation()', 'CREATE TRIGGER audit_log_immutable_update', 'CREATE TRIGGER audit_log_immutable_delete', 'RAISE EXCEPTION \'audit_log is immutable\'');
    expect($migration)->toContain('policies_rules_gin', 'audit_log_before_payload_gin', 'approvals_subject_check', 'approvals_reviewer_check');
});
