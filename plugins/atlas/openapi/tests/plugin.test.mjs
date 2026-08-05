import assert from 'node:assert/strict'; import { readFile } from 'node:fs/promises'; import path from 'node:path'; import test from 'node:test'; import { fileURLToPath } from 'node:url';
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
test('declara parsing OpenAPI candidato sem resolver referências externas', async () => {
  const manifest = JSON.parse(await readFile(path.join(root, 'plugin.json'))); const fixture = await readFile(path.join(root, 'tests/fixtures/openapi.yaml'), 'utf8');
  assert.deepEqual(manifest.capabilities, ['content.parser']); assert.equal(manifest.input_schema.task_type, 'openapi.parse'); assert.equal(manifest.configurations.follow_external_references, false);
  assert.equal(manifest.output_schema.$ref, '../../../../packages/contracts/schemas/v1/content-parse-result.json'); assert.equal(manifest.idempotency, 'task.idempotency_key'); assert.match(fixture, /^openapi: 3\.1\.0/m); assert.doesNotMatch(JSON.stringify(manifest), /postgres|redis|typesense|database/i);
});
