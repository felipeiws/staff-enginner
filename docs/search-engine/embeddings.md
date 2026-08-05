# Embeddings e índice vetorial

## Objetivo e fronteiras

Embeddings são uma projeção descartável para busca semântica; não são uma nova fonte de verdade nem uma decisão de IA. O worker Python (ADR-002) recebe uma revisão observada pela fila/contrato, produz chunks e um evento `EmbeddingGenerated` versão `1.0.0`. O `EmbeddingPersistenceService` do módulo Search valida e persiste esse resultado. O worker não acessa PostgreSQL, Typesense nem tabelas internas diretamente.

Cada registro em `chunk_embeddings` referencia um `chunks.id` e preserva modelo, versão do modelo, parâmetros do provedor e do chunking, checksum SHA-256 do texto do chunk, dimensão, custo/moeda e execução quando disponível. O checksum é comparado à linha de `chunks` antes de qualquer upsert. Assim uma mudança de conteúdo, modelo ou versão é detectável e permite reindexar somente os pares `(chunk, modelo, versão)` ausentes ou desatualizados.

## Estratégia de chunking

O worker aplica `regex-v1`, uma estimativa determinística de tokens, com alvo de **800 tokens** e sobreposição de **120 tokens** entre blocos de prosa. Os offsets são offsets de caracteres Unicode no conteúdo observado; `ordinal`, texto, contagem de tokens e checksum são persistidos em `chunks`.

Blocos fenced de código e tabelas Markdown são indivisíveis: não são quebrados nem repetidos pela sobreposição. Se um desses blocos isoladamente exceder 800 tokens, ele é emitido como um único chunk excepcional para não destruir sua evidência. A estimativa serve ao limite previsível; o uso/custo informado pelo provedor fica registrado nos parâmetros da execução.

## Modelo, custo e armazenamento

O alvo inicial é `text-embedding-3-small`, versão de configuração `2024-01-25`, com 1536 dimensões (`SEARCH_EMBEDDING_DIMENSIONS`). Em 31/07/2026, a tabela oficial informa US$ 0,02 por 1 milhão de tokens de entrada; o custo efetivo vem da resposta do provedor e é gravado por embedding em USD, sem tratar estimativas como dado autoritativo. O worker usa o endpoint de embeddings do provedor apenas quando a política de classificação permitir seu envio; conteúdo não autorizado não é recuperado nem enviado.

No PostgreSQL, `chunk_embeddings.embedding` é `vector(1536)` e usa um índice HNSW com `vector_cosine_ops`. SQLite mantém JSON somente para a suíte rápida de testes. A projeção Typesense usa o vetor da primeira evidência/chunk da revisão publicada; Typesense continua derivado e reconstruível.

## Recuperação segura

O chamador resolve autorização antes de chamar `SemanticSearchService`. O serviço exige escopo autorizado e classificações permitidas e inclui ambos no SQL antes de aplicar a distância coseno e o `LIMIT`; portanto vetores fora do escopo não entram no conjunto candidato. Resultados levam `revision_id`, `chunk_checksum`, modelo e versão para manter a citação/proveniência.

## Migração de modelo

1. Registre a proposta com modelo, versão, dimensões, parâmetros, custo e o conjunto de avaliação versionado; não substitua embeddings existentes.
2. Crie uma coleção Typesense com nova versão de schema se a dimensão mudar e configure temporariamente o novo modelo no worker.
3. Reprocesse seletivamente os chunks cujo checksum não possua embedding para `(novo modelo, nova versão)`; monitore custo, lag, recall e MRR/nDCG.
4. Compare o conjunto de fixtures e obtenha revisão humana para trocar a configuração ativa. Reindexe a projeção publicada e faça a troca atômica do alias Typesense.
5. Preserve os vetores antigos para rollback até a retenção aprovada. O rollback restaura modelo/configuração e alias anteriores, sem alterar chunks, revisões ou proveniência.

## Riscos e métricas

Monitorar cobertura de chunks com vetor, falhas/retries do worker, custo por revisão, lag até Typesense, latência p50/p95 e recall/MRR/nDCG. Uma alteração de tokenizador pode modificar checksums e aumentar reprocessamento; por isso o identificador do estimador integra os parâmetros persistidos.
