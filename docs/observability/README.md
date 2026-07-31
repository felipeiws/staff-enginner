# Observabilidade

O sistema precisa explicar não apenas falhas, mas como uma conclusão foi produzida. Logs, métricas e traces compartilham `correlation_id`, `source_id`, `revision_id`, `plugin_id` e `job_id` quando aplicável.

Métricas alvo: lag de ingestão/projeção, sucesso/retry/DLQ por conector, duração e custo por worker/modelo, freshness de fontes, falhas de parsing, cobertura de evidências, latência/qualidade de busca e decisões de aprovação. Auditoria é separada de logs operacionais e possui retenção/política própria.

Alertas devem se basear em impacto (por exemplo, aumento de lag ou falha contínua de fonte crítica), não em todo erro isolado. Dashboards são documentação executável do contrato operacional e devem acompanhar cada módulo entregue.
