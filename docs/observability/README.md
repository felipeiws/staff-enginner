# Observabilidade

A Fase 1 usa sinais operacionais separados da auditoria. Todo request da API recebe ou cria `correlation_id`; Portal, API e worker propagam W3C `traceparent`. A API registra spans de borda e o Portal emite spans JSON sem pergunta, contexto, resposta ou credenciais. O worker preserva o `correlation_id` da tarefa nos logs JSON. Esses spans formam o caminho crítico **ingestão → política → publicação → projeção → busca → Copiloto**; instrumentações de domínio futuras devem manter os mesmos IDs, sem colocar conteúdo de conhecimento em atributos.

Logs estruturados são JSON no `stderr` (`LOG_STACK=json_stderr`). `RedactingJsonFormatter` remove chaves sensíveis (`token`, `secret`, `password`, `content`, `body`, `prompt`) e limita strings. Logs não substituem `audit_log`, que continua sendo a trilha imutável e sanitizada de decisões.

## Métricas e SLOs iniciais

`GET /metrics` expõe Prometheus. O worker disponibiliza seu endpoint no `WORKER_HEALTH_PORT` (`/health` e `/metrics`). Sinais atuais:

- `atlas_search_projection_lag_milliseconds`, `atlas_outbox_dlq_size`, profundidade/idade de filas via Redis exporter planejado e `atlas_worker_in_flight`;
- `atlas_plugin_errors_total`, tarefas/falhas do worker e DLQ da outbox;
- amostras `atlas_search_latency_milliseconds` e `atlas_copilot_latency_milliseconds` (Grafana calcula p50/p95 no backend de séries), custo e tokens em `atlas_copilot_cost_usd` e `atlas_copilot_tokens_total`;
- `atlas_approval_proposals_pending` e `atlas_approval_duration_seconds`.

SLOs iniciais: projeção p95 abaixo de 5 minutos; busca p95 abaixo de 800 ms; Copiloto p95 abaixo de 12 s; DLQ igual a zero; e propostas pendentes com idade p95 menor que 48 h. São alvos operacionais, não evidência de qualidade do conhecimento.

Alertar quando houver DLQ por 5 min, lag de projeção acima de 5 min por 10 min, taxa de erro de plugin acima de 5% por 15 min, busca/Copiloto fora do SLO por 10 min, orçamento de IA acima de 80%, ou propostas pendentes por mais de 48 h. Um erro único é registrado, mas não pagina.

## Ambiente local

`docker compose -f docker/docker-compose.yml up prometheus tempo grafana` sobe Prometheus (`:9090`), Tempo (`:3200`) e Grafana (`:3000`) com o dashboard versionado em [`docker/observability/dashboards/atlas-platform.json`](../../docker/observability/dashboards/atlas-platform.json). Os processos do backend e worker continuam executados no host nas portas 8000 e 8081, respectivamente. Para produção, o coletor OpenTelemetry deve receber OTLP em `4317/4318` e aplicar retenção/controle de acesso antes de qualquer exportação externa.
