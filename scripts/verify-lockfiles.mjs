import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const root = process.cwd();
const required = [
  'pnpm-lock.yaml',
  'apps/backend/composer.lock',
  'packages/contracts-php/composer.lock',
  'apps/worker/uv.lock',
];
const missing = required.filter((file) => !existsSync(resolve(root, file)));

if (missing.length > 0) {
  throw new Error(`Lockfiles obrigatórios ausentes: ${missing.join(', ')}`);
}

const packageJson = JSON.parse(readFileSync(resolve(root, 'package.json'), 'utf8'));
if (!String(packageJson.packageManager).startsWith('pnpm@')) {
  throw new Error('package.json deve fixar a versão do pnpm no campo packageManager.');
}

const pyproject = readFileSync(resolve(root, 'apps/worker/pyproject.toml'), 'utf8');
if (!pyproject.includes('[project]')) {
  throw new Error('apps/worker/pyproject.toml não define o projeto gerenciado pelo uv.');
}

console.log(`Lockfiles obrigatórios presentes: ${required.join(', ')}.`);
