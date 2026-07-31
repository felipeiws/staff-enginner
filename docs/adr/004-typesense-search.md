# ADR-004: Typesense para projeção de busca

Status: Accepted

## Decisão

Adotar Typesense como índice derivado para texto, filtros, facetas e vetores, com recuperação híbrida e reindexação possível.

## Por quê

Entrega uma superfície operacional menor que alternativas mais extensas e cobre o caso inicial. PostgreSQL não é descartado para consultas autoritativas; Typesense nunca será fonte de verdade.

## Risco

Necessidades futuras de ranking/analytics podem motivar alternativa. A mitigação é manter contrato de projeção e avaliação de busca independentes do fornecedor.
