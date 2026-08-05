import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen } from '@testing-library/react';
import { http, HttpResponse } from 'msw';
import { setupServer } from 'msw/node';
import { MemoryRouter, useLocation } from 'react-router-dom';
import { afterAll, afterEach, beforeAll, describe, expect, it } from 'vitest';
import { CopilotPage } from './copilot-page';

const server = setupServer();
const provenance = { revision: 'r1', method: 'parser@1', confidence: 0.91, processed_at: '2026-07-31T00:00:00Z' };
beforeAll(() => server.listen({ onUnhandledRequest: 'bypass' }));
afterEach(() => server.resetHandlers());
afterAll(() => server.close());

function LocationProbe() { const location = useLocation(); return <output>{location.pathname}{location.search}</output>; }
function renderPage(initialEntry = '/copiloto') {
  return render(<QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}><MemoryRouter initialEntries={[initialEntry]}><CopilotPage /><LocationProbe /></MemoryRouter></QueryClientProvider>);
}
function sharedRequests() {
  return [
    http.get('*/api/v1/approvals', () => HttpResponse.json({ approvals: [] })),
    http.get('*/api/v1/organizations/nordika', () => HttpResponse.json({ id: 'nordika', name: 'Nordika', indexed_entities_count: 1, applications_count: 1, provenance })),
  ];
}
const answer = {
  opening: 'O cancelamento libera somente o saldo não faturado. revision_id: a91f3ce; uri: https://git.example/processos/cancelar.md', confidence: 92, source_count: 1,
  blocks: [{ text: 'A regra de faturamento mantém o saldo já faturado.', citation: { revision_id: 'a91f3ce', uri: 'https://git.example/processos/cancelar.md' } }],
  sources: [{ type: { name: 'Processo', color: 'violet' }, name: 'Cancelar Pedido de Compra', freshness: 'revisado há 3 dias', relevance: 100, provenance: { revision_id: 'a91f3ce', uri: 'https://git.example/processos/cancelar.md', method: 'parser@1', confidence: .92 } }],
  entities: [{ id: 'processo-1', type: 'Processo', name: 'Cancelar Pedido de Compra', color: 'violet' }],
  actions: [{ id: 'deepen_process', label: 'Aprofundar no processo' }, { id: 'view_graph', label: 'Ver no Graph' }, { id: 'create_faq', label: 'Transformar em FAQ' }, { id: 'report_error', label: 'Reportar erro' }],
  reasoning: ['Consulta limitada ao escopo ERP Financeiro.', '1 evidência citável selecionada.'], reasoning_trace: { version: 'copilot_reasoning_trace@1' },
} as const;

describe('CopilotPage', () => {
  it('reproduz o estado vazio e envia uma sugestão com Enter', async () => {
    server.use(...sharedRequests(), http.post('*/api/v1/copilot/ask', () => HttpResponse.json(answer)));
    renderPage();
    expect(screen.getByRole('heading', { name: 'Copiloto do conhecimento' })).toBeInTheDocument();
    fireEvent.change(screen.getByRole('textbox', { name: 'Pergunte ao Copiloto' }), { target: { value: 'Como cancelar?' } });
    fireEvent.keyDown(screen.getByRole('textbox', { name: 'Pergunte ao Copiloto' }), { key: 'Enter' });
    expect(await screen.findByText(/confiança 92% · 1 fonte/)).toBeInTheDocument();
    expect(screen.getByText('Como cancelar?')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Cancelar Pedido de Compra' })).toHaveAttribute('href', '/processos/processo-1');
  });

  it('mostra fontes, trilha colapsável e encaminha ações a rotas reais', async () => {
    server.use(...sharedRequests(), http.post('*/api/v1/copilot/ask', () => HttpResponse.json(answer)));
    renderPage('/copiloto?q=Como%20cancelar%3F');
    fireEvent.submit(screen.getByRole('button', { name: 'Enviar pergunta' }).closest('form')!);
    expect(await screen.findByText('Fontes utilizadas')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /ProcessoCancelar Pedido/ })).toHaveAttribute('href', 'https://git.example/processos/cancelar.md');
    fireEvent.click(screen.getByRole('button', { name: '▸ Ver linha de raciocínio' }));
    expect(screen.getByText('1 evidência citável selecionada.')).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Aprofundar no processo' }));
    expect(screen.getByText('/processos/processo-1')).toBeInTheDocument();
  });
});
