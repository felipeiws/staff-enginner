# API de busca híbrida v1

## Objetivo

`GET /api/v1/search` expõe a busca de conhecimento publicado para a tela Busca do protótipo. Recebe `q`, `organization` obrigatório e, opcionalmente, `application`, `module`, `entity_type[]`, `freshness[]`, `owner[]`, `page_size` e `embedding[]`. O embedding é produzido fora do request por worker/provedor configurado; sua ausência mantém a resposta lexical disponível.

## Fluxo e resposta

Antes de chamar o índice, a API autoriza `knowledge.read` no escopo solicitado e transforma as concessões em teto de classificação. O Typesense recebe somente esse filtro. As recuperações lexical e vetorial são feitas em paralelo quando há embedding; a resposta usa Reciprocal Rank Fusion (`rrf_k` configurável, padrão 60), preservando rank, pontuação RRF e score bruto por origem. Rerank permanece opcional e a resposta não depende dele.

Como defesa em profundidade, a resposta descarta novamente documentos fora do
teto autorizado antes de calcular resultados, `type_counts` ou facetas. Assim,
um índice atrasado ou adaptador incorreto não revela existência de conteúdo
restrito.

Cada resultado contém `type` (nome e token de cor), `path`, `freshness`, `title`, `excerpt.pre/hit/post`, `metadata`, `related_entities`, evidência (`origin_uri`, revisão de fonte, método, confiança e processamento) e a proveniência do ranking. `type_counts` alimenta os filtros de tipo; `facets.application`, `facets.freshness` e `facets.owner` alimentam a lateral. A projeção inclui somente revisões publicadas.

Consultas sem resultado registram `search.zero_result` com hash da consulta, escopo e correlação, para consumo do dashboard de saúde; o texto não é registrado.

## Riscos e rollback

O índice é derivado e a versão de schema foi elevada para 2; `search:reindex-published` cria e troca o alias de maneira atômica. Rerank e geração de embedding não bloqueiam resposta. A rota pode ser removida sem mutar conhecimento, e o alias anterior permanece rollback operacional.

## Referências

[Search Engine](../../search-engine/README.md), [API v1](api-v1.md), [ADR-004](../../adr/004-typesense-search.md), [ADR-010](../../adr/010-identity-and-authorization.md).
