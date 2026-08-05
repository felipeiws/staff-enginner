/* Gerado de packages/contracts/schemas/v1/embedding-result.json. Não edite manualmente. */

export interface EmbeddingGeneratedResultV1 {
  schema_version: '1.0.0';
  event_type: 'EmbeddingGenerated';
  task_id: string;
  revision_id: string;
  scope: {
    [k: string]: string | undefined;
  };
  provenance: {
    source_revision_id: string;
    method: string;
    processed_at: string;
    [k: string]: unknown | undefined;
  };
  /**
   * @minItems 1
   */
  chunks: [
    {
      ordinal: number;
      start_offset: number;
      end_offset: number;
      text: string;
      tokens: number;
      checksum: string;
      /**
       * @minItems 1
       */
      embedding: [number, ...number[]];
      model: string;
      model_version: string;
      parameters: {
        [k: string]: unknown | undefined;
      };
      cost?: number | null;
      cost_currency?: string | null;
      [k: string]: unknown | undefined;
    },
    ...{
      ordinal: number;
      start_offset: number;
      end_offset: number;
      text: string;
      tokens: number;
      checksum: string;
      /**
       * @minItems 1
       */
      embedding: [number, ...number[]];
      model: string;
      model_version: string;
      parameters: {
        [k: string]: unknown | undefined;
      };
      cost?: number | null;
      cost_currency?: string | null;
      [k: string]: unknown | undefined;
    }[],
  ];
  [k: string]: unknown | undefined;
}
