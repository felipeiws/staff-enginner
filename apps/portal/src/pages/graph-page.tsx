import { useMemo, useState, type PointerEvent } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { PortalHeader, PortalSidebar } from '@/components';
import { useGraphDiagnosis, useGraphNeighborhood, useGraphNode } from '@/data/hooks';
import type { GraphEdge, GraphNode, Provenance } from '@/data/types';
import { forceLayout } from './graph-layout';
import './graph-page.css';

const focusId = 'proc';
const provenance: Provenance = {
  origin: 'git',
  revision: 'a91f3ce',
  method: 'parser@1',
  confidence: 0.91,
  processed_at: '2026-07-31T00:00:00Z',
};
const sampleNodes: readonly GraphNode[] = [
  [
    'proc',
    'Processo',
    'Cancelar Pedido de Compra',
    'violet',
    9,
    'Fluxo de cancelamento de pedidos de compra, incluindo alçada, estorno fiscal e liberação de reserva.',
    'Time Suprimentos',
    'há 3 dias',
  ],
  [
    'rn142',
    'Regra',
    'RN-142',
    'warn',
    5,
    'Impede cancelamento integral de pedidos com faturamento parcial.',
    'Fiscal',
    'há 4 meses',
  ],
  [
    'rn088',
    'Regra',
    'RN-088 Alçada',
    'warn',
    3,
    'Pedidos acima de R$ 50.000 exigem aprovação de alçada antes do cancelamento.',
    'Controladoria',
    'há 1 ano',
  ],
  [
    'pc421',
    'Tela',
    'PC-0421',
    'info',
    7,
    'Tela de detalhe do pedido, origem da ação Cancelar.',
    'Squad Compras',
    'print há 5 meses',
  ],
  [
    'pc433',
    'Tela',
    'PC-0433',
    'info',
    4,
    'Modal de motivo do cancelamento, com categorias obrigatórias.',
    'Squad Compras',
    'há 2 meses',
  ],
  [
    'api1',
    'API',
    'DELETE /purchase-orders',
    'acc',
    6,
    'Endpoint de cancelamento. 409 quando há faturamento parcial.',
    'Squad Compras',
    'sync automático',
  ],
  [
    'api2',
    'API',
    'POST /reservations/release',
    'acc',
    4,
    'Libera linhas de reserva de estoque de forma assíncrona.',
    'Squad Estoque',
    'sync automático',
  ],
  [
    'evt',
    'Evento',
    'purchase_order.canceled',
    'pink',
    5,
    'Evento de domínio publicado no cancelamento. 3 consumidores.',
    'Plataforma',
    'há 20 dias',
  ],
].map(([id, type, label, color, weight, description, owner, updated_at]) => ({
  id: String(id),
  type: String(type),
  label: String(label),
  color: String(color),
  weight: Number(weight),
  description: String(description),
  owner: String(owner),
  updated_at: String(updated_at),
  relations_count: Number(weight),
  provenance,
}));
const sampleEdges: readonly GraphEdge[] = [
  ['proc', 'rn142'],
  ['proc', 'rn088'],
  ['proc', 'pc421'],
  ['proc', 'pc433'],
  ['proc', 'api1'],
  ['proc', 'api2'],
  ['proc', 'evt'],
].map(([origin, destination]) => ({
  origin: origin!,
  destination: destination!,
  predicate: 'related_to',
  weight: 1,
  provenance,
}));
const colors: Record<string, string> = {
  violet: 'var(--violet)',
  warn: 'var(--warn)',
  info: 'var(--info)',
  acc: 'var(--acc)',
  pink: 'var(--pink)',
};
const legend: readonly [string, string][] = [
  ['violet', 'Processo'],
  ['warn', 'Regra'],
  ['info', 'Tela'],
  ['acc', 'API'],
  ['pink', 'Evento'],
];
const routeFor = (node: GraphNode) =>
  ({ Processo: '/processos/', Regra: '/regras/', Tela: '/telas/', Endpoint: '/apis/', API: '/apis/' })[node.type];

export function GraphPage() {
  const [params] = useSearchParams();
  // Sem ?entity= na URL não há id real para consultar: 'proc' é só o nó de
  // exemplo local (sampleNodes) e nunca deve ser enviado à API, pois entities.id
  // é uuid no banco e a consulta falha com "invalid input syntax for type uuid".
  const focusEntity = params.get('entity');
  const [depth, setDepth] = useState<1 | 2 | 3>(1);
  const [selectedId, setSelectedId] = useState(focusEntity ?? focusId);
  const [transform, setTransform] = useState({ x: 0, y: 0, scale: 1 });
  const graph = useGraphNeighborhood({ entity: focusEntity ?? '', depth, group_by: 'type' });
  const diagnosis = useGraphDiagnosis(focusEntity ?? '');
  const remoteNodes = graph.data?.nodes ?? [];
  const nodes = remoteNodes.length ? remoteNodes : sampleNodes;
  const edges = graph.data?.edges.length ? graph.data.edges : sampleEdges;
  const selected = nodes.find((node) => node.id === selectedId) ?? nodes[0] ?? sampleNodes[0]!;
  const detail = useGraphNode(remoteNodes.length ? selected.id : '');
  const display = detail.data ?? selected;
  const displayOwner =
    typeof display?.owner === 'string'
      ? display.owner
      : (display?.owner as { id?: string } | undefined)?.id ?? 'Não informado';
  const points = useMemo(
    () => forceLayout(nodes, edges, `${focusEntity ?? focusId}:${depth}`),
    [nodes, edges, depth, focusEntity],
  );
  const positions = new Map(points.map((point) => [point.id, point]));
  const focus = nodes.find((node) => node.id === focusEntity) ?? nodes[0];
  const navigate = useNavigate();
  const diagnosisSummary =
    typeof diagnosis.data?.summary === 'string'
      ? diagnosis.data.summary
      : '1 regra sem responsável (RN-233), 1 tela sem print (AP-0902) e 1 evento com DLQ ativa. O Copiloto sugere 3 correções.';
  let drag: { x: number; y: number } | undefined;
  const startPan = (event: PointerEvent<SVGSVGElement>) => {
    if (event.target !== event.currentTarget) return;
    drag = { x: event.clientX, y: event.clientY };
    event.currentTarget.setPointerCapture(event.pointerId);
  };
  const pan = (event: PointerEvent<SVGSVGElement>) => {
    if (!drag) return;
    setTransform((current) => ({
      ...current,
      x: current.x + event.clientX - drag!.x,
      y: current.y + event.clientY - drag!.y,
    }));
    drag = { x: event.clientX, y: event.clientY };
  };
  const goToEntity = () => {
    const route = routeFor(selected);
    if (route) void navigate(`${route}${selected.id}`);
  };
  const zoom = (amount: number) => setTransform((value) => ({
    ...value,
    scale: Math.max(0.65, Math.min(1.8, value.scale + amount)),
  }));
  return (
    <div className="portal-shell">
      <PortalSidebar />
      <main id="conteudo-principal" className="portal-content">
        <PortalHeader />
        <div className="graph-page">
          <div className="graph-toolbar">
            <span className="graph-focus">foco: {focus?.label}</span>
            <button
              onClick={() => setDepth((value) => (value === 3 ? 1 : ((value + 1) as 1 | 2 | 3)))}
            >
              profundidade {depth}
            </button>
            <button>agrupar por tipo</button>
            <button className="graph-orphans">achar órfãos (14)</button>
            <span className="graph-toolbar-spacer" />
            {legend.map(([color, label]) => (
              <span className="graph-legend" key={label}>
                <i style={{ background: colors[color] ?? 'var(--tx3)' }} />
                {label}
              </span>
            ))}
          </div>
          <div className="graph-grid">
            <section className="graph-canvas-card" aria-label="Visualização do Knowledge Graph">
              <div className="graph-zoom-controls" aria-label="Controles de zoom do grafo">
                <button type="button" onClick={() => zoom(-0.1)} aria-label="Diminuir zoom">−</button>
                <output aria-label="Nível de zoom">{Math.round(transform.scale * 100)}%</output>
                <button type="button" onClick={() => zoom(0.1)} aria-label="Aumentar zoom">+</button>
                <button type="button" onClick={() => setTransform({ x: 0, y: 0, scale: 1 })}>redefinir</button>
              </div>
              <svg
                aria-describedby="graph-canvas-help"
                aria-label="Mapa visual das relações do Knowledge Graph. Use a lista navegável para selecionar entidades."
                role="img"
                viewBox="0 0 720 470"
                onPointerDown={startPan}
                onPointerMove={pan}
                onPointerUp={() => {
                  drag = undefined;
                }}
                onWheel={(event) => {
                  event.preventDefault();
                  setTransform((value) => ({
                    ...value,
                    scale: Math.max(
                      0.65,
                      Math.min(1.8, value.scale + (event.deltaY < 0 ? 0.1 : -0.1)),
                    ),
                  }));
                }}
              >
                <g transform={`translate(${transform.x} ${transform.y}) scale(${transform.scale})`}>
                  {edges.map((edge) => {
                    const from = positions.get(edge.origin);
                    const to = positions.get(edge.destination);
                    const connected = edge.origin === selectedId || edge.destination === selectedId;
                    return from && to ? (
                      <line
                        key={`${edge.origin}-${edge.destination}`}
                        x1={from.x}
                        y1={from.y}
                        x2={to.x}
                        y2={to.y}
                        stroke="var(--tx2)"
                        strokeWidth={connected ? 1.2 : 0.7}
                        strokeOpacity={connected ? 0.34 : 0.16}
                      />
                    ) : null;
                  })}
                  {nodes.map((node) => {
                    const point = positions.get(node.id)!;
                    const selectedNode = node.id === selectedId;
                    return (
                      <g
                        className="graph-node"
                        key={node.id}
                        onClick={() => setSelectedId(node.id)}
                      >
                        <circle
                          cx={point.x}
                          cy={point.y}
                          r={point.radius + 7}
                          fill={colors[node.color] ?? 'var(--tx3)'}
                          fillOpacity={selectedNode ? 0.13 : 0}
                        />
                        <circle
                          cx={point.x}
                          cy={point.y}
                          r={point.radius}
                          fill={colors[node.color] ?? 'var(--tx3)'}
                          stroke="var(--bg)"
                          strokeWidth="2"
                        />
                        <text
                          x={point.x + point.radius + 7}
                          y={point.y + 3.5}
                          fill="var(--tx)"
                          fillOpacity={selectedNode ? 1 : 0.62}
                        >
                          {node.label}
                        </text>
                      </g>
                    );
                  })}
                </g>
              </svg>
              <p className="graph-canvas-help" id="graph-canvas-help">
                Role para ampliar; arraste o fundo para mover o grafo. Use os controles para zoom por toque; a lista navegável abaixo é a alternativa textual.
              </p>
            </section>
            <aside className="graph-aside">
              <section className="graph-detail">
                <span style={{ color: colors[display?.color ?? ''] ?? 'var(--tx3)' }}>
                  {display?.type}
                </span>
                <h1>{display?.label}</h1>
                <p>{display?.description ?? 'Detalhe indisponível.'}</p>
                <dl>
                  <div>
                    <dt>relações</dt>
                    <dd>{display?.relations_count ?? selected.weight}</dd>
                  </div>
                  <div>
                    <dt>responsável</dt>
                    <dd>{displayOwner}</dd>
                  </div>
                  <div>
                    <dt>atualizado</dt>
                    <dd>{display?.updated_at ?? 'Não informado'}</dd>
                  </div>
                </dl>
                <div className="graph-detail-actions">
                  <button className="graph-open" onClick={goToEntity}>
                    abrir página
                  </button>
                  <button onClick={() => setDepth(3)}>expandir +2 níveis</button>
                </div>
              </section>
              <section className="graph-diagnosis">
                <h2>Diagnóstico do cluster</h2>
                <p>
                  {diagnosisSummary}
                </p>
                <button onClick={() => void navigate('/saude')}>Ver no dashboard →</button>
              </section>
              <section className="graph-keyboard">
                <h2>Lista navegável</h2>
                <ul>
                  {nodes.map((node) => (
                    <li key={node.id}>
                      <button
                        aria-label={`Selecionar ${node.label}`}
                        aria-pressed={node.id === selectedId}
                        onClick={() => setSelectedId(node.id)}
                      >
                        <i style={{ background: colors[node.color] ?? 'var(--tx3)' }} />
                        {node.label}
                      </button>
                    </li>
                  ))}
                </ul>
              </section>
            </aside>
          </div>
        </div>
      </main>
    </div>
  );
}
