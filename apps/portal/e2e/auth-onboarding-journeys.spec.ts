import { expect, test } from '@playwright/test';
import { demoCredentials } from './fixtures';

test.describe('login', () => {
  test('renderiza formulário completo, sem nenhum provedor de SSO', async ({ page }) => {
    await page.goto('/login');
    await expect(page.getByLabel('E-mail')).toBeVisible();
    await expect(page.getByLabel('Senha')).toBeVisible();
    await expect(page.getByRole('checkbox', { name: 'Manter conectado' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Esqueci a senha' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Entrar' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Configurar o workspace' })).toBeVisible();
    await expect(page.getByText('148 mil')).toBeVisible();
    await expect(page.getByText('4', { exact: true })).toBeVisible();
    await expect(page.getByText('92%')).toBeVisible();
    for (const provider of ['Google', 'Microsoft', 'GitHub', 'Entrar com']) {
      await expect(page.getByText(provider, { exact: false })).toHaveCount(0);
    }
    await expect(page.getByText(/\bSSO\b/)).toHaveCount(0);
  });

  test('credenciais inválidas mostram alerta e permanecem em /login', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('E-mail').fill('alguem@nordika.test');
    await page.getByLabel('Senha').fill('senha-errada');
    await page.getByRole('button', { name: 'Entrar' }).click();
    await expect(page.getByRole('alert')).toBeVisible();
    await expect(page).toHaveURL(/\/login/);
  });

  test('credenciais válidas navegam para a home autenticada', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('E-mail').fill(demoCredentials.email);
    await page.getByLabel('Senha').fill(demoCredentials.password);
    await page.getByRole('button', { name: 'Entrar' }).click();
    await expect(page).toHaveURL('/');
    await expect(page.getByRole('heading', { name: 'Pergunte qualquer coisa sobre suas aplicações' })).toBeVisible();
  });
});

test.describe('guard de rota protegida', () => {
  test('redireciona rota protegida sem sessão e volta pra ela após login', async ({ page }) => {
    await page.goto('/aprovacoes');
    await expect(page).toHaveURL(/\/login\?next=/);
    await page.getByLabel('E-mail').fill(demoCredentials.email);
    await page.getByLabel('Senha').fill(demoCredentials.password);
    await page.getByRole('button', { name: 'Entrar' }).click();
    await expect(page).toHaveURL('/aprovacoes');
    await expect(page.getByRole('heading', { name: 'Aprovações' })).toBeVisible();
  });
});

test.describe('onboarding', () => {
  test('percorre os 4 passos e todos os botões do wizard', async ({ page }) => {
    await page.goto('/onboarding');
    await expect(page.getByRole('heading', { name: 'Conecte as fontes de verdade' })).toBeVisible();

    const gitSource = page.getByRole('button', { name: /Repositório Git/ });
    const openapiSource = page.getByRole('button', { name: /Contrato de API \(OpenAPI\)/ });
    await gitSource.click();
    await expect(gitSource).toContainText('conectado');
    await openapiSource.click();
    await expect(openapiSource).toContainText('conectado');

    await page.getByRole('button', { name: 'Continuar' }).click();
    await expect(page.getByRole('heading', { name: 'Escolha o escopo inicial' })).toBeVisible();
    const crmScope = page.getByRole('button', { name: /CRM de Vendas/ });
    await crmScope.click();
    await expect(crmScope).toContainText('✓');

    await page.getByRole('button', { name: 'Continuar' }).click();
    await expect(page.getByRole('heading', { name: 'Defina quem responde por cada domínio' })).toBeVisible();
    await expect(page.getByText('ana.reis@empresa.com')).toBeVisible();
    await expect(page.getByRole('button', { name: '+ Convidar outra pessoa' })).toBeVisible();

    await page.getByRole('button', { name: 'Continuar' }).click();
    await expect(page.getByRole('heading', { name: 'A indexação já começou' })).toBeVisible();
    await expect(page.getByRole('progressbar')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Pergunte ao Copiloto' })).toBeVisible();

    await page.getByRole('button', { name: 'Voltar' }).click();
    await expect(page.getByRole('heading', { name: 'Defina quem responde por cada domínio' })).toBeVisible();

    await page.getByRole('button', { name: 'Responsáveis' }).click();
    await expect(page.getByRole('heading', { name: 'Defina quem responde por cada domínio' })).toBeVisible();

    await page.getByRole('button', { name: 'Indexação' }).click();
    await expect(page.getByRole('heading', { name: 'A indexação já começou' })).toBeVisible();
    await page.getByRole('button', { name: 'Concluir' }).click();
    // Concluir navega para "/", que é rota protegida: sem sessão, o AuthGuard redireciona para /login.
    await expect(page).toHaveURL(/\/login/);
  });

  test('pular configuração navega para a home (ou /login, sem sessão ativa)', async ({ page }) => {
    await page.goto('/onboarding');
    await page.getByRole('button', { name: 'pular configuração →' }).click();
    // Mesmo raciocínio: "pular configuração" navega para "/", e o AuthGuard redireciona sem sessão.
    await expect(page).toHaveURL(/\/login/);
  });
});
