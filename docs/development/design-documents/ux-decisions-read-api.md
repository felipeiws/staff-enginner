# API de leitura de decisões de UX

## Objetivo

Disponibilizar `GET /api/v1/ux-decisions` para a tela **Decisões de UX** do Portal. A tela lê documentos/ADRs de UX publicados no Knowledge Graph; ela não mantém uma cópia estática de decisões de produto.

## Contrato proposto

A rota exige principal autenticado com `knowledge.read`, resolve escopo e classificação antes da recuperação e retorna `decisions`. Cada item tem `id` estável, `type` (`Documento` ou `ADR`), `title`, `description`, `outcome` e `provenance` (`origin`, `revision`, `method`, `confidence`, `processed_at`). Somente revisões `published` com `attributes.kind = ux_decision` são elegíveis; a ordenação vem de `attributes.ux_decision_order`.

O seed inicial usa a versão do protótipo em [`../../design/prototype/atlas-prototype.html`](../../design/prototype/atlas-prototype.html#L1397-L1404) como fonte versionada e registra método humano declarado e confiança 1,0. Alterações futuras entram como novas revisões, sujeitas à governança normal.

## Limites, operação e rollback

É uma consulta somente de leitura, sem paginação enquanto o conjunto editorial for pequeno. Medir latência, 403 e quantidade de decisões retornadas. Campos são aditivos em v1. O rollback remove rota, modelo de leitura e Portal, sem apagar entidades ou revisões do grafo.

## Decisões relacionadas

[API HTTP v1](api-v1.md), [Knowledge Engine](../../knowledge-engine/README.md), [Governança](../../governance/README.md), [Knowledge Graph](../../database/knowledge-graph.md), [ADR-006](../../adr/006-knowledge-graph-provenance.md) e [ADR-010](../../adr/010-identity-and-authorization.md).
