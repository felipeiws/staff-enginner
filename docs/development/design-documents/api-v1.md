# API HTTP v1

## Problema e objetivo

A plataforma precisa de uma borda HTTP estável antes de expor casos de uso de produto. Esta proposta define convenções comuns para `/api/v1`, sem criar recursos de conhecimento nem expor tabelas PostgreSQL.

## Não objetivos

Não define endpoints de domínio, schemas de escrita de entidades, buscas ou administração de identidade. Cada família de recursos exigirá Design Document próprio antes de ser adicionada.

## Contexto e evidências

O [Knowledge Engine](../../knowledge-engine/README.md) exige comandos idempotentes e trilha auditável; a [Segurança](../../security/architecture.md) exige autorização antes da recuperação; e o [ADR-010](../../adr/010-identity-and-authorization.md) limita todo principal por escopo e classificação.

## Proposta e contratos

- Toda rota pública nova usa `/api/v1`; `/api/me` permanece apenas como compatibilidade da sessão já entregue e não é base para recursos novos.
- Respostas de erro seguem RFC 9457 com `application/problem+json`, `code` de domínio e `correlation_id`. O cabeçalho `X-Correlation-Id` é aceito quando válido ou gerado e devolvido em toda resposta v1.
- Consultas de coleção usarão cursor opaco estável, baseado na tupla ordenável `(sort, id)`, e `page_size` de 1 a 100. O cursor não contém dados de autorização.
- Todo comando (`POST`, `PUT`, `PATCH`, `DELETE`) exigirá `Idempotency-Key`. A chave é isolada por ator, método e rota; mesma chave e payload retorna a resposta gravada, payload distinto retorna `409 command.idempotency_key_reused`.
- O rate limit é contado por ator e escopo. Sem principal, a chave é o IP e o escopo público; o limite inicial é 120 requisições por minuto.
- A negociação aceita JSON e `application/problem+json`; corpos de comando são `application/json`.
- Requests especializam `ApiRequest`; Resources especializam `ApiResource` e expõem modelos de leitura, nunca modelos Eloquent ou schemas de tabelas.
- Operações anotadas com `ApiOperation` geram o documento OpenAPI 3.1 em `/api/v1/openapi.json`. A primeira operação publicada é `GET /api/v1/me`.

## Alternativas consideradas

Usar rotas sem versão dificultaria evolução incompatível. Gerar OpenAPI de modelos Eloquent acoplaria a API à persistência. JWTs de longa duração não substituem a autorização por escopo decidida no ADR-010.

## Dados, segurança e privacidade

Toda recuperação protegida continua passando pela porta de autorização antes da consulta. Classificação é parte dessa decisão e nenhum cursor a substitui. A camada HTTP não persiste conhecimento; futuros comandos devem preservar origem, revisão, método e confiança nos contratos do Knowledge Engine. Chaves de idempotência e correlação são metadados operacionais, não conteúdo de conhecimento.

## Operação: métricas, alertas e falhas

Medir erros por `code`, respostas 429, replays/conflitos de idempotência, latência por rota e cobertura de `correlation_id`. Falhas de serialização ou validação retornam problemas estruturados; falhas 5xx não são armazenadas como resultados idempotentes.

## Compatibilidade, migração e rollback

`v1` é explícita no path. Campos de resposta são aditivos dentro da versão; remoções ou mudança semântica exigem nova versão. `/api/me` será documentada como legado até uma janela de migração definida por sua API consumidora. O rollback remove rotas v1 sem tocar dados de domínio; clientes devem tratar 404 de recursos ainda não publicados.

## Limites, retenção e depreciação

O limite padrão é 120 requisições/minuto por ator+escopo, `page_size` máximo é 100 e chaves de idempotência expiram em 24 horas no cache operacional. Não há retenção de payload de domínio nesta tarefa. Deprecações serão anunciadas em OpenAPI e por cabeçalhos `Deprecation`/`Sunset` antes de retirar uma rota.

## Riscos e questões em aberto

A persistência distribuída de idempotência e a política de limites por plano serão reavaliadas antes de tráfego multi-região. Cada endpoint futuro definirá autorização, classificação, consumidores e retenção no seu Design Document.

## Decisões/ADRs relacionados

[ADR-001](../../adr/001-laravel-backend.md), [ADR-003](../../adr/003-postgresql-system-of-record.md), [ADR-006](../../adr/006-knowledge-graph-provenance.md) e [ADR-010](../../adr/010-identity-and-authorization.md).
