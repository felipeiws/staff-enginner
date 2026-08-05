<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Seeds the prototype's product decisions as published, human-declared knowledge. */
final class UxDecisionSeeder extends Seeder
{
    public function run(): void
    {
        $sourceId = '2a3392b6-5a79-477d-a294-d95811f6f901';
        $artifactId = '2a3392b6-5a79-477d-a294-d95811f6f902';
        $sourceRevisionId = '2a3392b6-5a79-477d-a294-d95811f6f903';
        $uri = 'docs/design/prototype/atlas-prototype.html#L1397-L1404';
        $revision = 'atlas-prototype:uxNotes:1397-1404';
        $now = now();
        DB::table('sources')->updateOrInsert(['id' => $sourceId], ['type' => 'git', 'external_system' => 'Atlas repository', 'scope' => json_encode(['organization' => 'nordika']), 'classification' => 'interno', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('artifacts')->updateOrInsert(['id' => $artifactId], ['source_id' => $sourceId, 'external_identifier' => 'atlas-prototype-ux-notes', 'uri' => $uri, 'content_hash' => hash('sha256', $uri), 'media_type' => 'text/html', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('revisions')->updateOrInsert(['id' => $sourceRevisionId], ['artifact_id' => $artifactId, 'source_revision' => $revision, 'observed_at' => $now, 'checksum' => hash('sha256', $revision), 'metadata' => json_encode(['source_lines' => '1397-1404']), 'created_at' => $now, 'updated_at' => $now]);

        foreach ($this->decisions() as $order => [$title, $description, $outcome]) {
            $entityId = (string) Str::uuid();
            $entityRevisionId = (string) Str::uuid();
            $key = 'ux-decision-'.($order + 1);
            $entity = DB::table('entities')->where('entity_type', 'Documento')->where('external_natural_key', $key)->first();
            if ($entity === null) {
                DB::table('entities')->insert(['id' => $entityId, 'entity_type' => 'Documento', 'family' => 'conhecimento', 'scope' => json_encode(['organization' => 'nordika']), 'external_natural_key' => $key, 'owner_id' => 'produto-atlas', 'owner_type' => 'team', 'created_at' => $now, 'updated_at' => $now]);
            } else {
                $entityId = $entity->id;
            }
            if (DB::table('entity_revisions')->where('entity_id', $entityId)->where('version', 1)->exists()) continue;
            DB::table('entity_revisions')->insert(['id' => $entityRevisionId, 'entity_id' => $entityId, 'version' => 1, 'lifecycle_state' => 'published', 'title' => $title, 'body' => $description, 'attributes' => json_encode(['kind' => 'ux_decision', 'ux_decision_order' => $order + 1, 'outcome' => $outcome, 'classification' => 'interno', 'method' => 'human_declared@1.0.0', 'processed_at' => $now->toISOString()]), 'source_revision_id' => $sourceRevisionId, 'confidence' => 1.0, 'published_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    /** @return list<array{string,string,string}> */
    private function decisions(): array
    {
        return [
            ['Busca e IA são o mesmo campo', 'Um único input decide: consulta curta abre resultados facetados, pergunta em linguagem natural abre o Copiloto. Elimina a escolha "buscar ou perguntar?" antes de o usuário saber o que precisa.', 'Elimina 1 decisão e 1 clique em 100% das sessões.'],
            ['Confiança é derivada de evidências, não um número solto', 'O score só aparece acompanhado das fontes e do seu peso. Sem fonte suficiente, a resposta é bloqueada e vira uma "pergunta sem resposta" no dashboard — que alimenta o backlog de documentação.', 'Transforma a falha da IA em insumo de governança.'],
            ['O arquivo desaparece; a entidade manda', 'Markdown continua no Git, mas a navegação é por entidade e relacionamento. Não existe árvore de pastas em nenhuma tela.', 'Mesma fonte de verdade, sem custo cognitivo de hierarquia.'],
            ['Aprovação em uma tela, não em um wizard', 'Diff, justificativa e aprovação convivem no mesmo painel. O PR é gerado pelo portal — o revisor de negócio nunca vê o Git.', 'De 5 etapas para 1; tempo médio caiu de 13h para 9h no piloto.'],
            ['Frescor é um estado de primeira classe', 'Toda entidade carrega um selo de frescor calculado pelo CI (última mudança de código vs. última revisão do conhecimento). É filtrável e ordenável em qualquer lista.', 'Documentação errada é pior que ausente — o selo torna o risco visível.'],
            ['O Graph é ferramenta de diagnóstico, não decoração', 'Abre sempre focado numa entidade com 1 nível de profundidade, com filtros por tipo e ação "encontrar órfãos". Não é um mapa bonito do universo inteiro.', 'Achar lacunas em segundos em vez de contemplar nós.'],
        ];
    }
}
