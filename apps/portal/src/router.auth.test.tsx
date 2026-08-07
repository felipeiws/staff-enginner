import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import { http, HttpResponse } from 'msw';
import { setupServer } from 'msw/node';
import { RouterProvider } from 'react-router-dom';
import { afterAll, afterEach, beforeAll, describe, expect, it } from 'vitest';
import { router } from '@/router';

const server = setupServer();
beforeAll(() => server.listen({ onUnhandledRequest: 'bypass' }));
afterEach(() => server.resetHandlers());
afterAll(() => server.close());

const provenance = {
  revision: 'r1',
  method: 'parser',
  confidence: 1,
  processed_at: '2026-07-31T00:00:00Z',
};

function mockAnonymous() {
  server.use(
    http.get('*/api/v1/me', () =>
      HttpResponse.json(
        { title: 'Não autenticado', status: 401 },
        { status: 401, headers: { 'Content-Type': 'application/problem+json' } },
      ),
    ),
  );
}

function mockAuthenticated() {
  server.use(
    http.get('*/api/v1/me', () =>
      HttpResponse.json({
        principal: { id: 'u1', type: 'human', name: 'Ana', avatar_initials: 'AN' },
      }),
    ),
    http.get('*/api/v1/ux-decisions', () => HttpResponse.json({ decisions: [] })),
    http.get('*/api/v1/approvals', () => HttpResponse.json({ approvals: [] })),
    http.get('*/api/v1/organizations/nordika', () =>
      HttpResponse.json({
        id: 'nordika',
        name: 'Nordika',
        indexed_entities_count: 1,
        applications_count: 1,
        provenance,
      }),
    ),
  );
}

async function renderRouterAt(path: string) {
  await router.navigate(path);
  return render(
    <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
      <RouterProvider router={router} />
    </QueryClientProvider>,
  );
}

describe('guard de autenticação do router', () => {
  it('redireciona para /login com a rota protegida em "next" quando não há sessão', async () => {
    mockAnonymous();
    await renderRouterAt('/aprovacoes');
    expect(await screen.findByLabelText('E-mail')).toBeInTheDocument();
    expect(router.state.location.pathname).toBe('/login');
    expect(router.state.location.search).toBe('?next=%2Faprovacoes');
  });

  it('preserva a query string da rota protegida em "next"', async () => {
    mockAnonymous();
    await renderRouterAt('/busca?q=pagamento');
    expect(await screen.findByLabelText('E-mail')).toBeInTheDocument();
    expect(router.state.location.search).toBe('?next=%2Fbusca%3Fq%3Dpagamento');
  });

  it('renderiza a rota protegida normalmente quando há sessão', async () => {
    mockAuthenticated();
    await renderRouterAt('/decisoes-ux');
    expect(await screen.findByRole('heading', { name: 'Decisões de UX' })).toBeInTheDocument();
    expect(router.state.location.pathname).toBe('/decisoes-ux');
  });

  it('renderiza /login sem passar pelo guard', async () => {
    mockAnonymous();
    await renderRouterAt('/login');
    expect(screen.getByLabelText('E-mail')).toBeInTheDocument();
    expect(screen.queryByLabelText('Conteúdo da página')).not.toBeInTheDocument();
    expect(router.state.location.pathname).toBe('/login');
  });

  it('renderiza /onboarding sem consultar a sessão', async () => {
    let principalRequests = 0;
    server.use(
      http.get('*/api/v1/me', () => {
        principalRequests += 1;
        return HttpResponse.json(
          { title: 'Não autenticado', status: 401 },
          { status: 401, headers: { 'Content-Type': 'application/problem+json' } },
        );
      }),
    );
    await renderRouterAt('/onboarding');
    expect(screen.getByRole('heading', { name: 'Conecte as fontes de verdade' })).toBeInTheDocument();
    expect(router.state.location.pathname).toBe('/onboarding');
    expect(principalRequests).toBe(0);
  });
});
