import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen } from '@testing-library/react';
import { http, HttpResponse } from 'msw';
import { setupServer } from 'msw/node';
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom';
import { afterAll, afterEach, beforeAll, describe, expect, it } from 'vitest';
import { RulePage } from './rule-page';

const server = setupServer();
const provenance = { origin: 'atlas-demo://regra/rn-142', revision: 'a91f3ce', method: 'parser@1', confidence: .92, processed_at: '2026-07-31T00:00:00Z' };
const rule = { id: 'rn-142', type: 'Regra', title: 'Regra de cancelamento de pedido', provenance, projection: { code: 'RN-142', statement: 'Pedidos de compra com nota fiscal emitida não podem ser cancelados integralmente.', validity: '12/03/2026 — indeterminada', approved_by: 'M. Reis · Fiscal', change_reason: 'Auditoria apontou lacuna em faturamento parcial', impact: '3 processos · 2 telas · 1 endpoint', versions: [{ version: 4, date: '12/03/2026', author: 'M. Reis', status: 'vigente', old: ['− Texto da v3'], current: ['+ Texto da v4'] }, { version: 3, date: '08/11/2025', author: 'R. Souza', status: 'substituída', old: ['− Texto da v2'], current: ['+ Texto da v3'] }], code_implementation: 'PurchaseOrderCancelService.java:214 · a91f3ce', affects: [{ type: 'Processo', label: 'Cancelar Pedido de Compra', path: '/processos/cancelar-pedido-compra' }], pinned_comment: 'Comentário de suporte.', comment_author: 'L. Duarte', comment_time: 'há 7h' } };

beforeAll(() => server.listen({ onUnhandledRequest: 'bypass' }));
afterEach(() => server.resetHandlers());
afterAll(() => server.close());

function Location() { const location = useLocation(); return <output data-testid="location">{location.search}</output>; }
function renderPage() { return render(<QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}><MemoryRouter initialEntries={['/regras/rn-142']}><Routes><Route path="/regras/:id" element={<><RulePage /><Location /></>} /></Routes></MemoryRouter></QueryClientProvider>); }

describe('RulePage', () => {
  it('reproduz a regra publicada e troca o diff pela URL', async () => {
    server.use(http.get('*/api/v1/entities/rn-142', () => HttpResponse.json(rule)));
    renderPage();
    expect(await screen.findByText('Regra de negócio')).toBeInTheDocument();
    expect(screen.getByText('Enunciado')).toBeInTheDocument();
    expect(screen.getByText('Código que implementa a regra')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /Processo\s*·\s*Cancelar Pedido de Compra/ }).getAttribute('href')).toBe('/processos/cancelar-pedido-compra');
    fireEvent.click(screen.getByRole('button', { name: /v3\s*08\/11\/2025\s*·\s*R\. Souza\s*substituída/i }));
    expect(screen.getByText('Diferenças · v2 → v3')).toBeInTheDocument();
    expect(screen.getByText('+ Texto da v3')).toBeInTheDocument();
    expect(screen.getByTestId('location')).toHaveTextContent('?versao=3');
  });
});
