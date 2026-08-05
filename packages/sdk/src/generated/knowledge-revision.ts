/* Gerado de packages/contracts/schemas/v1/knowledge-revision.json. Não edite manualmente. */

export type LifecycleState =
  'observed' | 'draft' | 'candidate' | 'review' | 'published' | 'superseded' | 'retracted';

export interface KnowledgeRevisionV1 {
  schema_version: '1.0.0';
  revision_id: string;
  entity_id: string;
  source_observation_id: string;
  source_revision: string;
  lifecycle_state: LifecycleState;
  method: string;
  confidence: number;
  processed_at: string;
  checksum: string;
  content_ref?: {
    [k: string]: unknown | undefined;
  };
  command: Command;
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
