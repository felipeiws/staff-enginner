/* Gerado de packages/contracts/schemas/v1/code-analysis-result.json. Não edite manualmente. */

export interface CodeAnalysisResultV1 {
  schema_version: '1.0.0';
  event_type: 'CodeAnalyzed';
  task_id: string;
  revision_id: string;
  scope: {
    [k: string]: string | undefined;
  };
  artifact_uri: string;
  provenance: {
    [k: string]: unknown | undefined;
  };
  execution: {
    kind: 'static_analysis';
    [k: string]: unknown | undefined;
  };
  /**
   * @minItems 1
   */
  candidates: [
    {
      entity_type:
        | 'Controller'
        | 'Service'
        | 'Job'
        | 'Evento'
        | 'Listener'
        | 'Model'
        | 'Tabela'
        | 'Migration'
        | 'Endpoint'
        | 'Tela';
      lifecycle_state: 'candidate';
      evidence: Evidence;
      [k: string]: unknown | undefined;
    },
    ...{
      entity_type:
        | 'Controller'
        | 'Service'
        | 'Job'
        | 'Evento'
        | 'Listener'
        | 'Model'
        | 'Tabela'
        | 'Migration'
        | 'Endpoint'
        | 'Tela';
      lifecycle_state: 'candidate';
      evidence: Evidence;
      [k: string]: unknown | undefined;
    }[],
  ];
  assertions: {
    predicate: 'calls' | 'reads' | 'writes' | 'produces' | 'consumes' | 'implements' | 'exposes';
    lifecycle_state: 'candidate';
    evidence: Evidence;
    [k: string]: unknown | undefined;
  }[];
  [k: string]: unknown | undefined;
}
export interface Evidence {
  kind: 'artifact';
  pointer: string;
  offsets: {
    unit: 'line';
    [k: string]: unknown | undefined;
  };
  checksum: string;
  commit: string;
  provenance: {
    [k: string]: unknown | undefined;
  };
  [k: string]: unknown | undefined;
}
