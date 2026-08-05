import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { http, HttpResponse } from 'msw';
import { setupServer } from 'msw/node';
import { MemoryRouter, useLocation } from 'react-router-dom';
import { afterAll, afterEach, beforeAll, describe, expect, it } from 'vitest';
import { KnowledgeHealthPage } from './knowledge-health-page';

const server = setupServer();
const provenance = { revision: 'health-r1', method: 'knowledge_health_dashboard_v1', confidence: .86, processed_at: '2026-07-31T00:00:00Z' };
const scope = '5e5b2d33-7b16-4d04-8e4b-7d7a5ddbc819';
const series = (values: number[]) => values.map((value, index) => ({ at: `2026-07-${String(index + 1).padStart(2, '0')}T00:00:00Z`, value }));
const dashboard = { scope_id: scope, period: '30d', provenance,
  metrics: [
    { key: 'coverage', label: 'Cobertura documental', value: 74, unit: '%', variation: 4, trend_color: 'var(--acc)', series: series([62,63,65,66,68,69,70,72,74]), provenance },
    { key: 'ai_confidence', label: 'Índice de confiança da IA', value: .86, unit: 'decimal', variation: .04, trend_color: 'var(--acc)', series: series([.82,.83,.84,.85,.86,.86,.86,.86,.86]), provenance },
    { key: 'unanswered_questions', label: 'Perguntas sem resposta', value: 61, unit: 'count', variation: -7, trend_color: 'var(--acc)', series: series([68,68,67,66,65,64,63,62,61]), provenance },
    { key: 'approval_time_hours', label: 'Tempo médio de aprovação', value: 18.4, unit: 'hours', variation: -2.1, trend_color: 'var(--acc)', series: series([20.5,20,19.8,19.4,19.1,18.9,18.7,18.5,18.4]), provenance },
  ],
  knowledge_debt: [
    { label: 'Documentações desatualizadas', value: 18, reference_total: 82, percentage: 22, color: 'var(--warn)', provenance },
    { label: 'Regras sem responsável', value: 3, reference_total: 24, percentage: 13, color: 'var(--danger)', provenance },
    { label: 'Telas sem print', value: 7, reference_total: 42, percentage: 17, color: 'var(--warn)', provenance },
    { label: 'Documentos nunca acessados', value: 12, reference_total: 82, percentage: 15, color: 'var(--tx3)', provenance },
  ],
  unanswered_questions: [{ id: 'gap-1', question: 'Como estornar o frete de um pedido cancelado?', occurrence_count: 31, period: { started_at: '2026-07-01T00:00:00Z', ended_at: '2026-07-30T00:00:00Z', days: 30 }, color: 'var(--danger)', provenance }],
  period_insight: { low_coverage_threshold: 50, support_tickets_in_low_coverage_modules: 61, support_tickets_total: 100, support_ticket_share: 61, text: 'Módulos com cobertura abaixo de 50% concentram 61% dos tickets de suporte.', provenance },
};
beforeAll(() => server.listen({ onUnhandledRequest: 'bypass' }));
afterEach(() => server.resetHandlers());
afterAll(() => server.close());
function LocationProbe() { const location = useLocation(); return <output>{location.search}</output>; }
function renderPage(initialEntry = `/saude?scope=${scope}&organization=nordika&application=erp-financeiro`) { return render(<QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}><MemoryRouter initialEntries={[initialEntry]}><KnowledgeHealthPage /><LocationProbe /></MemoryRouter></QueryClientProvider>); }
function api() { server.use(http.get('*/api/v1/approvals', () => HttpResponse.json({ approvals: [] })), http.get('*/api/v1/knowledge-health', () => HttpResponse.json(dashboard))); }

describe('KnowledgeHealthPage', () => {
  it('reproduz o dashboard com métricas, dívida, lacunas e sparklines', async () => {
    api(); renderPage();
    expect(await screen.findByText('Cobertura documental')).toBeInTheDocument();
    expect(screen.getByText('74%')).toBeInTheDocument();
    expect(screen.getByText('Documentações desatualizadas')).toBeInTheDocument();
    expect(screen.getByText('Como estornar o frete de um pedido cancelado?')).toBeInTheDocument();
    expect(screen.getByRole('img', { name: 'Tendência de Cobertura documental' })).toBeInTheDocument();
    expect(screen.getByText('61% dos tickets de suporte', { exact: false })).toBeInTheDocument();
  });
  it('compartilha o escopo na URL e atribui uma lacuna com tarefa documentada', async () => {
    api(); let assignmentBody = '';
    server.use(http.post('*/api/v1/knowledge-gaps/gap-1/assign', async ({ request }) => { assignmentBody = await request.text(); return HttpResponse.json({ gap_id: 'gap-1', owner: { id: 'ana', type: 'human' }, documentation_task: { id: 'task-17', status: 'open', provenance: { method: 'human_assignment_v1', confidence: 1 } } }, { status: 201 }); }));
    renderPage(); await screen.findByText('Cobertura documental');
    fireEvent.change(screen.getByLabelText('Escopo da saúde'), { target: { value: 'c6f0c221-09fd-4e66-9af0-3cce5f5f1433' } });
    fireEvent.blur(screen.getByLabelText('Escopo da saúde'));
    await waitFor(() => expect(screen.getByText(/scope=c6f0c221/)).toBeInTheDocument());
    fireEvent.click(await screen.findByRole('button', { name: 'atribuir' }));
    fireEvent.change(screen.getByLabelText('Responsável'), { target: { value: 'ana' } });
    fireEvent.click(screen.getByRole('button', { name: 'atribuir e gerar tarefa' }));
    expect(await screen.findByText('Tarefa de documentação task-17 gerada para a lacuna.')).toBeInTheDocument();
    expect(assignmentBody).toContain('"owner_id":"ana"');
    expect(assignmentBody).toContain('"application":"erp-financeiro"');
  });
});
