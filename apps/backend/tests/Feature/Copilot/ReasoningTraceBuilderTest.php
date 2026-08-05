<?php

declare(strict_types=1);

use Atlas\Modules\Copilot\Application\ReasoningTraceBuilder;

it('deriva contagens, descarte, confronto e restrição somente dos dados do pipeline', function (): void {
    $source = static fn(string $id, string $type, string $entity, string $revision, string $text): array => [
        'id' => $id, 'entity_id' => $entity, 'title' => $type . ' ' . $id, 'type' => ['name' => $type],
        'excerpt' => ['pre' => $text, 'hit' => '', 'post' => ''],
        'evidence' => ['source_revision' => $revision, 'origin_uri' => 'https://git.example/' . $id, 'method' => 'parser@1', 'confidence' => .9],
    ];
    $builder = new ReasoningTraceBuilder();
    $trace = $builder->build(
        ['kind' => 'consulta de conhecimento', 'scope' => 'nordika › Compras'],
        ['lexical_candidates' => 8, 'semantic_candidates' => 7, 'fused_candidates' => 10, 'discarded_by_answer_limit' => 3, 'rerank' => ['applied' => false, 'decision' => 'not_configured']],
        [$source('doc-1', 'Processo', 'pedido-1', 'doc-r1', 'Cancelar somente o saldo não faturado.'), $source('code-1', 'Código', 'pedido-1', 'code-r1', 'if faturado then bloqueia'), $source('rn-1', 'Regra', 'regra-1', 'rule-r1', 'Não cancelar pedido integralmente faturado.')],
        2,
    );

    expect($trace['retrieval']['fused_candidates'])->toBe(10)
        ->and($trace['retrieval']['selected_candidates'])->toBe(3)
        ->and($trace['retrieval']['discarded_candidates'])->toBe(5)
        ->and($trace['retrieval']['discarded'])->toBe([['reason' => 'limite_da_resposta', 'count' => 3], ['reason' => 'sem_proveniencia_citavel', 'count' => 2]])
        ->and($trace['documentation_code_confrontations'][0]['documentation_revision_id'])->toBe('doc-r1')
        ->and($trace['documentation_code_confrontations'][0]['code_revision_id'])->toBe('code-r1')
        ->and($trace['business_rule_constraints'][0]['revision_id'])->toBe('rule-r1')
        ->and($builder->steps($trace)[1])->toContain('Recuperei 10 trechos; descartei 5');
});
