import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import path from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

test('declara o analisador React estático, limitado e rastreável', async () => {
  const manifest = JSON.parse(await readFile(path.join(root, 'plugin.json')));
  const fixture = async (file) => readFile(path.join(root, 'tests/fixtures/react', file), 'utf8');

  assert.deepEqual(manifest.capabilities, ['code.analyzer']);
  assert.equal(manifest.configurations.framework, 'react');
  assert.deepEqual(manifest.configurations.compatible_framework_versions, ['^17.0.0', '^18.0.0', '^19.0.0']);
  assert.equal(manifest.configurations.max_repository_files, 5000);
  assert.equal(manifest.configurations.max_analysis_seconds, manifest.limits.timeout_seconds);
  assert.equal(manifest.permissions.includes('network.egress'), false);
  assert.equal(manifest.output_schema.$ref, '../../../../packages/contracts/schemas/v1/code-analysis-result.json');
  assert.match(await fixture('src/routes/app-routes.tsx'), /PurchaseOrderScreen/);
  assert.match(await fixture('src/screens/PurchaseOrderScreen.tsx'), /fetch\(/);
  assert.match(await fixture('src/services/purchase-orders.ts'), /axios\.post/);
});
