# Primeiros passos locais

Este caminho inicia as dependências, o backend, o Portal e o domínio demonstrativo Nordika. Ele termina com uma pergunta respondida pelo Copiloto determinístico, sem enviar conteúdo a provedor externo.

## Pré-requisitos

- Git, Docker Engine 24+ com Compose v2, Node.js 22.22.2+, pnpm 11.18.0+, PHP 8.4+ e Composer 2;
- Python 3.12+ e `uv` para executar o worker (opcional para a primeira pergunta).

## Clone e dependências

```bash
git clone <URL_DO_REPOSITORIO> atlas-knowledge-platform
cd atlas-knowledge-platform
cp .env.example .env
pnpm install --frozen-lockfile
composer --working-dir=apps/backend install --no-interaction
cp apps/backend/.env.example apps/backend/.env
(cd apps/backend && php artisan key:generate)
```

O valor padrão de `TYPESENSE_API_KEY` em `apps/backend/.env` já coincide com `.env.example`. Para a demonstração local, altere `COPILOT_MODEL_PROVIDER=deterministic` no arquivo `apps/backend/.env`; não configure uma chave de provedor de IA para esse percurso.

## Suba e prepare o domínio demonstrativo

Em um terminal, inicie as dependências e mantenha-o disponível até os health checks concluírem:

```bash
./scripts/dev-up.sh
```

Em outro terminal, migre, carregue Nordika e reconstrua a projeção publicada:

```bash
cd apps/backend
php artisan migrate:fresh --seed
php artisan atlas:seed-demo
php artisan search:reindex-published
php artisan serve
```

Em um terceiro terminal, inicie o Portal:

```bash
pnpm --filter @atlas/portal dev
```

O Portal fica em `http://localhost:5173`; entre com `demo@nordika.test` e a senha `atlas-demo-password`.

## Primeira pergunta ao Copiloto

Abra `http://localhost:5173/copiloto`, faça login e pergunte: `Como cancelar um pedido de compra?`. O Copiloto responde usando as revisões demonstrativas e mostra citações. Se não houver evidência suficiente, ele se abstém e registra uma lacuna; essa é a resposta segura esperada, não uma falha de autorização da IA.

Para verificar o caminho sem navegador, após autenticar uma sessão com cliente HTTP que preserve cookies e obtenha o CSRF em `GET /auth/csrf`, envie `POST /api/v1/copilot/ask` com `Idempotency-Key`, a pergunta e o escopo `{"organization":"nordika"}`. Consulte o [Design Document da API do Copiloto](design-documents/copilot-ask-api.md) para o corpo validado.

## Worker opcional

Em outro terminal, depois de emitir um token de máquina limitado, execute:

```bash
cd apps/worker
uv sync --extra dev
KNOWLEDGE_ENGINE_TOKEN=<token-de-curta-duracao> uv run python -m atlas_worker.main
```

O worker consome Redis e envia resultados à API; nunca recebe credenciais PostgreSQL. Pare os processos locais com `Ctrl+C` e encerre as dependências com `./scripts/dev-down.sh`.
