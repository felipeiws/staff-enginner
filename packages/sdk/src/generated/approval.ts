/* Gerado de packages/contracts/schemas/v1/approval.json. Não edite manualmente. */

export interface ApprovalV1 {
  schema_version: '1.0.0';
  approval_id: string;
  subject: Subject;
  decision: 'approved' | 'rejected';
  decided_at: string;
  decided_by: Actor;
  reason: string;
  provenance: Provenance;
  command: Command;
  [k: string]: unknown | undefined;
}
export interface Subject {
  kind: 'entity_revision' | 'assertion';
  id: string;
  [k: string]: unknown | undefined;
}
export interface Actor {
  actor_id: string;
  actor_type: 'human';
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
