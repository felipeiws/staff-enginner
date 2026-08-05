<?php

declare(strict_types=1);

namespace Atlas\Modules\Copilot\Application;

use Atlas\Contracts\Dto\Actor;
use Atlas\Contracts\Dto\AuditRecord;
use Atlas\Contracts\Port\AuditTrail;
use Atlas\Modules\Copilot\Domain\ModelProvider;
use Atlas\Modules\Copilot\Domain\ModelRequest;
use DomainException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;

/** Creates review-only FAQ/process-update candidates from cited Copilot evidence. */
final readonly class KnowledgeCandidateService
{
    public function __construct(private DatabaseManager $database, private ModelProvider $model, private ExternalProviderContentPolicy $contentPolicy, private AuditTrail $auditTrail) {}

    /** @param array{organization:string,application?:string,module?:string} $scope
     * @return array{approval_id:string,entity_id:string,revision_id:string,kind:string,confidence:int,sources:list<array<string,mixed>>}
     */
    public function propose(array $actor, string $idempotencyKey, string $correlationId, string $executionId, string $kind, array $scope, ?string $processEntityId = null): array
    {
        $this->assertReadable($actor, $scope, 'publico');
        if (! in_array($kind, ['faq', 'process_update'], true)) {
            throw new DomainException('A IA só pode propor FAQ ou atualização de um Processo existente.');
        }
        if ($kind === 'process_update' && $processEntityId === null) {
            throw new DomainException('Atualização de processo exige um Processo existente selecionado por humano.');
        }
        $execution = $this->database->table('executions')->where('id', $executionId)->where('status', 'completed')->first();
        if ($execution === null) {
            throw new DomainException('Execução citável do Copiloto não encontrada.');
        }
        $stored = json_decode((string) $execution->result, true, 512, JSON_THROW_ON_ERROR);
        $sources = array_values(array_filter((array) ($stored['candidate_sources'] ?? []), fn (mixed $source): bool => is_array($source) && $this->sourceMatchesScope($source, $scope)));
        if ($sources === []) {
            throw new DomainException('A execução não possui fontes citáveis no escopo solicitado.');
        }
        foreach ($sources as $source) {
            $this->assertReadable($actor, $scope, (string) ($source['classification'] ?? 'interno'));
        }
        $context = array_map(fn (array $source): array => ['content' => (string) $source['content'], 'classification' => (string) $source['classification'], 'origin' => (string) $source['origin_uri'], 'revision' => (string) $source['source_revision'], 'method' => (string) $source['method'], 'confidence' => (float) $source['confidence']], $sources);
        $this->contentPolicy->assertAllowed($context);
        $draft = $this->model->generate(new ModelRequest((string) Str::uuid(), $idempotencyKey, (string) config('copilot.model'), (string) config('copilot.model_version'), (float) config('copilot.temperature'), min(1024, (int) config('copilot.max_output_tokens')), 'Você redige propostas candidatas, nunca fatos publicados.', [['role' => 'user', 'content' => $this->prompt($kind, $context)]], (int) config('copilot.timeout_seconds'), (int) config('copilot.retry_attempts')));
        $this->assertCitations($draft->text, $sources);
        $confidence = $this->confidence($sources);

        return $this->database->transaction(function () use ($correlationId, $executionId, $kind, $processEntityId, $scope, $sources, $draft, $confidence): array {
            $anchor = $this->anchor($sources[0]);
            $now = now();
            if ($kind === 'faq') {
                $entityId = (string) Str::uuid();
                $this->database->table('entities')->insert(['id' => $entityId, 'entity_type' => 'FAQ', 'family' => 'conhecimento', 'scope' => json_encode($scope, JSON_THROW_ON_ERROR), 'external_natural_key' => 'FAQ-'.strtoupper(substr(str_replace('-', '', $entityId), 0, 8)), 'owner_id' => 'human:unassigned', 'owner_type' => 'human', 'created_at' => $now, 'updated_at' => $now]);
                $version = 1;
                $title = 'FAQ · '.Str::limit((string) ($sources[0]['content'] ?? 'Conhecimento relacionado'), 72, '');
            } else {
                $entity = $this->database->table('entities')->where('id', $processEntityId)->where('entity_type', 'Processo')->first();
                if ($entity === null) {
                    throw new DomainException('A IA não cria Processo: selecione um Processo existente para atualização.');
                }
                $entityId = (string) $entity->id;
                $version = (int) $this->database->table('entity_revisions')->where('entity_id', $entityId)->max('version') + 1;
                $title = 'Atualização proposta · '.(string) $this->database->table('entity_revisions')->where('entity_id', $entityId)->orderByDesc('version')->value('title');
            }
            $revisionId = (string) Str::uuid();
            $proposalId = (string) Str::uuid();
            $provenance = array_map(fn (array $source): array => ['uri' => $source['origin_uri'], 'revision' => $source['source_revision'], 'method' => $source['method'], 'confidence' => $source['confidence']], $sources);
            $attributes = ['method' => 'inference@'.config('copilot.model_version'), 'processed_at' => $now->toISOString(), 'classification' => (string) $sources[0]['classification'], 'source_citations' => $provenance, 'execution_id' => $executionId];
            $this->database->table('entity_revisions')->insert(['id' => $revisionId, 'entity_id' => $entityId, 'version' => $version, 'lifecycle_state' => 'review', 'title' => $title, 'body' => $draft->text, 'attributes' => json_encode($attributes, JSON_THROW_ON_ERROR), 'source_revision_id' => $anchor['id'], 'confidence' => $confidence, 'created_at' => $now, 'updated_at' => $now]);
            $proposal = ['kind' => 'ai_knowledge_candidate', 'candidate_kind' => $kind, 'origin' => ['kind' => 'ai', 'revision' => $anchor['source_revision'], 'detected_by' => 'Copiloto'], 'ai_analysis' => ['model' => config('copilot.model'), 'confidence' => $confidence, 'text' => 'Evidências correlatas agrupadas para revisão humana.'], 'model_configuration' => ['provider' => config('copilot.provider'), 'model' => config('copilot.model'), 'model_version' => config('copilot.model_version'), 'temperature' => config('copilot.temperature')], 'current_text' => '', 'proposed_text' => $draft->text, 'source_citations' => $provenance, 'evidence_grouping' => $this->grouping($sources, $scope), 'impacted_entities' => array_values(array_filter(array_column($sources, 'entity_id'))), 'impact_level' => 'baixo', 'method' => $attributes['method'], 'confidence' => $confidence, 'processed_at' => $now->toISOString(), 'commit_message' => 'docs(knowledge): proposta de '.($kind === 'faq' ? 'FAQ' : 'atualização de processo').' revisada por humano'];
            $this->database->table('approvals')->insert(['id' => $proposalId, 'subject_kind' => 'entity_revision', 'entity_revision_id' => $revisionId, 'proposal' => json_encode($proposal, JSON_THROW_ON_ERROR), 'proposed_by_id' => 'model:copilot', 'proposed_by_type' => 'model', 'proposed_at' => $now, 'correlation_id' => $correlationId, 'created_at' => $now, 'updated_at' => $now]);
            $this->auditTrail->record(new AuditRecord(new Actor('model:copilot', 'model'), 'knowledge_candidate.proposed', 'entity_revision', $revisionId, $entityId, $revisionId, $anchor['id'], null, 'Rascunho gerado como candidato; aprovação humana é obrigatória.', $correlationId, $scope, null, ['approval_id' => $proposalId, 'source_citations' => $provenance], (string) $sources[0]['classification'], $attributes['method'], $confidence));

            return ['approval_id' => $proposalId, 'entity_id' => $entityId, 'revision_id' => $revisionId, 'kind' => $kind, 'confidence' => (int) round($confidence * 100), 'sources' => $provenance];
        });
    }

    private function sourceMatchesScope(array $source, array $scope): bool
    {
        foreach ($scope as $key => $value) {
            if (($source['scope'][$key] ?? null) !== $value) {
                return false;
            }
        }

return true;
    }

    private function assertReadable(array $actor, array $scope, string $classification): void
    {
        $levels = ['publico' => 0, 'interno' => 1, 'restrito' => 2, 'confidencial' => 3];
        foreach ((array) ($actor['grants'] ?? []) as $grant) {
            if (! in_array('knowledge.read', (array) ($grant['permissions'] ?? []), true) && ! in_array('*', (array) ($grant['permissions'] ?? []), true)) {
                continue;
            } if (($levels[$grant['classification_ceiling'] ?? ''] ?? -1) < ($levels[$classification] ?? PHP_INT_MAX)) {
                continue;
            } $matches = true;
            foreach ($scope as $key => $value) {
                if (isset($grant['scope'][$key]) && $grant['scope'][$key] !== $value) {
                    $matches = false;
                }
            } if ($matches && isset($grant['scope']['organization'])) {
                return;
            }
        } throw new DomainException('Acesso negado para este escopo ou classificação.');
    }

    /** @return array{id:string,source_revision:string} */
    private function anchor(array $source): array
    {
        $row = $this->database->table('revisions as r')->join('artifacts as a', 'a.id', '=', 'r.artifact_id')->where('r.source_revision', (string) $source['source_revision'])->where('a.uri', (string) $source['origin_uri'])->select(['r.id', 'r.source_revision'])->first();
        if ($row === null) {
            throw new DomainException('A fonte citada não possui revisão rastreável.');
        }

return ['id' => (string) $row->id, 'source_revision' => (string) $row->source_revision];
    }

    private function assertCitations(string $text, array $sources): void
    {
        foreach ($sources as $source) {
            if (! str_contains($text, (string) $source['source_revision']) || ! str_contains($text, (string) $source['origin_uri'])) {
                throw new DomainException('O rascunho da IA não preservou todas as citações de fonte.');
            }
        }
    }

    private function confidence(array $sources): float
    {
        $remaining = 1.0;
        foreach ($sources as $source) {
            $remaining *= 1 - max(0.0, min(1.0, (float) $source['confidence']));
        }

return round(1 - $remaining, 3);
    }

    private function prompt(string $kind, array $context): string
    {
        $label = $kind === 'faq' ? 'FAQ' : 'atualização de Processo existente';

        return "Redija um rascunho de {$label} a partir apenas das evidências. Cite cada fonte literalmente como 'revision_id: ...; uri: ...'. Não crie Processo e não invente lacunas.\n\n".implode("\n\n", array_map(fn (array $s): string => "[revision_id: {$s['revision']}; uri: {$s['origin']}; método: {$s['method']}; confiança: {$s['confidence']}]\n{$s['content']}", $context));
    }

    private function grouping(array $sources, array $scope): array
    {
        $types = array_count_values(array_map(fn (array $source): string => (string) $source['entity_type'], $sources));
        $rated = 0;
        foreach ($this->database->table('executions')->where('status', 'completed')->get(['result']) as $execution) {
            $result = json_decode((string) $execution->result, true) ?: [];
            if ((float) ($result['feedback']['rating'] ?? 0) >= 4.0) {
                $rated++;
            }
        }

return ['method' => 'correlated_evidence_v1', 'scope' => $scope, 'tickets' => $types['Issue'] ?? 0, 'repeated_questions' => (int) $this->database->table('knowledge_gaps')->where('scope_key', hash('sha256', json_encode($scope, JSON_THROW_ON_ERROR)))->sum('occurrence_count'), 'well_rated_copilot_answers' => $rated, 'sources' => count($sources)];
    }
}
