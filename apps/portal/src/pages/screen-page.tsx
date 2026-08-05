import { type DragEvent, type ReactNode, useEffect, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { PortalHeader, PortalSidebar } from '@/components';
import { useEntity, useVisualArtifactUpload } from '@/data/hooks';
import { DataError, DataSkeleton } from '@/data/query-state';
import type { EntityDetail } from '@/data/types';
import './screen-page.css';

type Detail = Record<string, unknown>;
type VisualVariant = 'desktop' | 'mobile' | 'video';
const record = (value: unknown): Detail => value !== null && typeof value === 'object' && !Array.isArray(value) ? value as Detail : {};
const text = (value: unknown, fallback = '') => typeof value === 'string' || typeof value === 'number' ? String(value) : fallback;
const list = (value: unknown) => Array.isArray(value) ? value.map(record) : [];
const fallback: Detail = {
  code: 'PC-0421', desktop: { dimensions: '1440×900' }, mobile: null, video: { duration: '1:48' },
  fields: [{ name: 'status', type: 'Status', rule: 'RN-142', rule_path: '/regras/rn-142', origin: 'purchase_orders.status' }, { name: 'valor_total', type: 'Moeda', rule: 'RN-142', rule_path: '/regras/rn-142', origin: 'purchase_orders.total' }, { name: 'motivo_cancelamento', type: 'Texto', rule: 'RN-142', rule_path: '/regras/rn-142', origin: 'cancel_reason.description' }],
  known_issues: [{ title: 'Status pode ficar desatualizado após cancelamento', severity: 'médio', color: 'var(--warn)' }, { title: 'Print não acompanha a versão atual do fluxo', severity: 'baixo', color: 'var(--tx3)' }],
  permissions: ['PC.VIEW', 'PC.CANCEL', 'PC.ADMIN', 'FISCAL.REVERSE'],
  relationships: [{ type: 'Processo', title: 'Cancelar Pedido de Compra', path: '/processos/cancelar-pedido-compra' }, { type: 'Regra', title: 'RN-142', path: '/regras/rn-142' }, { type: 'API', title: 'GET /v2/purchase-orders/{id}', path: '/apis/get-purchase-orders-id' }], relation_count: 7,
};

function screenDetail(entity: EntityDetail): Detail { return { ...fallback, ...record(entity.projection) }; }

export function ScreenPage() {
  const { id = '' } = useParams();
  const entity = useEntity(id);
  if (entity.isLoading) return <ScreenShell><DataSkeleton lines={9} /></ScreenShell>;
  if (entity.error) return <ScreenShell><DataError error={entity.error} retry={() => void entity.refetch()} /></ScreenShell>;
  if (!entity.data || entity.data.type !== 'Tela') return <ScreenShell><DataError error={new Error('Tela não encontrada.')} /></ScreenShell>;
  return <ScreenShell><ScreenDetail entity={entity.data} id={id} /></ScreenShell>;
}

function ScreenDetail({ entity, id }: { entity: EntityDetail; id: string }) {
  const data = screenDetail(entity);
  const [active, setActive] = useState<VisualVariant>('desktop');
  const activeVisual = record(data[active]);
  const freshness = record(entity.freshness);
  const relations = list(data.relationships);
  return <div className="screen-page">
    <header className="screen-identity"><span className="screen-type">Tela</span><span className="screen-code">{text(data.code, id.toUpperCase())}</span><span className="screen-freshness">{text(freshness.label, 'print desatualizado há 5 meses')}</span></header>
    <h1>{text(entity.title, 'Detalhe do Pedido de Compra')}</h1>
    <div className="screen-grid"><div className="screen-main">
      <section className="screen-visual-card"><div className="screen-tabs" role="tablist" aria-label="Artefatos visuais">
        {(['desktop', 'mobile', 'video'] as const).map((variant) => <button className={active === variant ? 'is-active' : ''} key={variant} onClick={() => setActive(variant)} role="tab" aria-selected={active === variant} type="button">{variant === 'desktop' ? 'Desktop' : variant === 'mobile' ? 'Mobile' : `Vídeo ${text(record(data.video).duration, '1:48')}`}</button>)}
      </div><VisualDropzone visual={activeVisual} variant={active} entity={entity} screenId={id} /></section>
      <Fields fields={list(data.fields)} />
    </div><aside className="screen-aside"><Issues issues={list(data.known_issues)} /><Card title="Permissões"><div className="screen-permissions">{(Array.isArray(data.permissions) ? data.permissions : []).map((permission) => <span key={text(permission)}>{text(permission)}</span>)}</div></Card><Card title="Relacionamentos"><div className="screen-relations">{relations.map((relation) => <Link key={`${text(relation.type)}-${text(relation.title)}`} to={text(relation.path, '/graph')}><span>{text(relation.type)} · </span>{text(relation.title)}</Link>)}<Link className="screen-graph-link" to={`/graph?entity=${encodeURIComponent(text(entity.id, id))}`}>Ver {text(data.relation_count, String(relations.length))} relações no Graph →</Link></div></Card></aside></div>
  </div>;
}

function VisualDropzone({ visual, variant, entity, screenId }: { visual: Detail; variant: VisualVariant; entity: EntityDetail; screenId: string }) {
  const input = useRef<HTMLInputElement>(null); const [preview, setPreview] = useState(''); const [dragging, setDragging] = useState(false); const [qaRequested, setQaRequested] = useState(false); const upload = useVisualArtifactUpload();
  useEffect(() => () => { if (preview) URL.revokeObjectURL(preview); }, [preview]);
  const receive = (file?: File) => { if (!file) return; if (preview) URL.revokeObjectURL(preview); setPreview(URL.createObjectURL(file)); upload.mutate({ file, screenKey: screenId, screenTitle: text(entity.title), variant: variant === 'mobile' ? 'mobile' : 'desktop', kind: variant === 'video' ? 'video' : 'print', scope: record(entity.scope) as Record<string, string> }); };
  const drop = (event: DragEvent<HTMLDivElement>) => { event.preventDefault(); setDragging(false); receive(event.dataTransfer.files[0]); };
  const requestQaCapture = () => { setQaRequested(true); window.dispatchEvent(new CustomEvent('atlas:qa-capture-request', { detail: { screenId: entity.id, variant, provenance: entity.provenance } })); };
  const url = text(visual.url, text(visual.preview_url));
  return <div className={`screen-dropzone${dragging ? ' is-dragging' : ''}`} onDragEnter={(event) => { event.preventDefault(); setDragging(true); }} onDragOver={(event) => event.preventDefault()} onDragLeave={() => setDragging(false)} onDrop={drop}>
    {preview || url ? variant === 'video' ? <video aria-label="Vídeo da tela" controls src={preview || url} /> : <img alt="Print da tela" src={preview || url} /> : <div className="screen-placeholder"><span>print da tela · {text(visual.dimensions, '1440×900')}</span><span>arraste um arquivo ou aguarde a captura do QA automatizado</span><button onClick={() => input.current?.click()} type="button">Enviar arquivo</button><button className="screen-qa-button" onClick={requestQaCapture} type="button">{qaRequested ? 'Captura solicitada ao QA' : 'Capturar pelo QA automatizado'}</button>{upload.isPending ? <small>Enviando observação versionada…</small> : null}{upload.isError ? <small role="alert">Não foi possível enviar o artefato.</small> : null}</div>}
    <input accept={variant === 'video' ? 'video/*' : 'image/*'} aria-label="Enviar artefato visual" hidden onChange={(event) => receive(event.target.files?.[0])} ref={input} type="file" />
  </div>;
}

function Fields({ fields }: { fields: Detail[] }) { return <section className="screen-fields"><h2>Campos e origem do dado</h2><div className="screen-fields__header"><span>CAMPO</span><span>TIPO</span><span>REGRA</span><span>ORIGEM</span></div>{fields.map((field) => <div className="screen-fields__row" key={text(field.name)}><span>{text(field.name)}</span><span>{text(field.type)}</span><Link to={text(field.rule_path, `/regras/${encodeURIComponent(text(field.rule))}`)}>{text(field.rule)}</Link><span>{text(field.origin)}</span></div>)}</section>; }
function Issues({ issues }: { issues: Detail[] }) { return <Card title="Problemas conhecidos"><div className="screen-issues">{issues.map((issue) => <div key={text(issue.title)}><strong>{text(issue.title)}</strong><small style={{ color: text(issue.color, issue.severity === 'alto' ? 'var(--danger)' : 'var(--warn)') }}>{text(issue.severity)}</small></div>)}</div></Card>; }
function Card({ title, children }: { title: string; children: ReactNode }) { return <section className="screen-card"><h2>{title}</h2>{children}</section>; }
function ScreenShell({ children }: { children: ReactNode }) { return <div className="portal-shell"><PortalSidebar /><main id="conteudo-principal" className="portal-content"><PortalHeader />{children}</main></div>; }
