/* Gerado de packages/contracts/schemas/v1/visual-artifact-result.json. Não edite manualmente. */

export interface VisualArtifactReadResultV1 {
  schema_version: '1.0.0';
  event_type: 'VisualArtifactRead';
  task_id: string;
  scope: {
    [k: string]: string | undefined;
  };
  screen_key: string;
  variant: 'desktop' | 'mobile';
  kind: 'print' | 'video';
  content_base64: string;
  content_hash: string;
  media_type: string;
  thumbnail_base64?: string;
  duration_seconds?: number;
  dimensions?: {
    [k: string]: unknown | undefined;
  };
  provenance: {
    [k: string]: unknown | undefined;
  };
  ocr_evidence: {
    [k: string]: unknown | undefined;
  };
  [k: string]: unknown | undefined;
}
