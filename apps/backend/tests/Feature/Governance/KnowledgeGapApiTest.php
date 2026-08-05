<?php

declare(strict_types=1);

use App\Models\User;
use Atlas\Contracts\Dto\KnowledgeGapOccurrence;
use Atlas\Contracts\Port\KnowledgeGapRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function knowledgeGapUser(array $permissions): User
{
    $user = User::factory()->create();
    DB::table('identity_grants')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'role' => 'aprovador_de_dominio', 'scope' => json_encode(['organization' => 'nordika']), 'permissions' => json_encode($permissions), 'classification_ceiling' => 'interno', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);

    return $user;
}

it('agrupa perguntas semanticamente equivalentes e expõe período e cor de severidade', function (): void {
    $recorder = app(KnowledgeGapRecorder::class);
    $first = $recorder->record(new KnowledgeGapOccurrence('Como estornar o frete?', ['organization' => 'nordika'], 'copilot.abstention', 'copilot_evidence_threshold_v1', 1.0));
    $second = $recorder->record(new KnowledgeGapOccurrence('Como faço o estorno de frete?', ['organization' => 'nordika'], 'search.zero_result', 'search_zero_result_v1', 1.0));

    expect($second)->toBe($first);
    $this->actingAs(knowledgeGapUser(['knowledge.read']))->getJson('/api/v1/knowledge-gaps?organization=nordika')
        ->assertOk()->assertJsonPath('data.0.question', 'Como estornar o frete?')->assertJsonPath('data.0.occurrence_count', 2)->assertJsonPath('data.0.period.days', 1)->assertJsonPath('data.0.severity.color', 'var(--warn)')->assertJsonPath('data.0.provenance.method', 'unanswered_question_v1');
});

it('atribui uma lacuna e cria uma tarefa de documentação auditável', function (): void {
    $gapId = app(KnowledgeGapRecorder::class)->record(new KnowledgeGapOccurrence('Qual é o SLA do Itaú?', ['organization' => 'nordika'], 'search.zero_result', 'search_zero_result_v1', 1.0));

    $this->actingAs(knowledgeGapUser(['knowledge.read', 'knowledge.approve']))
        ->postJson('/api/v1/knowledge-gaps/' . $gapId . '/assign', ['owner_id' => 'human:marina', 'organization' => 'nordika'], ['Idempotency-Key' => 'knowledge-gap-assign-001'])
        ->assertCreated()->assertJsonPath('gap_id', $gapId)->assertJsonPath('owner.id', 'human:marina')->assertJsonPath('documentation_task.status', 'open');

    expect(DB::table('documentation_tasks')->where('knowledge_gap_id', $gapId)->where('owner_id', 'human:marina')->exists())->toBeTrue()
        ->and(DB::table('audit_log')->where('subject_kind', 'knowledge_gap')->where('subject_id', $gapId)->where('action', 'knowledge_gap.assigned')->exists())->toBeTrue();
});
