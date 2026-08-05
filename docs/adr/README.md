# Architecture Decision Records

ADRs registram decisões de longa duração no formato: contexto, decisão, alternativas, consequências e status. Status possíveis: `proposed`, `accepted`, `superseded` e `deprecated`. Um ADR aceito não é reescrito; um novo ADR aponta para o anterior.

| ADR                                               | Decisão                                        | Status   |
| ------------------------------------------------- | ---------------------------------------------- | -------- |
| [001](001-laravel-backend.md)                     | Laravel como backend modular                   | Accepted |
| [002](002-python-workers.md)                      | Python para workers especializados             | Accepted |
| [003](003-postgresql-system-of-record.md)         | PostgreSQL como sistema de registro            | Accepted |
| [004](004-typesense-search.md)                    | Typesense para projeção de busca               | Accepted |
| [005](005-plugin-boundaries.md)                   | Extensibilidade por plugins isolados           | Accepted |
| [006](006-knowledge-graph-provenance.md)          | Grafo com proveniência e versões               | Accepted |
| [007](007-markdown-source-of-truth.md)            | Markdown como fonte de verdade documental      | Accepted |
| [008](008-open-source-license.md)                 | Apache-2.0 como licença recomendada            | Proposed |
| [009](009-observed-ingestion-lifecycle.md)        | Estado `observed` para recepção de observações | Accepted |
| [010](010-identity-and-authorization.md)          | Identidade e autorização antes da recuperação  | Accepted |
| [011](011-portal-stack.md)                        | Vite, React e TypeScript estrito no Portal     | Accepted |
| [012](012-embedding-model.md)                     | Modelo de embeddings versionado e reindexável  | Accepted |
| [013](013-plugin-runtime-isolation.md)            | Execução de plugins em runtime isolado         | Accepted |
| [014](014-portal-and-identity-review-criteria.md) | Critérios de revisão de Portal e identidade    | Accepted |
