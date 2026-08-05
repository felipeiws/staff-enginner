import { createBrowserRouter } from 'react-router-dom';
import { PortalHeader, PortalSidebar } from '@/components';
import { RouteErrorPage } from '@/pages/route-error-page';
import { HomePage } from '@/pages/home-page';
import { SearchPage } from '@/pages/search-page';
import { CopilotPage } from '@/pages/copilot-page';
import { GraphPage } from '@/pages/graph-page';
import { ApplicationPage } from '@/pages/application-page';
import { ProcessPage } from '@/pages/process-page';
import { RulePage } from '@/pages/rule-page';
import { ScreenPage } from '@/pages/screen-page';
import { EndpointPage } from '@/pages/endpoint-page';
import { ApprovalsPage } from '@/pages/approvals-page';
import { KnowledgeHealthPage } from '@/pages/knowledge-health-page';
import { UxDecisionsPage } from '@/pages/ux-decisions-page';

const PendingView = () => (
  <div className="portal-shell">
    <PortalSidebar />
    <main aria-label="Conteúdo da página" className="portal-content">
      <PortalHeader />
    </main>
  </div>
);

export const routePaths = [
  '/',
  '/busca',
  '/copiloto',
  '/graph',
  '/aplicacoes/:id',
  '/processos/:id',
  '/regras/:id',
  '/telas/:id',
  '/apis/:id',
  '/aprovacoes',
  '/saude',
  '/decisoes-ux',
] as const;

export const router = createBrowserRouter([
  { path: '/', element: <HomePage />, errorElement: <RouteErrorPage /> },
  { path: '/busca', element: <SearchPage />, errorElement: <RouteErrorPage /> },
  { path: '/copiloto', element: <CopilotPage />, errorElement: <RouteErrorPage /> },
  { path: '/graph', element: <GraphPage />, errorElement: <RouteErrorPage /> },
  { path: '/aplicacoes/:id', element: <ApplicationPage />, errorElement: <RouteErrorPage /> },
  { path: '/processos/:id', element: <ProcessPage />, errorElement: <RouteErrorPage /> },
  { path: '/regras/:id', element: <RulePage />, errorElement: <RouteErrorPage /> },
  { path: '/telas/:id', element: <ScreenPage />, errorElement: <RouteErrorPage /> },
  { path: '/apis/:id', element: <EndpointPage />, errorElement: <RouteErrorPage /> },
  { path: '/aprovacoes', element: <ApprovalsPage />, errorElement: <RouteErrorPage /> },
  { path: '/saude', element: <KnowledgeHealthPage />, errorElement: <RouteErrorPage /> },
  { path: '/decisoes-ux', element: <UxDecisionsPage />, errorElement: <RouteErrorPage /> },
  ...routePaths
    .filter(
      (path) =>
        path !== '/' &&
        path !== '/busca' &&
        path !== '/copiloto' &&
        path !== '/graph' &&
        path !== '/aplicacoes/:id' &&
        path !== '/processos/:id' &&
        path !== '/regras/:id' &&
        path !== '/telas/:id' &&
        path !== '/apis/:id' &&
        path !== '/aprovacoes' &&
        path !== '/saude' &&
        path !== '/decisoes-ux',
    )
    .map((path) => ({
      path,
      element: <PendingView />,
      errorElement: <RouteErrorPage />,
    })),
  { path: '*', element: <RouteErrorPage notFound /> },
]);
