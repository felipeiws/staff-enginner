<?php

declare(strict_types=1);

use Atlas\Contracts\Enum\GraphPredicate;
use Atlas\Modules\KnowledgeEngine\Application\KnowledgeLifecycleService;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Identifier;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\LifecycleState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function lifecycleId(int $number): string
{
    return sprintf('40000000-0000-4000-8000-%012d', $number);
}

function lifecycleFixture(int $number, string $state = 'observed', ?string $entityId = null): array
{
    $sourceId = lifecycleId($number * 10 + 1);
    $artifactId = lifecycleId($number * 10 + 2);
    $sourceRevisionId = lifecycleId($number * 10 + 3);
    $entityId ??= lifecycleId($number * 10 + 4);
    $revisionId = lifecycleId($number * 10 + 5);
    $now = now();
    DB::table('sources')->insert(['id' => $sourceId, 'type' => 'git_repository', 'scope' => json_encode(['application' => 'atlas']), 'classification' => 'interno', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
    DB::table('artifacts')->insert(['id' => $artifactId, 'source_id' => $sourceId, 'external_identifier' => "doc-{$number}", 'uri' => "file:///doc-{$number}", 'content_hash' => "hash-{$number}", 'media_type' => 'text/plain', 'created_at' => $now, 'updated_at' => $now]);
    DB::table('revisions')->insert(['id' => $sourceRevisionId, 'artifact_id' => $artifactId, 'source_revision' => "git:{$number}", 'observed_at' => $now, 'checksum' => "checksum-{$number}", 'metadata' => json_encode([]), 'created_at' => $now, 'updated_at' => $now]);
    if (! DB::table('entities')->where('id', $entityId)->exists()) {
        DB::table('entities')->insert(['id' => $entityId, 'entity_type' => 'Documento', 'family' => 'conhecimento', 'scope' => json_encode(['application' => 'atlas']), 'external_natural_key' => "documento-{$entityId}", 'owner_id' => 'human:ana', 'owner_type' => 'human', 'created_at' => $now, 'updated_at' => $now]);
    }
    DB::table('entity_revisions')->insert(['id' => $revisionId, 'entity_id' => $entityId, 'version' => (int) DB::table('entity_revisions')->where('entity_id', $entityId)->max('version') + 1, 'lifecycle_state' => $state, 'title' => "Documento {$number}", 'attributes' => json_encode(['method' => 'parser@1', 'processed_at' => $now->toISOString()]), 'source_revision_id' => $sourceRevisionId, 'confidence' => 0.9, 'published_at' => $state === 'published' ? $now : null, 'created_at' => $now, 'updated_at' => $now]);

    return ['entity_id' => $entityId, 'revision_id' => $revisionId];
}

function lifecyclePolicy(bool $automatic): void
{
    DB::table('policies')->insert(['id' => lifecycleId(900), 'name' => 'ciclo-de-vida', 'priority' => 100, 'active' => true, 'rules' => json_encode(['applies_to' => [], 'automatic_publication' => $automatic, 'review_required_classifications' => [], 'approver_roles' => ['technical'], 'retraction_behavior' => 'retain_audit_history']), 'created_by_id' => 'human:ana', 'created_by_type' => 'human', 'created_at' => now(), 'updated_at' => now()]);
}

function lifecycleAssertion(int $number, string $subjectRevisionId, string $state, string $literal): string
{
    $id = lifecycleId(600 + $number);
    DB::table('assertions')->insert(['id' => $id, 'subject_entity_revision_id' => $subjectRevisionId, 'predicate' => GraphPredicate::Documents->value, 'literal_value' => json_encode(['value' => $literal]), 'scope' => json_encode(['application' => 'atlas']), 'confidence' => 0.8, 'method' => 'human_declared', 'actor_id' => 'human:ana', 'actor_type' => 'human', 'lifecycle_state' => $state, 'valid_from' => now(), 'created_at' => now(), 'updated_at' => now()]);

    return $id;
}

it('covers observed to candidate and candidate to review with audit and outbox events', function (): void {
    $fixture = lifecycleFixture(1);
    $service = app(KnowledgeLifecycleService::class);
    $candidate = $service->transitionRevision(new Identifier($fixture['revision_id']), LifecycleState::Candidate, 'worker:normalizer', 'worker', 'Normalização concluída.', 'lifecycle-1', lifecycleId(801));
    $review = $service->transitionRevision($candidate->id, LifecycleState::Review, 'worker:normalizer', 'worker', 'Política exige revisão.', 'lifecycle-2', lifecycleId(802));

    expect($review->lifecycleState)->toBe(LifecycleState::Review)
        ->and(DB::table('audit_log')->count())->toBe(2)
        ->and(DB::table('outbox_events')->where('tipo', 'KnowledgeRevisionLifecycleChanged')->count())->toBe(2);
});

it('covers candidate to published automatically and review to published by a human', function (): void {
    lifecyclePolicy(true);
    $automatic = lifecycleFixture(2, 'candidate');
    $review = lifecycleFixture(3, 'review');
    $service = app(KnowledgeLifecycleService::class);

    expect($service->transitionRevision(new Identifier($automatic['revision_id']), LifecycleState::Published, 'service:policy', 'service', 'Política permite publicação automática.', 'lifecycle-3')->lifecycleState)->toBe(LifecycleState::Published)
        ->and($service->transitionRevision(new Identifier($review['revision_id']), LifecycleState::Published, 'human:ana', 'human', 'Aprovação da revisão.', 'lifecycle-4')->lifecycleState)->toBe(LifecycleState::Published);
});

it('covers review to retracted and published to retracted while propagating assertions dependentes', function (): void {
    $review = lifecycleFixture(4, 'review');
    $published = lifecycleFixture(5, 'published');
    $assertionId = lifecycleAssertion(1, $published['revision_id'], 'published', 'referência');
    $service = app(KnowledgeLifecycleService::class);

    expect($service->transitionRevision(new Identifier($review['revision_id']), LifecycleState::Retracted, 'human:ana', 'human', 'Rejeitada na curadoria.', 'lifecycle-5')->lifecycleState)->toBe(LifecycleState::Retracted);
    $service->transitionRevision(new Identifier($published['revision_id']), LifecycleState::Retracted, 'human:ana', 'human', 'Fonte retirada.', 'lifecycle-6');
    expect(DB::table('assertions')->where('id', $assertionId)->value('lifecycle_state'))->toBe('retracted')
        ->and(DB::table('assertions')->where('id', $assertionId)->exists())->toBeTrue();
});

it('covers published to superseded and publication of a new revision supersedes the previous one', function (): void {
    $entityId = lifecycleId(70);
    $old = lifecycleFixture(7, 'published', $entityId);
    $successor = lifecycleFixture(8, 'candidate', $entityId);
    lifecyclePolicy(true);
    $service = app(KnowledgeLifecycleService::class);
    expect($service->transitionRevision(new Identifier($old['revision_id']), LifecycleState::Superseded, 'human:ana', 'human', 'Substituída por correção.', 'lifecycle-7', null, new Identifier($successor['revision_id']))->lifecycleState)->toBe(LifecycleState::Superseded);

    $old2 = lifecycleFixture(9, 'published', lifecycleId(71));
    $new2 = lifecycleFixture(10, 'candidate', $old2['entity_id']);
    $service->transitionRevision(new Identifier($new2['revision_id']), LifecycleState::Published, 'service:policy', 'service', 'Nova revisão publicada.', 'lifecycle-8');
    expect(DB::table('entity_revisions')->where('id', $old2['revision_id'])->value('lifecycle_state'))->toBe('superseded')
        ->and(DB::table('entity_revisions')->where('id', $old2['revision_id'])->value('superseded_by'))->toBe($new2['revision_id']);
});

it('keeps concurrent conflicting assertions and registers contradicts between them', function (): void {
    lifecyclePolicy(true);
    $fixture = lifecycleFixture(11, 'published');
    $first = lifecycleAssertion(2, $fixture['revision_id'], 'published', 'ativo');
    $second = lifecycleAssertion(3, $fixture['revision_id'], 'candidate', 'inativo');

    app(KnowledgeLifecycleService::class)->transitionAssertion(new Identifier($second), LifecycleState::Published, 'service:policy', 'service', 'Política permite publicação automática.', 'lifecycle-9');

    expect(DB::table('assertions')->where('id', $first)->value('lifecycle_state'))->toBe('published')
        ->and(DB::table('assertions')->where('id', $second)->value('lifecycle_state'))->toBe('published')
        ->and(DB::table('assertion_relations')->where('source_assertion_id', $first)->orWhere('target_assertion_id', $first)->where('predicate', 'contradicts')->count())->toBe(1);
});
