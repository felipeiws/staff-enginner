import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { describe, expect, it } from 'vitest';
import { forceLayout } from './graph-layout';
import { GraphPage } from './graph-page';

describe('Knowledge Graph', () => {
  it('mantém o layout dirigido por força determinístico para a mesma semente', () => {
    const nodes = [
      {
        id: 'a',
        type: 'Processo',
        label: 'A',
        color: 'violet',
        weight: 4,
        provenance: { revision: 'r1', method: 'human', confidence: 1, processed_at: '2026-07-31' },
      },
      {
        id: 'b',
        type: 'Regra',
        label: 'B',
        color: 'warn',
        weight: 2,
        provenance: { revision: 'r1', method: 'human', confidence: 1, processed_at: '2026-07-31' },
      },
    ];
    const edges = [
      {
        origin: 'a',
        destination: 'b',
        predicate: 'related_to',
        weight: 1,
        provenance: nodes[0]!.provenance,
      },
    ];
    expect(forceLayout(nodes, edges, 'atlas')).toEqual(forceLayout(nodes, edges, 'atlas'));
  });

  it('reproduz o foco inicial, seleção e alternativa navegável por teclado', () => {
    render(
      <QueryClientProvider
        client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
      >
        <MemoryRouter>
          <GraphPage />
        </MemoryRouter>
      </QueryClientProvider>,
    );
    expect(screen.getByText('foco: Cancelar Pedido de Compra')).toBeInTheDocument();
    expect(screen.getByText('Diagnóstico do cluster')).toBeInTheDocument();
    const target = screen.getByRole('button', { name: /Selecionar RN-142/ });
    fireEvent.keyDown(target, { key: 'Enter' });
    expect(screen.getAllByText('RN-142').length).toBeGreaterThan(1);
    expect(screen.getByRole('button', { name: 'expandir +2 níveis' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Aumentar zoom' })).toBeInTheDocument();
    expect(screen.getByText(/alternativa textual/)).toBeInTheDocument();
  });
});
