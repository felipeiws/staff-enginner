/* Gerado de packages/contracts/schemas/v1/plugin-manifest.json. Não edite manualmente. */

export interface AtlasPluginManifestV1 {
  schema_version: '1.0.0';
  id: string;
  version: string;
  platform_compatibility: string;
  runtime: 'container' | 'python_worker';
  /**
   * @minItems 1
   */
  capabilities: [
    (
      | 'source.connector'
      | 'content.parser'
      | 'code.analyzer'
      | 'artifact.reader'
      | 'enrichment.provider'
    ),
    ...(
      | 'source.connector'
      | 'content.parser'
      | 'code.analyzer'
      | 'artifact.reader'
      | 'enrichment.provider'
    )[],
  ];
  configurations: {
    [k: string]: unknown | undefined;
  };
  /**
   * @minItems 1
   */
  permissions: [
    'artifact.read' | 'network.egress' | 'secrets.read' | 'webhook.receive',
    ...('artifact.read' | 'network.egress' | 'secrets.read' | 'webhook.receive')[],
  ];
  input_schema: {
    [k: string]: unknown | undefined;
  };
  output_schema: {
    [k: string]: unknown | undefined;
  };
  egress_allowlist?: string[];
  limits: {
    max_concurrency: number;
    timeout_seconds: number;
    max_payload_bytes: number;
    cpu_millicores: number;
    memory_mebibytes: number;
    [k: string]: unknown | undefined;
  };
  retry_policy: {
    max_attempts: number;
    backoff_seconds: number;
    [k: string]: unknown | undefined;
  };
  [k: string]: unknown | undefined;
}
