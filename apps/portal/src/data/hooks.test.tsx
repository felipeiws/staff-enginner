import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import { http, HttpResponse } from 'msw';
import { setupServer } from 'msw/node';
import { afterAll, afterEach, beforeAll, describe, expect, it } from 'vitest';
import { useApplications, useCurrentPrincipal, useLogin, useSearch } from './hooks';
import { DataError, DataSkeleton } from './query-state';

const server = setupServer();
beforeAll(() => server.listen({ onUnhandledRequest: 'error' }));
afterEach(() => server.resetHandlers());
afterAll(() => server.close());

function renderWithQuery(ui: React.ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(<QueryClientProvider client={client}>{ui}</QueryClientProvider>);
}

function ApplicationsView() {
  const query = useApplications();
  if (query.isLoading) return <DataSkeleton />;
  if (query.error) return <DataError error={query.error} />;
  return (
    <span>
      {query.data?.length === 0 ? 'Nenhuma aplicação encontrada.' : query.data?.[0]?.name}
    </span>
  );
}

function SearchView() {
  const query = useSearch({ q: 'pagamento', organization: 'erp' });
  if (query.isLoading) return <DataSkeleton />;
  if (query.error) return <DataError error={query.error} />;
  return <span>{query.data?.results.length} resultados</span>;
}

function PrincipalView() {
  const query = useCurrentPrincipal();
  if (query.isLoading) return <DataSkeleton />;
  if (query.error) return <DataError error={query.error} />;
  return <span>{query.data?.name}</span>;
}

function LoginView() {
  const mutation = useLogin();
  return (
    <button onClick={() => mutation.mutate({ email: 'ana@atlas.dev', password: 'senha123' })}>
      Entrar
    </button>
  );
}

describe('camada de dados do portal', () => {
  it('renderiza carregamento e sucesso com MSW', async () => {
    server.use(
      http.get('*/api/v1/applications', async () => {
        await new Promise((resolve) => setTimeout(resolve, 20));
        return HttpResponse.json({
          applications: [
            {
              id: 'erp',
              name: 'ERP Financeiro',
              modules_count: 1,
              screens_count: 2,
              knowledge_health: { score: 90, status: 'atual' },
              provenance: {
                revision: 'r1',
                method: 'parser',
                confidence: 0.9,
                processed_at: '2026-07-31T00:00:00Z',
              },
            },
          ],
        });
      }),
    );
    renderWithQuery(<ApplicationsView />);
    expect(screen.getByLabelText('Carregando conteúdo')).toBeInTheDocument();
    expect(await screen.findByText('ERP Financeiro')).toBeInTheDocument();
  });

  it('renderiza uma coleção vazia', async () => {
    server.use(http.get('*/api/v1/applications', () => HttpResponse.json({ applications: [] })));
    renderWithQuery(<ApplicationsView />);
    expect(await screen.findByText('Nenhuma aplicação encontrada.')).toBeInTheDocument();
  });

  it('traduz o problem RFC 9457 para uma mensagem útil', async () => {
    server.use(
      http.get('*/api/v1/search', () =>
        HttpResponse.json(
          { title: 'Escopo negado', status: 403, detail: 'Você não tem acesso a este escopo.' },
          { status: 403, headers: { 'Content-Type': 'application/problem+json' } },
        ),
      ),
    );
    renderWithQuery(<SearchView />);
    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Você não tem acesso a este escopo.',
    );
  });

  it('mantém a resposta vazia de busca como resultado válido', async () => {
    server.use(
      http.get('*/api/v1/search', () =>
        HttpResponse.json({ results: [], type_counts: {}, facets: {} }),
      ),
    );
    renderWithQuery(<SearchView />);
    await waitFor(() => expect(screen.getByText('0 resultados')).toBeInTheDocument());
  });

  it('carrega o principal autenticado', async () => {
    server.use(
      http.get('*/api/v1/me', () =>
        HttpResponse.json({
          principal: { id: 'u1', type: 'human', name: 'Ana', avatar_initials: 'AN' },
        }),
      ),
    );
    renderWithQuery(<PrincipalView />);
    expect(await screen.findByText('Ana')).toBeInTheDocument();
  });

  it('não tenta novamente quando o usuário não está autenticado (401)', async () => {
    let requestCount = 0;
    server.use(
      http.get('*/api/v1/me', () => {
        requestCount += 1;
        return HttpResponse.json(
          { title: 'Não autenticado', status: 401 },
          { status: 401, headers: { 'Content-Type': 'application/problem+json' } },
        );
      }),
    );
    renderWithQuery(<PrincipalView />);
    await screen.findByRole('alert');
    expect(requestCount).toBe(1);
  });

  it('login grava o principal no cache de ["me"] sem round-trip extra', async () => {
    server.use(
      http.get('*/auth/csrf', () => HttpResponse.json({ csrf_token: 'token-123' })),
      http.post('*/auth/session', () =>
        HttpResponse.json({
          principal: { id: 'u1', type: 'human', name: 'Ana', avatar_initials: 'AN' },
        }),
      ),
    );
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    render(
      <QueryClientProvider client={client}>
        <LoginView />
      </QueryClientProvider>,
    );
    screen.getByText('Entrar').click();
    await waitFor(() =>
      expect(client.getQueryData(['me'])).toEqual({
        principal: { id: 'u1', type: 'human', name: 'Ana', avatar_initials: 'AN' },
      }),
    );
  });
});
