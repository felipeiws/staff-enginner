import { useState, type FormEvent, type ReactNode } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { PortalHeader, PortalSidebar } from '@/components';
import { useApprovals, useHome, useInsights } from '@/data/hooks';
import { DataError, DataSkeleton } from '@/data/query-state';
import './home-page.css';

const organizationId = 'nordika';
const relativeTime = (value: string) => {
  const minutes = Math.max(0, Math.floor((Date.now() - new Date(value).getTime()) / 60_000));
  return minutes < 60 ? `há ${Math.max(minutes, 1)} min` : minutes < 1440 ? `há ${Math.floor(minutes / 60)}h` : `há ${Math.floor(minutes / 1440)}d`;
};
const healthColor = (score: number) => score >= 75 ? 'var(--acc)' : score >= 50 ? 'var(--warn)' : 'var(--danger)';

export function HomePage() {
  const navigate = useNavigate();
  const home = useHome(organizationId);
  const insights = useInsights();
  const approvals = useApprovals();
  const [question, setQuestion] = useState('');
  const submit = (event: FormEvent) => {
    event.preventDefault();
    const value = question.trim();
    if (value) void navigate(`/copiloto?q=${encodeURIComponent(value)}`);
  };
  if (home.isLoading || insights.isLoading || approvals.isLoading) return <HomeShell><DataSkeleton lines={8} /></HomeShell>;
  if (home.error) return <HomeShell><DataError error={home.error} /></HomeShell>;
  const data = home.data;
  if (!data) return null;
  const maximumVolume = Math.max(...data.top_processes.map((process) => process.volume), 1);
  return <HomeShell>
    <div className="home-page">
      <section className="home-hero" aria-labelledby="home-title">
        <h1 id="home-title">Pergunte qualquer coisa sobre suas aplicações</h1>
        <p>{data.organization.indexed_entities_count.toLocaleString('pt-BR')} entidades indexadas em {data.organization.applications_count} aplicações. Respostas sempre com fonte, código e responsável.</p>
        <form className="home-question" onSubmit={submit}>
          <span aria-hidden="true">✦</span><input value={question} onChange={(event) => setQuestion(event.target.value)} placeholder="Ex.: o que acontece com a reserva de estoque ao cancelar um pedido?" aria-label="Pergunte ao Copiloto" />
          <button type="submit">↵ perguntar</button>
        </form>
        <div className="home-suggestions">{data.frequent_questions.map((item) => <button key={item.id} type="button" onClick={() => { setQuestion(item.question); void navigate(`/copiloto?q=${encodeURIComponent(item.question)}`); }}>{item.question}</button>)}</div>
      </section>
      <section className="home-primary-grid">
        <div className="home-card home-applications"><header><span>Aplicações</span><small>saúde do conhecimento</small></header>{data.applications.map((app) => <Link key={app.id} to={`/aplicacoes/${app.id}`}><div><strong>{app.name}</strong><small>{app.modules_count} módulos · {app.screens_count} telas</small></div><div><b style={{ color: healthColor(app.knowledge_health.score) }}>{app.knowledge_health.score}%</b><small>{app.knowledge_health.status}</small></div></Link>)}</div>
        <div className="home-card home-insights"><header><span><i>✦</i> Insights da IA</span></header>{insights.data?.insights.map((item, index) => <article key={`${item.type}-${index}`}><div><em style={{ color: item.color }}>{item.type}</em><strong>{item.title}</strong></div><p>{item.description}</p><Link to={item.type === 'Lacuna' ? '/saude' : '/aprovacoes'}>{item.action} →</Link></article>)}</div>
      </section>
      <section className="home-secondary-grid">
        <div className="home-card"><header><span>Processos mais buscados</span></header>{data.top_processes.map((process) => <Link className="home-process" key={process.id} to={`/processos/${process.id}`}><div><strong>{process.name}</strong><small>{process.volume}</small></div><i><b style={{ width: `${process.volume / maximumVolume * 100}%` }} /></i></Link>)}</div>
        <div className="home-card"><header><span>Aguardando aprovação</span><Link to="/aprovacoes">ver {approvals.data?.approvals.length ?? 0}</Link></header>{approvals.data?.approvals.map((approval) => <Link className="home-approval" key={approval.id} to="/aprovacoes"><i className={`home-approval__bar home-approval__bar--${approval.entity_type_color ?? 'acc'}`} /><div><strong>{approval.title}</strong><small>{approval.author?.id ?? ''} · {approval.age ?? ''}</small></div></Link>)}</div>
        <div className="home-card"><header><span>Atividade recente</span></header>{data.activity.map((activity, index) => <div className="home-activity" key={`${activity.occurred_at}-${index}`}><i style={{ background: activity.color }} /><p><strong>{activity.actor}</strong> {activity.action} <small>{relativeTime(activity.occurred_at)}</small></p></div>)}</div>
      </section>
    </div>
  </HomeShell>;
}
function HomeShell({ children }: { children: ReactNode }) { return <div className="portal-shell"><PortalSidebar /><main id="conteudo-principal" className="portal-content"><PortalHeader />{children}</main></div>; }
