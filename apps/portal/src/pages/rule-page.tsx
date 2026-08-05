import type { ReactNode } from 'react';
import { Link, useParams, useSearchParams } from 'react-router-dom';
import { PortalHeader, PortalSidebar } from '@/components';
import { useEntity } from '@/data/hooks';
import { DataError, DataSkeleton } from '@/data/query-state';
import type { EntityDetail } from '@/data/types';
import './rule-page.css';

type Detail = Record<string, unknown>;
type RuleVersion = { version: number; date: string; author: string; status: string; old: readonly string[]; current: readonly string[] };

const fallback: Detail = {
  code: 'RN-142',
  statement: 'Pedidos de compra com nota fiscal emitida não podem ser cancelados integralmente. Em faturamento parcial, apenas o saldo não faturado pode ser cancelado, e sua reserva de estoque é liberada.',
  validity: '12/03/2026 — indeterminada',
  approved_by: 'M. Reis · Fiscal',
  change_reason: 'Auditoria apontou lacuna em faturamento parcial',
  impact: '3 processos · 2 telas · 1 endpoint',
  versions: [
    { version: 4, date: '12/03/2026', author: 'M. Reis', status: 'vigente', old: ['− Pedido com faturamento parcial não pode ser cancelado.'], current: ['+ Apenas o saldo não faturado pode ser cancelado.', '+ A reserva de estoque do saldo é liberada.'] },
    { version: 3, date: '08/11/2025', author: 'R. Souza', status: 'substituída', old: ['− Pedido com nota fiscal emitida não pode ser cancelado.'], current: ['+ Pedido com faturamento parcial não pode ser cancelado.'] },
    { version: 2, date: '19/06/2025', author: 'R. Souza', status: 'substituída', old: ['− Cancelamento de pedido é permitido antes da expedição.'], current: ['+ Pedido com nota fiscal emitida não pode ser cancelado.'] },
    { version: 1, date: '02/02/2025', author: 'A. Nunes', status: 'substituída', old: ['− Não havia enunciado versionado para esta regra.'], current: ['+ Cancelamento de pedido é permitido antes da expedição.'] },
  ],
  code_implementation: 'PurchaseOrderCancelService.java:214 · a91f3ce\nOrderEligibility.java:88 · 4de91b0\nPurchaseOrderCancelServiceTest.java:301 · cobre a exceção',
  affects: [
    { type: 'Processo', label: 'Cancelar Pedido de Compra', path: '/processos/cancelar-pedido-compra' },
    { type: 'Tela', label: 'PC-0421 Detalhe do Pedido', path: '/telas/pc-0421' },
    { type: 'Tela', label: 'PC-0433 Motivo do Cancelamento', path: '/telas/pc-0433' },
    { type: 'API', label: 'DELETE /v2/purchase-orders/{id}', path: '/apis/delete-purchase-orders-id' },
  ],
  pinned_comment: '“Suporte: a v4 resolveu 80% dos tickets de 409. Falta atualizar o print da PC-0421.”',
  comment_author: 'L. Duarte',
  comment_time: 'há 7h',
};

const record = (value: unknown): Detail => value && typeof value === 'object' && !Array.isArray(value) ? value as Detail : {};
const text = (value: unknown, alternative = '') => typeof value === 'string' || typeof value === 'number' ? String(value) : alternative;
const versions = (value: unknown): RuleVersion[] => Array.isArray(value) ? value.map((item) => {
  const version = record(item);
  return { version: Number(version.version), date: text(version.date), author: text(version.author), status: text(version.status), old: Array.isArray(version.old) ? version.old.map(String) : [], current: Array.isArray(version.current) ? version.current.map(String) : [] };
}).filter((item) => Number.isFinite(item.version)) : [];

function ruleDetail(entity: EntityDetail): Detail {
  const projection = record(entity.projection);
  // A projeção autorizada pode ainda não expor o histórico editorial. Nesse
  // caso, preservamos a referência demonstrativa em vez de transformar uma
  // lista vazia em uma tela sem versão selecionável.
  return {
    ...fallback,
    ...projection,
    versions: Array.isArray(projection.versions) && projection.versions.length > 0
      ? projection.versions
      : fallback.versions,
  };
}

export function RulePage() {
  const { id = '' } = useParams();
  const [params, setParams] = useSearchParams();
  const entity = useEntity(id);
  if (entity.isLoading) return <RuleShell><DataSkeleton lines={9} /></RuleShell>;
  if (entity.error) return <RuleShell><DataError error={entity.error} retry={() => void entity.refetch()} /></RuleShell>;
  if (!entity.data || entity.data.type !== 'Regra') return <RuleShell><DataError error={new Error('Regra de negócio não encontrada.')} /></RuleShell>;

  const data = ruleDetail(entity.data);
  const allVersions = versions(data.versions).sort((left, right) => right.version - left.version);
  const requested = Number(params.get('versao'));
  const active = allVersions.find((item) => item.version === requested) ?? allVersions[0];
  if (!active) return <RuleShell><DataError error={new Error('A regra não possui versões publicadas.')} /></RuleShell>;
  const isCurrent = active.version === allVersions[0]?.version;
  const selectVersion = (version: number) => setParams(version === allVersions[0]?.version ? {} : { versao: String(version) });

  return <RuleShell><div className="rule-page">
    <header className="rule-identity">
      <span className="rule-type">Regra de negócio</span>
      <span className="rule-code">{text(data.code, id.toUpperCase())}</span>
      <span className="rule-freshness">v{allVersions[0]?.version} vigente desde {allVersions[0]?.date}</span>
    </header>
    <section className="rule-statement">
      <h1>Enunciado</h1>
      <p>{text(data.statement, text(entity.data.title))}</p>
      <div className="rule-metadata">
        <Metadata label="Vigência" value={text(data.validity)} />
        <Metadata label="Aprovada por" value={text(data.approved_by)} />
        <Metadata label="Motivo da última mudança" value={text(data.change_reason)} />
        <Metadata label="Impacto" value={text(data.impact)} />
      </div>
    </section>
    <div className="rule-grid">
      <section className="rule-diff-card">
        <div className="rule-diff-heading"><h2>Diferenças · v{Math.max(1, active.version - 1)} → v{active.version}</h2><span>texto · código · telas</span></div>
        <div className="rule-diff-columns">
          <DiffColumn label={`v${Math.max(1, active.version - 1)} · ${active.date}`} entries={active.old} old />
          <DiffColumn label={isCurrent ? `v${active.version} · vigente` : `v${active.version} · ${active.date}`} entries={active.current} />
        </div>
        <div className="rule-code-card"><h2>Código que implementa a regra</h2><pre>{text(data.code_implementation)}</pre></div>
      </section>
      <aside className="rule-aside">
        <section className="rule-card rule-versions"><h2>Versões</h2>{allVersions.map((version) => <button className={version.version === active.version ? 'is-selected' : ''} key={version.version} onClick={() => selectVersion(version.version)} type="button"><b>v{version.version}</b><span>{version.date} · {version.author}</span><em className={version.version === allVersions[0]?.version ? 'is-current' : ''}>{version.status}</em></button>)}</section>
        <section className="rule-card"><h2>Afeta</h2><div className="rule-affects">{(Array.isArray(data.affects) ? data.affects : []).map((item) => { const affected = record(item); return <Link key={text(affected.path, text(affected.label))} to={text(affected.path, '/graph')}><span>{text(affected.type)} · </span>{text(affected.label)}</Link>; })}</div></section>
        <section className="rule-card rule-comment"><h2>Comentário fixado</h2><p>{text(data.pinned_comment)} — <strong>{text(data.comment_author)}</strong>, {text(data.comment_time)}</p></section>
      </aside>
    </div>
  </div></RuleShell>;
}

function Metadata({ label, value }: { label: string; value: string }) { return <div><small>{label}</small><span>{value}</span></div>; }
function DiffColumn({ label, entries, old = false }: { label: string; entries: readonly string[]; old?: boolean }) { return <div className={`rule-diff-column${old ? ' is-old' : ' is-new'}`}><small>{label}</small><div>{entries.map((entry, index) => <p key={`${entry}-${index}`}>{entry}</p>)}</div></div>; }
function RuleShell({ children }: { children: ReactNode }) { return <div className="portal-shell"><PortalSidebar /><main id="conteudo-principal" className="portal-content"><PortalHeader />{children}</main></div>; }
