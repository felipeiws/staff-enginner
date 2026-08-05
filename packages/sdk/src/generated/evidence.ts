/* Gerado de packages/contracts/schemas/v1/evidence.json. Não edite manualmente. */

export interface EvidenceV1 {
  schema_version: '1.0.0';
  evidence_id: string;
  kind: 'chunk' | 'artifact' | 'event';
  pointer: string;
  offsets?: Offsets;
  checksum: string;
  provenance: Provenance;
  command: Command;
  [k: string]: unknown | undefined;
}
export interface Offsets {
  start?: number;
  end?: number;
  unit?: 'byte' | 'character' | 'line' | 'token';
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
  actor: {
    actor_id: string;
    actor_type: 'human' | 'service' | 'plugin' | 'worker';
    [k: string]: unknown | undefined;
  };
  [k: string]: unknown | undefined;
}
