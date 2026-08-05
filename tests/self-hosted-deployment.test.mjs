import assert from 'node:assert/strict';
import { access, readFile } from 'node:fs/promises';
import path from 'node:path';
import test from 'node:test';

const root = process.cwd();
const read = (relativePath) => readFile(path.join(root, relativePath), 'utf8');

test('empacota backend, worker e portal com estágios e usuário não-root', async () => {
  for (const relativePath of [
    'apps/backend/Dockerfile',
    'apps/worker/Dockerfile',
    'apps/portal/Dockerfile',
  ]) {
    const dockerfile = await read(relativePath);
    assert.match(dockerfile, /\bAS\b/i, `${relativePath} deve usar multi-stage`);
    assert.match(
      dockerfile,
      /^USER\s+(?!root\b).+/m,
      `${relativePath} deve terminar com usuário não-root`,
    );
  }
});

test('declara a stack self-hosted, persistência, saúde e backup recuperável', async () => {
  const compose = await read('docker/docker-compose.production.yml');
  for (const service of [
    'postgres',
    'redis',
    'typesense',
    'minio',
    'backend',
    'worker',
    'portal',
    'backup',
  ]) {
    assert.match(compose, new RegExp(`^  ${service}:`, 'm'));
  }
  for (const volume of ['postgres-data', 'typesense-data', 'minio-data', 'backup-data']) {
    assert.match(compose, new RegExp(`^  ${volume}:`, 'm'));
  }
  assert.ok((compose.match(/healthcheck:/g) ?? []).length >= 7);
  await access(path.join(root, 'docker/backup/backup.sh'));
  await access(path.join(root, 'docker/backup/restore.sh'));
});

test('oferece instalação, atualização e bootstrap idempotente do administrador', async () => {
  const install = await read('scripts/install-self-hosted.sh');
  const update = await read('scripts/update-self-hosted.sh');
  const command = await read('apps/backend/app/Console/CreateInitialAdministrator.php');
  assert.match(install, /atlas:create-initial-admin/);
  assert.match(install, /atlas:seed-demo/);
  assert.match(update, /migrate --force/);
  assert.match(update, /search:reindex-published/);
  assert.match(command, /updateOrInsert/);
  assert.match(command, /administrador_de_plataforma/);
});
