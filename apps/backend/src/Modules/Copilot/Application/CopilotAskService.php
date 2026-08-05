<?php

declare(strict_types=1);

namespace Atlas\Modules\Copilot\Application;

/**
 * Read-only RAG orchestration. Search and graph reads each authorize before
 * retrieval; this service never treats the model output as persisted knowledge.
 */
final readonly class CopilotAskService
{
    public function __construct(
        private KnowledgeRetriever $retriever,
        private CopilotExecutionService $execution,
        private ReasoningTraceBuilder $reasoning,
    ) {}

    /** @param array{id:string,grants:list<array{role?:string,scope:array<string,string>,permissions:list<string>,classification_ceiling:string}>} $actor
     * @param  array{organization:string,application?:string,module?:string}  $scope
     * @param  list<float|int>|null  $embedding
     * @return array<string,mixed>
     */
    public function ask(array $actor, string $idempotencyKey, string $correlationId, string $question, array $scope, ?array $embedding = null): array
    {
        $intent = $this->intent($question, $scope);
        $retrieval = $this->retriever->retrieve($actor, $question, $scope, $embedding);
        $sources = array_values(array_filter($retrieval['sources'], fn (array $result): bool => $this->hasCitation($result)));
        if (! $this->hasSufficientEvidence($sources)) {
            throw new CopilotInsufficientEvidence('Não há evidência publicada e citável suficiente para responder a esta pergunta.');
        }

        $entities = $retrieval['entities'];
        $context = array_map(fn (array $source): array => [
            'content' => $this->sourceText($source),
            'classification' => (string) $source['evidence']['classification'],
            'origin' => (string) $source['evidence']['origin_uri'],
            'revision' => (string) $source['evidence']['source_revision'],
            'method' => (string) $source['evidence']['method'],
            'confidence' => (float) $source['evidence']['confidence'],
        ], $sources);
        $trace = $this->reasoning->build($intent, $retrieval['retrieval'], $sources, count($retrieval['sources']) - count($sources));
        $model = $this->execution->execute(new CopilotRequest(
            $idempotencyKey,
            (string) $actor['id'],
            $correlationId,
            $this->systemPrompt(),
            $question,
            $context,
            reasoningTrace: $trace,
            candidateSources: array_map(fn (array $source): array => [
                'entity_id' => (string) ($source['entity_id'] ?? ''), 'entity_type' => (string) $source['type']['name'],
                'content' => $this->sourceText($source), 'scope' => $scope,
                'origin_uri' => (string) $source['evidence']['origin_uri'], 'source_revision' => (string) $source['evidence']['source_revision'],
                'method' => (string) $source['evidence']['method'], 'confidence' => (float) $source['evidence']['confidence'],
                'classification' => (string) $source['evidence']['classification'],
            ], $sources),
        ));
        if (! $this->hasRequiredCitations($model['text'], $sources)) {
            throw new CopilotInsufficientEvidence('A resposta do modelo não contém revision id e URI para cada afirmação.');
        }

        $relevance = $this->relevance($sources);

        return [
            'opening' => trim($model['text']),
            'confidence' => (int) round(array_sum(array_column(array_column($sources, 'evidence'), 'confidence')) / count($sources) * 100),
            'source_count' => count($sources),
            'blocks' => array_map(fn (array $source): array => ['text' => $this->sourceText($source), 'citation' => ['revision_id' => $source['evidence']['source_revision'], 'uri' => $source['evidence']['origin_uri']]], $sources),
            'sources' => array_map(fn (array $source, int $index): array => ['type' => $source['type'], 'name' => $source['title'], 'freshness' => $source['freshness'], 'relevance' => $relevance[$index], 'provenance' => ['revision_id' => $source['evidence']['source_revision'], 'uri' => $source['evidence']['origin_uri'], 'method' => $source['evidence']['method'], 'confidence' => $source['evidence']['confidence']]], $sources, array_keys($sources)),
            'entities' => $entities,
            'actions' => [
                ['id' => 'deepen_process', 'label' => 'Aprofundar no processo'],
                ['id' => 'view_graph', 'label' => 'Ver no Graph'],
                ['id' => 'create_faq', 'label' => 'Transformar em FAQ'],
                ['id' => 'report_error', 'label' => 'Reportar erro'],
            ],
            'reasoning' => $this->reasoning->steps($model['reasoning_trace']),
            'reasoning_trace' => $model['reasoning_trace'],
            'intent' => $intent,
            'execution' => ['id' => $model['execution_id'], 'model' => $model['model'], 'model_version' => $model['model_version']],
        ];
    }

    /** @param array<string,mixed> $result */
    private function hasCitation(array $result): bool
    {
        return trim((string) ($result['evidence']['source_revision'] ?? '')) !== '' && filter_var($result['evidence']['origin_uri'] ?? null, FILTER_VALIDATE_URL) !== false;
    }

    /** @param list<array<string,mixed>> $sources */
    private function hasSufficientEvidence(array $sources): bool
    {
        $minimumSources = max((int) config('knowledge_gaps.minimum_source_count', 1), 1);
        if (count($sources) < $minimumSources) {
            return false;
        }
        $score = array_sum(array_map(fn (array $source): float => (float) $source['evidence']['confidence'], $sources)) / count($sources);

        return $score >= (float) config('knowledge_gaps.minimum_evidence_score', 0.80);
    }

    /** @param list<array<string,mixed>> $sources */
    private function hasRequiredCitations(string $text, array $sources): bool
    {
        foreach ($sources as $source) {
            $revision = preg_quote((string) $source['evidence']['source_revision'], '/');
            $uri = preg_quote((string) $source['evidence']['origin_uri'], '/');
            if (preg_match('/(?:revision(?:_id)?|revisão)\s*[:=]\s*'.$revision.'.{0,120}(?:uri|URL)\s*[:=]\s*'.$uri.'/iu', $text) !== 1) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string,mixed> $source */
    private function sourceText(array $source): string
    {
        return trim(implode('', (array) $source['excerpt']));
    }

    /**
     * @param  list<array<string,mixed>>  $sources
     * @return list<int>
     */
    private function relevance(array $sources): array
    {
        if ($sources === []) {
            return [];
        }
        $max = max(array_map(fn (array $source): float => (float) ($source['ranking']['rrf'] ?? 0), $sources)) ?: 1.0;

        return array_map(fn (array $source): int => (int) round(((float) ($source['ranking']['rrf'] ?? 0) / $max) * 100), $sources);
    }

    /**
     * @param  array{organization:string,application?:string,module?:string}  $scope
     * @return array{kind:string,scope:string}
     */
    private function intent(string $question, array $scope): array
    {
        $kind = preg_match('/como|fluxo|processo|funciona/iu', $question) ? 'explicação de processo' : 'consulta de conhecimento';

        return ['kind' => $kind, 'scope' => implode(' › ', array_filter($scope))];
    }

    private function systemPrompt(): string
    {
        return 'Você é o Copiloto do conhecimento. Responda apenas com o contexto fornecido; não complete lacunas. Cada afirmação deve citar TODAS as fontes que a sustentam no formato "revision_id: <id>; uri: <uri>". Se as evidências forem insuficientes, diga que não sabe.';
    }
}
