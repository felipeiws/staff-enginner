/* Gerado de packages/contracts/schemas/v1/documentation-code-divergence-result.json. Não edite manualmente. */

export interface DocumentationCodeDivergenceResultV1 {
  schema_version: '1.0.0';
  event_type: 'DocumentationCodeDivergenceDetected';
  task_id: string;
  scope: {
    [k: string]: string | undefined;
  };
  document_revision_id: string;
  code_revision_id: string;
  provenance: Provenance;
  execution: {
    kind: 'deterministic_comparison';
    [k: string]: unknown | undefined;
  };
  divergences: Divergence[];
  [k: string]: unknown | undefined;
}
export interface Provenance {
  source_revision: string;
  method: string;
  processed_at: string;
  [k: string]: unknown | undefined;
}
export interface Divergence {
  candidate_key: string;
  confidence: number;
  assertion: {
    subject_revision_id: string;
    predicate: 'contradicts';
    object_revision_id: string;
    lifecycle_state: 'candidate';
    confidence: number;
    /**
     * @minItems 2
     */
    evidence: [Evidence, Evidence, ...Evidence[]];
    [k: string]: unknown | undefined;
  };
  proposal: {
    kind: 'documentation_code_divergence';
    subject_kind: 'assertion';
    assertion_candidate_key: string;
    current_text: string;
    proposed_text: string;
    analysis: string;
    /**
     * @minItems 2
     */
    impacted_entities: [
      {
        entity_id: string;
        revision_id: string;
        role: string;
        [k: string]: unknown | undefined;
      },
      {
        entity_id: string;
        revision_id: string;
        role: string;
        [k: string]: unknown | undefined;
      },
      ...{
        entity_id: string;
        revision_id: string;
        role: string;
        [k: string]: unknown | undefined;
      }[],
    ];
    origin: Provenance;
    [k: string]: unknown | undefined;
  };
  [k: string]: unknown | undefined;
}
export interface Evidence {
  pointer: string;
  [k: string]: unknown | undefined;
}
