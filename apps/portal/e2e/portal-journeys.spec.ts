import { entityId, expect, graphReady, test } from './fixtures';

test.describe.serial('percursos do portal contra o seed Nordika', () => {
  test('da Home pergunta sobre o efeito do cancelamento no estoque e recebe evidências', async ({ page }) => {
    await page.goto('/');
    await page.getByLabel('Pergunte ao Copiloto').fill('Cancelar Pedido de Compra');
    await page.getByRole('button', { name: '↵ perguntar' }).click();
    await expect(page).toHaveURL(/\/copiloto\?q=/);
    await page.getByLabel('Pergunte ao Copiloto').press('Enter');
    await expect(page.getByText('Fontes utilizadas')).toBeVisible();
    await expect(page.getByText(/reserva de estoque é liberada/i)).toBeVisible();
    await expect(page.getByText(/confiança \d+%/)).toBeVisible();
    await page.getByRole('button', { name: /Ver linha de raciocínio/ }).click();
    await expect(page.locator('.copilot-reasoning-steps')).toBeVisible();
  });

  test('busca, filtra Processo e percorre as relações até Regra, Tela e Endpoint', async ({ page }) => {
    const processId = await entityId(page, 'Cancelar Pedido de Compra', 'Processo');
    await page.goto('/busca?q=cancelamento');
    await page.getByRole('button', { name: /Processos/ }).click();
    await expect(page).toHaveURL(/entity_type=Processo/);
    await page.getByRole('link', { name: /Cancelar Pedido de Compra/ }).first().click();
    await expect(page.getByRole('heading', { name: 'Cancelar Pedido de Compra' })).toBeVisible();
    const initialGraph = graphReady(page);
    await page.getByRole('button', { name: 'Abrir no Graph' }).click();
    await initialGraph;
    await page.getByRole('button', { name: /Selecionar RN-142/ }).click();
    await page.getByRole('button', { name: 'abrir página' }).click();
    await expect(page.getByText('Enunciado')).toBeVisible();
    const screenGraph = graphReady(page);
    await page.goto(`/graph?entity=${processId}`);
    await screenGraph;
    await page.getByRole('button', { name: /Selecionar PC-0421/ }).click();
    await page.getByRole('button', { name: 'abrir página' }).click();
    await expect(page.getByText('Campos e origem do dado')).toBeVisible();
    const endpointGraph = graphReady(page);
    await page.goto(`/graph?entity=${processId}`);
    await endpointGraph;
    await page.getByRole('button', { name: /Selecionar DELETE \/v2\/purchase-orders/ }).click();
    await page.getByRole('button', { name: 'abrir página' }).click();
    await expect(page.getByRole('heading', { name: 'Parâmetros' })).toBeVisible();
  });

  test('abre o Graph de uma entidade, seleciona outro nó e abre sua página', async ({ page }) => {
    const processId = await entityId(page, 'Cancelar Pedido de Compra', 'Processo');
    const graphLoad = graphReady(page);
    await page.goto(`/graph?entity=${processId}`);
    await graphLoad;
    await expect(page.getByText(/foco: Cancelar Pedido de Compra/)).toBeVisible();
    const candidate = page.getByRole('button', { name: /Selecionar RN-142/ });
    await candidate.click();
    await expect(page.getByRole('heading', { name: 'RN-142' })).toBeVisible();
    await page.getByRole('button', { name: 'abrir página' }).click();
    await expect(page).toHaveURL(/\/regras\//);
    await expect(page.getByText('Enunciado')).toBeVisible();
  });

  test('bloqueia aprovação sem justificativa e aprova com referência de PR', async ({ page }) => {
    await page.goto('/aprovacoes');
    await expect(page.getByRole('heading', { name: 'Aprovações' })).toBeVisible();
    await page.getByRole('button', { name: 'Aprovar e abrir PR' }).click();
    await expect(page.getByRole('alert')).toContainText('Informe a justificativa');
    await page.getByLabel('Justificativa (vai para o commit)').fill('Revisado com o domínio Fiscal; o comportamento 409 está documentado.');
    await page.getByRole('button', { name: 'Aprovar e abrir PR' }).click();
    await expect(page.getByText(/Aprovado · .* aberto em erp-financeiro\/knowledge/)).toBeVisible();
  });

  test('percorre saúde e atribui uma pergunta sem resposta', async ({ page }) => {
    const organizationId = await entityId(page, 'Nordika', 'Organização');
    await page.goto(`/saude?scope=${organizationId}&organization=nordika`);
    await expect(page.getByRole('heading', { name: 'Saúde do conhecimento' })).toBeVisible();
    await page.getByRole('button', { name: 'atribuir' }).first().click();
    await page.getByLabel('Responsável').fill('m-reis');
    await page.getByRole('button', { name: 'atribuir e gerar tarefa' }).click();
    await expect(page.getByRole('status')).toContainText('Tarefa de documentação');
  });

  test('abre a paleta com ⌘K e navega até uma entidade', async ({ page }) => {
    await page.goto('/');
    await page.keyboard.press('Meta+k');
    await expect(page.getByRole('dialog', { name: 'Paleta de comandos' })).toBeVisible();
    await page.getByLabel('Ir para entidade, ação ou painel').fill('RN-142');
    await page.getByRole('option', { name: /^Regra RN-142$/ }).click();
    await expect(page).toHaveURL(/\/regras\//);
    await expect(page.getByText('Enunciado')).toBeVisible();
  });

  test('persiste o tema após recarregar', async ({ page }) => {
    await page.goto('/');
    const toggle = page.getByRole('button', { name: /Ativar tema/ });
    const before = await page.locator('html').getAttribute('data-theme');
    await toggle.click();
    const after = await page.locator('html').getAttribute('data-theme');
    expect(after).not.toBe(before);
    await page.reload();
    await expect(page.locator('html')).toHaveAttribute('data-theme', after!);
  });
});
