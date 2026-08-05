/* Gerado de packages/contracts/schemas/v1/source-observation.json. Não edite manualmente. */

export type SourceObservationV1 = {
  [k: string]: unknown | undefined;
} & {
  schema_version: '1.0.0';
  observation_id: Id;
  /**
   * Identificador estável na fonte externa.
   */
  external_id: string;
  source_type: string;
  uri: string;
  revision: string;
  observed_at: string;
  content?: string;
  content_ref?: ContentReference;
  checksum: Checksum;
  metadata: {
    [k: string]: unknown | undefined;
  };
  classification: string;
  command: CommandContext;
  [k: string]: unknown | undefined;
};
export type Id = string;
export type Checksum = string;

export interface ContentReference {
  uri: string;
  checksum: Checksum;
  media_type?: string;
  [k: string]: unknown | undefined;
}
export interface CommandContext {
  idempotency_key: string;
  actor: ActorContext;
  correlation_id?: string;
  [k: string]: unknown | undefined;
}
export interface ActorContext {
  actor_id: string;
  actor_type: 'human' | 'service' | 'plugin' | 'worker';
  display_name?: string;
  [k: string]: unknown | undefined;
}
