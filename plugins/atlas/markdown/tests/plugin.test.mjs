import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import path from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
test('declara o parser Markdown isolado e uma fixture representativa', async () => {
  const manifest = JSON.parse(await readFile(path.join(root, 'plugin.json')));
  const fixture = await readFile(path.join(root, 'tests/fixtures/documento.md'), 'utf8');
  assert.deepEqual(manifest.capabilities, ['content.parser']);
  assert.equal(manifest.runtime, 'python_worker'); assert.equal(manifest.input_schema.task_type, 'content.parse');
  assert.equal(manifest.idempotency, 'task.idempotency_key'); assert.ok(manifest.observability.metrics.includes('parse_documents_total'));
  assert.match(fixture, /^---[\s\S]*```mermaid/); assert.doesNotMatch(JSON.stringify(manifest), /postgres|redis|typesense|database/i);
});
