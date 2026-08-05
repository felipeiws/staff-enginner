# ADR-012: Modelo de embeddings versionado e reindexável

Status: Accepted

## Contexto

A busca semântica precisa produzir vetores com qualidade, custo e proveniência verificáveis. Tratar o vetor como dado definitivo ou substituir vetores quando o modelo muda impediria explicar uma recuperação, comparar qualidade e reverter com segurança.

## Alternativas descartadas

- Usar somente busca lexical elimina recuperação por proximidade semântica e não atende consultas cujo vocabulário diverge da fonte.
- Gravar um único vetor mutável por chunk simplifica armazenamento, mas perde a relação com modelo, versão e parâmetros, e torna rollback impossível.
- Executar o provedor de embeddings no backend Laravel acopla I/O de IA ao caminho transacional e contraria o ADR-002.
- Adotar um banco vetorial como sistema de registro acrescenta consistência distribuída sem substituir a necessidade de chunks, revisões e evidências no PostgreSQL.

## Decisão

O worker Python gera embeddings como projeção candidata e devolve o resultado versionado à API. A configuração ativa inicial é `text-embedding-3-small`, versão `2024-01-25`, com 1536 dimensões, chunking `regex-v1` de 800 tokens e sobreposição de 120. O backend persiste cada vetor em `chunk_embeddings`, identificado por chunk, checksum, modelo, versão e parâmetros; PostgreSQL com pgvector é a persistência pesquisável e Typesense recebe apenas uma projeção publicada.

Uma troca de modelo cria vetores paralelos, é avaliada com o conjunto de qualidade versionado, recebe revisão humana e reindexa uma coleção Typesense nova antes da troca atômica de alias. Vetores anteriores são preservados pelo período de retenção aprovado.

## Consequências

Há custo de armazenamento e de reprocessamento, e o contrato entre worker e API precisa validar dimensão, checksum e proveniência. Em troca, cada resultado semântico é rastreável, a mudança de fornecedor ou configuração é mensurável e o rollback não modifica fontes, chunks ou revisões.

Conteúdo só é enviado ao provedor quando classificação e autorização o permitem. Embeddings não concedem acesso, não publicam conhecimento e não são autoridade de IA.

## Critérios de revisão

Revisar esta decisão antes de trocar modelo, dimensão, tokenizador/chunker, provedor ou store vetorial; também quando a avaliação mostrar regressão material de recall, MRR/nDCG, latência, custo ou cobertura de citações. A proposta deve incluir benchmark reproduzível, estimativa de custo, plano de migração e rollback.
