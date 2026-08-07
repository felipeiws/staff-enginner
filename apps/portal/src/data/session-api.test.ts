import { http, HttpResponse } from 'msw';
import { setupServer } from 'msw/node';
import { afterAll, afterEach, beforeAll, describe, expect, it } from 'vitest';
import { getCsrfToken, login, LoginValidationError, logout } from './session-api';

const server = setupServer();
beforeAll(() => server.listen({ onUnhandledRequest: 'error' }));
afterEach(() => server.resetHandlers());
afterAll(() => server.close());

describe('session-api', () => {
  it('obtém o token CSRF', async () => {
    server.use(http.get('*/auth/csrf', () => HttpResponse.json({ csrf_token: 'token-123' })));
    await expect(getCsrfToken()).resolves.toBe('token-123');
  });

  it('lança erro quando o CSRF falha', async () => {
    server.use(http.get('*/auth/csrf', () => new HttpResponse(null, { status: 500 })));
    await expect(getCsrfToken()).rejects.toThrow('Não foi possível obter o token CSRF.');
  });

  it('loga com sucesso e envia o header X-CSRF-TOKEN', async () => {
    let receivedCsrfHeader: string | null = null;
    server.use(
      http.get('*/auth/csrf', () => HttpResponse.json({ csrf_token: 'token-123' })),
      http.post('*/auth/session', ({ request }) => {
        receivedCsrfHeader = request.headers.get('X-CSRF-TOKEN');
        return HttpResponse.json({
          principal: { id: 'u1', type: 'human', name: 'Ana', avatar_initials: 'AN' },
        });
      }),
    );
    const principal = await login('ana@atlas.dev', 'senha123');
    expect(principal).toEqual({ id: 'u1', type: 'human', name: 'Ana', avatar_initials: 'AN' });
    expect(receivedCsrfHeader).toBe('token-123');
  });

  it('lança LoginValidationError em 422 com os erros de validação', async () => {
    server.use(
      http.get('*/auth/csrf', () => HttpResponse.json({ csrf_token: 'token-123' })),
      http.post('*/auth/session', () =>
        HttpResponse.json(
          { message: 'Credenciais inválidas.', errors: { email: ['Credenciais inválidas.'] } },
          { status: 422 },
        ),
      ),
    );
    const error = await login('ana@atlas.dev', 'errada').catch((caught: unknown) => caught);
    expect(error).toBeInstanceOf(LoginValidationError);
    expect((error as LoginValidationError).message).toBe('Credenciais inválidas.');
    expect((error as LoginValidationError).fieldErrors).toEqual({
      email: ['Credenciais inválidas.'],
    });
  });

  it('encerra a sessão', async () => {
    server.use(http.delete('*/auth/session', () => new HttpResponse(null, { status: 204 })));
    await expect(logout()).resolves.toBeUndefined();
  });

  it('lança erro quando o logout falha', async () => {
    server.use(http.delete('*/auth/session', () => new HttpResponse(null, { status: 500 })));
    await expect(logout()).rejects.toThrow('Não foi possível encerrar a sessão.');
  });
});
