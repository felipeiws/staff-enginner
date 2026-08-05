# Conector GitHub

`atlas.github` é o primeiro plugin `source.connector` da Atlas. Ele usa o contrato de observação da plataforma e mantém a fronteira de Git pequena para que GitLab e Azure DevOps possam ser variantes futuras do mesmo adaptador (`listRepositories`, `getHeadCommit`, `listFiles`, `getFileContent`, `listCommits` e operações de PR).

## Operações

- `discoverIncremental` pagina repositórios autorizados, resolve a cabeça da branch em um commit SHA e devolve somente referências de arquivos sob os prefixos autorizados. O cursor é por página; conteúdo não é carregado nessa etapa.
- `fetchArtifact` exige uma referência de commit SHA estável e converte o arquivo em `SourceObservation v1`. O `external_id` usa o ID imutável do repositório e o path; URI, revisão, checksum SHA-256, método, referência de credencial, autor/data dos commits e data de processamento são preservados.
- `acceptWebhook` aceita push de branches após HMAC em tempo constante e rate limit por `plugin + origem`. O segredo rotacionável é materializado somente no adaptador do runtime; o manifesto e a observação guardam apenas `secret://...`.
- `openPullRequest` é a operação usada pela ação **Aprovar e abrir PR**: cria branch determinística pela chave de idempotência, grava o texto aprovado e abre o PR. Ela recusa destino fora do repositório ou prefixo documental autorizado.

O plugin não chama PostgreSQL, Redis, Typesense ou tabelas de governança. O host recebe observações e a referência do PR; a Governança só publica uma revisão depois que essa referência externa existe, conforme a [especificação de aprovações](../../../docs/development/design-documents/approval-api.md).

## Configuração e segurança

O manifesto usa runtime `container`, egress somente para `api.github.com`, limites explícitos e permissões mínimas (`artifact.read`, `network.egress`, `secrets.read`, `webhook.receive`). Configure `credential_reference` com `secret://`; tokens e segredos de webhook nunca entram no manifesto, logs ou observações. O escopo obrigatório restringe organização, IDs de repositório, prefixos de path e classificação.

O adaptador HTTP em `src/github-rest-client.mjs` recebe o token temporário do runtime. Produção deve injetar também uma função `webhookHmac(body)` ligada ao segredo rotacionável. Não há fallback para assinatura sem segredo.

## Observabilidade e rollback

Emita as métricas declaradas no manifesto por operação, com `plugin_id` e `correlation_id`, sem conteúdo ou credenciais. Para rollback, desabilite o registro `atlas.github`; observações e PRs já registrados permanecem auditáveis e o núcleo continua operando. Reabilite somente depois de corrigir a configuração e revisar erros de assinatura, limite e API.

## Verificação

Execute `npm test` e `npm run lint` dentro deste diretório. Os testes simulam API do GitHub, paginação de repositório grande, rate limit, assinatura inválida e abertura de PR autorizada.
