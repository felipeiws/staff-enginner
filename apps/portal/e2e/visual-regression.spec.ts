import { entityId, expect, test } from './fixtures';

const themes = ['dark', 'light'] as const;

test.describe('regressão visual das doze rotas', () => {
  for (const theme of themes) {
    test(`captura as doze rotas em tema ${theme}`, async ({ page }) => {
      const processId = await entityId(page, 'Cancelar Pedido de Compra', 'Processo');
      const ruleId = await entityId(page, 'RN-142', 'Regra');
      const screenId = await entityId(page, 'PC-0421', 'Tela');
      const endpointId = await entityId(page, 'DELETE /v2/purchase-orders', 'Endpoint');
      const applicationId = await entityId(page, 'ERP Financeiro', 'Aplicação');
      const organizationId = await entityId(page, 'Nordika', 'Organização');
      const routes = [
        ['home', '/'], ['busca', '/busca?q=cancelamento'], ['copiloto', '/copiloto'], ['graph', `/graph?entity=${processId}`],
        ['aplicacao', `/aplicacoes/${applicationId}`], ['processo', `/processos/${processId}`], ['regra', `/regras/${ruleId}`], ['tela', `/telas/${screenId}`],
        ['endpoint', `/apis/${endpointId}`], ['aprovacoes', '/aprovacoes'], ['saude', `/saude?scope=${organizationId}&organization=nordika`], ['decisoes-ux', '/decisoes-ux'],
      ] as const;
      await page.addInitScript((selectedTheme) => {
        document.documentElement.dataset.theme = selectedTheme;
        localStorage.setItem('atlas.theme', selectedTheme);
      }, theme);
      for (const [name, route] of routes) {
        await page.goto('/');
        if (route !== '/') {
          await page.evaluate((destination) => {
            window.history.pushState({}, '', destination);
            window.dispatchEvent(new PopStateEvent('popstate'));
          }, route);
        }
        await page.waitForLoadState('networkidle');
        await expect(page.locator('main')).toBeVisible();
        await expect(page).toHaveScreenshot(`${theme}-${name}.png`, {
          fullPage: true,
          animations: 'disabled',
          // A idade da atividade é calculada no cliente; o conteúdo do card
          // continua coberto, mas a contagem relativa não torna o pixel instável.
          mask: [page.locator('.home-activity small')],
        });
      }
    });
  }
});
