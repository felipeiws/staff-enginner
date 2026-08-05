import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import path from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

test('declara o analisador Laravel estático, limitado e rastreável', async () => {
  const manifest = JSON.parse(await readFile(path.join(root, 'plugin.json')));
  const fixture = async (file) => readFile(path.join(root, 'tests/fixtures/laravel', file), 'utf8');

  assert.deepEqual(manifest.capabilities, ['code.analyzer']);
  assert.equal(manifest.configurations.framework, 'laravel');
  assert.deepEqual(manifest.configurations.compatible_framework_versions, ['^10.0.0', '^11.0.0', '^12.0.0', '^13.0.0']);
  assert.equal(manifest.configurations.max_repository_files, 5000);
  assert.equal(manifest.configurations.max_analysis_seconds, manifest.limits.timeout_seconds);
  assert.equal(manifest.permissions.includes('network.egress'), false);
  assert.equal(manifest.output_schema.$ref, '../../../../packages/contracts/schemas/v1/code-analysis-result.json');
  assert.match(await fixture('routes/web.php'), /PurchaseOrderController/);
  assert.match(await fixture('app/Listeners/ReleaseStockListener.php'), /PurchaseOrderCancelled/);
  assert.match(await fixture('app/Models/PurchaseOrder.php'), /purchase_orders/);
  assert.match(await fixture('database/migrations/2026_01_01_create_purchase_orders_table.php'), /Schema::create/);
});
