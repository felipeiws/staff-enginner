# API de saúde do conhecimento v1

## Objetivo

Fornecer à tela **Saúde do conhecimento** do protótipo uma projeção reproduzível
do Knowledge Graph e da auditoria. O endpoint não armazena score e não cria ou
publica conhecimento.

## Contrato

`GET /api/v1/knowledge-health?scope={uuid}&period=30d` requer principal
autenticado, `knowledge.read`, classificação `interno` e período exatamente
`30d`. Retorna quatro métricas com valor, variação, token de tendência e nove
pontos de série; dívida de conhecimento com valor, total e percentual; até três
lacunas; insight de correlação de tickets; e proveniência agregada. O UUID é da
entidade de contexto e não uma chave interna exposta de uma tabela de negócio.

Campos são aditivos em v1. A resposta é cacheada por cinco minutos e o
consumidor idempotente de eventos invalida apenas a chave do escopo afetado. O
rollback remove a rota e consumidor, sem apagar grafo, lacunas ou auditoria.

A projeção é calculada para o principal autorizado: revisões cuja classificação
ultrapassa o teto da concessão são removidas antes de qualquer métrica, dívida,
contagem ou insight. A chave de cache inclui a identidade e concessões para não
reutilizar um agregado mais privilegiado.

## Referências

[Saúde do conhecimento](../../governance/knowledge-health.md),
[API v1](api-v1.md), [ADR-003](../../adr/003-postgresql-system-of-record.md),
[ADR-006](../../adr/006-knowledge-graph-provenance.md) e
[ADR-010](../../adr/010-identity-and-authorization.md).
