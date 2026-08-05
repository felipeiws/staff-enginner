import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { RouteErrorPage } from '@/pages/route-error-page';

describe('RouteErrorPage', () => {
  it('exibe o estado 404 e um caminho de retorno', () => {
    render(
      <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
        <MemoryRouter>
          <RouteErrorPage notFound />
        </MemoryRouter>
      </QueryClientProvider>,
    );
    expect(
      screen.getByRole('heading', { name: 'Esta página não foi encontrada' }),
    ).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /voltar ao início/i })).toHaveAttribute('href', '/');
  });
});
