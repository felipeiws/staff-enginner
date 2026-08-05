/* Gerado de packages/contracts/schemas/v1/assertion.json. Não edite manualmente. */

export type AssertionV1 = {
  schema_version: '1.0.0';
  assertion_id: string;
  subject: EntityReference;
  predicate: Predicate;
  object?: EntityReference;
  value?: unknown;
  scope: {
    [k: string]: unknown | undefined;
  };
  confidence: number;
  method: string;
  author: Actor;
  lifecycle_state: LifecycleState;
  provenance: Provenance;
  evidence_ids?: string[];
  command: Command;
  [k: string]: unknown | undefined;
} & {
  [k: string]: unknown | undefined;
};
export type Predicate =
  | 'contains'
  | 'belongs_to'
  | 'implements'
  | 'calls'
  | 'exposes'
  | 'consumes'
  | 'produces'
  | 'reads'
  | 'writes'
  | 'governs'
  | 'documents'
  | 'depends_on'
  | 'supersedes'
  | 'owned_by'
  | 'related_to'
  | 'contradicts';
export type LifecycleState =
  'observed' | 'draft' | 'candidate' | 'review' | 'published' | 'superseded' | 'retracted';

export interface EntityReference {
  entity_id: string;
  revision_id?: string;
  [k: string]: unknown | undefined;
}
export interface Actor {
  actor_id: string;
  actor_type: 'human' | 'service' | 'plugin' | 'worker';
  [k: string]: unknown | undefined;
}
export interface Provenance {
  source_revision: string;
  method: string;
  processed_at: string;
  [k: string]: unknown | undefined;
}
export interface Command {
  idempotency_key: string;
  actor: Actor;
  [k: string]: unknown | undefined;
}
