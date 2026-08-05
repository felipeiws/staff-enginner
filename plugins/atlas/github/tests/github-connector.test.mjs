import assert from 'node:assert/strict';
import { createHmac } from 'node:crypto';
import test from 'node:test';
import { GitRepositoryConnector, RateLimitError, WebhookSignatureError } from '../src/github-connector.mjs';

const SHA = 'a'.repeat(40);
const command = { idempotency_key: 'command-1', actor: { actor_id: 'plugin:atlas.github', actor_type: 'plugin' }, correlation_id: 'd6cd1295-4508-4c8e-aec8-2bd4adb71d10' };
const repository = { id: 'repo-docs', organization: 'acme', full_name: 'acme/docs', default_branch: 'main' };

function api(overrides = {}) {
  return {
    listRepositories: async () => ({ items: [repository, { ...repository, id: 'outside' }], nextCursor: null }),
    getHeadCommit: async () => SHA,
    listFiles: async () => [{ path: 'docs/guide.md' }, { path: 'src/private.js' }],
    getFileContent: async () => ({ encoding: 'base64', content: Buffer.from('# Guia\n').toString('base64'), sha: 'blob-1' }),
    listCommits: async () => [{ sha: SHA, author: 'Ana Silva', committed_at: '2026-08-01T12:00:00Z' }],
    getBranchHead: async () => SHA,
    createBranch: async () => undefined,
    createOrUpdateFile: async () => undefined,
    createPullRequest: async () => ({ url: 'https://github.com/acme/docs/pull/42', number: 42 }),
    webhookHmac: (body) => createHmac('sha256', 'rotating-secret').update(body).digest('hex'),
    ...overrides
  };
}
function connector(overrides = {}) { return new GitRepositoryConnector({ api: api(overrides.api), scope: { organization: 'acme', repository_ids: ['repo-docs'], path_prefixes: ['docs/'], classification: 'internal' }, credentialReference: 'secret://atlas/github/test', now: () => new Date('2026-08-01T13:00:00Z'), uuid: () => '7208e9d9-a872-43a2-9f9d-98d7a36f54f8', webhookLimit: overrides.webhookLimit ?? 60 }); }

test('descobre apenas repositório e arquivos autorizados, sempre por commit SHA', async () => {
  const result = await connector().discoverIncremental({ command });
  assert.equal(result.artifacts.length, 1);
  assert.deepEqual(result.artifacts[0].repository, repository);
  assert.equal(result.artifacts[0].path, 'docs/guide.md');
  assert.equal(result.artifacts[0].revision, SHA);
});

test('lê uma revisão imutável e produz SourceObservation com proveniência, checksum e histórico', async () => {
  const observation = await connector().fetchArtifact({ reference: { repository, path: 'docs/guide.md', revision: SHA }, command });
  assert.equal(observation.external_id, 'github:repo-docs:docs/guide.md');
  assert.equal(observation.uri, `https://github.com/acme/docs/blob/${SHA}/docs/guide.md`);
  assert.equal(observation.revision, SHA);
  assert.equal(observation.content, '# Guia\n');
  assert.match(observation.checksum, /^sha256:[a-f0-9]{64}$/);
  assert.equal(observation.metadata.method, 'atlas.github@1.0.0');
  assert.equal(observation.metadata.credential_reference, 'secret://atlas/github/test');
  assert.deepEqual(observation.metadata.commit_history[0], { sha: SHA, author: 'Ana Silva', committed_at: '2026-08-01T12:00:00Z' });
});

test('repositório grande é paginado e mantém cursor sem carregar conteúdo na descoberta', async () => {
  const result = await connector({ api: { listRepositories: async ({ cursor }) => ({ items: cursor ? [] : [repository], nextCursor: cursor ? null : 2 }) } }).discoverIncremental({ command });
  assert.equal(result.cursor.repositories, 2);
  assert.equal(result.artifacts.length, 1);
});

test('rejeita webhook com assinatura inválida antes de consultar a API', async () => {
  let calls = 0;
  const subject = connector({ api: { listFiles: async () => { calls += 1; return []; } } });
  await assert.rejects(() => subject.acceptWebhook({ body: JSON.stringify({ repository, ref: 'refs/heads/main', after: SHA }), signature: 'sha256=invalida', remoteAddress: '203.0.113.7', command }), WebhookSignatureError);
  assert.equal(calls, 0);
});

test('limita webhooks por plugin e origem', async () => {
  const subject = connector({ webhookLimit: 1 });
  const body = JSON.stringify({ repository, ref: 'refs/heads/main', after: SHA });
  const signature = `sha256=${api().webhookHmac(body)}`;
  await subject.acceptWebhook({ body, signature, remoteAddress: '203.0.113.7', command });
  await assert.rejects(() => subject.acceptWebhook({ body, signature, remoteAddress: '203.0.113.7', command }), RateLimitError);
});

test('abre Pull Request somente no repositório e caminho de documentação autorizados', async () => {
  const result = await connector().openPullRequest({ proposal: { id: 'approval-9', title: 'Atualizar guia', proposed_text: '# Atualizado\n', documentation_target: { repository, path: 'docs/guide.md' } }, commitMessage: 'docs: atualiza guia\n\nAprovado por Ana.', idempotencyKey: 'approval-9' });
  assert.equal(result.reference, 'https://github.com/acme/docs/pull/42');
  assert.equal(result.number, 42);
});
