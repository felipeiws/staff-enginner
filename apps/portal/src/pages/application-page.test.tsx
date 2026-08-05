import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen } from '@testing-library/react';
import { http, HttpResponse } from 'msw';
import { setupServer } from 'msw/node';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { afterAll, afterEach, beforeAll, describe, expect, it } from 'vitest';
import { ApplicationPage } from './application-page';

const server = setupServer();
const provenance = { revision: 'r1', method: 'published_entity_aggregation_v1', confidence: 1, processed_at: '2026-07-31T00:00:00Z' };
const technical = [
  ['architecture', 'Arquitetura', 2], ['code', 'Código', 8], ['apis', 'APIs', 96], ['database', 'Banco', 4], ['integrations', 'Integrações', 3], ['queues_events', 'Filas e Eventos', 5],
].map(([key, name, count]) => ({ key, name, count, breakdown: {}, provenance }));
const functional = [
  ['processes', 'Processos', 14], ['flows', 'Fluxos', 7], ['faqs', 'FAQs', 10], ['troubleshooting', 'Troubleshooting', 3], ['business_rules', 'Regras de negócio', 22], ['screens', 'Telas', 210],
].map(([key, name, count]) => ({ key, name, count, breakdown: {}, provenance }));

beforeAll(() => server.listen({ onUnhandledRequest: 'bypass' }));
afterEach(() => server.resetHandlers());
afterAll(() => server.close());

function renderPage() {
  return render(<QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}><MemoryRouter initialEntries={['/aplicacoes/erp']}><Routes><Route path="/aplicacoes/:id" element={<ApplicationPage />} /></Routes></MemoryRouter></QueryClientProvider>);
}

describe('ApplicationPage', () => {
  it('reproduz a visão com dados autorizados e alterna a árvore funcional', async () => {
    server.use(
      http.get('*/api/v1/applications/erp', () => HttpResponse.json({ id: 'erp', name: 'ERP Financeiro', modules_count: 14, screens_count: 210, description: 'Núcleo financeiro da Nordika.', documentation_coverage: 74, pending_approvals: 7, knowledge_health: { score: 88, status: 'Saudável' }, provenance })),
      http.get('*/api/v1/applications/erp/tree', ({ request }) => HttpResponse.json({ nav: new URL(request.url).searchParams.get('nav'), groups: new URL(request.url).searchParams.get('nav') === 'technical' ? technical : functional })),
      http.get('*/api/v1/applications/erp/modules', () => HttpResponse.json({ modules: [{ id: 'compras', name: 'Compras', processes_count: 4, apis_count: 8, knowledge_health: { score: 91, status: 'Saudável' }, provenance }] })),
      http.get('*/api/v1/organizations/nordika', () => HttpResponse.json({ id: 'nordika', name: 'Nordika', indexed_entities_count: 1, applications_count: 1, provenance })),
      http.get('*/api/v1/approvals', () => HttpResponse.json({ approvals: [] })),
    );
    renderPage();
    expect(await screen.findByRole('heading', { name: 'ERP Financeiro', level: 1 })).toBeInTheDocument();
    expect(screen.getByText('14 módulos · 210 telas · 96 endpoints.', { exact: false })).toBeInTheDocument();
    expect(screen.getByText('Cobertura')).toBeInTheDocument();
    expect(screen.getByText('Compras')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /APIs 96/ }).getAttribute('href')).toContain('application=erp');
    fireEvent.click(screen.getByRole('button', { name: 'Navegação Funcional' }));
    expect(await screen.findByRole('link', { name: /Regras de negócio 22/ })).toBeInTheDocument();
  });
});
