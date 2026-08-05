import { describe, expect, it } from 'vitest';
import { routePaths } from '@/router';

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
    ]);
  });
});
