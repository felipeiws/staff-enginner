import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { PortalHeader, PortalSidebar } from '@/components';
import { useAssignKnowledgeGap, useKnowledgeHealth } from '@/data/hooks';
import { DataError, DataSkeleton } from '@/data/query-state';
import type { KnowledgeHealth, KnowledgeHealthMetric, UnansweredQuestion } from '@/data/types';
import './knowledge-health-page.css';

const period = '30d' as const;
const defaultScopeLabel = 'ERP Financeiro';

const metricValue = (metric: KnowledgeHealthMetric) => {
  if (metric.unit === '%') return `${metric.value}%`;
  if (metric.unit === 'decimal') return metric.value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  if (metric.unit === 'hours') return `${metric.value.toLocaleString('pt-BR', { maximumFractionDigits: 1 })}h`;
  return metric.value.toLocaleString('pt-BR');
};
const metricVariation = (metric: KnowledgeHealthMetric) => {
  const prefix = metric.variation > 0 ? '+' : '';
  if (metric.unit === '%') return `${prefix}${metric.variation} pp`;
  if (metric.unit === 'hours') return `${prefix}${metric.variation.toLocaleString('pt-BR', { maximumFractionDigits: 1 })}h`;
  return `${prefix}${metric.variation.toLocaleString('pt-BR', { maximumFractionDigits: 2 })}`;
};
const sparkline = (metric: KnowledgeHealthMetric) => {
  const values = metric.series.map((point) => point.value);
  const minimum = Math.min(...values);
  const maximum = Math.max(...values);
  return values
    .map((value, index) => {
      const x = values.length < 2 ? 75 : (150 * index) / (values.length - 1);
      const y = maximum === minimum ? 17 : 31 - ((value - minimum) / (maximum - minimum)) * 28;
      return `${x.toFixed(1)},${y.toFixed(1)}`;
    })
    .join(' ');
};

export function KnowledgeHealthPage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const scope = searchParams.get('scope') ?? '';
  const scopeLabel = searchParams.get('scope_label') ?? defaultScopeLabel;
  const organization = searchParams.get('organization') ?? 'nordika';
  const application = searchParams.get('application') ?? undefined;
  const module = searchParams.get('module') ?? undefined;
  const health = useKnowledgeHealth(scope, period);
  const [scopeInput, setScopeInput] = useState(scope);
  const [assigned, setAssigned] = useState<{ gapId: string; taskId: string }>();

  useEffect(() => setScopeInput(scope), [scope]);
  const updateUrl = (change: (next: URLSearchParams) => void) => {
    const next = new URLSearchParams(searchParams);
    change(next);
    setSearchParams(next);
  };
  const selectScope = () => {
    const value = scopeInput.trim();
    if (!value) return;
    updateUrl((next) => {
      next.set('scope', value);
      next.set('period', period);
    });
  };

  return <div className="portal-shell"><PortalSidebar /><main id="conteudo-principal" className="portal-content"><PortalHeader />
    <div className="knowledge-health-page">
      <div className="knowledge-health-page__title-row">
        <h1>Saúde do conhecimento</h1>
        <span>{scopeLabel} · últimos 30 dias</span>
        <div className="knowledge-health-page__filters" aria-label="Filtros do dashboard">
          <label>Escopo<input aria-label="Escopo da saúde" value={scopeInput} onChange={(event) => setScopeInput(event.target.value)} onBlur={selectScope} onKeyDown={(event) => { if (event.key === 'Enter') selectScope(); }} placeholder="UUID do escopo" /></label>
          <label>Período<select aria-label="Período da saúde" value={period} onChange={() => updateUrl((next) => next.set('period', period))}><option value="30d">últimos 30 dias</option></select></label>
        </div>
      </div>
      {!scope ? <section className="knowledge-health-empty"><strong>Selecione um escopo para consultar a saúde.</strong><p>Informe o UUID autorizado da organização, aplicação ou módulo; ele será preservado na URL compartilhável.</p></section> : null}
      {scope && health.isLoading ? <DataSkeleton lines={8} /> : null}
      {scope && health.error ? <DataError error={health.error} retry={() => void health.refetch()} /> : null}
      {scope && health.data ? <HealthDashboard data={health.data} organization={organization} {...(application ? { application } : {})} {...(module ? { module } : {})} {...(assigned ? { assigned } : {})} onAssigned={setAssigned} /> : null}
    </div>
  </main></div>;
}

function HealthDashboard({ data, organization, application, module, assigned, onAssigned }: {
  data: KnowledgeHealth;
  organization: string;
  application?: string;
  module?: string;
  assigned?: { gapId: string; taskId: string };
  onAssigned: (assigned: { gapId: string; taskId: string }) => void;
}) {
  const [assigning, setAssigning] = useState<UnansweredQuestion>();
  return <>
    <section className="knowledge-health-metrics" aria-label="Métricas do período">
      {data.metrics.map((metric) => <article key={metric.key} className="knowledge-health-metric">
        <span>{metric.label}</span><div><strong>{metricValue(metric)}</strong><small style={{ color: metric.trend_color }}>{metricVariation(metric)}</small></div>
        <svg viewBox="0 0 150 34" role="img" aria-label={`Tendência de ${metric.label}`}><polyline points={sparkline(metric)} fill="none" stroke={metric.trend_color} strokeWidth="1.4" /></svg>
      </article>)}
    </section>
    <section className="knowledge-health-grid">
      <article className="knowledge-health-card knowledge-health-debt"><h2>Dívida de conhecimento</h2>{data.knowledge_debt.map((item) => <div key={item.label} className="knowledge-health-debt__row">
        <div><span>{item.label}</span><strong style={{ color: item.color }}>{item.value}</strong></div><i><b style={{ width: `${item.percentage}%`, background: item.color }} /></i><small>{item.value} de {item.reference_total}</small>
      </div>)}</article>
      <div className="knowledge-health-side">
        <article className="knowledge-health-card knowledge-health-gaps"><header><h2>Perguntas sem resposta</h2><span>geram tarefas de documentação</span></header>
          {data.unanswered_questions.map((gap) => <div className="knowledge-health-gap" key={gap.id}><div><p>{gap.question}</p><small style={{ color: gap.color }}>{gap.occurrence_count} perguntas · últimos {gap.period.days} dias</small></div><button type="button" onClick={() => setAssigning(gap)}>atribuir</button></div>)}
        </article>
        <article className="knowledge-health-card knowledge-health-insight"><h2>Insight do período</h2><p>{data.period_insight.text} {data.period_insight.support_tickets_total ? <>Documentar Custos e Fiscal deve reduzir ~<strong>140 tickets/mês</strong> — maior retorno por hora investida.</> : null}</p></article>
      </div>
    </section>
    {assigning ? <AssignmentDialog gap={assigning} scope={{ organization, ...(application ? { application } : {}), ...(module ? { module } : {}) }} onClose={() => setAssigning(undefined)} onAssigned={onAssigned} /> : null}
    {assigned ? <p className="knowledge-health-assigned" role="status">Tarefa de documentação {assigned.taskId} gerada para a lacuna.</p> : null}
  </>;
}

function AssignmentDialog({ gap, scope, onClose, onAssigned }: { gap: UnansweredQuestion; scope: { organization: string; application?: string; module?: string }; onClose: () => void; onAssigned: (value: { gapId: string; taskId: string }) => void }) {
  const [ownerId, setOwnerId] = useState('');
  const assignment = useAssignKnowledgeGap();
  const submit = async () => {
    if (!ownerId.trim()) return;
    try { const result = await assignment.mutateAsync({ id: gap.id, ownerId: ownerId.trim(), scope }); onAssigned({ gapId: gap.id, taskId: result.documentation_task.id }); onClose(); } catch { /* The inline error keeps the dialog actionable. */ }
  };
  return <div className="knowledge-health-dialog-backdrop" role="presentation"><section aria-modal="true" aria-labelledby="assignment-title" className="knowledge-health-dialog" role="dialog"><h2 id="assignment-title">Atribuir lacuna</h2><p>{gap.question}</p><label htmlFor="knowledge-gap-owner">Responsável</label><input id="knowledge-gap-owner" value={ownerId} onChange={(event) => setOwnerId(event.target.value)} placeholder="id do responsável" autoFocus />{assignment.error ? <small role="alert">Não foi possível gerar a tarefa de documentação.</small> : null}<footer><button type="button" onClick={onClose}>cancelar</button><button type="button" disabled={!ownerId.trim() || assignment.isPending} onClick={() => void submit()}>atribuir e gerar tarefa</button></footer></section></div>;
}
