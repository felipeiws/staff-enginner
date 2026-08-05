# API de resultados de worker v1

## Objetivo e consumidor

`POST /api/v1/worker-results/embeddings` recebe o resultado determinístico de `embedding.generate` do runtime Python. É a única borda de retorno desse handler: o worker não consulta nem escreve PostgreSQL. O consumidor é o `EmbeddingPersistenceService`, que cria chunks e sua projeção vetorial de forma atômica e sempre vinculada à revisão de origem.

## Contrato e autorização

O corpo contém `schema_version: "1.0.0"`, `event_type: "EmbeddingGenerated"`, `task_id`, `revision_id`, `scope` e a lista de chunks. Cada chunk informa offsets, checksum, texto, tokens, vetor, modelo, versão, parâmetros e custo. `Idempotency-Key` e `X-Correlation-Id` são obrigatórios nas convenções de API v1; o primeiro torna o reenvio seguro.

O token opaco de curta duração do worker precisa de `knowledge.contribute`. Antes de persistir, o backend resolve a fonte da `revision_id`, recupera escopo e classificação autoritativos e autoriza o principal. O `scope` devolvido pelo worker é registro operacional, não autoridade de segurança. O resultado não publica revisões ou assertions: embeddings continuam projeção derivada, e qualquer saída de IA permanece candidata conforme governança.

## Limites, falhas e operação

Há ao menos um chunk e texto por chunk é limitado a 200 kB. Checksum, dimensão configurada e determinismo de chunks são validados pelo serviço de aplicação. `422` indica contrato/dado incompatível; `403`, escopo ou classificação não autorizados; indisponibilidade e `5xx` devem gerar retry com backoff pelo worker. A API mede respostas, erros e correlação; não registra vetor ou texto em logs.

Não há exclusão nem retenção adicional neste comando: a retenção segue a revisão e os dados de proveniência. O rollback remove a rota e o worker volta a colocar a tarefa em DLQ, sem alterar conhecimento publicado. Mudança incompatível cria nova versão de contrato/rota.

## Resultado de parsing de conteúdo (planejado)

`POST /api/v1/worker-results/content-parses` é a borda prevista para `content.parse`, definida pelo contrato [`content-parse-result.json`](../../../packages/contracts/schemas/v1/content-parse-result.json). Ela recebe a estrutura do Markdown/PDF, chunks endereçáveis por offset e candidatos `Documento`, `ADR`, `FAQ` ou `Regra`, cada qual com evidência no trecho exato. A persistência deve preservar a revisão de origem e encaminhar todos os candidatos, ainda em estado `candidate`, ao motor de políticas. Essa rota não é uma autorização de publicação e permanece planejada até sua implementação no Knowledge Engine.

## Resultado de análise estática de código (planejado)

`POST /api/v1/worker-results/code-analyses` é a borda prevista para `code.analyze`, definida pelo contrato [`code-analysis-result.json`](../../../packages/contracts/schemas/v1/code-analysis-result.json). Ela recebe candidatos `Controller`, `Service`, `Job`, `Evento`, `Tabela`, `Migration` e `Tela`, e assertions candidatas do vocabulário canônico. A evidência deve carregar `arquivo:linha`, offsets por linha, checksum e commit/revisão. `execution.analyzer_version` será persistido na família `executions`, permitindo reprocessar apenas resultados produzidos por versões anteriores. A borda autoriza o token curto, resolve referências HTTP externas contra entidades existentes e encaminha o resultado para política/revisão; nunca publica relações diretamente.

`POST /api/v1/worker-results/documentation-code-divergences` é a borda prevista para `documentation-code.detect-divergence`, definida pelo contrato [`documentation-code-divergence-result.json`](../../../packages/contracts/schemas/v1/documentation-code-divergence-result.json). Ela aceita exclusivamente revisões e propostas candidatas: persiste a assertion `contradicts` com as evidências das duas revisões e abre a proposta pendente vinculada a essa assertion, contendo diff, análise, confiança e entidades impactadas. A operação é idempotente pela chave da tarefa; não altera a revisão documentada, não publica a assertion e não decide a proposta.

## Referências

[API v1](api-v1.md), [Knowledge Engine](../../knowledge-engine/README.md), [Search Engine](../../search-engine/README.md), [Segurança](../../security/architecture.md), [ADR-002](../../adr/002-python-workers.md) e [ADR-010](../../adr/010-identity-and-authorization.md).
