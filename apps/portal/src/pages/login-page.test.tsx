import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { http, HttpResponse } from 'msw';
import { setupServer } from 'msw/node';
import { MemoryRouter, useLocation } from 'react-router-dom';
import { afterAll, afterEach, beforeAll, describe, expect, it } from 'vitest';
import { LoginPage } from './login-page';

const server = setupServer();
beforeAll(() => server.listen({ onUnhandledRequest: 'bypass' }));
afterEach(() => server.resetHandlers());
afterAll(() => server.close());

function LocationProbe() {
  const location = useLocation();
  return <output>{location.pathname}{location.search}</output>;
}

function renderPage(initialEntries: string[] = ['/login']) {
  return render(
    <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
      <MemoryRouter initialEntries={initialEntries}>
        <LoginPage />
        <LocationProbe />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

function mockAnonymous() {
  server.use(
    http.get('*/api/v1/me', () =>
      HttpResponse.json(
        { title: 'Não autenticado', status: 401 },
        { status: 401, headers: { 'Content-Type': 'application/problem+json' } },
      ),
    ),
  );
}

describe('LoginPage', () => {
  it('renderiza os dois painéis com todos os campos e sem elementos de SSO', async () => {
    mockAnonymous();
    renderPage();
    expect(await screen.findByLabelText('E-mail')).toBeInTheDocument();
    expect(screen.getByLabelText('Senha')).toBeInTheDocument();
    expect(screen.getByLabelText('Manter conectado')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Esqueci a senha' })).toHaveAttribute('href', '#');
    expect(screen.getByRole('button', { name: 'Entrar' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Configurar o workspace' })).toBeInTheDocument();
    expect(screen.getByText('148 mil')).toBeInTheDocument();
    expect(screen.getByText('4')).toBeInTheDocument();
    expect(screen.getByText('92%')).toBeInTheDocument();
    expect(screen.getAllByRole('button')).toHaveLength(2);
    expect(screen.queryByText('ou e-mail corporativo')).not.toBeInTheDocument();
  });

  it('submissão com sucesso navega para "/"', async () => {
    mockAnonymous();
    server.use(
      http.get('*/auth/csrf', () => HttpResponse.json({ csrf_token: 'token-123' })),
      http.post('*/auth/session', () =>
        HttpResponse.json({
          principal: { id: 'u1', type: 'human', name: 'Ana', avatar_initials: 'AN' },
        }),
      ),
    );
    renderPage();
    await screen.findByLabelText('E-mail');
    fireEvent.change(screen.getByLabelText('E-mail'), { target: { value: 'ana@atlas.dev' } });
    fireEvent.change(screen.getByLabelText('Senha'), { target: { value: 'senha123' } });
    fireEvent.click(screen.getByRole('button', { name: 'Entrar' }));
    await waitFor(() => expect(screen.getByText('/')).toBeInTheDocument());
  });

  it('submissão com sucesso navega para o valor de "next"', async () => {
    mockAnonymous();
    server.use(
      http.get('*/auth/csrf', () => HttpResponse.json({ csrf_token: 'token-123' })),
      http.post('*/auth/session', () =>
        HttpResponse.json({
          principal: { id: 'u1', type: 'human', name: 'Ana', avatar_initials: 'AN' },
        }),
      ),
    );
    renderPage(['/login?next=/aprovacoes']);
    await screen.findByLabelText('E-mail');
    fireEvent.change(screen.getByLabelText('E-mail'), { target: { value: 'ana@atlas.dev' } });
    fireEvent.change(screen.getByLabelText('Senha'), { target: { value: 'senha123' } });
    fireEvent.click(screen.getByRole('button', { name: 'Entrar' }));
    await waitFor(() => expect(screen.getByText('/aprovacoes')).toBeInTheDocument());
  });

  it('submissão com credenciais inválidas mostra erro acessível e não navega', async () => {
    mockAnonymous();
    server.use(
      http.get('*/auth/csrf', () => HttpResponse.json({ csrf_token: 'token-123' })),
      http.post('*/auth/session', () =>
        HttpResponse.json(
          { message: 'Credenciais inválidas.', errors: { email: ['E-mail ou senha incorretos.'] } },
          { status: 422 },
        ),
      ),
    );
    renderPage();
    await screen.findByLabelText('E-mail');
    fireEvent.change(screen.getByLabelText('E-mail'), { target: { value: 'ana@atlas.dev' } });
    fireEvent.change(screen.getByLabelText('Senha'), { target: { value: 'senha-errada' } });
    fireEvent.click(screen.getByRole('button', { name: 'Entrar' }));
    expect(await screen.findByRole('alert')).toHaveTextContent('Credenciais inválidas.');
    expect(screen.getByText('/login')).toBeInTheDocument();
  });

  it('usuário já autenticado é redirecionado sem precisar submeter', async () => {
    server.use(
      http.get('*/api/v1/me', () =>
        HttpResponse.json({
          principal: { id: 'u1', type: 'human', name: 'Ana', avatar_initials: 'AN' },
        }),
      ),
    );
    renderPage();
    await waitFor(() => expect(screen.getByText('/')).toBeInTheDocument());
  });
});
