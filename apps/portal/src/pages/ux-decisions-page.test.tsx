import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import { http, HttpResponse } from 'msw';
import { setupServer } from 'msw/node';
import { MemoryRouter } from 'react-router-dom';
import { afterAll, afterEach, beforeAll, describe, expect, it } from 'vitest';
import { UxDecisionsPage } from './ux-decisions-page';

const server = setupServer();
const provenance = { origin: 'docs/design/prototype/atlas-prototype.html#L1397-L1404', revision: 'atlas-prototype:uxNotes:1397-1404', method: 'human_declared@1.0.0', confidence: 1, processed_at: '2026-07-31T00:00:00Z' };
const decision = { id: 'ux-1', type: 'Documento', title: 'Busca e IA são o mesmo campo', description: 'Um único input decide: consulta curta abre resultados facetados, pergunta em linguagem natural abre o Copiloto.', outcome: 'Elimina 1 decisão e 1 clique em 100% das sessões.', provenance };

beforeAll(() => server.listen({ onUnhandledRequest: 'bypass' }));
afterEach(() => server.resetHandlers());
afterAll(() => server.close());

describe('UxDecisionsPage', () => {
  it('renderiza as decisões publicadas retornadas pela API, sem conteúdo local', async () => {
    server.use(http.get('*/api/v1/ux-decisions', () => HttpResponse.json({ decisions: [decision], provenance })));
    render(<QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}><MemoryRouter><UxDecisionsPage /></MemoryRouter></QueryClientProvider>);
    expect(await screen.findByRole('heading', { name: decision.title })).toBeInTheDocument();
    expect(screen.getByText(decision.description)).toBeInTheDocument();
    expect(screen.getByText(`→ ${decision.outcome}`)).toBeInTheDocument();
    expect(screen.getByText(/Cada decisão elimina etapas em vez de adicionar telas/)).toBeInTheDocument();
  });
});
