import assert from 'node:assert/strict';
import { readFile, stat } from 'node:fs/promises';
import path from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

async function read(relativePath) {
  return readFile(path.join(root, relativePath), 'utf8');
}

test('declara a stack local, health checks e volumes persistentes', async () => {
  const compose = await read('docker/docker-compose.yml');

  for (const service of ['postgres', 'redis', 'typesense', 'minio', 'mailpit']) {
    assert.match(compose, new RegExp(`^  ${service}:`, 'm'));
  }
  assert.equal((compose.match(/healthcheck:/g) ?? []).length, 5);
  for (const volume of [
    'postgres-data',
    'redis-data',
    'typesense-data',
    'minio-data',
    'mailpit-data',
  ]) {
    assert.match(compose, new RegExp(`^  ${volume}:`, 'm'));
  }
  assert.match(compose, /pgvector\/pgvector:pg16/);
  assert.match(compose, /typesense\/typesense:27\.1/);
});

test('habilita extensões e fornece scripts de ciclo de vida', async () => {
  const extensions = await read('docker/postgres/init/001-extensions.sql');
  assert.match(extensions, /CREATE EXTENSION IF NOT EXISTS vector;/);
  assert.match(extensions, /CREATE EXTENSION IF NOT EXISTS pg_trgm;/);
  assert.match(extensions, /CREATE EXTENSION IF NOT EXISTS "uuid-ossp";/);

  for (const script of ['scripts/dev-up.sh', 'scripts/dev-down.sh']) {
    const metadata = await stat(path.join(root, script));
    assert.equal(metadata.isFile(), true);
    assert.match(await read(script), /docker compose/);
  }
  assert.match(await read('scripts/dev-up.sh'), /up --detach/);
  assert.match(await read('scripts/dev-up.sh'), /docker inspect --format/);
});
