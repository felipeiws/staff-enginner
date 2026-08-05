import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import { http, HttpResponse } from 'msw';
import { setupServer } from 'msw/node';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { afterAll, afterEach, beforeAll, describe, expect, it } from 'vitest';
import { EndpointPage } from './endpoint-page';

const server = setupServer();
const provenance = { origin: 'repo://erp/routes/orders', revision: 'a91f3ce', method: 'code.analyzer@1', confidence: .94, processed_at: '2026-07-31T00:00:00Z' };
const endpoint = { id: 'delete-purchase-orders-id', type: 'Endpoint', title: 'DELETE /v2/purchase-orders/{id}', freshness: { label: 'sincronizado do repo há 4 min' }, provenance, projection: { method: 'DELETE', path: '/v2/purchase-orders/{id}', description: 'Cancela um pedido de compra. Idempotente. Publica purchase_order.canceled e delega a liberação de reserva ao Estoque.', parameters: [{ name: 'id', location_type: 'path · uuid', description: 'Identificador do pedido.' }, { name: 'motivo', location_type: 'body · enum', description: 'Motivo do cancelamento.' }, { name: 'forçar', location_type: 'query · bool', description: 'Tentativa com alçada.' }], responses: [{ code: '204', description: 'Pedido cancelado.' }, { code: '409', description: 'Faturamento parcial: aplica a regra RN-142.' }, { code: '403', description: 'A alçada acima de R$ 50k é exigida pela RN-088.' }], error_example: '409 Conflict\\n{ "code": "PARTIAL_INVOICE" }', production: { p95_ms: 184, error_rate: .4, latency_points: [101, 98, 110, 95, 120, 103, 115] }, consumers: [{ name: 'Portal de Compras', daily_volume: '8,4 mil/dia' }], rules: [{ code: 'RN-142', title: 'bloqueio pós-faturamento', path: '/regras/rn-142' }] } };
beforeAll(() => server.listen({ onUnhandledRequest: 'bypass' })); afterEach(() => server.resetHandlers()); afterAll(() => server.close());
function renderPage() { return render(<QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}><MemoryRouter initialEntries={['/apis/delete-purchase-orders-id']}><Routes><Route path="/apis/:id" element={<EndpointPage />} /></Routes></MemoryRouter></QueryClientProvider>); }

describe('EndpointPage', () => {
  it('reproduz o detalhe do endpoint com métricas e relações navegáveis', async () => {
    server.use(http.get('*/api/v1/entities/delete-purchase-orders-id', () => HttpResponse.json(endpoint))); renderPage();
    expect(await screen.findByText('/v2/purchase-orders/{id}')).toBeInTheDocument();
    expect(screen.getByText('path · uuid')).toBeInTheDocument();
    expect(screen.getByText('body · enum')).toBeInTheDocument();
    expect(screen.getByText('query · bool')).toBeInTheDocument();
    expect(screen.getByText('184')).toBeInTheDocument();
    expect(screen.getByText('0,4% erro')).toBeInTheDocument();
    expect(screen.getByRole('img', { name: 'Latência nos últimos 7 dias' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'RN-142 · bloqueio pós-faturamento' }).getAttribute('href')).toBe('/regras/rn-142');
  });

  it('não inventa métricas de produção quando a observabilidade não as retorna', async () => {
    server.use(http.get('*/api/v1/entities/delete-purchase-orders-id', () => HttpResponse.json({ ...endpoint, projection: { ...endpoint.projection, production: undefined, consumers: [] } }))); renderPage();
    expect(await screen.findByText('Métricas indisponíveis')).toBeInTheDocument();
    expect(screen.getByText('Consumidores indisponíveis')).toBeInTheDocument();
    expect(screen.queryByText('184')).not.toBeInTheDocument();
  });
});
