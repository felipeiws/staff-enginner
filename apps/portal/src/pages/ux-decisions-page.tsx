import { PortalHeader, PortalSidebar } from '@/components';
import { useUxDecisions } from '@/data/hooks';
import { DataError, DataSkeleton } from '@/data/query-state';
import './ux-decisions-page.css';

export function UxDecisionsPage() {
  const decisions = useUxDecisions();
  return <div className="portal-shell"><PortalSidebar /><main id="conteudo-principal" className="portal-content"><PortalHeader />
    <div className="ux-decisions-page">
      <h1>Decisões de UX</h1>
      <p className="ux-decisions-page__intro">O que foi questionado no briefing e por que a solução escolhida é superior. Cada decisão elimina etapas em vez de adicionar telas.</p>
      {decisions.isLoading ? <DataSkeleton lines={6} /> : null}
      {decisions.error ? <DataError error={decisions.error} retry={() => void decisions.refetch()} /> : null}
      {decisions.data ? <section className="ux-decisions-page__list" aria-label="Decisões de UX publicadas">
        {decisions.data.decisions.map((decision) => <article className="ux-decision-card" key={decision.id}>
          <h2>{decision.title}</h2><p>{decision.description}</p><div>→ {decision.outcome}</div>
        </article>)}
      </section> : null}
    </div>
  </main></div>;
}
