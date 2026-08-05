/* Gerado de packages/contracts/schemas/v1/audit-entry.json. Não edite manualmente. */

export interface AuditEntryV1 {
  schema_version: '1.0.0';
  audit_entry_id: string;
  occurred_at: string;
  action: string;
  actor: Actor;
  scope: {
    [k: string]: unknown | undefined;
  };
  subject: Subject;
  reason: string;
  correlation_id: string;
  command: Command;
  [k: string]: unknown | undefined;
}
export interface Actor {
  actor_id: string;
  actor_type: 'human' | 'ci' | 'plugin' | 'model' | 'service' | 'worker';
  [k: string]: unknown | undefined;
}
export interface Subject {
  kind: string;
  id: string;
  revision_id?: string;
  [k: string]: unknown | undefined;
}
export interface Command {
  idempotency_key: string;
  actor: Actor;
  [k: string]: unknown | undefined;
}
