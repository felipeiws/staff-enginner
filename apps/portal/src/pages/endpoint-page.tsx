import type { ReactNode } from 'react';
import { Link, useParams } from 'react-router-dom';
import { Card, EmptyState, EntityBadge, FreshnessSeal, PortalHeader, PortalSidebar, Sparkline } from '@/components';
import { useEntity } from '@/data/hooks';
import { DataError, DataSkeleton } from '@/data/query-state';
import type { EntityDetail } from '@/data/types';
import './endpoint-page.css';

type Detail = Record<string, unknown>;
type ProductionMetric = { p95: number; errorRate: number; points: string };
const record = (value: unknown): Detail => value !== null && typeof value === 'object' && !Array.isArray(value) ? value as Detail : {};
const text = (value: unknown, fallback = '') => typeof value === 'string' || typeof value === 'number' ? String(value) : fallback;
const list = (value: unknown) => Array.isArray(value) ? value.map(record) : [];
const number = (value: unknown) => typeof value === 'number' && Number.isFinite(value) ? value : undefined;

const fallback: Detail = {
  method: 'DELETE',
  path: '/v2/purchase-orders/{id}',
  description: 'Cancela um pedido de compra. Idempotente. Publica purchase_order.canceled e delega a liberação de reserva ao Estoque.',
  parameters: [
    { name: 'id', location_type: 'path · uuid', description: 'Identificador do pedido de compra.' },
    { name: 'motivo', location_type: 'body · enum', description: 'Motivo do cancelamento informado pelo solicitante.' },
    { name: 'forçar', location_type: 'query · bool', description: 'Permite a tentativa somente para usuários com alçada.' },
  ],
  responses: [
    { code: '204', description: 'Pedido cancelado; a reserva do saldo é liberada.', tone: 'success' },
    { code: '409', description: 'Faturamento parcial: aplica a regra RN-142.', tone: 'warning' },
    { code: '403', description: 'A alçada acima de R$ 50k é exigida pela RN-088.', tone: 'danger' },
  ],
  error_example: '409 Conflict\n{ "code": "PARTIAL_INVOICE",\n  "rule": "RN-142",\n  "hint": "cancele apenas o saldo não faturado" }',
  rules: [
    { code: 'RN-142', title: 'bloqueio pós-faturamento', path: '/regras/rn-142' },
    { code: 'RN-088', title: 'alçada acima de R$ 50k', path: '/regras/rn-088' },
  ],
};

function endpointDetail(entity: EntityDetail): Detail { return { ...fallback, ...record(entity.projection) }; }
function responseTone(response: Detail) {
  const value = response.tone;
  if (value === 'success' || value === 'acc') return 'endpoint-response--success';
  if (value === 'warning' || value === 'warn') return 'endpoint-response--warning';
  if (value === 'info') return 'endpoint-response--info';
  const code = Number(response.code);
  if (code >= 200 && code < 300) return 'endpoint-response--success';
  if (code >= 400 && code < 500 && code !== 409) return 'endpoint-response--danger';
  if (code >= 400 && code < 500) return 'endpoint-response--warning';
  return 'endpoint-response--danger';
}
function metric(detail: Detail): ProductionMetric | undefined {
  const value = record(detail.production ?? detail.metrics);
  const p95 = number(value.p95_ms ?? value.latency_p95_ms);
  const errorRate = number(value.error_rate ?? value.error_rate_percent);
  const candidatePoints = value.latency_points ?? value.sparkline;
  const numericPoints: number[] = [];
  if (Array.isArray(candidatePoints)) {
    candidatePoints.forEach((point: unknown) => {
      const parsed = number(point);
      if (parsed !== undefined) numericPoints.push(parsed);
    });
  }
  if (p95 === undefined || errorRate === undefined || numericPoints.length < 2) return undefined;
  const max = Math.max(...numericPoints);
  const min = Math.min(...numericPoints);
  const range = max - min || 1;
  const points = numericPoints.map((point, index) => `${Math.round((150 / (numericPoints.length - 1)) * index)},${Math.round(28 - ((point - min) / range) * 18)}`).join(' ');
  return { p95, errorRate, points };
}

export function EndpointPage() {
  const { id = '' } = useParams(); const entity = useEntity(id);
  if (entity.isLoading) return <EndpointShell><DataSkeleton lines={8} /></EndpointShell>;
  if (entity.error) return <EndpointShell><DataError error={entity.error} retry={() => void entity.refetch()} /></EndpointShell>;
  if (!entity.data || !['Endpoint', 'API'].includes(text(entity.data.type))) return <EndpointShell><DataError error={new Error('Endpoint não encontrado.')} /></EndpointShell>;
  return <EndpointShell><EndpointDetail entity={entity.data} /></EndpointShell>;
}

function EndpointDetail({ entity }: { entity: EntityDetail }) {
  const data = endpointDetail(entity); const freshness = record(entity.freshness); const production = metric(data);
  const consumers = list(data.consumers);
  return <div className="endpoint-page">
    <header className="endpoint-identity">
      <EntityBadge type="Endpoint">{text(data.method, 'DELETE')}</EntityBadge>
      <span className="endpoint-path">{text(data.path, text(entity.title))}</span>
      <FreshnessSeal state="atual">{text(freshness.label, 'sincronizado do repo')}</FreshnessSeal>
    </header>
    <p className="endpoint-description">{text(data.description)}</p>
    <div className="endpoint-grid"><div className="endpoint-main">
      <Card className="endpoint-card"><h2>Parâmetros</h2>{list(data.parameters).map((parameter) => <div className="endpoint-parameter" key={text(parameter.name)}><code>{text(parameter.name)}</code><span>{text(parameter.location_type, `${text(parameter.location)} · ${text(parameter.type)}`)}</span><p>{text(parameter.description)}</p></div>)}</Card>
      <Card className="endpoint-card"><h2>Respostas</h2>{list(data.responses).map((response) => <div className="endpoint-response" key={text(response.code)}><code className={responseTone(response)}>{text(response.code)}</code><p>{text(response.description)}</p></div>)}<pre aria-label="Exemplo de payload de erro">{text(data.error_example)}</pre></Card>
    </div><aside className="endpoint-aside">
      <Card className="endpoint-production"><h2>Produção · 7 dias</h2>{production ? <><div className="endpoint-production__values"><strong>{production.p95}<small>ms p95</small></strong><span>{production.errorRate.toLocaleString('pt-BR', { maximumFractionDigits: 2 })}% erro</span></div><Sparkline label="Latência nos últimos 7 dias" points={production.points} /></> : <EmptyState title="Métricas indisponíveis" description="A API de observabilidade ainda não forneceu dados para este endpoint." />}</Card>
      <Card className="endpoint-consumers"><h2>Consumidores</h2>{consumers.length ? consumers.map((consumer) => <div key={text(consumer.name)}><span>{text(consumer.name)}</span><small>{text(consumer.daily_volume ?? consumer.volume_daily)}</small></div>) : <EmptyState title="Consumidores indisponíveis" description="A observabilidade ainda não informou volumes diários." />}</Card>
      <Card className="endpoint-rules"><h2>Regras aplicadas</h2><div>{list(data.rules).map((rule) => <Link key={text(rule.code)} to={text(rule.path, `/regras/${encodeURIComponent(text(rule.code))}`)}>{text(rule.code)} · {text(rule.title)}</Link>)}</div></Card>
    </aside></div>
  </div>;
}

function EndpointShell({ children }: { children: ReactNode }) { return <div className="portal-shell"><PortalSidebar /><main id="conteudo-principal" className="portal-content"><PortalHeader />{children}</main></div>; }
