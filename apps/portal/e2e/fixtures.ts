import { expect, test as base } from '@playwright/test';

const demoCredentials = { email: 'demo@nordika.test', password: 'atlas-demo-password' };

export const test = base.extend({
  page: async ({ page }, use) => {
    const csrf = await page.request.get('/auth/csrf');
    expect(csrf.ok(), `não foi possível iniciar a sessão CSRF: ${csrf.status()}`).toBeTruthy();
    const { csrf_token: csrfToken } = await csrf.json();
    const login = await page.request.post('/auth/session', {
      data: demoCredentials,
      headers: { 'X-CSRF-TOKEN': csrfToken },
    });
    expect(login.ok(), `não foi possível criar a sessão de demonstração: ${login.status()}`).toBeTruthy();
    await use(page);
  },
});

export { expect };

export async function entityId(page: import('@playwright/test').Page, term: string, type: string) {
  const response = await page.request.get(`/api/v1/search?q=${encodeURIComponent(term)}&organization=nordika&page_size=100`);
  expect(response.ok(), `a busca de ${term} deve usar o backend real`).toBeTruthy();
  const result = (await response.json()).results.find((item: { type: { name: string } }) => item.type.name === type);
  expect(result, `entidade ${type} para ${term} não encontrada no seed`).toBeTruthy();
  return result.entity_id ?? result.id;
}

/** Espera a leitura autorizada do Graph, evitando selecionar o grafo demonstrativo transitório. */
export async function graphReady(page: import('@playwright/test').Page) {
  await page.waitForResponse(
    (response) => response.url().includes('/api/v1/graph/neighborhood') && response.status() === 200,
  );
}
