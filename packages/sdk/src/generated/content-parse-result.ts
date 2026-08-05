/* Gerado de packages/contracts/schemas/v1/content-parse-result.json. Não edite manualmente. */

export interface ContentParsedResultV1 {
  schema_version: '1.0.0';
  event_type: 'ContentParsed';
  task_id: string;
  revision_id: string;
  scope: {
    [k: string]: string | undefined;
  };
  artifact_uri: string;
  provenance: {
    [k: string]: unknown | undefined;
  };
  /**
   * @minItems 1
   */
  chunks: [
    {
      [k: string]: unknown | undefined;
    },
    ...{
      [k: string]: unknown | undefined;
    }[],
  ];
  /**
   * @minItems 1
   */
  candidates: [
    {
      entity_type:
        'Documento' | 'ADR' | 'FAQ' | 'Regra' | 'Aplicação' | 'Endpoint' | 'Schema' | 'Evento';
      lifecycle_state: 'candidate';
      [k: string]: unknown | undefined;
    },
    ...{
      entity_type:
        'Documento' | 'ADR' | 'FAQ' | 'Regra' | 'Aplicação' | 'Endpoint' | 'Schema' | 'Evento';
      lifecycle_state: 'candidate';
      [k: string]: unknown | undefined;
    }[],
  ];
  assertions?: {
    predicate: 'exposes' | 'consumes' | 'produces';
    lifecycle_state: 'candidate';
    confidence: number;
    [k: string]: unknown | undefined;
  }[];
  contract_divergences?: {
    severity: 'breaking';
    [k: string]: unknown | undefined;
  }[];
  [k: string]: unknown | undefined;
}
