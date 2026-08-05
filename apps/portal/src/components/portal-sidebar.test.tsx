import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen } from '@testing-library/react';
import { http, HttpResponse } from 'msw';
import { setupServer } from 'msw/node';
import { afterAll, afterEach, beforeAll, describe, expect, it } from 'vitest';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { PortalSidebar } from './portal-sidebar';

const server = setupServer();
beforeAll(() => server.listen({ onUnhandledRequest: 'error' }));
afterEach(() => server.resetHandlers());
afterAll(() => server.close());

function renderSidebar(path = '/') {
  return render(
    <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
      <MemoryRouter initialEntries={[path]}>
        <Routes>
          <Route path="/aplicacoes/:id" element={<PortalSidebar />} />
          <Route path="*" element={<PortalSidebar />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('PortalSidebar', () => {
  it('reproduz os grupos de navegação e usa a API para aprovações e indexação', async () => {
    server.use(
      http.get('*/api/v1/organizations/nordika', () => HttpResponse.json({
        id: 'nordika', name: 'Nordika', indexed_entities_count: 10, applications_count: 1,
        provenance: { revision: 'r1', method: 'CI', confidence: 1, processed_at: new Date().toISOString() },
      })),
      http.get('*/api/v1/approvals', () => HttpResponse.json({ approvals: [{ id: 'a1' }, { id: 'a2' }] })),
    );
    renderSidebar();
    expect(screen.getByRole('navigation', { name: 'Seções do Atlas' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /copiloto/i })).toHaveTextContent('IA');
    expect(await screen.findByText('2')).toBeInTheDocument();
    expect(await screen.findByText(/CI indexou há menos de 1 min/)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Abrir paleta de comandos' })).toHaveTextContent('⌘K');
  });

  it('mostra contagens da árvore da aplicação em escopo', async () => {
    server.use(
      http.get('*/api/v1/organizations/nordika', () => HttpResponse.json({ provenance: { revision: 'r1', method: 'CI', confidence: 1, processed_at: new Date().toISOString() } })),
      http.get('*/api/v1/approvals', () => HttpResponse.json({ approvals: [] })),
      http.get('*/api/v1/applications/erp', () => HttpResponse.json({ id: 'erp', name: 'ERP', screens_count: 4 })),
      http.get('*/api/v1/applications/erp/tree', ({ request }) => {
        const nav = new URL(request.url).searchParams.get('nav');
        return HttpResponse.json(nav === 'functional'
          ? { nav, groups: [{ key: 'processes', count: 3 }, { key: 'business_rules', count: 6 }, { key: 'screens', count: 4 }] }
          : { nav, groups: [{ key: 'apis', count: 8 }] });
      }),
    );
    renderSidebar('/aplicacoes/erp');
    expect(await screen.findByRole('heading', { name: 'ERP' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /processos/i })).toHaveTextContent('3');
    expect(screen.getByRole('link', { name: /^APIs/i })).toHaveTextContent('8');
  });

  it('abre e fecha a paleta com Ctrl/Cmd+K e Esc', () => {
    server.use(
      http.get('*/api/v1/organizations/nordika', () => HttpResponse.json({ provenance: { revision: 'r1', method: 'CI', confidence: 1, processed_at: new Date().toISOString() } })),
      http.get('*/api/v1/approvals', () => HttpResponse.json({ approvals: [] })),
    );
    renderSidebar();
    fireEvent.keyDown(window, { key: 'k', ctrlKey: true });
    expect(screen.getByRole('dialog', { name: 'Paleta de comandos' })).toBeInTheDocument();
    fireEvent.keyDown(window, { key: 'Escape' });
    expect(screen.queryByRole('dialog', { name: 'Paleta de comandos' })).not.toBeInTheDocument();
  });
});
