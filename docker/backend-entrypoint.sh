#!/bin/sh
set -eu

required_vars="APP_KEY DB_HOST DB_PORT POSTGRES_DB POSTGRES_USER POSTGRES_PASSWORD REDIS_HOST REDIS_PASSWORD TYPESENSE_HOST TYPESENSE_PORT TYPESENSE_API_KEY AWS_BUCKET AWS_ACCESS_KEY_ID AWS_SECRET_ACCESS_KEY KNOWLEDGE_ENGINE_TOKEN"

missing=""
for key in $required_vars; do
  eval "value=\${$key:-}"
  if [ -z "$value" ]; then
    missing="$missing $key"
  fi
done

if [ -n "$missing" ]; then
  echo "atlas-entrypoint: variáveis obrigatórias ausentes:$missing" >&2
  exit 1
fi

attempts=0
max_attempts=30
until php artisan migrate --force; do
  attempts=$((attempts + 1))
  if [ "$attempts" -ge "$max_attempts" ]; then
    echo "atlas-entrypoint: falha ao migrar após ${max_attempts} tentativas" >&2
    exit 1
  fi
  echo "atlas-entrypoint: banco indisponível, tentando novamente em 2s (${attempts}/${max_attempts})" >&2
  sleep 2
done

php artisan atlas:create-initial-admin

if [ "${ATLAS_SEED_DEMO:-false}" = "true" ]; then
  php artisan atlas:seed-demo
fi

if [ "${ATLAS_REINDEX_ON_BOOT:-true}" = "true" ]; then
  php artisan search:reindex-published
fi

exec "$@"
