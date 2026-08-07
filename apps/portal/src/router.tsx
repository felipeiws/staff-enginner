import type { ReactNode } from 'react';
import { createBrowserRouter, Navigate, useLocation } from 'react-router-dom';
import { PortalHeader, PortalSidebar } from '@/components';
import { useCurrentPrincipal } from '@/data/hooks';
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
import { LoginPage } from '@/pages/login-page';
import { OnboardingPage } from '@/pages/onboarding-page';

const PendingView = () => (
  <div className="portal-shell">
    <PortalSidebar />
    <main aria-label="Conteúdo da página" className="portal-content">
      <PortalHeader />
    </main>
  </div>
);

function AuthGuard({ children }: { children: ReactNode }) {
  const currentPrincipal = useCurrentPrincipal();
  const location = useLocation();
  if (currentPrincipal.isLoading) return <PendingView />;
  if (currentPrincipal.error) {
    const next = `${location.pathname}${location.search}`;
    return <Navigate to={`/login?next=${encodeURIComponent(next)}`} replace />;
  }
  return <>{children}</>;
}

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
  '/login',
  '/onboarding',
] as const;

export const router = createBrowserRouter([
  { path: '/', element: <AuthGuard><HomePage /></AuthGuard>, errorElement: <RouteErrorPage /> },
  { path: '/busca', element: <AuthGuard><SearchPage /></AuthGuard>, errorElement: <RouteErrorPage /> },
  { path: '/copiloto', element: <AuthGuard><CopilotPage /></AuthGuard>, errorElement: <RouteErrorPage /> },
  { path: '/graph', element: <AuthGuard><GraphPage /></AuthGuard>, errorElement: <RouteErrorPage /> },
  { path: '/aplicacoes/:id', element: <AuthGuard><ApplicationPage /></AuthGuard>, errorElement: <RouteErrorPage /> },
  { path: '/processos/:id', element: <AuthGuard><ProcessPage /></AuthGuard>, errorElement: <RouteErrorPage /> },
  { path: '/regras/:id', element: <AuthGuard><RulePage /></AuthGuard>, errorElement: <RouteErrorPage /> },
  { path: '/telas/:id', element: <AuthGuard><ScreenPage /></AuthGuard>, errorElement: <RouteErrorPage /> },
  { path: '/apis/:id', element: <AuthGuard><EndpointPage /></AuthGuard>, errorElement: <RouteErrorPage /> },
  { path: '/aprovacoes', element: <AuthGuard><ApprovalsPage /></AuthGuard>, errorElement: <RouteErrorPage /> },
  { path: '/saude', element: <AuthGuard><KnowledgeHealthPage /></AuthGuard>, errorElement: <RouteErrorPage /> },
  { path: '/decisoes-ux', element: <AuthGuard><UxDecisionsPage /></AuthGuard>, errorElement: <RouteErrorPage /> },
  { path: '/login', element: <LoginPage />, errorElement: <RouteErrorPage /> },
  { path: '/onboarding', element: <OnboardingPage />, errorElement: <RouteErrorPage /> },
  // Todo path já declarado acima precisa ficar de fora deste filtro: sem a
  // exclusão, o path ganharia uma segunda entrada com PendingView no router.
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
        path !== '/decisoes-ux' &&
        path !== '/login' &&
        path !== '/onboarding',
    )
    .map((path) => ({
      path,
      element: <PendingView />,
      errorElement: <RouteErrorPage />,
    })),
  { path: '*', element: <RouteErrorPage notFound /> },
]);
