# Deploy no EasyPanel

Este guia complementa [`README.md`](README.md) para quem vai hospedar o Atlas em um servidor com [EasyPanel](https://easypanel.io) em vez de rodar `scripts/install-self-hosted.sh` diretamente via SSH. O EasyPanel roda sobre Docker/Traefik e não executa `docker compose exec` depois do deploy — por isso o bootstrap (migrations, administrador inicial, reindexação) foi movido para dentro do container do `backend`, em [`docker/backend-entrypoint.sh`](../../docker/backend-entrypoint.sh). Veja a seção 4 antes de configurar as variáveis de ambiente.

## Pré-requisitos

- Servidor com EasyPanel instalado e um domínio (ou subdomínio) com DNS apontando para o IP do servidor;
- Acesso admin ao painel;
- Segredos gerados antecipadamente (mesma lista da [matriz de variáveis](README.md#matriz-de-variáveis)): `APP_KEY`, senhas de Postgres/Redis/MinIO, `TYPESENSE_API_KEY`, `KNOWLEDGE_ENGINE_TOKEN`, credenciais do administrador inicial (`ATLAS_ADMIN_*`).

Gere o `APP_KEY` localmente antes de preencher o env, pois o entrypoint recusa subir sem ele:

```bash
docker run --rm -v "$PWD/apps/backend":/app -w /app composer:2.8 sh -c \
  "composer install --no-dev --no-interaction --quiet && php artisan key:generate --show"
```

## 1. Por que existe um compose separado na raiz do repositório

Use [`docker-compose.production.yml`](../../docker-compose.production.yml), na **raiz** do repositório — não [`docker/docker-compose.production.yml`](../../docker/docker-compose.production.yml) (esse é usado só pelo fluxo self-hosted via SSH, `scripts/install-self-hosted.sh`/`update-self-hosted.sh`, que já passam `--env-file` explicitamente).

O EasyPanel gera um `docker-compose.override.yml` na raiz do checkout clonado e resolve o `.env` do serviço relativo a essa raiz (não ao diretório do arquivo `docker-compose.yml` apontado na fonte). Se o compose de produção ficasse em `docker/`, a interpolação `${VAR:?...}` do painel falha com erros do tipo `required variable REDIS_PASSWORD is missing a value`, mesmo com a variável preenchida corretamente no campo de ambiente. A cópia na raiz evita essa ambiguidade de diretório. Mantenha os dois arquivos em sincronia manualmente ao alterar serviços.

A cópia da raiz também já vem sem a publicação de porta do `portal` (`ports: ['${ATLAS_PORT:-8080}:8080']`), pois o EasyPanel usa os próprios domínios do painel para tráfego HTTP em vez de portas publicadas (evita conflito de porta com outros projetos no mesmo servidor). Os demais serviços (`postgres`, `redis`, `typesense`, `minio`, `backend`, `worker`) já não publicam portas — permanecem só na rede interna do Compose, como no caminho self-hosted padrão.

## 2. Criar o projeto e o serviço Compose

1. No painel: **Projects → New Project**, com nome `atlas`.
2. Dentro do projeto: **New Service → Compose**.
3. Escolha a fonte:
   - **Git**: aponte para o repositório, branch e o caminho `docker-compose.production.yml` (raiz). Para repositório privado, adicione a chave SSH que o painel gera como deploy key no repositório.
   - **Inline**: cole o conteúdo do arquivo da raiz.
4. Revise os avisos de compatibilidade que o painel mostrar (tipicamente sobre `container_name` ou `ports`) — este compose já não usa `container_name` fixo.
5. Ative **Create .env file**.

## 3. Configurar variáveis de ambiente

Copie `docker/.env.production.example`, preencha todos os valores vazios e cole no campo de environment do serviço (formato `.env`, referenciado no YAML como `${VAR}`). Trate esse conteúdo como segredo — não o commite no repositório.

## 4. O que o entrypoint do backend faz automaticamente

No fluxo self-hosted por SSH, `scripts/install-self-hosted.sh` sobe a stack e depois roda `docker compose exec backend ...` para migrar, criar o administrador e reindexar. O EasyPanel não expõe esse passo pós-deploy no fluxo padrão do painel, então essa lógica agora roda dentro do próprio container na inicialização (`docker/backend-entrypoint.sh`, usado como `ENTRYPOINT` em [`apps/backend/Dockerfile`](../../apps/backend/Dockerfile)):

1. Valida que as variáveis obrigatórias (DB, Redis, Typesense, MinIO/S3, `KNOWLEDGE_ENGINE_TOKEN`) estão presentes — falha rápido e loga qual falta, em vez de subir quebrado.
2. Tenta `php artisan migrate --force` com retry (30 tentativas, 2s de intervalo) até o Postgres responder. Isso existe porque não há garantia de que o orquestrador por trás do EasyPanel respeite `depends_on: condition: service_healthy` da mesma forma que Docker Compose puro — o entrypoint não depende disso.
3. Roda `php artisan atlas:create-initial-admin` (idempotente: cria ou atualiza o mesmo usuário a cada boot, a partir de `ATLAS_ADMIN_*`).
4. Roda `php artisan atlas:seed-demo` somente se `ATLAS_SEED_DEMO=true`.
5. Roda `php artisan search:reindex-published` se `ATLAS_REINDEX_ON_BOOT` não for `false` (padrão: roda).

Variáveis relevantes a esse comportamento:

| Variável | Padrão | Efeito |
| --- | --- | --- |
| `ATLAS_SEED_DEMO` | `false` | carrega dados de demonstração no boot; use só em ambiente de demonstração |
| `ATLAS_REINDEX_ON_BOOT` | `true` | reindexação automática a cada start do `backend`; defina `false` ao escalar para mais de uma réplica, para evitar reindexações concorrentes, e rode `search:reindex-published` manualmente após deploys |

## 5. Deploy

Clique em **Deploy** e acompanhe os logs de build (composer, pnpm, uv — o build multi-stage pode levar alguns minutos). Depois, confira o log do serviço `backend`: as mensagens do entrypoint (migração, `Administrador inicial pronto para ...`) confirmam que o bootstrap rodou.

## 6. Configurar o domínio público

Na aba **Domains** do serviço Compose, aponte o host público para o serviço interno `portal`, porta `8080`, protocolo HTTP — o Traefik do EasyPanel termina o TLS. Se ainda não configurou um domínio custom no painel, faça isso primeiro em **Settings → General → Custom Service Domain**, com um registro DNS `A` (ou wildcard) para o IP do servidor.

## 7. Validar

- Acesse o domínio configurado e entre com `ATLAS_ADMIN_EMAIL`/`ATLAS_ADMIN_PASSWORD`.
- Confira os logs de `backend`, `worker` e `portal` em busca de erros.
- Valide uma entidade publicada na busca (confirma que a reindexação rodou).

## 8. Backup

O serviço `backup` usa `profiles: [backup]` e não sobe junto com o `deploy` padrão do painel — isso é intencional, pois ele roda uma vez e sai. Para executá-lo, use o console/SSH do servidor onde o EasyPanel mantém o compose desse projeto:

```bash
docker compose -f <caminho-do-compose-do-projeto-no-easypanel> --profile backup run --rm backup
```

Agende esse comando externamente (cron do host) até que o painel ofereça um serviço de cron nativo para este projeto.
