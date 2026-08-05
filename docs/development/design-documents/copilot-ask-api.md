# API do Copiloto v1

## Objetivo

`POST /api/v1/copilot/ask` responde à tela Copiloto com uma síntese candidata, nunca com conhecimento autoritativo. O comando recebe `question`, `organization` obrigatória e `application`, `module` e `embedding` opcionais. Exige sessão autenticada e `Idempotency-Key`.

## Fluxo

1. A API interpreta intenção e delimita o escopo solicitado.
2. Antes de recuperar, `HybridSearchService` autoriza `knowledge.read`; busca lexical e semântica são fundidas por RRF. O reranking é determinístico, pela pontuação, proveniência e confiança das evidências publicadas.
3. A entidade do melhor resultado é expandida no Knowledge Graph em profundidade 1, aplicando novamente escopo e classificação antes da leitura.
4. Apenas trechos com URI, revision id, método, confiança e classificação entram no contexto. A política de classificação decide se esse contexto pode ir ao provedor externo.
5. O modelo recebe a instrução de citar `revision_id` e `uri` em cada afirmação. Uma resposta sem ambas as referências para cada fonte é rejeitada; falta de evidência também resulta em abstenção `422 copilot.insufficient_evidence`.
6. Antes da chamada ao modelo, o pipeline monta `reasoning_trace` determinístico: interpretação e escopo, contagens lexical/semântica/fundida, descartes por limite ou falta de proveniência citável, decisão de rerank, confrontos documentação–código com o mesmo `entity_id` e restrições de fontes do tipo `Regra`. Ele não aceita texto livre produzido pelo modelo. O traço é persistido em `executions.result.reasoning_trace` e repetido de forma idempotente.

## Resposta

O objeto contém `opening`, `confidence`, `source_count`, `blocks` (com citação), `sources` (tipo, nome, frescor, relevância e proveniência), `entities` (com token de cor), `actions`, `reasoning` (passos renderizáveis) e `reasoning_trace` (dados estruturados auditáveis). As ações são `Aprofundar no processo`, `Ver no Graph`, `Transformar em FAQ` e `Reportar erro`, exatamente como a referência visual. O Portal renderiza `reasoning` no bloco colapsável **“Ver linha de raciocínio”** dentro do card **“Fontes utilizadas”** do protótipo; os passos sempre são derivados de `reasoning_trace`.

## Riscos e rollback

Busca e grafo são somente leitura; nenhuma resposta é persistida no grafo. A execução do modelo mantém configuração e custo em `executions`. Desabilitar a rota interrompe a geração sem alterar conhecimento; respostas com citações inválidas continuam bloqueadas por segurança.

## Referências

[Copilot](../../copilot/README.md), [Search Engine](../../search-engine/README.md), [Governança](../../governance/README.md), [ADR-010](../../adr/010-identity-and-authorization.md).
