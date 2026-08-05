<?php

declare(strict_types=1);

namespace App\Console;

use Atlas\Contracts\Dto\KnowledgeGapOccurrence;
use Atlas\Modules\Governance\Application\CreateReviewProposal;
use Atlas\Modules\Governance\Application\KnowledgeGapService;
use Atlas\Modules\Governance\Application\YamlPolicyLoader;
use Atlas\Modules\Ingestion\Application\ReceiveSourceObservation;
use Atlas\Modules\KnowledgeEngine\Application\AssertionEvidenceInput;
use Atlas\Modules\KnowledgeEngine\Application\AssertionGraphService;
use Atlas\Modules\KnowledgeEngine\Application\CreateAssertion;
use Atlas\Modules\KnowledgeEngine\Application\KnowledgeLifecycleService;
use Atlas\Modules\KnowledgeEngine\Application\RevisionReference;
use Atlas\Modules\KnowledgeEngine\Domain\Aggregate\EvidenceKind;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Identifier;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\LifecycleState;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Scope;
use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

/** Seeds the prototype's Nordika domain only through observation, lifecycle and graph boundaries. */
final class SeedDemoDomain extends Command
{
    protected $signature = 'atlas:seed-demo';
    protected $description = 'Popula o domínio demonstrativo Nordika com proveniência rastreável.';

    /** @var array<string, array{entity:string,revision:string,artifact:string}> */
    private array $records = [];

    public function __construct(private readonly DatabaseManager $database, private readonly ReceiveSourceObservation $ingestion, private readonly KnowledgeLifecycleService $lifecycle, private readonly AssertionGraphService $graph, private readonly YamlPolicyLoader $policies, private readonly CreateReviewProposal $proposals, private readonly KnowledgeGapService $gaps)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->ensureDemoPrincipal();
        $this->registerCatalogSource();
        $this->loadPolicy();
        foreach ($this->entities() as $entity) {
            $this->observe($entity, true);
        }
        foreach ($this->ruleVersions() as $entity) {
            $this->observe($entity, true);
        }
        $this->connectGraph();
        $this->createPendingProposals();
        $this->recordGaps();
        $this->info('Domínio demonstrativo Nordika pronto (idempotente).');

        return self::SUCCESS;
    }

    /** Creates an explicit, local-only principal so the demo remains behind the API authorization boundary. */
    private function ensureDemoPrincipal(): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => 'demo@nordika.test'],
            ['name' => 'Rafa Silva', 'password' => Hash::make('atlas-demo-password')],
        );

        $this->database->table('identity_grants')->updateOrInsert(
            ['user_id' => $user->getKey(), 'scope' => json_encode(['organization' => 'nordika'], JSON_THROW_ON_ERROR)],
            [
                'id' => (string) Str::uuid(),
                'role' => 'aprovador_de_dominio',
                'permissions' => json_encode(['knowledge.read', 'knowledge.contribute', 'knowledge.approve'], JSON_THROW_ON_ERROR),
                'classification_ceiling' => 'interno',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    private function registerCatalogSource(): void
    {
        $source = $this->database->table('sources')->where('type', 'atlas_demo_catalog')->first();
        if ($source === null) {
            $this->database->table('sources')->insert(['id' => (string) Str::uuid(), 'type' => 'atlas_demo_catalog', 'scope' => json_encode(['organization' => 'nordika'], JSON_THROW_ON_ERROR), 'classification' => 'interno', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
            return;
        }
        $this->database->table('sources')->where('id', $source->id)->update(['scope' => json_encode(['organization' => 'nordika'], JSON_THROW_ON_ERROR), 'classification' => 'interno', 'active' => true, 'updated_at' => now()]);
    }

    private function loadPolicy(): void
    {
        if ($this->database->table('policies')->where('policy_key', 'atlas-demo-publicacao')->exists()) return;
        $this->policies->load(<<<'YAML'
schema_version: 1.0.0
policies:
  - policy_key: atlas-demo-publicacao
    version: 1
    name: Catálogo demonstrativo Nordika
    priority: 999
    applies_to:
      source_types: [atlas_demo_catalog]
      classifications: [interno]
    outcome:
      action: publish
      approver_roles: [technical, business]
      retraction_behavior: retain_audit_history
  - policy_key: atlas-demo-propostas
    version: 1
    name: Propostas demonstrativas pendentes
    priority: 1000
    applies_to:
      source_types: [atlas_demo_catalog]
      entity_types: [Documento]
      classifications: [interno]
    outcome:
      action: review
      approver_roles: [technical, business]
      retraction_behavior: retain_audit_history
YAML, 'service:atlas-demo');
    }

    /** @param array<string,mixed> $item */
    private function observe(array $item, bool $publish): void
    {
        $key = $item['key'];
        $revision = $item['revision'] ?? 'demo:2026-07-31';
        $content = $item['content'];
        $checksum = 'sha256:'.hash('sha256', $content);
        $existing = $this->database->table('entities')->where('external_natural_key', 'atlas_demo_catalog://'.strtolower($key))->first();
        $existingRevision = $existing === null ? null : $this->database->table('entity_revisions')->where('entity_id', $existing->id)->where('attributes->source_revision', $revision)->first();
        if ($existingRevision !== null) {
            $artifact = $this->database->table('artifacts as a')->join('revisions as r', 'r.artifact_id', '=', 'a.id')->where('r.id', $existingRevision->source_revision_id)->value('a.id');
            $this->records[$key] = ['entity' => (string) $existing->id, 'revision' => (string) $existingRevision->id, 'artifact' => (string) $artifact];
            return;
        }
        $result = $this->ingestion->handle(['schema_version' => '1.0.0', 'observation_id' => (string) Str::uuid(), 'external_id' => $key, 'source_type' => 'atlas_demo_catalog', 'uri' => 'atlas-demo://nordika/'.rawurlencode($key), 'revision' => $revision, 'observed_at' => $item['observed_at'] ?? '2026-07-31T12:00:00Z', 'content' => $content, 'checksum' => $checksum, 'metadata' => ['title' => $item['title'], 'entity_type' => $item['type'], 'scope' => $this->scopeFor($key, $item['type']), 'attributes' => $item['attributes'] ?? []], 'classification' => 'interno', 'command' => ['idempotency_key' => 'atlas-demo:'.$key.':'.$revision, 'actor' => ['actor_id' => $item['actor'] ?? 'ci:atlas-demo', 'actor_type' => 'service'], 'correlation_id' => (string) Str::uuid()]]);
        $this->records[$key] = ['entity' => $result->entityId, 'revision' => $result->entityRevisionId, 'artifact' => $result->artifactId];
        if ($publish) {
            $this->lifecycle->transitionRevision(new Identifier($result->entityRevisionId), LifecycleState::Candidate, 'service:atlas-demo', 'service', 'Normalização do catálogo demonstrativo com fonte e revisão.', 'atlas-demo:candidate:'.$key.':'.$revision);
            $this->lifecycle->transitionRevision(new Identifier($result->entityRevisionId), LifecycleState::Published, 'service:atlas-demo', 'service', 'Política declarativa do catálogo demonstrativo permite publicação.', 'atlas-demo:publish:'.$key.':'.$revision);
        }
    }

    private function edge(string $from, string $predicate, string $to): void
    {
        $id = 'atlas-demo:edge:'.$from.':'.$predicate.':'.$to;
        if ($this->database->table('assertions')->where('actor_id', $id)->exists()) return;
        $left = $this->records[$from]; $right = $this->records[$to];
        $assertion = $this->graph->create(new CreateAssertion(new Identifier((string) Str::uuid()), new RevisionReference(new Identifier($left['entity']), new Identifier($left['revision'])), \Atlas\Contracts\Enum\GraphPredicate::from($predicate), new RevisionReference(new Identifier($right['entity']), new Identifier($right['revision'])), null, new Scope(['organization' => 'nordika']), 'parser', $id, 'service', [new AssertionEvidenceInput(new Identifier((string) Str::uuid()), EvidenceKind::Artifact, new Identifier($left['artifact']), 1.0)], .95, $id));
        $this->lifecycle->transitionAssertion($assertion->id, LifecycleState::Published, 'service:atlas-demo', 'service', 'Relação extraída da observação versionada do catálogo.', $id.':publish');
    }

    /** @return array<string, string> */
    private function scopeFor(string $key, string $type): array
    {
        if ($type === 'Organização') return ['organization' => 'nordika'];
        if ($type === 'Aplicação') return ['organization' => 'nordika', 'application' => $key];
        if ($type === 'Módulo') return ['organization' => 'nordika', 'application' => 'erp-financeiro', 'module' => $key];

        return ['organization' => 'nordika', 'application' => 'erp-financeiro', 'module' => 'compras'];
    }

    private function connectGraph(): void
    {
        foreach (['erp-financeiro','portal-fornecedor','wms-estoque','gente-gestao'] as $app) $this->edge('nordika', 'contains', $app);
        foreach (['compras','contas-pagar','contabilidade','fiscal','tesouraria','custos'] as $module) $this->edge('erp-financeiro', 'contains', $module);
        foreach (['rn-142','rn-088','rn-201','rn-233','pc-0421','pc-0433','pc-0110','ap-0902','delete-purchase-order','post-release-reservations','get-eligibility','purchase-order-canceled','erp-4821','adr-017'] as $item) $this->edge('cancelar-pedido-compra', 'related_to', $item);
        foreach (['time-suprimentos','m-reis','squad-estoque','fiscal-owner','controladoria','suporte-n2','l-duarte','arquitetura'] as $owner) $this->edge('cancelar-pedido-compra', 'related_to', $owner);
        $this->edge('purchase-order-canceled', 'writes', 'stock-reservations');
        $this->edge('post-release-reservations', 'writes', 'stock-reservations');
        $this->edge('delete-purchase-order', 'related_to', 'erp-4821');
        $this->edge('pc-0421', 'documents', 'faq-231');
    }

    private function createPendingProposals(): void
    {
        $items = [['processo-atualizacao','Processo Cancelar Pedido de Compra','IA · commit a91f3ce'], ['rn142-v5','RN-142 · nova versão v5','M. Reis'], ['faq231-reescrita','FAQ-231 · reescrita','IA · 18 tickets'], ['pc0421-print','PC-0421 · print atualizado','QA automação'], ['eligibility-doc','GET eligibility · consumidores','CI'], ['adr017-revisao','ADR-017 · revisão de compensação','Arquitetura'], ['wms-release-runbook','Release de reserva · runbook','Squad Estoque']];
        foreach ($items as [$key, $title, $actor]) {
            $this->observe(['key' => $key, 'title' => $title, 'type' => 'Documento', 'content' => "# {$title}\n\nProposta candidata para revisão humana, com evidência no catálogo Nordika.", 'actor' => 'model:atlas-demo'], false);
            $revision = $this->records[$key]['revision'];
            $state = $this->database->table('entity_revisions')->where('id', $revision)->value('lifecycle_state');
            if ($state === 'observed') {
                $this->lifecycle->transitionRevision(new Identifier($revision), LifecycleState::Candidate, 'service:atlas-demo', 'service', 'Candidata criada a partir da observação versionada.', 'atlas-demo:proposal-candidate:'.$key);
                $state = 'candidate';
            }
            if ($state === 'candidate') $this->lifecycle->transitionRevision(new Identifier($revision), LifecycleState::Review, 'service:atlas-demo', 'service', 'Proposta requer aprovação humana antes de publicação.', 'atlas-demo:proposal-review:'.$key);
            if (! $this->database->table('approvals')->where('entity_revision_id', $revision)->exists()) $this->proposals->create($revision, ['kind' => 'ai_knowledge_candidate', 'title' => $title, 'origin' => ['kind' => str_starts_with($actor, 'IA') ? 'ai' : 'ci'], 'method' => 'inference@atlas-demo', 'confidence' => .91, 'source_citations' => [['uri' => 'atlas-demo://nordika/'.rawurlencode($key), 'revision' => 'demo:2026-07-31']]], 'model:atlas-demo', 'model', (string) Str::uuid());
        }
    }

    private function recordGaps(): void
    {
        foreach ([['Como estornar o frete de um pedido cancelado?',31], ['Quem aprova reajuste de contrato guarda-chuva?',18], ['Qual o SLA da integração com o banco Itaú?',12]] as [$question, $count]) {
            if ($this->database->table('knowledge_gaps')->where('question', $question)->exists()) continue;
            for ($i = 0; $i < $count; $i++) $this->gaps->record(new KnowledgeGapOccurrence($question, ['organization' => 'nordika'], 'support:nordika-demo', 'unanswered_question_v1', 1.0, (string) Str::uuid()));
        }
    }

    /** @return list<array<string,mixed>> */
    private function entities(): array
    {
        $make = fn (string $key, string $title, string $type, string $content, array $attributes = []): array => compact('key','title','type','content','attributes');
        return [$make('nordika','Nordika','Organização','# Nordika\nOrganização fictícia do catálogo demonstrativo.'), $make('erp-financeiro','ERP Financeiro','Aplicação','# ERP Financeiro\nSaúde do conhecimento: 88.', ['health' => 88]), $make('portal-fornecedor','Portal do Fornecedor','Aplicação','# Portal do Fornecedor\nSaúde: 61.', ['health' => 61]), $make('wms-estoque','WMS Estoque','Aplicação','# WMS Estoque\nSaúde: 43.', ['health' => 43]), $make('gente-gestao','Gente & Gestão','Aplicação','# Gente & Gestão\nSaúde: 76.', ['health' => 76]), $make('compras','Compras','Módulo','# Compras\nSaúde: 88.', ['health' => 88]), $make('contas-pagar','Contas a Pagar','Módulo','# Contas a Pagar\nSaúde: 72.', ['health' => 72]), $make('contabilidade','Contabilidade','Módulo','# Contabilidade\nSaúde: 54.', ['health' => 54]), $make('fiscal','Fiscal','Módulo','# Fiscal\nSaúde: 47.', ['health' => 47]), $make('tesouraria','Tesouraria','Módulo','# Tesouraria\nSaúde: 81.', ['health' => 81]), $make('custos','Custos','Módulo','# Custos\nSaúde: 35.', ['health' => 35]), $make('cancelar-pedido-compra','Cancelar Pedido de Compra','Processo',"# Cancelar Pedido de Compra\n\n1. Solicitação: usuário aciona Cancelar em PC-0421.\n2. Validações: RN-142, RN-088 e PC.CANCEL.\n3. Alçada: acima de R$ 50 mil, aprovação do gestor.\n4. Cancelamento: DELETE /v2/purchase-orders/{id}.\n5. Compensação: evento e liberação de reserva assíncrona; falha conhecida ERP-4821 quando o release retorna 409.\n6. Notificação: e-mail ao fornecedor e requisitante."), $make('rn-088','RN-088','Regra','# RN-088\nAlçada obrigatória acima de R$ 50.000.'), $make('rn-201','RN-201','Regra','# RN-201\nMotivo do cancelamento é obrigatório e categorizado.'), $make('rn-233','RN-233','Regra','# RN-233\nCancelamento não permitido em pedido de contrato guarda-chuva.', ['owner' => null]), $make('pc-0421','PC-0421 Detalhe do Pedido','Tela','# PC-0421\nTela de detalhe do pedido; print desatualizado há 5 meses.', ['artifact_at' => '2026-02-28T12:00:00Z']), $make('pc-0433','PC-0433 Motivo do Cancelamento','Tela','# PC-0433\nModal com categorias obrigatórias.', ['artifact_at' => '2026-07-20T12:00:00Z']), $make('pc-0110','PC-0110 Lista de Pedidos','Tela','# PC-0110\nLista de pedidos.', ['artifact_at' => '2026-07-20T12:00:00Z']), $make('ap-0902','AP-0902 Fila de Alçadas','Tela','# AP-0902\nFila de alçadas sem print.', ['artifact_at' => null]), $make('delete-purchase-order','DELETE /v2/purchase-orders/{id}','Endpoint','# DELETE /v2/purchase-orders/{id}\nParâmetros: id path uuid; reason body enum (RN-201); force query bool, exige PC.ADMIN. Códigos: 204 cancelado e compensação enfileirada; 409 faturamento parcial (RN-142) ou alçada pendente; 403 sem PC.CANCEL. Consumidores: Portal do Fornecedor 2,1k req/dia; App Compras mobile 840; Robô RPA Fiscal 120.'), $make('post-release-reservations','POST /v2/stock/reservations/release','Endpoint','# POST /v2/stock/reservations/release\nLibera linhas de reserva de estoque de forma assíncrona.'), $make('get-eligibility','GET /v2/purchase-orders/{id}/eligibility','Endpoint','# GET /v2/purchase-orders/{id}/eligibility\nRetorna elegibilidade para cancelamento.'), $make('purchase-order-canceled','purchase_order.canceled','Evento','# purchase_order.canceled\nEvento de domínio do cancelamento; três consumidores.'), $make('stock-reservations','stock_reservations','Tabela','# stock_reservations\nReservas de estoque por item e pedido.'), $make('faq-231','FAQ-231','FAQ','# FAQ-231\nExplica as três causas do botão Cancelar desabilitado.'), $make('erp-4821','ERP-4821','Issue','# ERP-4821\nReserva órfã quando o release retorna 409.'), $make('adr-017','ADR-017','ADR','# ADR-017\nCompensação assíncrona em vez de transação distribuída.'), $make('time-suprimentos','Time Suprimentos','Responsável','# Time Suprimentos'), $make('m-reis','M. Reis','Responsável','# M. Reis'), $make('squad-estoque','Squad Estoque','Responsável','# Squad Estoque'), $make('fiscal-owner','Fiscal','Responsável','# Fiscal'), $make('controladoria','Controladoria','Responsável','# Controladoria'), $make('suporte-n2','Suporte N2','Responsável','# Suporte N2'), $make('l-duarte','L. Duarte','Responsável','# L. Duarte'), $make('arquitetura','Arquitetura','Responsável','# Arquitetura')];
    }

    /** @return list<array<string,mixed>> */
    private function ruleVersions(): array
    {
        return [['key' => 'rn-142', 'title' => 'RN-142', 'type' => 'Regra', 'revision' => 'rn-142:v1', 'observed_at' => '2025-01-02T12:00:00Z', 'content' => '# RN-142 v1\nPedidos com nota fiscal emitida não podem ser cancelados.'], ['key' => 'rn-142', 'title' => 'RN-142', 'type' => 'Regra', 'revision' => 'rn-142:v2', 'observed_at' => '2025-06-21T12:00:00Z', 'content' => '# RN-142 v2\nPedidos com nota fiscal emitida não podem ser cancelados.'], ['key' => 'rn-142', 'title' => 'RN-142', 'type' => 'Regra', 'revision' => 'rn-142:v3', 'observed_at' => '2025-11-08T12:00:00Z', 'content' => "# RN-142 v3\nPedidos com nota fiscal emitida não podem ser cancelados.\nO estorno é feito pelo Fiscal em até 5 dias úteis.\nNão há tratamento para faturamento parcial."], ['key' => 'rn-142', 'title' => 'RN-142', 'type' => 'Regra', 'revision' => 'rn-142:v4', 'observed_at' => '2026-03-12T12:00:00Z', 'content' => "# RN-142 v4\nPedidos com nota fiscal emitida não podem ser cancelados integralmente.\nO estorno é feito pelo Fiscal em até 5 dias úteis.\nEm faturamento parcial, o saldo não faturado pode ser cancelado e sua reserva é liberada.", 'attributes' => ['diff_from' => 'v3', 'diff_to' => 'v4']]];
    }
}
