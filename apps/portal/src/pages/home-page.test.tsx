import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen } from '@testing-library/react';
import { http, HttpResponse } from 'msw';
import { setupServer } from 'msw/node';
import { MemoryRouter, useLocation } from 'react-router-dom';
import { afterAll, afterEach, beforeAll, describe, expect, it } from 'vitest';
import { HomePage } from './home-page';

const server = setupServer();
beforeAll(() => server.listen({ onUnhandledRequest: 'bypass' }));
afterEach(() => server.resetHandlers());
afterAll(() => server.close());
const provenance = { revision: 'r1', method: 'parser', confidence: 1, processed_at: '2026-07-31T00:00:00Z' };
function LocationProbe() { const location = useLocation(); return <output>{location.pathname}{location.search}</output>; }
function renderPage() { return render(<QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}><MemoryRouter><HomePage /><LocationProbe /></MemoryRouter></QueryClientProvider>); }
describe('HomePage', () => {
  it('renderiza dados recebidos por API e envia a pergunta para o Copiloto', async () => {
    server.use(
      http.get('*/api/v1/home', () => HttpResponse.json({ organization: { id: 'nordika', name: 'Nordika', indexed_entities_count: 148000, applications_count: 4, provenance }, applications: [{ id: 'erp', name: 'ERP Financeiro', modules_count: 3, screens_count: 8, knowledge_health: { score: 84, status: 'Saudável' }, provenance }], frequent_questions: [{ id: 'faq-1', question: 'Como cancelar um pedido?', provenance }], top_processes: [{ id: 'process-1', name: 'Cancelar pedido', volume: 25, provenance }], activity: [{ actor: 'Ana', action: 'publicou uma revisão', occurred_at: '2026-07-31T00:00:00Z', color: 'var(--acc)', provenance }] })),
      http.get('*/api/v1/insights', () => HttpResponse.json({ insights: [{ type: 'Lacuna', color: 'var(--warn)', title: 'Perguntas sem resposta', description: 'Descrição', action: 'Ver perguntas' }] })),
      http.get('*/api/v1/approvals', () => HttpResponse.json({ approvals: [] })),
      http.get('*/api/v1/organizations/nordika', () => HttpResponse.json({ id: 'nordika', name: 'Nordika', indexed_entities_count: 1, applications_count: 1, provenance })),
    );
    renderPage();
    expect(await screen.findByText('ERP Financeiro')).toBeInTheDocument();
    expect(screen.getByText('148.000 entidades indexadas em 4 aplicações.', { exact: false })).toBeInTheDocument();
    fireEvent.change(screen.getByLabelText('Pergunte ao Copiloto'), { target: { value: 'Como cancelar?' } });
    fireEvent.submit(screen.getByRole('button', { name: '↵ perguntar' }).closest('form')!);
    expect(screen.getByText('/copiloto?q=Como%20cancelar%3F')).toBeInTheDocument();
  });
});
