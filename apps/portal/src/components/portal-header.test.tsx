import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen } from '@testing-library/react';
import { http, HttpResponse } from 'msw';
import { setupServer } from 'msw/node';
import { afterAll, afterEach, beforeAll, describe, expect, it } from 'vitest';
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom';
import { breadcrumbForPath, classifyUnifiedSearch, PortalHeader } from './portal-header';

const server = setupServer();
beforeAll(() => server.listen({ onUnhandledRequest: 'error' }));
afterEach(() => server.resetHandlers());
afterAll(() => server.close());

function Location() {
  return (
    <output aria-label="Localização">
      {useLocation().pathname}
      {useLocation().search}
    </output>
  );
}

function renderHeader(path = '/') {
  return render(
    <QueryClientProvider
      client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
    >
      <MemoryRouter initialEntries={[path]}>
        <PortalHeader />
        <Routes>
          <Route path="*" element={<Location />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('PortalHeader', () => {
  it('classifica termos e perguntas no destino apropriado', () => {
    expect(classifyUnifiedSearch('RN-142')).toBe('search');
    expect(classifyUnifiedSearch('DELETE /v2/purchase-orders')).toBe('search');
    expect(classifyUnifiedSearch('Como cancelar um pedido faturado?')).toBe('copilot');
    expect(classifyUnifiedSearch('cancelamento de pedido')).toBe('search');
    expect(classifyUnifiedSearch('Explique o impacto do cancelamento de pedido no estoque')).toBe(
      'copilot',
    );
  });

  it('reproduz o breadcrumb de cada rota do protótipo', () => {
    expect(breadcrumbForPath('/', '')).toBe('Organização Nordika');
    expect(breadcrumbForPath('/busca', 'RN-142')).toBe('Busca › "RN-142"');
    expect(breadcrumbForPath('/copiloto', '')).toBe('Copiloto · escopo ERP Financeiro');
    expect(breadcrumbForPath('/processos/pc-42', '')).toBe('ERP Financeiro › Compras › Processo');
    expect(breadcrumbForPath('/aprovacoes', '')).toBe('Governança › Aprovações');
  });

  it('navega pela heurística, permite forçar o Copiloto e mostra pendências', async () => {
    server.use(
      http.get('*/api/v1/approvals', () =>
        HttpResponse.json({ approvals: [{ id: 'a1' }, { id: 'a2' }] }),
      ),
    );
    renderHeader();
    const input = screen.getByRole('textbox', { name: 'Busque ou pergunte qualquer coisa' });
    fireEvent.change(input, { target: { value: 'RN-142' } });
    fireEvent.submit(input.closest('form')!);
    expect(screen.getByRole('status', { name: 'Localização' })).toHaveTextContent(
      '/busca?q=RN-142',
    );
    fireEvent.change(input, { target: { value: 'cancelamento de pedido' } });
    fireEvent.click(screen.getByRole('button', { name: /perguntar/i }));
    expect(screen.getByRole('status', { name: 'Localização' })).toHaveTextContent(
      '/copiloto?q=cancelamento%20de%20pedido',
    );
    expect(await screen.findByRole('button', { name: '2 pendentes' })).toBeInTheDocument();
  });

  it('oferece menu de navegação e salto para o conteúdo principal', () => {
    server.use(http.get('*/api/v1/approvals', () => HttpResponse.json({ approvals: [] })));
    const { container } = renderHeader();
    expect(container.querySelector('.portal-header-menu')).toHaveAttribute(
      'aria-controls',
      'portal-sidebar',
    );
    expect(screen.getByRole('link', { name: 'Pular para o conteúdo' })).toHaveAttribute(
      'href',
      '#conteudo-principal',
    );
  });
});
