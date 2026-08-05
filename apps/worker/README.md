# Atlas Worker

Runtime Python 3.12 isolado para tarefas longas. É gerenciado pelo `uv` a partir de `pyproject.toml`:

```bash
uv sync --extra dev
uv run pytest
uv run ruff check .
uv run mypy atlas_worker
```

Em ambientes que ainda não têm `uv`, `pip install -r requirements.txt` é somente compatibilidade local; a definição autoritativa de dependências permanece no `pyproject.toml`.

## Operação

O processo consome o Redis Stream `atlas:worker:tasks` no grupo `atlas-workers`, com os contratos `worker-task.json` e `embedding-result.json` em `packages/contracts`. Cada tarefa tem `task_id`, `idempotency_key`, `correlation_id`, escopo e deadline. Falhas transitórias usam backoff exponencial até cinco tentativas; a última falha e deadlines vencidos vão para `atlas:worker:dlq`.

`GET /health` (porta `8081`) retorna o estado do processo e tarefas em voo. `SIGTERM` e `SIGINT` param o recebimento de novas mensagens e deixam a tarefa atual encerrar. Logs são JSON e incluem apenas identificadores operacionais, nunca conteúdo ou token.

O handler `embedding.generate` envia resultados somente para `POST /api/v1/worker-results/embeddings`, com `KNOWLEDGE_ENGINE_TOKEN` de curta duração. Não configure credenciais PostgreSQL neste processo.

## Análise estática de código

O handler `code.analyze` implementa `code.analyzer` para PHP/Laravel e TypeScript/React. Ele recebe exclusivamente `payload.files` com `path` e `content_base64`: não abre paths, segue imports ou executa código. Produz candidatos `Controller`, `Service`, `Job`, `Evento`, `Tabela`, `Migration` e `Tela`, e assertions candidatas `calls`, `reads`, `writes`, `produces`, `consumes` e `implements` quando identificáveis.

Cada resultado contém evidência `arquivo:linha`, offset em linhas, checksum e commit (`evidence.commit`), além de `execution.analyzer_version` para reprocessamento seletivo. URLs HTTP são referências externas a resolver contra endpoints existentes. O retorno vai a `POST /api/v1/worker-results/code-analyses`; o worker nunca publica candidatos.

## Parsing de conteúdo

O handler `content.parse` recebe bytes inline em `payload.content_base64`, nunca uma URL ou caminho a abrir. Markdown produz front matter, títulos, código, tabelas, Mermaid, links, imagens e chunks com offsets Unicode. PDF é lido localmente por `pypdf`; cada chunk informa a página e offsets no texto extraído. O parser limita bytes, páginas e tempo (`CONTENT_PARSER_MAX_BYTES`, `CONTENT_PARSER_MAX_PAGES`, `CONTENT_PARSER_TIMEOUT_SECONDS`) e não realiza I/O de rede.

O resultado usa `content-parse-result.json` e é enviado a `POST /api/v1/worker-results/content-parses`. Entidades extraídas são somente `candidate`, com evidência exata, revisão, método e confiança. A borda do Knowledge Engine deve persistir e encaminhar cada candidato ao motor de políticas; o worker não publica nem acessa PostgreSQL.

## Detector de divergência entre documentação e código

O handler `documentation-code.detect-divergence` recebe uma revisão documentada e um comportamento já extraído do código, ambos com entidade, revisão, data da revisão (`revised_at`), evidência, origem e data de processamento. Quando a revisão do código é mais nova e a regra documentada de `bloqueio total` contradiz o tratamento restrito de faturamento parcial/409, ele devolve uma assertion candidata `contradicts` entre as revisões e uma proposta de aprovação com diff, análise, confiança e entidades impactadas. O retorno segue `documentation-code-divergence-result.json` para `POST /api/v1/worker-results/documentation-code-divergences`; essa borda deve persistir os candidatos e submetê-los à política. Nenhuma correção é aplicada pelo worker.

## Parsing de OpenAPI

O handler `openapi.parse` recebe uma especificação OpenAPI 3.0 ou 3.1 em
`payload.content_base64`, JSON ou YAML. Ele resolve apenas referências JSON
Pointer internas; referências externas, URLs e caminhos locais são recusados.
O resultado contém candidatos `Endpoint`, `Aplicação`, `Schema` e `Evento`,
sempre em `candidate`, com método, path, parâmetros, corpos de requisição,
respostas/exemplos e servidores.

As assertions candidatas usam `exposes`, `consumes` e `produces`. Eventos são
declarados em `x-atlas-events` ou `x-events`, com `name`/`event` e, se preciso,
`direction: consumes|produces`. Se a tarefa incluir `previous_content_base64`,
o parser emite `contract_divergences` para remoção de endpoint/parâmetro/2xx e
adição ou endurecimento de parâmetro obrigatório. É um sinal diagnóstico: não
publica nem bloqueia uma revisão automaticamente.
