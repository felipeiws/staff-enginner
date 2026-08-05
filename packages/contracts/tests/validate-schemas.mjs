import assert from 'node:assert/strict';
import { mkdtemp, rm, writeFile } from 'node:fs/promises';
import { spawnSync } from 'node:child_process';
import { tmpdir } from 'node:os';
import path from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const directory = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const schemas = path.join(directory, 'schemas/v1');
const id = '75e7d5be-30e0-4fd9-b948-f821257d0a24';
const actor = { actor_id: 'ana@example.com', actor_type: 'human' };
const command = { idempotency_key: 'cmd-001', actor };
const provenance = {
  source_revision: 'git:abc123',
  method: 'parser@1.0.0',
  processed_at: '2026-07-31T12:00:00Z',
};
const entityRef = { entity_id: id, revision_id: id };

const examples = {
  'source-observation.json': {
    schema_version: '1.0.0',
    observation_id: id,
    external_id: 'github:acme/atlas:README.md',
    source_type: 'repository_file',
    uri: 'https://github.com/acme/atlas/blob/main/README.md',
    revision: 'abc123',
    observed_at: '2026-07-31T12:00:00Z',
    content: '# Atlas',
    checksum: 'sha256:abcdef',
    metadata: { repository: 'acme/atlas' },
    classification: 'internal',
    command,
  },
  'knowledge-entity.json': {
    schema_version: '1.0.0',
    entity_id: id,
    entity_type: 'repository',
    stable_key: 'acme/atlas',
    lifecycle_state: 'candidate',
    provenance: { source_observation_id: id, ...provenance, confidence: 0.9 },
    command,
  },
  'knowledge-revision.json': {
    schema_version: '1.0.0',
    revision_id: id,
    entity_id: id,
    source_observation_id: id,
    source_revision: 'abc123',
    lifecycle_state: 'candidate',
    method: 'parser@1.0.0',
    confidence: 0.9,
    processed_at: '2026-07-31T12:00:00Z',
    checksum: 'sha256:abcdef',
    command,
  },
  'assertion.json': {
    schema_version: '1.0.0',
    assertion_id: id,
    subject: entityRef,
    predicate: 'contains',
    object: entityRef,
    scope: { organization_id: 'acme' },
    confidence: 0.9,
    method: 'parser@1.0.0',
    author: actor,
    lifecycle_state: 'candidate',
    provenance,
    evidence_ids: [id],
    command,
  },
  'evidence.json': {
    schema_version: '1.0.0',
    evidence_id: id,
    kind: 'chunk',
    pointer: 's3://atlas/artifacts/a#chunk-1',
    offsets: { start: 0, end: 42, unit: 'character' },
    checksum: 'sha256:abcdef',
    provenance,
    command,
  },
  'approval.json': {
    schema_version: '1.0.0',
    approval_id: id,
    subject: { kind: 'assertion', id },
    decision: 'approved',
    decided_at: '2026-07-31T12:00:00Z',
    decided_by: actor,
    reason: 'Evidência suficiente.',
    provenance,
    command,
  },
  'audit-entry.json': {
    schema_version: '1.0.0',
    audit_entry_id: id,
    occurred_at: '2026-07-31T12:00:00Z',
    action: 'assertion.approved',
    actor,
    scope: { organization_id: 'acme' },
    subject: { kind: 'assertion', id, revision_id: id },
    reason: 'Evidência suficiente.',
    correlation_id: id,
    command,
  },
  'domain-event.json': {
    event_id: id,
    event_type: 'KnowledgeRevision.Created',
    schema_version: '1.0.0',
    occurred_at: '2026-07-31T12:00:00Z',
    correlation_id: id,
    actor,
    scope: { organization_id: 'acme' },
    entity: entityRef,
    payload: { lifecycle_state: 'candidate' },
    command,
  },
  'worker-task.json': {
    schema_version: '1.0.0',
    task_id: id,
    task_type: 'embedding.generate',
    idempotency_key: 'worker-001',
    correlation_id: id,
    scope: { organization: 'acme' },
    deadline_at: '2026-07-31T12:10:00Z',
    payload: { revision_id: id },
  },
  'embedding-result.json': {
    schema_version: '1.0.0',
    event_type: 'EmbeddingGenerated',
    task_id: id,
    revision_id: id,
    scope: { organization: 'acme' },
    provenance: {
      source_revision_id: id,
      method: 'embedding@fixture:1',
      processed_at: '2026-07-31T12:00:00Z',
    },
    chunks: [
      {
        ordinal: 0,
        start_offset: 0,
        end_offset: 5,
        text: 'Atlas',
        tokens: 1,
        checksum: 'a'.repeat(64),
        embedding: [0.1],
        model: 'fixture',
        model_version: '1',
        parameters: {},
      },
    ],
  },
  'code-analysis-result.json': {
    schema_version: '1.0.0', event_type: 'CodeAnalyzed', task_id: id, revision_id: id,
    scope: { organization: 'acme' }, artifact_uri: 'git://acme/atlas',
    provenance: { source_observation_id: id, ...provenance },
    execution: { analyzer_version: 'code.analyzer@1.0.0', kind: 'static_analysis', reprocessable: true },
    candidates: [{ entity_type: 'Service', title: 'PurchaseOrderCancelService', stable_key: 'code:service:PurchaseOrderCancelService', lifecycle_state: 'candidate', confidence: 0.92, provenance, evidence: { kind: 'artifact', pointer: 'app/Services/PurchaseOrderCancelService.php:214', offsets: { start: 214, end: 214, unit: 'line' }, checksum: 'abc', commit: 'a91f3ce', provenance } }],
    assertions: [{ subject_stable_key: 'code:service:PurchaseOrderCancelService', predicate: 'writes', object_stable_key: 'code:tabela:purchase_orders', lifecycle_state: 'candidate', confidence: 0.9, method: 'code.analyzer@1.0.0', provenance, evidence: { kind: 'artifact', pointer: 'app/Services/PurchaseOrderCancelService.php:214', offsets: { start: 214, end: 214, unit: 'line' }, checksum: 'abc', commit: 'a91f3ce', provenance } }],
  },
  'documentation-code-divergence-result.json': {
    schema_version: '1.0.0', event_type: 'DocumentationCodeDivergenceDetected', task_id: id, scope: { organization: 'acme' }, document_revision_id: id, code_revision_id: id,
    provenance, execution: { detector_version: 'documentation.code-divergence@1.0.0', kind: 'deterministic_comparison', reprocessable: true },
    divergences: [{ candidate_key: 'candidate-1', confidence: 0.94, assertion: { subject_revision_id: id, predicate: 'contradicts', object_revision_id: id, lifecycle_state: 'candidate', method: 'documentation.code-divergence@1.0.0', confidence: 0.94, evidence: [{ pointer: 'docs/rn-142.md:4' }, { pointer: 'app/Services/Cancel.php:12' }] }, proposal: { kind: 'documentation_code_divergence', subject_kind: 'assertion', assertion_candidate_key: 'candidate-1', current_text: 'bloqueio total', proposed_text: 'bloqueio condicionado', analysis: 'O código diverge do texto.', impacted_entities: [{ entity_id: id, revision_id: id, role: 'documentação' }, { entity_id: id, revision_id: id, role: 'código' }], origin: provenance } }],
  },
  'visual-artifact-result.json': {
    schema_version: '1.0.0', event_type: 'VisualArtifactRead', task_id: id, scope: { organization: 'acme' },
    screen_key: 'PC-0421', variant: 'desktop', kind: 'print', content_base64: 'ZmFrZQ==',
    content_hash: `sha256:${'a'.repeat(64)}`, media_type: 'image/png',
    provenance, ocr_evidence: { text: 'Cancelar pedido', method: 'tesseract@5', confidence: 0.94, processed_at: '2026-07-31T12:00:00Z' },
  },
  'plugin-manifest.json': {
    schema_version: '1.0.0', id: 'atlas.code-analyzer', version: '1.0.0', platform_compatibility: '^1.0.0', runtime: 'python_worker',
    capabilities: ['code.analyzer'], configurations: {}, permissions: ['artifact.read'], input_schema: {}, output_schema: {},
    limits: { max_concurrency: 1, timeout_seconds: 60, max_payload_bytes: 1000000, cpu_millicores: 1000, memory_mebibytes: 1024 }, retry_policy: { max_attempts: 3, backoff_seconds: 2 },
  },
};

function validate(schema, dataFile) {
  return spawnSync(
    'npx',
    [
      '--yes',
      '-p',
      'ajv-cli@5',
      '-p',
      'ajv-formats',
      'ajv',
      'validate',
      '--spec=draft2020',
      '-c',
      'ajv-formats',
      '-s',
      path.join(schemas, schema),
      '-d',
      dataFile,
    ],
    { encoding: 'utf8' },
  );
}

test('cada schema v1 aceita exemplo válido e rejeita um inválido', async () => {
  const temporary = await mkdtemp(path.join(tmpdir(), 'atlas-contracts-'));
  try {
    for (const [schema, valid] of Object.entries(examples)) {
      const validPath = path.join(temporary, `valid-${schema}`);
      const invalidPath = path.join(temporary, `invalid-${schema}`);
      await writeFile(validPath, JSON.stringify({ ...valid, extension_field: true }));
      await writeFile(invalidPath, JSON.stringify((({ schema_version, ...rest }) => rest)(valid)));
      const accepted = validate(schema, validPath);
      assert.equal(
        accepted.status,
        0,
        `${schema} deveria aceitar exemplo válido: ${accepted.stderr}`,
      );
      const rejected = validate(schema, invalidPath);
      assert.notEqual(rejected.status, 0, `${schema} deveria rejeitar exemplo sem schema_version`);
    }
  } finally {
    await rm(temporary, { recursive: true, force: true });
  }
});
