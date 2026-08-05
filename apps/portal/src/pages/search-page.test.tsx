import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen } from '@testing-library/react';
import { http, HttpResponse } from 'msw';
import { setupServer } from 'msw/node';
import { MemoryRouter, useLocation } from 'react-router-dom';
import { afterAll, afterEach, beforeAll, describe, expect, it } from 'vitest';
import { SearchPage } from './search-page';

const server = setupServer();
const provenance = {
  revision: 'r1',
  method: 'parser@1',
  confidence: 0.91,
  processed_at: '2026-07-31T00:00:00Z',
};
beforeAll(() => server.listen({ onUnhandledRequest: 'bypass' }));
afterEach(() => server.resetHandlers());
afterAll(() => server.close());

function LocationProbe() {
  const location = useLocation();
  return (
    <output>
      {location.pathname}
      {location.search}
    </output>
  );
}
function renderPage(initialEntry = '/busca?q=faturamento') {
  return render(
    <QueryClientProvider
      client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
    >
      <MemoryRouter initialEntries={[initialEntry]}>
        <SearchPage />
        <LocationProbe />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}
function sharedRequests() {
  return [
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
  ];
}
const result = {
  id: 'regra-1',
  entity_id: 'ent-2',
  type: { name: 'Regra', color: 'warn' },
  path: 'ERP Financeiro › Compras › Regras',
  freshness: 'Atualizado há 3 dias',
  title: 'RN-142',
  excerpt: { pre: 'Não pode cancelar após ', hit: 'faturamento', post: '.' },
  metadata: 'git · confiança 91% · Time Suprimentos',
  related_entities: ['Cancelar pedido'],
  evidence: {
    origin_uri: 'https://git.example/rn-142.md',
    source_revision: 'a91f3ce',
    method: 'parser@1',
    confidence: 0.91,
    processed_at: '2026-07-31T00:00:00Z',
  },
};

describe('SearchPage', () => {
  it('reproduz a busca facetada com evidência e compartilha filtros na URL', async () => {
    server.use(
      ...sharedRequests(),
      http.get('*/api/v1/search', () =>
        HttpResponse.json({
          results: [result],
          type_counts: { Tudo: 148, Regra: 12 },
          facets: {
            application: [{ value: 'ERP Financeiro', count: 48 }],
            freshness: [{ value: 'Últimos 7 dias', count: 31 }],
            owner: [{ value: 'Time Suprimentos', count: 12 }],
          },
        }),
      ),
    );
    renderPage();
    expect(await screen.findByText('Resposta direta · confiança 91%')).toBeInTheDocument();
    expect(screen.getByText('RN-142')).toBeInTheDocument();
    expect(screen.getByText(/a91f3ce/)).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Regras12' }));
    expect(screen.getByText('/busca?q=faturamento&entity_type=Regra')).toBeInTheDocument();
    fireEvent.click(await screen.findByRole('button', { name: 'ERP Financeiro 48' }));
    expect(
      screen.getByText('/busca?q=faturamento&entity_type=Regra&application=ERP+Financeiro'),
    ).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: '✦ pedir uma resposta' }));
    expect(screen.getByText('/copiloto?q=faturamento')).toBeInTheDocument();
  });

  it('mostra o estado vazio no visual da busca', async () => {
    server.use(
      ...sharedRequests(),
      http.get('*/api/v1/search', () =>
        HttpResponse.json({ results: [], type_counts: { Tudo: 0 }, facets: {} }),
      ),
    );
    renderPage('/busca?q=ausente');
    expect(await screen.findByText('Nenhum resultado encontrado')).toBeInTheDocument();
  });

  it('mostra erro recuperável quando a API falha', async () => {
    server.use(
      ...sharedRequests(),
      http.get('*/api/v1/search', () =>
        HttpResponse.json(
          {
            title: 'Busca indisponível',
            detail: 'Índice temporariamente indisponível',
            status: 503,
          },
          { status: 503 },
        ),
      ),
    );
    renderPage();
    expect(await screen.findByRole('alert', {}, { timeout: 5_000 })).toHaveTextContent(
      'Índice temporariamente indisponível',
    );
    expect(screen.getByRole('button', { name: 'Tentar novamente' })).toBeInTheDocument();
  });
});
