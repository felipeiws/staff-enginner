import { createHash, randomUUID, timingSafeEqual } from 'node:crypto';

const CONNECTOR_VERSION = 'atlas.github@1.0.0';
const SHA = /^[0-9a-f]{40,64}$/i;

export class AuthorizationError extends Error {}
export class WebhookSignatureError extends Error {}
export class RateLimitError extends Error {}

/**
 * Provider-neutral source connector. GitLab and Azure DevOps only need adapters
 * implementing the same small GitApi surface used here.
 */
export class GitRepositoryConnector {
  constructor({ api, scope, credentialReference, now = () => new Date(), uuid = randomUUID, webhookLimit = 60 }) {
    this.api = api;
    this.scope = normalizeScope(scope);
    this.credentialReference = assertSecretReference(credentialReference);
    this.now = now;
    this.uuid = uuid;
    this.webhookLimit = webhookLimit;
    this.webhookAttempts = new Map();
  }

  async discoverIncremental({ cursor = {}, command }) {
    const repositories = await this.api.listRepositories({ organization: this.scope.organization, cursor: cursor.repositories });
    const artifacts = [];
    for (const repository of repositories.items) {
      if (!this.isRepositoryAllowed(repository)) continue;
      const revision = await this.api.getHeadCommit({ repository });
      assertCommitSha(revision);
      const files = await this.api.listFiles({ repository, revision });
      for (const file of files) {
        if (this.isPathAllowed(file.path)) artifacts.push(this.artifactReference(repository, file.path, revision, command));
      }
    }
    return { artifacts, cursor: { repositories: repositories.nextCursor ?? null }, provenance: this.provenance() };
  }

  async fetchArtifact({ reference, command }) {
    this.assertReferenceAllowed(reference);
    assertCommitSha(reference.revision);
    const content = await this.api.getFileContent({ repository: reference.repository, path: reference.path, revision: reference.revision });
    const history = await this.api.listCommits({ repository: reference.repository, path: reference.path, revision: reference.revision, limit: 20 });
    const text = decodeContent(content);
    const checksum = `sha256:${createHash('sha256').update(text).digest('hex')}`;
    const uri = `https://github.com/${reference.repository.full_name}/blob/${reference.revision}/${encodePath(reference.path)}`;
    return {
      schema_version: '1.0.0', observation_id: this.uuid(),
      external_id: `github:${reference.repository.id}:${reference.path}`,
      source_type: 'repository_file', uri, revision: reference.revision,
      observed_at: this.now().toISOString(), content: text, checksum,
      classification: this.scope.classification,
      metadata: {
        provider: 'github', repository: { id: reference.repository.id, full_name: reference.repository.full_name }, path: reference.path,
        source_revision: reference.revision, method: CONNECTOR_VERSION, credential_reference: this.credentialReference,
        commit_history: history.map(({ sha, author, committed_at }) => ({ sha, author, committed_at })),
        freshness: { last_commit_at: history[0]?.committed_at ?? null }, scope: { organization: this.scope.organization }
      },
      command: pluginCommand(command)
    };
  }

  async acceptWebhook({ body, signature, remoteAddress, command }) {
    this.assertWebhookRate(remoteAddress);
    this.assertWebhookSignature(body, signature);
    const event = JSON.parse(body);
    if (event.repository && !this.isRepositoryAllowed(event.repository)) return { accepted: true, artifacts: [], ignored: true };
    if (event.ref && !event.ref.startsWith('refs/heads/')) return { accepted: true, artifacts: [], ignored: true };
    const revision = event.after;
    assertCommitSha(revision);
    const repository = event.repository;
    const files = await this.api.listFiles({ repository, revision });
    return {
      accepted: true,
      artifacts: files.filter((file) => this.isPathAllowed(file.path)).map((file) => this.artifactReference(repository, file.path, revision, command)),
      provenance: this.provenance()
    };
  }

  async openPullRequest({ proposal, commitMessage, idempotencyKey }) {
    const target = proposal.documentation_target;
    if (!target || !this.isRepositoryAllowed(target.repository) || !this.isPathAllowed(target.path)) throw new AuthorizationError('O destino da documentação não está autorizado pelo escopo do conector.');
    const branch = `atlas/approval-${safeBranchPart(idempotencyKey)}`;
    const base = target.base_branch ?? 'main';
    const baseSha = await this.api.getBranchHead({ repository: target.repository, branch: base });
    await this.api.createBranch({ repository: target.repository, branch, sha: baseSha });
    const existing = await this.api.getFileContent({ repository: target.repository, path: target.path, revision: base });
    await this.api.createOrUpdateFile({ repository: target.repository, path: target.path, branch, content: proposal.proposed_text, previousSha: existing.sha, message: commitMessage });
    const pullRequest = await this.api.createPullRequest({ repository: target.repository, title: proposal.title ?? 'Atualização aprovada da documentação', body: `${commitMessage}\n\nProposta: ${proposal.id ?? 'sem-id'}`, head: branch, base });
    return { reference: pullRequest.url, number: pullRequest.number, branch, provenance: this.provenance() };
  }

  artifactReference(repository, path, revision, command) { return { repository, path, revision, command: pluginCommand(command) }; }
  isRepositoryAllowed(repository) { return repositoryOrganization(repository) === this.scope.organization && this.scope.repositoryIds.has(String(repository?.id)); }
  isPathAllowed(path) { return this.scope.pathPrefixes.some((prefix) => path.startsWith(prefix)); }
  assertReferenceAllowed(reference) { if (!this.isRepositoryAllowed(reference.repository) || !this.isPathAllowed(reference.path)) throw new AuthorizationError('Artefato fora do escopo autorizado.'); }
  provenance() { return { provider: 'github', method: CONNECTOR_VERSION, credential_reference: this.credentialReference }; }
  assertWebhookSignature(body, signature) {
    // The isolated runtime injects the rotating secret into the provider adapter;
    // the manifest and plugin state retain only its secret:// reference.
    if (typeof this.api.webhookHmac !== 'function') throw new WebhookSignatureError('Validador de assinatura não configurado.');
    const expected = `sha256=${this.api.webhookHmac(body)}`;
    const left = Buffer.from(expected); const right = Buffer.from(signature);
    if (left.length !== right.length || !timingSafeEqual(left, right)) throw new WebhookSignatureError('Assinatura de webhook inválida.');
  }
  assertWebhookRate(remoteAddress) {
    const key = `atlas.github:${remoteAddress}`; const now = this.now().getTime(); const windowStart = now - 60_000;
    const attempts = (this.webhookAttempts.get(key) ?? []).filter((at) => at > windowStart);
    if (attempts.length >= this.webhookLimit) throw new RateLimitError('Webhook excedeu o limite de requisições.');
    attempts.push(now); this.webhookAttempts.set(key, attempts);
  }
}

function normalizeScope(scope) { if (!scope?.organization || !Array.isArray(scope.repository_ids) || !Array.isArray(scope.path_prefixes) || !scope.classification) throw new AuthorizationError('Escopo de Git incompleto.'); return { organization: scope.organization, repositoryIds: new Set(scope.repository_ids), pathPrefixes: scope.path_prefixes, classification: scope.classification }; }
function assertSecretReference(value) { if (typeof value !== 'string' || !value.startsWith('secret://')) throw new AuthorizationError('Credenciais devem ser fornecidas por referência secret://.'); return value; }
function assertCommitSha(value) { if (typeof value !== 'string' || !SHA.test(value)) throw new Error('A revisão deve ser um commit SHA imutável.'); }
function decodeContent(content) { return content.encoding === 'base64' ? Buffer.from(content.content.replace(/\n/g, ''), 'base64').toString('utf8') : content.content; }
function encodePath(path) { return path.split('/').map(encodeURIComponent).join('/'); }
function pluginCommand(command) { if (!command?.idempotency_key || !command?.actor) throw new Error('Comando com ator e idempotency_key é obrigatório.'); return command; }
function safeBranchPart(value) { return createHash('sha256').update(value).digest('hex').slice(0, 16); }
function repositoryOrganization(repository) { return repository?.organization ?? repository?.owner?.login; }
