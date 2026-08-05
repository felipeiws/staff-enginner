import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom';
import { CommandPalette } from './command-palette';

function Location() { return <output aria-label="Localização">{useLocation().pathname}{useLocation().search}</output>; }
function renderPalette(onClose = () => undefined) {
  return render(<QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}><MemoryRouter><CommandPalette open onClose={onClose} /><Routes><Route path="*" element={<Location />} /></Routes></MemoryRouter></QueryClientProvider>);
}
describe('CommandPalette', () => {
  it('reproduz a entrada, ações e executa o item focado por teclado', () => {
    renderPalette();
    const input = screen.getByRole('textbox', { name: 'Ir para entidade, ação ou painel' });
    expect(input).toHaveAttribute('placeholder', 'Ir para entidade, ação ou painel…');
    expect(screen.getByText('Digite uma pergunta para enviar ao Copiloto · ⌘K abre e fecha')).toBeInTheDocument();
    fireEvent.keyDown(input, { key: 'ArrowDown' });
    fireEvent.keyDown(input, { key: 'Enter' });
    expect(screen.getByRole('status', { name: 'Localização' })).toHaveTextContent('/graph');
  });
  it('fecha ao clicar no overlay', () => {
    const onClose = vi.fn();
    renderPalette(onClose);
    fireEvent.mouseDown(screen.getByRole('presentation'));
    expect(onClose).toHaveBeenCalledOnce();
  });
});
