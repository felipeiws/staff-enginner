import assert from 'node:assert/strict'; import { readFile } from 'node:fs/promises'; import path from 'node:path'; import test from 'node:test'; import { fileURLToPath } from 'node:url';
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
test('declara leitura de artefato isolada com captura limitada por allowlist', async () => {
  const manifest = JSON.parse(await readFile(path.join(root, 'plugin.json'))); const fixture = JSON.parse(await readFile(path.join(root, 'tests/fixtures/print.json')));
  assert.deepEqual(manifest.capabilities, ['artifact.reader']); assert.equal(manifest.input_schema.task_type, 'artifact.read'); assert.deepEqual(manifest.egress_allowlist, manifest.configurations.capture_hosts);
  assert.equal(manifest.idempotency, 'task.idempotency_key'); assert.ok(manifest.observability.metrics.includes('ocr_characters_total')); assert.equal(fixture.kind, 'print'); assert.doesNotMatch(JSON.stringify(manifest), /postgres|redis|typesense|database/i);
});
