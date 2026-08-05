<?php

declare(strict_types=1);

use Atlas\Contracts\Enum\GraphPredicate;
use Atlas\Modules\KnowledgeEngine\Application\AssertionEvidenceInput;
use Atlas\Modules\KnowledgeEngine\Application\AssertionGraphService;
use Atlas\Modules\KnowledgeEngine\Application\CreateAssertion;
use Atlas\Modules\KnowledgeEngine\Application\KnowledgeLifecycleService;
use Atlas\Modules\KnowledgeEngine\Application\RevisionReference;
use Atlas\Modules\KnowledgeEngine\Domain\Aggregate\EvidenceKind;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Identifier;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\LifecycleState;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Scope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function assertionGraphId(int $number): string
{
    return sprintf('70000000-0000-4000-8000-%012d', $number);
}

/** @return array{left_entity: string, left_revision: string, right_entity: string, right_revision: string, artifact: string} */
function assertionGraphFixture(): array
{
    $now = now();
    $source = assertionGraphId(1);
    $artifact = assertionGraphId(2);
    $sourceRevision = assertionGraphId(3);
    $leftEntity = assertionGraphId(4);
    $leftRevision = assertionGraphId(5);
    $rightEntity = assertionGraphId(6);
    $rightRevision = assertionGraphId(7);
    DB::table('sources')->insert(['id' => $source, 'type' => 'git_repository', 'scope' => json_encode(['application' => 'atlas']), 'classification' => 'interno', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
    DB::table('artifacts')->insert(['id' => $artifact, 'source_id' => $source, 'external_identifier' => 'docs/relacoes.md', 'uri' => 'file:///docs/relacoes.md', 'content_hash' => 'hash-relacoes', 'media_type' => 'text/markdown', 'created_at' => $now, 'updated_at' => $now]);
    DB::table('revisions')->insert(['id' => $sourceRevision, 'artifact_id' => $artifact, 'source_revision' => 'git:abc', 'observed_at' => $now, 'checksum' => 'checksum-relacoes', 'metadata' => json_encode([]), 'created_at' => $now, 'updated_at' => $now]);
    foreach ([[$leftEntity, $leftRevision, 'Tela', 'Detalhe do Pedido'], [$rightEntity, $rightRevision, 'Regra', 'RN-142']] as [$entity, $revision, $type, $title]) {
        DB::table('entities')->insert(['id' => $entity, 'entity_type' => $type, 'family' => 'software', 'scope' => json_encode(['application' => 'atlas']), 'external_natural_key' => strtolower($type).'-'.$entity, 'owner_id' => 'human:ana', 'owner_type' => 'human', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('entity_revisions')->insert(['id' => $revision, 'entity_id' => $entity, 'version' => 1, 'lifecycle_state' => 'published', 'title' => $title, 'attributes' => json_encode([]), 'source_revision_id' => $sourceRevision, 'confidence' => 1, 'published_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
    }
    DB::table('policies')->insert(['id' => assertionGraphId(8), 'name' => 'publicação automática', 'priority' => 100, 'active' => true, 'rules' => json_encode(['applies_to' => [], 'automatic_publication' => true, 'review_required_classifications' => [], 'approver_roles' => [], 'retraction_behavior' => 'retain_audit_history']), 'created_by_id' => 'human:ana', 'created_by_type' => 'human', 'created_at' => $now, 'updated_at' => $now]);

    return compact('leftEntity', 'leftRevision', 'rightEntity', 'rightRevision', 'artifact');
}

it('creates provenance-backed assertions and returns the inverse neighborhood grouped by predicate', function (): void {
    $fixture = assertionGraphFixture();
    $assertion = app(AssertionGraphService::class)->create(new CreateAssertion(
        new Identifier(assertionGraphId(10)),
        new RevisionReference(new Identifier($fixture['leftEntity'])),
        GraphPredicate::Contains,
        new RevisionReference(new Identifier($fixture['rightEntity'])),
        null,
        new Scope(['application' => 'atlas']),
        'parser@1',
        'worker:parser',
        'worker',
        [new AssertionEvidenceInput(new Identifier(assertionGraphId(11)), EvidenceKind::Artifact, new Identifier($fixture['artifact']), 0.8)],
        0.1,
        'assertion-graph-1',
    ));
    app(KnowledgeLifecycleService::class)->transitionAssertion($assertion->id, LifecycleState::Published, 'service:policy', 'service', 'Política automática.', 'assertion-graph-2');

    $neighborhood = app(AssertionGraphService::class)->neighborhood(new Identifier($fixture['rightEntity']));

    expect($assertion->confidence->value)->toBe(0.8)
        ->and($neighborhood->relationsByPredicate)->toHaveKey('belongs_to')
        ->and($neighborhood->relationsByPredicate['belongs_to'][0])->toMatchArray(['predicate' => 'contains', 'inverted' => true, 'confidence' => 0.8])
        ->and($neighborhood->relationsByPredicate['belongs_to'][0]['counterpart']['entity_id'])->toBe($fixture['leftEntity'])
        ->and($neighborhood->relationsByPredicate['belongs_to'][0]['evidence'][0])->toMatchArray(['kind' => 'artifact', 'pointer_id' => $fixture['artifact'], 'weight' => 0.8]);
});

it('requires evidence for a non-human assertion before creating it', function (): void {
    $fixture = assertionGraphFixture();

    expect(fn () => app(AssertionGraphService::class)->create(new CreateAssertion(
        new Identifier(assertionGraphId(12)),
        new RevisionReference(new Identifier($fixture['leftEntity'])),
        GraphPredicate::Calls,
        new RevisionReference(new Identifier($fixture['rightEntity'])),
        null,
        new Scope(['application' => 'atlas']),
        'inference@1',
        'worker:model',
        'worker',
        [],
        0.9,
        'assertion-graph-3',
    )))->toThrow(DomainException::class);
});

it('recomputes published assertions against the concrete successor revision as candidates', function (): void {
    $fixture = assertionGraphFixture();
    $service = app(AssertionGraphService::class);
    $original = $service->create(new CreateAssertion(
        new Identifier(assertionGraphId(13)),
        new RevisionReference(new Identifier($fixture['leftEntity'])),
        GraphPredicate::Calls,
        new RevisionReference(new Identifier($fixture['rightEntity'])),
        null,
        new Scope(['application' => 'atlas']),
        'parser@1',
        'worker:parser',
        'worker',
        [new AssertionEvidenceInput(new Identifier(assertionGraphId(14)), EvidenceKind::Artifact, new Identifier($fixture['artifact']), 0.7)],
        0.1,
        'assertion-graph-4',
    ));
    app(KnowledgeLifecycleService::class)->transitionAssertion($original->id, LifecycleState::Published, 'service:policy', 'service', 'Política automática.', 'assertion-graph-5');
    $successor = assertionGraphId(15);
    DB::table('entity_revisions')->insert(['id' => $successor, 'entity_id' => $fixture['leftEntity'], 'version' => 2, 'lifecycle_state' => 'published', 'title' => 'Detalhe do Pedido v2', 'attributes' => json_encode([]), 'source_revision_id' => assertionGraphId(3), 'confidence' => 1, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

    $service->recomputeForSupersededRevision(new Identifier($fixture['leftRevision']), new Identifier($successor));

    $replacement = DB::table('assertions')->where('id', '!=', (string) $original->id)->first();
    expect(DB::table('assertions')->where('id', (string) $original->id)->value('lifecycle_state'))->toBe('superseded')
        ->and($replacement->subject_entity_revision_id)->toBe($successor)
        ->and($replacement->lifecycle_state)->toBe('candidate')
        ->and(DB::table('assertion_evidence')->where('assertion_id', $replacement->id)->count())->toBe(1);
});
