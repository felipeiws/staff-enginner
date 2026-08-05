import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const readWorkflow = (name) => readFile(`.github/workflows/${name}`, 'utf8');

test('a CI cobre as validações e os módulos previstos', async () => {
  const ci = await readWorkflow('ci.yml');

  for (const expected of [
    'pull_request:',
    'branches:',
    '- main',
    'lint-javascript:',
    'lint-php:',
    'lint-python:',
    'backend-tests:',
    'worker-tests:',
    'portal-tests-build:',
    'contracts-json-schema:',
    'postgres:16-alpine',
    'redis:7-alpine',
    'PHP-CS-Fixer foi ignorado',
    'ruff foi ignorado',
    'pytest foi ignorado',
    'ajv-cli@5 compile',
    'actions/cache@v4',
  ]) {
    assert.match(ci, new RegExp(expected.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));
  }
});

test('a automação de documentação valida links e Mermaid', async () => {
  const docs = await readWorkflow('docs.yml');

  assert.match(docs, /Links relativos quebrados/);
  assert.match(docs, /@mermaid-js\/mermaid-cli/);
  assert.match(docs, /find docs -type f -name '\*\.md'/);
});
