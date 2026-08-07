import type { Principal } from './types';

export interface LoginError {
  message: string;
  errors?: Record<string, string[]>;
}

export class LoginValidationError extends Error {
  public constructor(
    public readonly fieldErrors: Record<string, string[]>,
    message: string,
  ) {
    super(message);
    this.name = 'LoginValidationError';
  }
}

/** `/auth/*` fica fora de `/api/v1`, então não passa pelo AtlasHttpClient. */
export async function getCsrfToken(): Promise<string> {
  const response = await fetch('/auth/csrf');
  if (!response.ok) throw new Error('Não foi possível obter o token CSRF.');
  const data = (await response.json()) as { csrf_token: string };
  return data.csrf_token;
}

export async function login(email: string, password: string): Promise<Principal> {
  const csrfToken = await getCsrfToken();
  const response = await fetch('/auth/session', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-CSRF-TOKEN': csrfToken,
    },
    body: JSON.stringify({ email, password }),
  });
  if (!response.ok) {
    const problem = (await response.json()) as LoginError;
    throw new LoginValidationError(problem.errors ?? {}, problem.message);
  }
  const data = (await response.json()) as { principal: Principal };
  return data.principal;
}

export async function logout(): Promise<void> {
  const response = await fetch('/auth/session', { method: 'DELETE' });
  if (!response.ok) throw new Error('Não foi possível encerrar a sessão.');
}
