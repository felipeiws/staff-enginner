import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen } from '@testing-library/react';
import { http, HttpResponse } from 'msw';
import { setupServer } from 'msw/node';
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom';
import { afterAll, afterEach, beforeAll, describe, expect, it } from 'vitest';
import { ProcessPage } from './process-page';

const server = setupServer();
const provenance = { origin: 'atlas-demo://processo', revision: 'a91f3ce', method: 'parser@1', confidence: .92, processed_at: '2026-07-31T00:00:00Z' };
const process = { id: 'cancelar-pedido-compra', type: 'Processo', title: 'Cancelar Pedido de Compra', summary: 'Resumo publicado.', provenance, freshness: { label: 'revisado há 3 dias' }, owners: [{ id: 'Time Suprimentos', responsibility: 'dono' }], projection: { kind: 'processo', summary: 'Resumo publicado.', rules: [{ id: 'rn-142', code: 'RN-142', title: 'Faturamento parcial impede cancelamento integral.', status: 'ativo' }], screens: [{ id: 'pc-0421', name: 'PC-0421 · Detalhe do Pedido', status: 'print há 5 meses' }], flow: { steps: [{ name: 'Solicitação', description: 'PC-0421' }], deviations: [], known_failures: [] }, technical: { endpoints: ['DELETE /v2/purchase-orders/{id}'], services: [], tables: [], events: [] }, relevant_code: 'PurchaseOrderCancelService.java:214', signals: { open_issues: 2, commits_30d: 17, support_tickets: 41, ai_confidence: .92 }, divergences: [{ description: 'Retorno 409 não documentado.', approval_id: 'processo-atualizacao' }], faqs: [{ id: 'faq-231', title: 'Por que o botão Cancelar está desabilitado?' }], history: [{ occurred_at: '31 jul 2026', action: 'Processo revisado.', actor: { id: 'M. Reis', type: 'human' } }] } };

beforeAll(() => server.listen({ onUnhandledRequest: 'bypass' }));
afterEach(() => server.resetHandlers());
afterAll(() => server.close());

function Location() { const location = useLocation(); return <output data-testid="location">{location.search}</output>; }
function renderPage() { return render(<QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}><MemoryRouter initialEntries={['/processos/cancelar-pedido-compra']}><Routes><Route path="/processos/:id" element={<><ProcessPage /><Location /></>} /></Routes></MemoryRouter></QueryClientProvider>); }

describe('ProcessPage', () => {
  it('reproduz o resumo e troca a aba pela URL compartilhável', async () => {
    server.use(
      http.get('*/api/v1/entities/cancelar-pedido-compra', () => HttpResponse.json(process)),
      http.get('*/api/v1/organizations/nordika', () => HttpResponse.json({ id: 'nordika', name: 'Nordika', indexed_entities_count: 1, applications_count: 1, provenance })),
      http.get('*/api/v1/approvals', () => HttpResponse.json({ approvals: [] })),
    );
    renderPage();
    expect(await screen.findByRole('heading', { name: 'Cancelar Pedido de Compra', level: 1 })).toBeInTheDocument();
    expect(screen.getByText('Regras aplicadas')).toBeInTheDocument();
    expect(screen.getByText('Divergência detectada')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Revisar proposta da IA →' }).getAttribute('href')).toBe('/aprovacoes?proposta=processo-atualizacao');
    fireEvent.click(screen.getByRole('button', { name: 'Fluxo' }));
    expect(await screen.findByText('ETAPA 1')).toBeInTheDocument();
    expect(screen.getByTestId('location')).toHaveTextContent('?aba=fluxo');
  });
});
