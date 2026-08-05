<?php

declare(strict_types=1);

namespace Atlas\Modules\Copilot\Application;

/**
 * Produces the auditable explanation shown below Copilot sources. Every field
 * is calculated from the request, search telemetry, and selected evidence.
 */
final class ReasoningTraceBuilder
{
    /**
     * @param array{kind:string,scope:string} $intent
     * @param array<string,mixed> $retrieval
     * @param list<array<string,mixed>> $sources
     * @return array<string,mixed>
     */
    public function build(array $intent, array $retrieval, array $sources, int $discardedWithoutCitation): array
    {
        $fused = (int) ($retrieval['fused_candidates'] ?? count($sources));
        $discardedByLimit = (int) ($retrieval['discarded_by_answer_limit'] ?? 0);
        $discarded = [
            ['reason' => 'limite_da_resposta', 'count' => $discardedByLimit],
            ['reason' => 'sem_proveniencia_citavel', 'count' => $discardedWithoutCitation],
        ];
        $discarded = array_values(array_filter($discarded, static fn(array $item): bool => $item['count'] > 0));

        $rules = array_values(array_filter($sources, static fn(array $source): bool => in_array((string) ($source['type']['name'] ?? ''), ['Regra', 'Regra de negócio'], true)));
        $confrontations = $this->confrontations($sources);
        $rerank = (array) ($retrieval['rerank'] ?? ['applied' => false, 'decision' => 'not_reported']);

        return [
            'version' => 'copilot_reasoning_trace@1',
            'interpretation' => ['kind' => $intent['kind'], 'scope' => $intent['scope'], 'method' => 'intent_classifier_v1'],
            'retrieval' => [
                'lexical_candidates' => (int) ($retrieval['lexical_candidates'] ?? 0),
                'semantic_candidates' => (int) ($retrieval['semantic_candidates'] ?? 0),
                'fused_candidates' => $fused,
                'selected_candidates' => count($sources),
                'discarded_candidates' => array_sum(array_column($discarded, 'count')),
                'discarded' => $discarded,
            ],
            'rerank' => ['applied' => (bool) ($rerank['applied'] ?? false), 'decision' => (string) ($rerank['decision'] ?? 'not_reported')],
            'documentation_code_confrontations' => $confrontations,
            'business_rule_constraints' => array_map(fn(array $source): array => [
                'source_id' => (string) $source['id'], 'name' => (string) $source['title'],
                'revision_id' => (string) $source['evidence']['source_revision'], 'uri' => (string) $source['evidence']['origin_uri'],
                'constraint' => $this->text($source),
            ], $rules),
        ];
    }

    /**
     * @param array<string,mixed> $trace
     * @return list<string>
     */
    public function steps(array $trace): array
    {
        $interpretation = $trace['interpretation'];
        $retrieval = $trace['retrieval'];
        $discarded = (int) $retrieval['discarded_candidates'];
        $reasons = implode(' ou ', array_map(static fn(array $item): string => str_replace('_', ' ', (string) $item['reason']), $retrieval['discarded']));
        $steps = [
            "Interpretei a pergunta como \"{$interpretation['kind']}\" no escopo {$interpretation['scope']}.",
            "Recuperei {$retrieval['fused_candidates']} trechos; descartei {$discarded}" . ($discarded > 0 ? " por {$reasons}." : '.'),
            (bool) $trace['rerank']['applied'] ? 'Apliquei reranking conforme a decisão registrada no pipeline.' : 'Não apliquei reranking: ' . $trace['rerank']['decision'] . '.',
        ];
        $confrontations = $trace['documentation_code_confrontations'];
        $steps[] = $confrontations === []
            ? 'Nenhum confronto entre documentação e código foi possível com as evidências recuperadas.'
            : 'Confrontei ' . count($confrontations) . ' par(es) de documentação e código pelo identificador de entidade.';
        $rules = $trace['business_rule_constraints'];
        $steps[] = $rules === []
            ? 'Nenhuma regra de negócio publicada foi recuperada para restringir a resposta.'
            : 'A regra ' . (string) $rules[0]['name'] . ' restringe a resposta: ' . (string) $rules[0]['constraint'];

        return $steps;
    }

    /**
     * @param list<array<string,mixed>> $sources
     * @return list<array<string,mixed>>
     */
    private function confrontations(array $sources): array
    {
        $documentation = array_values(array_filter($sources, static fn(array $source): bool => in_array((string) ($source['type']['name'] ?? ''), ['Documento', 'Processo', 'Regra', 'FAQ'], true)));
        $code = array_values(array_filter($sources, static fn(array $source): bool => in_array((string) ($source['type']['name'] ?? ''), ['Código', 'Code'], true)));
        $result = [];
        foreach ($documentation as $document) {
            foreach ($code as $implementation) {
                if (($document['entity_id'] ?? '') === '' || $document['entity_id'] !== ($implementation['entity_id'] ?? '')) {
                    continue;
                }
                $result[] = ['entity_id' => (string) $document['entity_id'], 'documentation_revision_id' => (string) $document['evidence']['source_revision'], 'code_revision_id' => (string) $implementation['evidence']['source_revision'], 'method' => 'shared_entity_id_v1', 'outcome' => 'compared'];
            }
        }
        return $result;
    }

    /** @param array<string,mixed> $source */
    private function text(array $source): string
    {
        return trim(implode('', (array) ($source['excerpt'] ?? [])));
    }
}
