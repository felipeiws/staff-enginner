import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen } from '@testing-library/react';
import { http, HttpResponse } from 'msw';
import { setupServer } from 'msw/node';
import { MemoryRouter } from 'react-router-dom';
import { afterAll, afterEach, beforeAll, describe, expect, it } from 'vitest';
import { ApprovalsPage } from './approvals-page';

const server = setupServer();
const provenance = { revision: 'a91f3ce', method: 'inference@gpt-test', confidence: .94, processed_at: '2026-07-31T00:00:00Z' };
const summary = { id: 'approval-1', title: 'Cancelar Pedido de Compra · etapa 4', entity_type: 'Processo', entity_type_color: 'violet', author: { id: 'worker:ci', type: 'worker' }, origin: { revision: 'a91f3ce', detected_by: 'CI' }, age: 'há 2h', provenance };
const detail = { ...summary, analysis: { text: 'O código passou a lançar ConflictException para faturamento parcial.', confidence: .94 }, diff: { current: 'Pedidos com nota fiscal emitida não podem ser cancelados.', proposed: 'Pedidos com nota fiscal emitida não podem ser cancelados integralmente.' }, impact: { level: 'alto', entities: ['RN-142', 'FAQ-231'] } };

beforeAll(() => server.listen({ onUnhandledRequest: 'bypass' }));
afterEach(() => server.resetHandlers());
afterAll(() => server.close());
function renderPage() { return render(<QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}><MemoryRouter><ApprovalsPage /></MemoryRouter></QueryClientProvider>); }
function api() { server.use(http.get('*/api/v1/approvals', () => HttpResponse.json({ approvals: [summary], low_impact_auto_approval: { count: 3, hours: 48 } })), http.get('*/api/v1/approvals/approval-1', () => HttpResponse.json(detail))); }

describe('ApprovalsPage', () => {
  it('reproduz a revisão com diff editável e bloqueia aprovação sem justificativa', async () => {
    api(); renderPage();
    expect(await screen.findByText('Análise da IA · confiança 0,94')).toBeInTheDocument();
    expect(screen.getByText('+3 propostas de baixo impacto · aprovação automática em 48h')).toBeInTheDocument();
    const proposed = screen.getByRole('textbox', { name: 'Texto proposto editável' });
    fireEvent.change(proposed, { target: { value: 'Texto revisado pelo domínio.' } });
    expect(proposed).toHaveValue('Texto revisado pelo domínio.');
    fireEvent.click(screen.getByRole('button', { name: 'Aprovar e abrir PR' }));
    expect(screen.getByRole('alert')).toHaveTextContent('Informe a justificativa antes de decidir');
  });

  it('aprova somente após justificativa e mostra o estado de PR aberto', async () => {
    api(); server.use(http.post('*/api/v1/approvals/approval-1/approve', () => HttpResponse.json({ approval_id: 'approval-1', decision: 'approved', pull_request: { reference: 'PR #4127' }, commit: 'docs(compras): alinha etapa 4 ao 409 (RN-142)', reindexing: { status: 'scheduled', target: '40s' } }, { status: 201 }))); renderPage();
    await screen.findByText('Análise da IA · confiança 0,94');
    fireEvent.change(screen.getByRole('textbox', { name: /Justificativa/ }), { target: { value: 'Confirmado pelo domínio.' } });
    fireEvent.click(screen.getByRole('button', { name: 'Aprovar e abrir PR' }));
    expect(await screen.findByText('Aprovado · PR #4127 aberto em erp-financeiro/knowledge')).toBeInTheDocument();
    expect(screen.getByText(/reindexação em 40s/)).toBeInTheDocument();
  });
});
