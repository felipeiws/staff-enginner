import { useState, type ReactNode } from 'react';
import { Link, useParams } from 'react-router-dom';
import { PortalHeader, PortalSidebar } from '@/components';
import { useApplication, useApplicationModules, useApplicationTree } from '@/data/hooks';
import { DataError, DataSkeleton } from '@/data/query-state';
import type { ApplicationTreeGroup } from '@/data/types';
import './application-page.css';

type Navigation = 'technical' | 'functional';

const healthColor = (score: number) =>
  score >= 75 ? 'var(--acc)' : score >= 50 ? 'var(--warn)' : 'var(--danger)';
const entityTypes: Record<string, readonly string[]> = {
  architecture: ['ADR', 'Diagrama'],
  code: ['Repositório', 'Controller', 'Service', 'Job'],
  apis: ['Endpoint'],
  database: ['Tabela', 'Migration'],
  integrations: ['Integração'],
  queues_events: ['Evento'],
  processes: ['Processo'],
  flows: ['Diagrama'],
  faqs: ['FAQ'],
  troubleshooting: ['Documento'],
  business_rules: ['Regra'],
  screens: ['Tela'],
};

const listingUrl = (application: string, group: ApplicationTreeGroup) => {
  const query = new URLSearchParams({ application });
  (entityTypes[group.key] ?? []).forEach((type) => query.append('entity_type', type));
  return `/busca?${query}`;
};

export function ApplicationPage() {
  const { id = '' } = useParams();
  const [nav, setNav] = useState<Navigation>('technical');
  const application = useApplication(id);
  const technicalTree = useApplicationTree(id, 'technical');
  const functionalTree = useApplicationTree(id, 'functional');
  const modules = useApplicationModules(id);

  if (application.isLoading || technicalTree.isLoading || functionalTree.isLoading || modules.isLoading) return <ApplicationShell><DataSkeleton lines={8} /></ApplicationShell>;
  if (application.error || technicalTree.error || functionalTree.error || modules.error) return <ApplicationShell><DataError error={application.error ?? technicalTree.error ?? functionalTree.error ?? modules.error} /></ApplicationShell>;
  if (!application.data || !technicalTree.data || !functionalTree.data || !modules.data) return null;

  const data = application.data;
  const tree = nav === 'technical' ? technicalTree.data : functionalTree.data;
  return <ApplicationShell>
    <div className="application-page">
      <section className="application-summary" aria-labelledby="application-title">
        <div className="application-summary__identity">
          <span>Aplicação</span>
          <h1 id="application-title">{data.name}</h1>
          <p>{data.modules_count} módulos · {data.screens_count} telas · {technicalTree.data.groups.find((group) => group.key === 'apis')?.count ?? 0} endpoints. {data.description}</p>
        </div>
        <div className="application-metrics" aria-label="Indicadores da aplicação">
          <div><span>Saúde</span><strong style={{ color: healthColor(data.knowledge_health.score) }}>{data.knowledge_health.score}</strong></div>
          <div><span>Cobertura</span><strong>{data.documentation_coverage ?? 0}%</strong></div>
          <div><span>Pendências</span><strong className="application-metrics__pending">{data.pending_approvals ?? 0}</strong></div>
        </div>
      </section>

      <nav className="application-navigation" aria-label="Navegação da aplicação">
        <button className={nav === 'technical' ? 'is-active' : ''} type="button" onClick={() => setNav('technical')}>Navegação Técnica</button>
        <button className={nav === 'functional' ? 'is-active' : ''} type="button" onClick={() => setNav('functional')}>Navegação Funcional</button>
        <span />
        <Link to={`/graph?application=${encodeURIComponent(id)}`}>⬡ Graph da aplicação</Link>
      </nav>

      <section className={`application-tree application-tree--${nav}`} aria-label={`Navegação ${nav === 'technical' ? 'técnica' : 'funcional'}`}>
        {tree.groups.map((group) => <Link key={group.key} to={listingUrl(id, group)}><strong>{group.name}</strong><small>{group.count}</small></Link>)}
      </section>

      <section className="application-modules" aria-labelledby="application-modules-title">
        <h2 id="application-modules-title">Módulos · saúde do conhecimento</h2>
        <div>
          {modules.data.map((module) => <Link key={module.id} to={`/busca?application=${encodeURIComponent(id)}&module=${encodeURIComponent(module.id)}`}>
            <header><strong>{module.name}</strong><b style={{ color: healthColor(module.knowledge_health.score) }}>{module.knowledge_health.score}</b></header>
            <small>{module.processes_count} processos · {module.apis_count} APIs</small>
            <i><em style={{ width: `${module.knowledge_health.score}%`, background: healthColor(module.knowledge_health.score) }} /></i>
          </Link>)}
        </div>
      </section>
    </div>
  </ApplicationShell>;
}

function ApplicationShell({ children }: { children: ReactNode }) {
  return <div className="portal-shell"><PortalSidebar /><main id="conteudo-principal" className="portal-content"><PortalHeader />{children}</main></div>;
}
