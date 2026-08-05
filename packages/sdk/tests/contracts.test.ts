import assert from 'node:assert/strict';
import test from 'node:test';
import {
  EntityFamily,
  GraphEntityType,
  GraphPredicate,
  KnowledgeLifecycleState,
  entityTypeTokenColor,
  isApproval,
  isAssertion,
  isAuditEntry,
  isDomainEvent,
  isEvidence,
  isKnowledgeEntity,
  isKnowledgeRevision,
  isSourceObservation,
  validateContract,
} from '../src/index.js';

const id = '75e7d5be-30e0-4fd9-b948-f821257d0a24';
const actor = { actor_id: 'ana@example.com', actor_type: 'human' };
const command = { idempotency_key: 'cmd-001', actor };
const provenance = {
  source_observation_id: id,
  source_revision: 'git:abc123',
  method: 'parser@1.0.0',
  confidence: 0.9,
  processed_at: '2026-07-31T12:00:00Z',
};

void test('valida uma entidade com proveniência e rejeita UUID inválido', () => {
  const entity = {
    schema_version: '1.0.0',
    entity_id: id,
    entity_type: 'Processo',
    stable_key: 'compras.cancelamento',
    lifecycle_state: 'candidate',
    provenance,
    command,
  };
  const valid = validateContract('knowledge-entity', entity);
  assert.equal(valid.valid, true);
  assert.equal(isKnowledgeEntity(entity), true);

  const invalid = validateContract('knowledge-entity', { ...entity, entity_id: 'não-é-uuid' });
  assert.equal(invalid.valid, false);
  assert.ok(invalid.errors.some((error) => error.keyword === 'format'));
});

void test('type guard de assertion exige objeto ou valor e predicado do vocabulário', () => {
  const base = {
    schema_version: '1.0.0',
    assertion_id: id,
    subject: { entity_id: id },
    predicate: GraphPredicate.Contains,
    scope: {},
    confidence: 0.8,
    method: 'parser@1.0.0',
    author: actor,
    lifecycle_state: KnowledgeLifecycleState.Candidate,
    provenance: {
      source_revision: 'git:abc123',
      method: 'parser@1.0.0',
      processed_at: '2026-07-31T12:00:00Z',
    },
    command,
  };
  assert.equal(isAssertion({ ...base, value: 'compras' }), true);
  assert.equal(isAssertion(base), false);
  assert.equal(isAssertion({ ...base, value: 'compras', predicate: 'inventado' }), false);
});

void test('cada validador Ajv aceita o contrato correspondente e rejeita campos obrigatórios ausentes', () => {
  const entityReference = { entity_id: id, revision_id: id };
  const commonProvenance = {
    source_revision: 'git:abc123',
    method: 'parser@1.0.0',
    processed_at: '2026-07-31T12:00:00Z',
  };
  const examples = {
    approval: {
      schema_version: '1.0.0',
      approval_id: id,
      subject: { kind: 'assertion', id },
      decision: 'approved',
      decided_at: '2026-07-31T12:00:00Z',
      decided_by: actor,
      reason: 'Evidência suficiente.',
      provenance: commonProvenance,
      command,
    },
    assertion: {
      schema_version: '1.0.0',
      assertion_id: id,
      subject: entityReference,
      predicate: GraphPredicate.Contains,
      object: entityReference,
      scope: {},
      confidence: 0.9,
      method: 'parser@1.0.0',
      author: actor,
      lifecycle_state: KnowledgeLifecycleState.Candidate,
      provenance: commonProvenance,
      command,
    },
    'audit-entry': {
      schema_version: '1.0.0',
      audit_entry_id: id,
      occurred_at: '2026-07-31T12:00:00Z',
      action: 'assertion.approved',
      actor,
      scope: {},
      subject: { kind: 'assertion', id },
      reason: 'Evidência suficiente.',
      correlation_id: id,
      command,
    },
    'domain-event': {
      event_id: id,
      event_type: 'KnowledgeRevision.Created',
      schema_version: '1.0.0',
      occurred_at: '2026-07-31T12:00:00Z',
      correlation_id: id,
      actor,
      scope: {},
      entity: entityReference,
      command,
    },
    evidence: {
      schema_version: '1.0.0',
      evidence_id: id,
      kind: 'chunk',
      pointer: 's3://atlas/artifacts/a#chunk-1',
      checksum: 'sha256:abcdef',
      provenance: commonProvenance,
      command,
    },
    'knowledge-entity': {
      schema_version: '1.0.0',
      entity_id: id,
      entity_type: 'Processo',
      stable_key: 'compras.cancelamento',
      lifecycle_state: KnowledgeLifecycleState.Candidate,
      provenance,
      command,
    },
    'knowledge-revision': {
      schema_version: '1.0.0',
      revision_id: id,
      entity_id: id,
      source_observation_id: id,
      source_revision: 'git:abc123',
      lifecycle_state: KnowledgeLifecycleState.Candidate,
      method: 'parser@1.0.0',
      confidence: 0.9,
      processed_at: '2026-07-31T12:00:00Z',
      checksum: 'sha256:abcdef',
      command,
    },
    'source-observation': {
      schema_version: '1.0.0',
      observation_id: id,
      external_id: 'github:acme/atlas:README.md',
      source_type: 'repository_file',
      uri: 'https://github.com/acme/atlas/blob/main/README.md',
      revision: 'abc123',
      observed_at: '2026-07-31T12:00:00Z',
      content: '# Atlas',
      checksum: 'sha256:abcdef',
      metadata: {},
      classification: 'internal',
      command,
    },
  } as const;

  for (const [name, example] of Object.entries(examples) as [
    keyof typeof examples,
    (typeof examples)[keyof typeof examples],
  ][]) {
    const valid = validateContract(name, example);
    assert.equal(valid.valid, true, `${name} deveria ser válido`);
    const missingRequired = Object.fromEntries(
      Object.entries(example).filter(([key]) => key !== 'schema_version'),
    );
    assert.equal(
      validateContract(name, missingRequired).valid,
      false,
      `${name} deveria exigir schema_version`,
    );
  }

  assert.equal(isApproval(examples.approval), true);
  assert.equal(isAuditEntry(examples['audit-entry']), true);
  assert.equal(isDomainEvent(examples['domain-event']), true);
  assert.equal(isEvidence(examples.evidence), true);
  assert.equal(isKnowledgeRevision(examples['knowledge-revision']), true);
  assert.equal(isSourceObservation(examples['source-observation']), true);
});

void test('expõe famílias e cores normativas do protótipo', () => {
  assert.equal(EntityFamily.Provenance, 'Proveniência');
  assert.equal(entityTypeTokenColor[GraphEntityType.Process], 'var(--violet)');
  assert.equal(entityTypeTokenColor[GraphEntityType.Rule], 'var(--warn)');
  assert.equal(entityTypeTokenColor[GraphEntityType.Endpoint], 'var(--acc)');
  assert.equal(entityTypeTokenColor[GraphEntityType.Document], 'var(--tx2)');
});
