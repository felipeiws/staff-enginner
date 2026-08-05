import { createRequire } from 'node:module';
import { chromium } from 'playwright';

const require = createRequire(import.meta.url);
const axe = require('axe-core');
const baseUrl = process.env.ATLAS_PORTAL_URL ?? 'http://127.0.0.1:4173';
const routes = [
  '/', '/busca', '/copiloto', '/graph', '/aplicacoes/erp', '/processos/proc',
  '/regras/rn142', '/telas/pc421', '/apis/api1', '/aprovacoes', '/saude', '/decisoes-ux',
];

const browser = await chromium.launch({ executablePath: process.env.CHROME_BIN ?? '/usr/bin/google-chrome', headless: true });
const page = await browser.newPage();
const failures = [];

for (const route of routes) {
  await page.goto(`${baseUrl}${route}`, { waitUntil: 'networkidle' });
  await page.evaluate(axe.source);
  const result = await page.evaluate(() => axe.run(document, {
  // Rotas dependentes da API exibem skeleton durante a auditoria estática; o
  // heading real pertence ao estado carregado e é coberto pelos testes da rota.
  rules: { 'color-contrast': { enabled: true }, 'page-has-heading-one': { enabled: false } },
  }));
  if (result.violations.length) failures.push({ route, violations: result.violations });
}

await browser.close();
if (failures.length) {
  console.error(JSON.stringify(failures, null, 2));
  process.exitCode = 1;
} else {
  console.log(`axe: 0 violações nas ${routes.length} rotas`);
}
