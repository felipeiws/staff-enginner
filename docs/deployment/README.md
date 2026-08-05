# Implantação e ambientes

Docker Compose é o ambiente local de referência porque torna dependências reproduzíveis sem impor um orquestrador de produção cedo demais. A composição em [`docker/docker-compose.yml`](../../docker/docker-compose.yml) inicia as dependências da plataforma: PostgreSQL 16 com pgvector, Redis 7, Typesense 27, MinIO e Mailpit. Backend Laravel, worker Python e Portal React são implementados e executados localmente fora dessa composição; o procedimento completo está no [guia de primeiros passos](../development/getting-started.md).

## Requisitos locais

- Docker Engine 24 ou superior, com Docker Compose v2 e acesso ao daemon;
- 4 GB de memória RAM livres como referência inicial para os cinco serviços;
- as portas listadas abaixo disponíveis no host.

Crie sua configuração local a partir do exemplo e suba a stack:

```bash
cp .env.example .env
./scripts/dev-up.sh
```

Os scripts são idempotentes. `dev-up.sh` inicia os contêineres em segundo plano e aguarda os health checks, inclusive em versões do Docker Compose que não oferecem timeout nativo; se `.env` não existir, usa os valores de `.env.example`. Para encerrar os contêineres sem remover dados locais:

```bash
./scripts/dev-down.sh
```

## Serviços e portas

| Serviço                  | Porta padrão                  | Uso local                                                                             |
| ------------------------ | ----------------------------- | ------------------------------------------------------------------------------------- |
| PostgreSQL 16 + pgvector | `5432` (`POSTGRES_PORT`)      | sistema de registro; cria `vector`, `pg_trgm` e `uuid-ossp` na primeira inicialização |
| Redis 7                  | `6379` (`REDIS_PORT`)         | fila, cache efêmero e locks                                                           |
| Typesense 27             | `8108` (`TYPESENSE_PORT`)     | projeção de busca; `http://localhost:8108/health`                                     |
| MinIO API                | `9000` (`MINIO_API_PORT`)     | armazenamento S3-compatível para artefatos, prints e vídeos                           |
| MinIO Console            | `9001` (`MINIO_CONSOLE_PORT`) | `http://localhost:9001`                                                               |
| Mailpit SMTP             | `1025` (`MAILPIT_SMTP_PORT`)  | captura de e-mails de desenvolvimento                                                 |
| Mailpit Web              | `8025` (`MAILPIT_WEB_PORT`)   | `http://localhost:8025`                                                               |

As demais credenciais e a chave local de Typesense também estão em [`.env.example`](../../.env.example). Elas são exclusivamente para desenvolvimento e devem ser substituídas em qualquer ambiente compartilhado.

## Dados e reset

Os serviços persistem dados em volumes nomeados: `postgres-data`, `redis-data`, `typesense-data`, `minio-data` e `mailpit-data`. O script de inicialização [`001-extensions.sql`](../../docker/postgres/init/001-extensions.sql) só é executado quando o volume do PostgreSQL é criado pela primeira vez.

Para resetar integralmente os dados locais, pare a stack e remova os volumes do projeto Compose:

```bash
docker compose --env-file .env -f docker/docker-compose.yml down --volumes --remove-orphans
```

Se você ainda não criou `.env`, troque `--env-file .env` por `--env-file .env.example`. Este comando é destrutivo: remove banco, índice de busca, objetos MinIO, mensagens capturadas e dados Redis locais.

## Produção

Produção deve separar dados persistentes de workloads stateless, usar segredos externos, TLS, backups testados, migrações compatíveis, health checks, limites de recursos e observabilidade. Compose não define a estratégia de produção; Kubernetes, serviços gerenciados ou VMs devem ser escolhidos por requisitos operacionais documentados.

Imagens devem ser reproduzíveis, não executar como root e ter versões fixadas. Reversão depende de compatibilidade de schema e de reconstrução de projeções; por isso, mudanças destrutivas exigem plano explícito.

## Self-hosted de produção

O caminho inicial suportado é Docker Compose em uma VM Linux. Ele preserva o PostgreSQL como sistema de registro, MinIO para blobs e Typesense apenas como projeção descartável, conforme os ADRs [003](../adr/003-postgresql-system-of-record.md) e [004](../adr/004-typesense-search.md). Backend, worker e Portal usam imagens multi-stage e usuários não-root. O worker só recebe Redis e a API; não recebe credenciais PostgreSQL.

Pré-requisitos: Docker Engine 24+, Compose v2, DNS/TLS terminado por proxy reverso externo e disco persistente. Somente a porta do Portal (`ATLAS_PORT`, padrão `8080`) é publicada; bancos e consoles permanecem na rede interna do Compose.

| Porte   | Usuários ativos |     CPU |   RAM | Disco persistente | Réplicas iniciais        |
| ------- | --------------: | ------: | ----: | ----------------: | ------------------------ |
| Pequeno |          até 25 |  4 vCPU |  8 GB |        100 GB SSD | 1 backend, 1 worker      |
| Médio   |         até 150 |  8 vCPU | 16 GB |        500 GB SSD | 2 backend, 2 workers*    |
| Grande  |         até 500 | 16 vCPU | 32 GB |         1 TB NVMe | migrar para orquestrador |

\* O Compose de referência define uma réplica por serviço; escale workers com `docker compose up -d --scale worker=2` depois de validar capacidade. Para alta disponibilidade real do Portal/backend e atualização sem interrupção, use um proxy com múltiplas réplicas ou um orquestrador: Compose sozinho não faz rollout progressivo.

### Instalação em um comando

```bash
cp docker/.env.production.example docker/.env.production
# Edite os valores vazios; gere APP_KEY com `php artisan key:generate --show`.
./scripts/install-self-hosted.sh
```

O script recusa segredos ausentes e sobe a stack; migração, criação/atualização do administrador inicial e reindexação da projeção publicada rodam automaticamente no entrypoint do serviço `backend` (veja [`docker/backend-entrypoint.sh`](../../docker/backend-entrypoint.sh)). Para carregar Nordika apenas em ambiente de demonstração, defina `ATLAS_SEED_DEMO=true` em `docker/.env.production` antes de rodar `./scripts/install-self-hosted.sh`. O administrador é limitado a `ATLAS_ADMIN_ORGANIZATION`, mesmo com o papel `administrador_de_plataforma`; a senha nunca é impressa pelo script.

Para hospedar em um servidor com [EasyPanel](https://easypanel.io) em vez de SSH direto, siga o [guia de deploy no EasyPanel](easypanel.md).

### Matriz de variáveis

Copie o exemplo acima; não versione `docker/.env.production`.

| Grupo          | Variáveis                                                                                            | Obrigatórias           | Finalidade                             |
| -------------- | ---------------------------------------------------------------------------------------------------- | ---------------------- | -------------------------------------- |
| Aplicação      | `APP_KEY`, `APP_URL`, `APP_NAME`, `LOG_LEVEL`                                                        | `APP_KEY`, `APP_URL`   | criptografia, URL pública e logs       |
| Banco          | `POSTGRES_DB`, `POSTGRES_USER`, `POSTGRES_PASSWORD`, `DB_PORT`                                       | todas exceto `DB_PORT` | registro autoritativo                  |
| Fila           | `REDIS_PASSWORD`                                                                                     | sim                    | Redis, cache e Streams                 |
| Object storage | `MINIO_ROOT_USER`, `MINIO_ROOT_PASSWORD`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_BUCKET` | sim                    | MinIO e artefatos por hash             |
| Busca          | `TYPESENSE_API_KEY`, `TYPESENSE_KNOWLEDGE_ALIAS`, `SEARCH_EMBEDDING_*`                               | chave                  | índice derivado e embeddings           |
| Worker         | `KNOWLEDGE_ENGINE_TOKEN`                                                                             | sim                    | token curto da borda da API; nunca DB  |
| IA             | `COPILOT_*`, `ANTHROPIC_API_KEY`, `ANTHROPIC_BASE_URL`                                               | chave se IA habilitada | inferência candidata, nunca publicação |
| Bootstrap      | `ATLAS_ADMIN_NAME`, `ATLAS_ADMIN_EMAIL`, `ATLAS_ADMIN_PASSWORD`, `ATLAS_ADMIN_ORGANIZATION`          | sim                    | primeiro administrador com escopo      |
| Backup         | `BACKUP_RETENTION_DAYS`                                                                              | não (14)               | retenção local de backups              |

Use um gerenciador de segredos para produção e restrinja o arquivo a `0600`. `AWS_SECRET_ACCESS_KEY` deve ser igual à credencial configurada no MinIO neste caminho de referência.

### Backup e restauração

O serviço de perfil `backup` gera um dump PostgreSQL em formato custom e um espelho do bucket MinIO no volume persistente `backup-data`:

```bash
docker compose --env-file docker/.env.production -f docker/docker-compose.production.yml --profile backup run --rm backup
```

Agende o mesmo comando diariamente no host e copie o conteúdo do volume para armazenamento fora da máquina. O script remove diretórios com mais de `BACKUP_RETENTION_DAYS`; não confunda isso com retenção regulatória. Teste de restauração ponta a ponta: em uma VM/stack isolada com os mesmos segredos, suba somente PostgreSQL e MinIO, localize o diretório UTC no volume de backup e execute `docker compose --env-file docker/.env.production -f docker/docker-compose.production.yml run --rm --entrypoint atlas-restore backup /backups/<timestamp>`. Depois, suba backend/worker, rode `php artisan search:reindex-published`, entre como o administrador e valide uma entidade, sua revisão e um artefato no bucket. A reindexação é obrigatória porque Typesense não é fonte de verdade.

### Atualização e rollback

```bash
./scripts/update-self-hosted.sh
```

O procedimento baixa/gera as imagens, mantém serviços saudáveis, aplica somente migrations compatíveis (`migrate --force`) e cria uma coleção nova antes de trocar o alias Typesense com `search:reindex-published`; a troca do alias é atômica, logo consultas seguem no índice anterior durante a carga. Faça backup antes da atualização. O rollback de aplicação só é permitido enquanto migrations forem retrocompatíveis; para schema destrutivo, restaure PostgreSQL/MinIO e aponte o alias para a coleção anterior conforme o plano da release.

### Runbook de incidentes

| Sintoma                     | Diagnóstico                                                                | Mitigação segura                                                                                                   |
| --------------------------- | -------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------ |
| Fila travada                | `docker compose logs worker` e `redis-cli XINFO GROUPS atlas:worker:tasks` | confirme Redis e token da API, reinicie workers; não escreva no PostgreSQL pelo worker                             |
| DLQ crescendo               | conte `atlas:worker:dlq`, agrupe por `task_type` e `correlation_id`        | corrija a causa, reprocese pela borda/comando controlado; preserve envelope e auditoria                            |
| Índice dessincronizado      | compare `search_projection_metrics`, falhas e revisões publicadas          | execute `php artisan search:reindex-published`; não copie dados diretamente ao Typesense                           |
| Provedor de IA indisponível | erros/latência de `COPILOT_*` e orçamento                                  | mantenha busca disponível, devolva abstenção candidata e lacuna; não use IA alternativa sem classificação/política |
