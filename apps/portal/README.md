# Portal Atlas

Aplicação React do Portal Atlas. Ela é consumidora da API e de `@atlas/contracts`; não acessa banco, índice ou módulos internos do backend.

## Desenvolvimento

`pnpm --filter @atlas/portal dev` inicia o Vite. Requisições para `/api` são encaminhadas para `http://localhost:8000` por padrão; configure `ATLAS_API_URL` para outro backend local.

## Testes end-to-end

A suíte Playwright em [`e2e`](./e2e) usa o backend Laravel e o domínio
demonstrativo Nordika de verdade. Inicie os serviços com
`./scripts/dev-up.sh`, migre o backend e execute `php artisan atlas:seed-demo`
em `apps/backend`; depois inicie o backend em `:8000` e o Portal em `:4173`.
Use `pnpm --filter @atlas/portal test:e2e`. As referências visuais das doze
rotas, nos temas claro e escuro, ficam versionadas em
`e2e/visual-regression.spec.ts-snapshots`; atualize-as apenas após revisão
visual intencional com `pnpm --filter @atlas/portal test:e2e:update`.

O seed cria somente para este ambiente a conta `demo@nordika.test` com a senha
`atlas-demo-password` e concessão de `aprovador_de_dominio` limitada à
organização Nordika. A suíte autentica por sessão, portanto não contorna a
autorização da API.

Com `APP_ENV=testing`, o backend usa um adaptador de Pull Request determinístico
somente para o seed E2E; ele devolve a referência exigida pelo fluxo sem chamar
um provedor externo. Nos demais ambientes, a aprovação continua bloqueada até
um conector de repositório configurado retornar a referência real.

Rotas compartilháveis e a página de erro/404 existem desde o bootstrap. As telas de produto permanecem planejadas até a implementação dos respectivos Design Documents. Consulte o [ADR-011](../../docs/adr/011-portal-stack.md).

## Dados da API

A camada em `src/data` é a única borda de dados das telas: ela expõe hooks
TanStack Query tipados por recurso, chaves estáveis, retry apenas para falhas
transitórias e estados reutilizáveis de skeleton/erro. O cliente fala somente
com `/api/v1`, normaliza RFC 9457 e oferece SSE para a resposta do Copiloto
quando a API negociar `text/event-stream` (com fallback JSON v1). Componentes
de tela não devem chamar `fetch` diretamente.

`useCopilotAsk` usa `Idempotency-Key` por envio e invalida insights e saúde do
escopo depois da execução, pois uma abstenção pode registrar uma lacuna. As
respostas continuam candidatas: a camada não as trata como conhecimento
publicado.

## Visão da aplicação

A rota `/aplicacoes/:id` consome exclusivamente os modelos de leitura de
aplicação, árvore técnica/funcional e módulos previstos na
[API de leitura de organizações e aplicações](../../docs/development/design-documents/application-read-api.md).
As contagens, indicadores e saúde são projeções autorizadas pela API; o Portal
não os calcula a partir de fontes locais nem os persiste.

## Cabeçalho e busca unificada

O cabeçalho compartilhado segue o protótipo em
[`docs/design/prototype/atlas-prototype.html`](../../docs/design/prototype/atlas-prototype.html).
Seu único campo encaminha termos curtos e identificadores técnicos para a rota
de Busca facetada; perguntas, interrogativos e solicitações longas são abertas
no Copiloto. O botão **“✦ perguntar”** força o Copiloto e `Ctrl/Cmd+Enter`
força Busca, preservando a escolha do usuário. Essa decisão só seleciona uma
rota: recuperação, autorização e eventual IA permanecem na API.

## Tela de Busca

A rota `/busca` lê `q`, `entity_type`, `application`, `freshness` e `owner` da
query string. Assim, filtros de tipo e facetas geram URLs compartilháveis e a
tela solicita apenas `GET /api/v1/search`, cujo resultado publicado inclui
evidência, revisão, método e confiança. O atalho **“✦ pedir uma resposta”**
preserva a consulta e encaminha para `/copiloto`; ele não transforma resultados
de busca em conhecimento autoritativo.

## Tela do Copiloto

A rota `/copiloto` usa exclusivamente `POST /api/v1/copilot/ask` via
`useCopilotAsk`. Ela mantém perguntas e respostas candidatas apenas na sessão
da tela, mostra fontes com revisão, URI, método, confiança e frescor, e renderiza
a linha de raciocínio a partir do traço determinístico devolvido pela API. A
abstenção da API continua sendo uma lacuna rastreada: o Portal não tenta completar
a resposta. Chips de entidades e ações navegam por rotas internas do Portal; as
fontes mantêm seus URIs de proveniência como links externos.

## Tela de Aprovações

A rota `/aprovacoes` consulta a fila e o detalhe em `/api/v1/approvals` e
`/api/v1/approvals/{id}`. As ações de editar, pedir ajuste à IA, aprovar e
rejeitar usam os comandos idempotentes da [API de aprovações v1](../../docs/development/design-documents/approval-api.md).
A justificativa é obrigatória antes de aprovar ou rejeitar e compõe o commit;
o texto proposto permanece editável e candidato até a aprovação humana. O
estado de sucesso só é mostrado com a referência do PR retornada pela API.

## Saúde do conhecimento

A rota `/saude` usa `scope` (UUID opaco) e `period=30d` na query string para
consultar exclusivamente `GET /api/v1/knowledge-health`. O campo de escopo
preserva a seleção na URL e dispara uma nova consulta. O contrato v1 admite
somente 30 dias, por isso o seletor de período apresenta exclusivamente essa
janela. A atribuição de uma lacuna abre o diálogo de responsável e chama
`POST /api/v1/knowledge-gaps/{id}/assign` com chave de idempotência; o retorno
da tarefa é mostrado sem converter a lacuna em conhecimento publicado.

## Detalhe de Processo

A rota `/processos/:id` consome `GET /api/v1/entities/{id}`, o modelo de
leitura autorizado e versionado de detalhe. As abas `resumo`, `fluxo`,
`tecnico` e `historico` são refletidas em `?aba=` para que o estado seja
compartilhável. Relações, sinais e histórico continuam projeções com
proveniência; a divergência de IA aponta para uma proposta em Aprovações e não
é tratada como conhecimento publicado.

## Detalhe de Regra de negócio

A rota `/regras/:id` consome `GET /api/v1/entities/{id}` e apresenta somente a
projeção autorizada de uma entidade `Regra`: enunciado publicado, vigência,
aprovação humana, impacto, versões e relações navegáveis. A comparação de
versões é um estado de URL (`?versao=`), compartilhável e sem nova requisição;
o Portal não calcula nem persiste versões. Origem, revisão, método e confiança
continuam obrigatórios na resposta da API, conforme o modelo de leitura de
entidade.

## Detalhe de Tela

A rota `/telas/:id` consome a projeção `tela` de `GET /api/v1/entities/{id}`:
artefatos Desktop/Mobile/Vídeo, campos, problemas, permissões e relações
publicadas. O upload arrastado envia `POST /api/v1/visual-artifacts` como
observação idempotente e versionada; ele não altera a revisão publicada da Tela.
O botão de captura emite `atlas:qa-capture-request` para a integração de QA
automatizado, que deve devolver o artefato pela mesma fronteira autorizada.

## Detalhe de Endpoint

A rota `/apis/:id` consome a projeção `endpoint` de `GET /api/v1/entities/{id}`.
Parâmetros, respostas, exemplo de erro e regras são conteúdo publicado da revisão;
as métricas de p95, taxa de erro, série de latência e volumes de consumidores são
apenas dados da API de observabilidade. Quando essa API não os retorna, o Portal
mostra o estado vazio e nunca os substitui por zero ou por valores de exemplo.

## Knowledge Graph

A rota `/graph` consome as leituras autoritativas de vizinhança, detalhe e
diagnóstico em `/api/v1/graph`. Ela abre no foco com profundidade 1 e calcula no
cliente um layout dirigido por força determinístico; coordenadas não fazem parte
da API. O canvas oferece zoom, pan, seleção e uma lista navegável por teclado,
sem transformar o diagnóstico ou qualquer sugestão em conhecimento publicado.

## Paleta de comandos e tema

A paleta abre/fecha com `Ctrl/Cmd+K` e fecha com `Esc`. Ela busca apenas
entidades publicadas em `GET /api/v1/search`; ações e painéis são destinos
locais. O tema persiste em `localStorage` e, na primeira visita, segue
`prefers-color-scheme` por meio de `data-theme` na raiz.

## Responsividade e acessibilidade

Em até 1100px, os layouts de conteúdo e painel lateral passam para uma coluna e
o painel vem após o conteúdo. Em até 900px, a navegação lateral vira um drawer
acionado pelo botão de menu no cabeçalho. Tabelas e o fluxo de processo mantêm
rolagem horizontal no próprio componente, sem ampliar a página. O grafo mantém
pan/zoom por ponteiro, botões de zoom para toque e uma lista textual navegável.

Todos os controles têm foco visível; `prefers-reduced-motion` desativa animações.
O atalho `Ctrl/Cmd+K` abre a paleta, `Esc` a fecha (e fecha o drawer), e
`Ctrl/Cmd+Enter` no campo global força a Busca. Há um link de salto para o
conteúdo principal e o Copiloto anuncia seu carregamento em uma região viva.
