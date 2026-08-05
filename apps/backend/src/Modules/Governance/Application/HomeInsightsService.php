<?php

declare(strict_types=1);

namespace Atlas\Modules\Governance\Application;

use Illuminate\Database\DatabaseManager;

/** Read projection for the three Home insight cards; it never creates knowledge. */
final readonly class HomeInsightsService
{
    public function __construct(private DatabaseManager $database) {}

    /** @return array{insights:list<array{type:string,color:string,title:string,description:string,action:string}>} */
    public function list(ApprovalAccess $access): array
    {
        $insights = [];
        foreach ($this->database->table('approvals')->whereNull('decision')->orderByDesc('proposed_at')->get() as $approval) {
            $proposal = json_decode((string) $approval->proposal, true) ?: [];
            if (($proposal['kind'] ?? null) === 'documentation_code_divergence') {
                $insights[] = ['type' => 'Divergência', 'color' => 'var(--danger)', 'title' => (string) ($proposal['title'] ?? 'Divergência entre documentação e código'), 'description' => (string) ($proposal['ai_analysis']['text'] ?? 'Uma divergência com evidências requer revisão.'), 'action' => 'Abrir divergência'];
            }
            if (($proposal['kind'] ?? null) === 'ai_knowledge_candidate') {
                $count = (int) ($proposal['evidence_grouping']['tickets'] ?? count((array) ($proposal['source_citations'] ?? [])));
                $insights[] = ['type' => 'Sugestão', 'color' => 'var(--acc)', 'title' => "Gerar FAQ a partir de {$count} tickets", 'description' => 'A IA agrupou evidências correlatas e redigiu um rascunho de FAQ.', 'action' => 'Revisar rascunho'];
            }
        }
        foreach ($this->database->table('knowledge_gaps')->orderByDesc('occurrence_count')->limit(1)->get() as $gap) {
            $insights[] = ['type' => 'Lacuna', 'color' => 'var(--warn)', 'title' => (int) $gap->occurrence_count.' perguntas sem resposta', 'description' => 'Suporte perguntou sobre “'.(string) $gap->question.'” '.(int) $gap->occurrence_count.'× e não há conhecimento indexado.', 'action' => 'Ver perguntas'];
        }

        return ['insights' => $insights];
    }
}
