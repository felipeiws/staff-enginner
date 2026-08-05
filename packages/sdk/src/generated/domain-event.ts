/* Gerado de packages/contracts/schemas/v1/domain-event.json. Não edite manualmente. */

export interface DomainEventEnvelopeV1 {
  event_id: string;
  event_type: string;
  schema_version: '1.0.0';
  occurred_at: string;
  correlation_id: string;
  actor: Actor;
  scope: {
    [k: string]: unknown | undefined;
  };
  entity: EntityReference;
  payload?: {
    [k: string]: unknown | undefined;
  };
  command: Command;
  [k: string]: unknown | undefined;
}
export interface Actor {
  actor_id: string;
  actor_type: 'human' | 'service' | 'plugin' | 'worker';
  [k: string]: unknown | undefined;
}
export interface EntityReference {
  entity_id: string;
  revision_id?: string;
  [k: string]: unknown | undefined;
}
export interface Command {
  idempotency_key: string;
  actor: Actor;
  [k: string]: unknown | undefined;
}
