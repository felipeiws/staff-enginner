import assert from 'node:assert/strict';
import { readFile, stat } from 'node:fs/promises';
import path from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

async function readJson(relativePath) {
  return JSON.parse(await readFile(path.join(root, relativePath), 'utf8'));
}

test('declara os workspaces e scripts agregadores previstos', async () => {
  const workspace = await readFile(path.join(root, 'pnpm-workspace.yaml'), 'utf8');
  const packageJson = await readJson('package.json');

  assert.match(workspace, /^\s*- apps\/portal$/m);
  assert.match(workspace, /^\s*- packages\/\*$/m);
  for (const script of ['build', 'dev', 'lint', 'test']) {
    assert.equal(typeof packageJson.scripts[script], 'string');
  }
});

test('mantém TypeScript estrito e aliases dos contratos centrais', async () => {
  const tsconfig = await readJson('tsconfig.base.json');

  assert.equal(tsconfig.compilerOptions.strict, true);
  assert.deepEqual(tsconfig.compilerOptions.paths['@atlas/contracts'], [
    'packages/sdk/src/index.ts',
  ]);
  assert.deepEqual(tsconfig.compilerOptions.paths['@atlas/contracts-schemas'], [
    'packages/contracts/src/index.ts',
  ]);
});

test('preserva os artefatos normativos de produto e design', async () => {
  const required = [
    'docs/product/context.md',
    'docs/design/prototype/atlas-prototype.html',
    'docs/design/prototype/support.js',
    'docs/design/prototype/README.md',
  ];

  for (const relativePath of required) {
    const metadata = await stat(path.join(root, relativePath));
    assert.equal(metadata.isFile(), true, `${relativePath} deve ser um arquivo`);
  }

  const prototype = await readFile(
    path.join(root, 'docs/design/prototype/atlas-prototype.html'),
    'utf8',
  );
  assert.match(prototype, /<x-dc>/);
  assert.match(prototype, /<script src="\.\/support\.js"><\/script>/);
});
