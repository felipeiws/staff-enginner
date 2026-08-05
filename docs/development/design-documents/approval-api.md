# API de aprovações v1

## Objetivo

Expor ao Portal a fila de propostas candidatas, o detalhe para revisão humana e os comandos de editar, pedir ajuste à IA, aprovar e rejeitar. A superfície reproduz os dados da tela de Aprovações do [protótipo](../../design/prototype/atlas-prototype.html) e não torna uma sugestão de IA autoritativa.

## Contrato HTTP

- `GET /api/v1/approvals` retorna propostas pendentes visíveis ao principal com título, autor, origem, idade, cor do tipo, proveniência e o agrupamento `low_impact_auto_approval`. Propostas de impacto `baixo` com `auto_approve_after_hours` ficam agrupadas; a política de 48h ainda as mantém candidatas.
- `GET /api/v1/approvals/{id}` retorna análise de IA e confiança, diff `current`/`proposed`, impacto e proveniência.
- `POST /api/v1/approvals/{id}/approve` exige `justification`; abre o PR pelo conector, publica a revisão só depois da referência externa e retorna `pull_request.reference`, `commit` e a meta de reindexação de 40s.
- `POST /api/v1/approvals/{id}/reject` exige `reason` e retrata a revisão em `review`.
- `POST /api/v1/approvals/{id}/request-ai-revision` exige `instruction` e registra a solicitação auditável; não publica conteúdo.
- `PATCH /api/v1/approvals/{id}` exige `proposed_text` e registra snapshots anterior/posterior por `AuditTrail`.

Todos os comandos exigem `Idempotency-Key`, usam os problemas RFC 9457 da [API v1](api-v1.md) e requerem `knowledge.approve`, concedida ao papel `aprovador_de_dominio` dentro do escopo e teto de classificação aplicáveis. Leituras requerem `knowledge.read` antes de recuperar a proposta. O conteúdo retorna origem, revisão, método, confiança e data de processamento.

## Operação, compatibilidade e rollback

São medidos erros por código, replays de idempotência e latência por rota segundo a API v1. A referência de PR é criada pelo adaptador externo antes da transação local: em falha, a proposta continua pendente. Campos são aditivos em v1. O rollback remove as rotas; não remove decisões, auditoria ou revisões.

## Riscos e decisões relacionadas

O prazo de reindexação é uma meta operacional, não confirmação de projeção publicada. A política continua fonte da decisão de autoaprovação; este endpoint apenas a apresenta. Ver [Governança](../../governance/README.md), [ADR-006](../../adr/006-knowledge-graph-provenance.md) e [ADR-010](../../adr/010-identity-and-authorization.md).
