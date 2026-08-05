/* Gerado de packages/contracts/schemas/v1/worker-task.json. Não edite manualmente. */

export interface WorkerTaskV1 {
  schema_version: '1.0.0';
  task_id: string;
  task_type: string;
  idempotency_key: string;
  correlation_id: string;
  scope: {
    [k: string]: string | undefined;
  };
  deadline_at: string;
  attempts?: number;
  payload: {
    [k: string]: unknown | undefined;
  };
  [k: string]: unknown | undefined;
}
