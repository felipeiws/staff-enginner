import type { ReactNode } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { PortalHeader, PortalSidebar } from '@/components';
import { useEntity } from '@/data/hooks';
import { DataError, DataSkeleton } from '@/data/query-state';
import type { EntityDetail } from '@/data/types';
import './process-page.css';

type Tab = 'resumo' | 'fluxo' | 'tecnico' | 'historico';
type Detail = Record<string, unknown>;

const tabs: readonly [Tab, string][] = [['resumo', 'Resumo'], ['fluxo', 'Fluxo'], ['tecnico', 'Técnico'], ['historico', 'Histórico']];
const demo: Detail = {
  summary: 'Cancelamento total ou parcial de pedidos de compra, incluindo validação de faturamento, alçada financeira, estorno fiscal e liberação assíncrona da reserva de estoque.',
  volume: '1.284 buscas/mês',
  rules: [
    { id: 'rn-142', code: 'RN-142', title: 'Faturamento parcial impede cancelamento integral.', status: 'ativo', color: 'var(--acc)' },
    { id: 'rn-088', code: 'RN-088', title: 'Alçada financeira obrigatória acima de R$ 50.000.', status: 'ativo', color: 'var(--acc)' },
    { id: 'rn-201', code: 'RN-201', title: 'Motivo do cancelamento é obrigatório e categorizado.', status: 'revisar', color: 'var(--warn)' },
  ],
  screens: [
    { id: 'pc-0421', name: 'PC-0421 · Detalhe do Pedido', status: 'print há 5 meses' },
    { id: 'pc-0433', name: 'PC-0433 · Motivo do Cancelamento', status: 'print há 11 dias' },
  ],
  flow: { steps: [
    { name: 'Solicitação', description: 'PC-0421 · ação Cancelar', color: 'var(--info)' },
    { name: 'Validações', description: 'RN-142 · RN-201 · PC.CANCEL', color: 'var(--warn)' },
    { name: 'Alçada', description: 'acima de R$ 50 mil', color: 'var(--violet)' },
    { name: 'Cancelamento', description: 'DELETE /v2/purchase-orders/{id}', color: 'var(--acc)' },
    { name: 'Compensação', description: 'evento + release de reserva', color: 'var(--pink)' },
  ], deviations: [{ name: 'Desvio · faturamento parcial', description: 'Etapa 2 retorna 409, o cancelamento é reduzido ao saldo e o Fiscal recebe uma tarefa de estorno manual.' }], known_failures: [{ id: 'ERP-4821', description: 'Se o release de reserva falha, a mensagem cai na DLQ e o saldo disponível fica incorreto até o reprocesso.' }] },
  technical: { endpoints: ['DELETE /v2/purchase-orders/{id}', 'POST /v2/stock/reservations/release'], services: ['PurchaseOrderCancelService', 'StockReservationReleaseService'], tables: ['purchase_orders', 'stock_reservations'], events: ['purchase_order.canceled', 'stock.reservation.release.requested'] },
  relevant_code: 'PurchaseOrderCancelService.java:214\n  if (order.hasPartialInvoice()) {          // RN-142\n      throw new ConflictException("PARTIAL_INVOICE");\n  }\n  events.publish(new PurchaseOrderCanceled(order.id(), reason));',
  signals: { open_issues: 2, commits_30d: 17, support_tickets: 41, ai_confidence: 0.92 },
  divergences: [{ description: 'O texto da etapa 4 não menciona o retorno 409, presente no código desde o commit a91f3ce.', approval_id: 'processo-atualizacao' }],
  faqs: [{ id: 'faq-231', title: 'Por que o botão Cancelar está desabilitado?' }, { id: 'faq-estorno', title: 'Como acompanhar o estorno fiscal?' }, { id: 'faq-requisicao', title: 'Cancelamento reabre a requisição?' }],
  history: [{ occurred_at: '31 jul 2026', action: 'Processo revisado após alteração na integração de reserva.', actor: { id: 'M. Reis', type: 'human' } }, { occurred_at: '28 jul 2026', action: 'Divergência entre código e documentação detectada.', actor: { id: 'Copiloto', type: 'model' } }, { occurred_at: '12 jul 2026', action: 'Print da PC-0433 vinculado à revisão publicada.', actor: { id: 'QA automação', type: 'service' } }],
};

const record = (value: unknown): Detail => value && typeof value === 'object' && !Array.isArray(value) ? value as Detail : {};
const list = (value: unknown): Detail[] => Array.isArray(value) ? value.map(record) : [];
const text = (value: unknown, fallback = '') => typeof value === 'string' || typeof value === 'number' ? String(value) : fallback;
const route = (kind: string, id: string) => `/${kind}/${encodeURIComponent(id)}`;
const asNumber = (value: unknown) => typeof value === 'number' ? value : Number(value ?? 0);

function processDetail(entity: EntityDetail): Detail {
  const projection = record(entity.projection);
  return { ...demo, ...projection, flow: { ...record(demo.flow), ...record(projection.flow) }, technical: { ...record(demo.technical), ...record(projection.technical) }, signals: { ...record(demo.signals), ...record(projection.signals) } };
}

export function ProcessPage() {
  const { id = '' } = useParams();
  const [params, setParams] = useSearchParams();
  const entity = useEntity(id);
  const requested = params.get('aba');
  const active: Tab = tabs.some(([key]) => key === requested) ? requested as Tab : 'resumo';
  const changeTab = (tab: Tab) => setParams(tab === 'resumo' ? {} : { aba: tab });

  if (entity.isLoading) return <ProcessShell><DataSkeleton lines={9} /></ProcessShell>;
  if (entity.error) return <ProcessShell><DataError error={entity.error} retry={() => void entity.refetch()} /></ProcessShell>;
  if (!entity.data || entity.data.type !== 'Processo') return <ProcessShell><DataError error={new Error('Processo não encontrado.')} /></ProcessShell>;
  const data = processDetail(entity.data);
  return <ProcessShell>
    <div className="process-page">
      <ProcessHeader entity={entity.data} data={data} />
      <nav className="process-tabs" aria-label="Seções do processo">
        {tabs.map(([key, label]) => <button className={active === key ? 'is-active' : ''} key={key} onClick={() => changeTab(key)} type="button">{label}</button>)}
      </nav>
      {active === 'resumo' ? <Summary data={data} /> : null}
      {active === 'fluxo' ? <Flow data={data} /> : null}
      {active === 'tecnico' ? <Technical data={data} /> : null}
      {active === 'historico' ? <History data={data} /> : null}
    </div>
  </ProcessShell>;
}

function ProcessHeader({ entity, data }: { entity: EntityDetail; data: Detail }) {
  const navigate = useNavigate();
  const owners = list(entity.owners);
  return <div className="process-heading">
    <div className="process-heading__identity">
      <div className="process-heading__meta"><span className="process-type">Processo</span><span className="process-freshness">frescor: {text(record(entity.freshness).label, 'revisado há 3 dias')}</span><span>{text(data.volume, '1.284 buscas/mês')}</span></div>
      <h1>{text(entity.title, 'Cancelar Pedido de Compra')}</h1>
      <p>{text(data.summary, text(entity.summary))}</p>
    </div>
    <aside className="process-actions">
      <button className="process-actions__ask" onClick={() => void navigate(`/copiloto?processo=${encodeURIComponent(text(entity.id))}`)} type="button">✦ Perguntar sobre este processo</button>
      <button className="process-actions__graph" onClick={() => void navigate(`/graph?entity=${encodeURIComponent(text(entity.id))}`)} type="button">Abrir no Graph</button>
      <section className="process-owners"><h2>Responsáveis</h2>{(owners.length ? owners : [{ id: 'Time Suprimentos', responsibility: 'dono' }, { id: 'M. Reis', responsibility: 'aprovador de regras' }, { id: 'Squad Estoque', responsibility: 'compensação' }]).map((owner) => <p key={`${text(owner.id)}-${text(owner.responsibility)}`}><strong>{text(owner.name, text(owner.id))}</strong> · {text(owner.responsibility, 'dono')}</p>)}</section>
    </aside>
  </div>;
}

function Summary({ data }: { data: Detail }) {
  const rules = list(data.rules); const screens = list(data.screens); const signals = record(data.signals); const divergence = list(data.divergences)[0];
  return <div className="process-summary"><div className="process-summary__main"><Card title="Regras aplicadas"><div className="process-rules">{rules.map((rule) => <Link key={text(rule.id, text(rule.code))} to={route('regras', text(rule.id, text(rule.code)))}><b>{text(rule.code, text(rule.id))}</b><span>{text(rule.title, text(rule.statement))}</span><em style={{ color: text(rule.color, 'var(--acc)') }}>{text(rule.status, 'ativo')}</em></Link>)}</div></Card><Card title="Telas envolvidas"><div className="process-screens">{screens.map((screen) => <Link key={text(screen.id, text(screen.name))} to={route('telas', text(screen.id, text(screen.name)))}><div className="process-screen-print">print</div><div><strong>{text(screen.name, text(screen.title))}</strong><small>{text(screen.status, 'sem print')}</small></div></Link>)}</div></Card></div><aside className="process-summary__aside"><Card title="Sinais"><div className="process-signals"><Signal label="Issues abertas" value={text(signals.open_issues)} color="var(--danger)" /><Signal label="Commits em 30d" value={text(signals.commits_30d)} /><Signal label="Tickets de suporte" value={text(signals.support_tickets)} color="var(--warn)" /><Signal label="Confiança da IA" value={asNumber(signals.ai_confidence).toLocaleString('pt-BR', { minimumFractionDigits: 2 })} color="var(--acc)" /></div></Card>{divergence ? <section className="process-divergence"><h2>Divergência detectada</h2><p>{text(divergence.description)}</p><Link to={`/aprovacoes${text(divergence.approval_id) ? `?proposta=${encodeURIComponent(text(divergence.approval_id))}` : ''}`}>Revisar proposta da IA →</Link></section> : null}<Card title="FAQs vinculadas"><div className="process-faqs">{list(data.faqs).map((faq) => <Link key={text(faq.id, text(faq.title))} to={`/busca?q=${encodeURIComponent(text(faq.title))}`}>{text(faq.title, text(faq.id))}</Link>)}</div></Card></aside></div>;
}

function Signal({ label, value, color }: { label: string; value: string; color?: string }) { return <div><span>{label}</span><strong style={color ? { color } : undefined}>{value}</strong></div>; }
function Card({ title, children }: { title: string; children: ReactNode }) { return <section className="process-card"><h2>{title}</h2>{children}</section>; }
function Flow({ data }: { data: Detail }) { const flow = record(data.flow); return <section className="process-flow"><div className="process-flow__steps">{list(flow.steps).map((step, index) => <div className="process-flow__step" key={`${text(step.name)}-${index}`}><article style={{ borderTopColor: text(step.color, 'var(--acc)') }}><small>ETAPA {index + 1}</small><strong>{text(step.name, text(step.title))}</strong><p>{text(step.description)}</p></article>{index < list(flow.steps).length - 1 ? <span>→</span> : null}</div>)}</div><div className="process-flow__exceptions">{list(flow.deviations).map((item) => <article className="is-warning" key={text(item.name)}><strong>{text(item.name)}</strong><p>{text(item.description)}</p></article>)}{list(flow.known_failures).map((item) => <article className="is-danger" key={text(item.id)}><strong>Falha conhecida · {text(item.id)}</strong><p>{text(item.description)}</p></article>)}</div></section>; }
function Technical({ data }: { data: Detail }) { const technical = record(data.technical); const groups = [['Endpoints', 'endpoints', 'apis'], ['Services', 'services', 'graph'], ['Tabelas', 'tables', 'graph'], ['Eventos/Filas', 'events', 'graph']] as const; return <div className="process-technical">{groups.map(([name, key, destination]) => <Card key={key} title={name}><div className="process-tech-links">{(Array.isArray(technical[key]) ? technical[key] : []).map((item) => <Link key={text(item)} to={destination === 'apis' ? route('apis', text(item)) : `/graph?entity=${encodeURIComponent(text(item))}`}>{text(item)}</Link>)}</div></Card>)}<Card title="Código relacionado · maior relevância"><pre>{text(data.relevant_code)}</pre></Card></div>; }
function History({ data }: { data: Detail }) { return <section className="process-history">{list(data.history).map((item) => <article key={`${text(item.occurred_at)}-${text(item.action)}`}><time>{text(item.occurred_at)}</time><i className={`process-history__actor--${text(record(item.actor).type, 'service')}`} /><div><strong>{text(item.action)}</strong><small>{text(record(item.actor).id)}</small></div></article>)}</section>; }
function ProcessShell({ children }: { children: ReactNode }) { return <div className="portal-shell"><PortalSidebar /><main id="conteudo-principal" className="portal-content"><PortalHeader />{children}</main></div>; }
