<?php

declare(strict_types=1);

use Atlas\Contracts\Dto\Actor;
use Atlas\Contracts\Dto\AuditRecord;
use Atlas\Contracts\Port\AuditTrail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function auditEntityId(int $number): string
{
    return sprintf('30000000-0000-4000-8000-%012d', $number);
}

function auditRecord(string $entityId, string $actorType = 'human', ?array $before = null, ?array $after = null, string $reason = 'Alteração registrada.'): AuditRecord
{
    return new AuditRecord(new Actor("{$actorType}:atlas", $actorType), 'knowledge.entity.changed', 'entity', $entityId, $entityId, null, null, 'changed', $reason, '40000000-0000-4000-8000-000000000001', ['application' => 'atlas'], $before, $after, 'interno', 'human_declared', 1.0);
}

beforeEach(function (): void {
    DB::table('entities')->insert(['id' => auditEntityId(1), 'entity_type' => 'Processo', 'family' => 'contexto', 'scope' => json_encode(['application' => 'atlas']), 'external_natural_key' => 'processo:audit', 'owner_id' => 'human:atlas', 'owner_type' => 'human', 'created_at' => now(), 'updated_at' => now()]);
});

it('redacts secrets and omits excessive content before immutable persistence', function (): void {
    app(AuditTrail::class)->record(auditRecord(auditEntityId(1), 'plugin', ['authorization' => 'Bearer super-secret', 'nested' => ['api_key' => 'abcd']], ['body' => str_repeat('conteúdo proprietário ', 100), 'title' => 'Versão publicada']));

    $row = DB::table('audit_log')->first();
    expect((string) $row->before_payload)->not->toContain('super-secret', 'abcd')
        ->and((string) $row->before_payload)->toContain('[REDACTED]')
        ->and((string) $row->after_payload)->not->toContain('conteúdo proprietário')
        ->and((string) $row->after_payload)->toContain('[OMITTED: content');
});

it('keeps records immutable after they are accepted by the audit service', function (): void {
    app(AuditTrail::class)->record(auditRecord(auditEntityId(1)));
    $id = (string) DB::table('audit_log')->value('id');

    try {
        DB::table('audit_log')->where('id', $id)->update(['reason' => 'alteração']);
        $this->fail('Um registro de auditoria não pode ser atualizado.');
    } catch (Throwable $exception) {
        expect($exception->getMessage())->toContain('audit_log is immutable');
    }
});

it('returns an entity history ordered from the newest change to the oldest with prototype actor colors', function (): void {
    Carbon::setTestNow('2026-02-04 10:00:00');
    app(AuditTrail::class)->record(auditRecord(auditEntityId(1), 'ci', null, null, 'Importação inicial'));
    Carbon::setTestNow('2026-07-28 10:00:00');
    app(AuditTrail::class)->record(auditRecord(auditEntityId(1), 'model', null, null, 'Etapa reescrita pela IA'));
    Carbon::setTestNow();

    $history = app(AuditTrail::class)->historyForEntity(auditEntityId(1));

    expect($history)->toHaveCount(2)
        ->and($history[0]->description)->toBe('Etapa reescrita pela IA')
        ->and($history[0]->color)->toBe('var(--violet)')
        ->and($history[1]->description)->toBe('Importação inicial')
        ->and($history[1]->color)->toBe('var(--info)');
});
