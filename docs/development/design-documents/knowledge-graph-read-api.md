# API de leitura do Knowledge Graph

## Problema e objetivo

O Portal precisa diagnosticar lacunas no conhecimento a partir de uma entidade concreta, como a tela **Knowledge Graph** do protótipo. A API oferece quatro consultas autoritativas e limitadas: vizinhança, órfãos, detalhe do nó e diagnóstico do cluster.

## Não objetivos

Não expõe coordenadas de visualização, não é uma API de mapa global, não cria ou altera assertions e não transforma o diagnóstico em decisão automática de IA.

## Contexto e evidências

O protótipo em [`../../design/prototype/atlas-prototype.html`](../../design/prototype/atlas-prototype.html) (linhas 793–849 e 1158–1195) determina foco, profundidade 1, agrupamento por tipo, painel lateral e diagnóstico. A nota de UX na linha 1403 torna explícito que o Graph é ferramenta de diagnóstico, não um mapa do universo.

## Proposta e contratos

Todas as rotas exigem principal autenticado com `knowledge.read`, verificam escopo e classificação antes da recuperação e seguem `/api/v1`.

- `GET /graph/neighborhood?entity={uuid}&depth=1&group_by=type`: `depth` aceita 1–3, com padrão 1. Retorna `focus`, `depth`, `group_by`, `nodes` e `edges`. Cada nó tem `id`, `type`, `label`, `color` (token CSS do protótipo), `weight` (raio sugerido) e `provenance`; cada aresta tem `origin`, `destination`, `predicate`, `weight` e proveniência. O Portal calcula o layout.
- `GET /graph/orphans`: retorna entidades publicadas que estejam sem relação, sem responsável ou com relações sem evidência, inclusive motivos e proveniência.
- `GET /graph/nodes/{id}`: retorna descrição, contagem de relações visíveis, responsável, data de atualização e proveniência para o painel lateral.
- `GET /graph/diagnosis?scope={uuid}`: avalia o cluster de profundidade 1 e informa regra sem responsável, tela sem print e evento com DLQ ativa. Problemas são sinais determinísticos; eventuais sugestões do Copiloto continuam candidatas sujeitas à governança.

Os IDs são UUIDs estáveis. Campos são aditivos em v1. Um recurso inexistente ou inacessível devolve o mesmo 403.

## Alternativas consideradas

Uma API de grafo sem limite torna a navegação cara e contradiz o fluxo diagnóstico. Delegar coordenadas ao backend acoplaria a borda ao renderer do Portal. Consultar Typesense perderia a autoridade e as qualificações das assertions publicadas.

## Dados, segurança e privacidade

As leituras usam somente entidades e assertions publicadas no PostgreSQL, com origem, revisão, método, confiança e processamento em cada resultado. A autorização por escopo/classificação é feita antes de retornar nós, arestas ou metadados. Eventos em DLQ são reportados apenas quando sua entidade pertence ao cluster já autorizado.

## Operação: métricas, alertas e falhas

Medir latência por rota e profundidade, tamanho de nós/arestas, 403, taxa de órfãos e contagem de problemas por regra. A profundidade máxima 3 limita o custo síncrono; o rollback remove apenas as rotas e o modelo de leitura.

## Compatibilidade, migração e rollback

Não há migração: o modelo consulta as views autoritativas existentes. Remover as rotas e o serviço não altera entidades, assertions, evidências ou outbox.

## Riscos e questões em aberto

Diagnóstico de print depende de `attributes.detail.desktop` da revisão publicada; conectores devem preencher essa referência com proveniência. O volume de órfãos é intencionalmente uma consulta diagnóstica sem paginação nesta primeira versão e deve ser reavaliado com métricas reais.

## Decisões/ADRs relacionados

[API HTTP v1](api-v1.md), [Knowledge Engine](../../knowledge-engine/README.md), [Knowledge Graph](../../database/knowledge-graph.md), [ADR-003](../../adr/003-postgresql-system-of-record.md), [ADR-006](../../adr/006-knowledge-graph-provenance.md) e [ADR-010](../../adr/010-identity-and-authorization.md).
