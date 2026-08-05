/* Gerado de packages/contracts/schemas/v1/knowledge-entity.json. Não edite manualmente. */

export type LifecycleState =
  'observed' | 'draft' | 'candidate' | 'review' | 'published' | 'superseded' | 'retracted';

export interface KnowledgeEntityV1 {
  schema_version: '1.0.0';
  entity_id: string;
  entity_type: string;
  stable_key: string;
  lifecycle_state: LifecycleState;
  provenance: Provenance;
  command: Command;
  [k: string]: unknown | undefined;
}
export interface Provenance {
  source_observation_id: string;
  source_revision: string;
  method: string;
  confidence: number;
  processed_at: string;
  [k: string]: unknown | undefined;
}
export interface Command {
  idempotency_key: string;
  actor: {
    actor_id: string;
    actor_type: 'human' | 'service' | 'plugin' | 'worker';
    [k: string]: unknown | undefined;
  };
  [k: string]: unknown | undefined;
}
