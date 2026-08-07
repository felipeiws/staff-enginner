import { describe, expect, it } from 'vitest';
import { routePaths, router } from '@/router';

describe('rotas do Portal', () => {
  it('mantém todos os endereços compartilháveis previstos no protótipo', () => {
    expect(routePaths).toEqual([
      '/',
      '/busca',
      '/copiloto',
      '/graph',
      '/aplicacoes/:id',
      '/processos/:id',
      '/regras/:id',
      '/telas/:id',
      '/apis/:id',
      '/aprovacoes',
      '/saude',
      '/decisoes-ux',
      '/login',
      '/onboarding',
    ]);
  });

  it('registra cada endereço uma única vez no router, mais o fallback de 404', () => {
    expect(router.routes.map((route) => route.path)).toEqual([...routePaths, '*']);
  });
});
