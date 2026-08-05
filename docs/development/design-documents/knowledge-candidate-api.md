# API de propostas assistidas de conhecimento v1

## Problema e objetivo

`POST /api/v1/copilot/knowledge-candidates` transforma uma execução citável do Copiloto em uma proposta candidata de FAQ ou em uma atualização de um Processo existente. `GET /api/v1/insights` alimenta os cards de Divergência, Lacuna e Sugestão da Home.

## Proposta e contratos

O comando exige sessão, `Idempotency-Key`, `execution_id`, escopo e `kind` (`faq` ou `process_update`). A execução preserva as fontes citáveis; a geração revalida escopo, classificação e URI/revisão de cada fonte, calcula confiança por combinação independente e só cria `entity_revision` em `review` com uma `approval` pendente. A proposta inclui `source_citations`, método, modelo/configuração, processamento e agrupamento de tickets, perguntas repetidas e respostas bem avaliadas quando disponíveis.

`process_update` exige `process_entity_id` de uma entidade `Processo` pré-existente. Não há valor de comando que crie Processo: a IA apenas sugere atualização para revisão humana.

`GET /api/v1/insights` retorna `insights[]` com `type`, `color`, `title`, `description` e `action`, usando os tokens `var(--danger)`, `var(--warn)` e `var(--acc)` do protótipo.

## Dados, segurança e rollback

As citações são ancoradas em revisões de fonte e a proposta é auditada por `AuditTrail`. A autorização ocorre antes de recuperar a execução e para cada classificação citada. O rollback remove as rotas; candidatos, aprovações e auditoria existentes permanecem rastreáveis.

## Decisões/ADRs relacionados

[API v1](api-v1.md), [Governança](../../governance/README.md), [ADR-006](../../adr/006-knowledge-graph-provenance.md) e [ADR-010](../../adr/010-identity-and-authorization.md).
