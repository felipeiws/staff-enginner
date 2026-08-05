#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
compose_file="$root_dir/docker/docker-compose.production.yml"
env_file="$root_dir/docker/.env.production"

if [[ ! -f "$env_file" ]]; then
  cp "$root_dir/docker/.env.production.example" "$env_file"
  echo "Arquivo criado em docker/.env.production. Preencha todos os valores vazios e execute este comando novamente." >&2
  exit 2
fi

required=(APP_KEY POSTGRES_PASSWORD REDIS_PASSWORD MINIO_ROOT_PASSWORD AWS_SECRET_ACCESS_KEY TYPESENSE_API_KEY KNOWLEDGE_ENGINE_TOKEN ATLAS_ADMIN_NAME ATLAS_ADMIN_EMAIL ATLAS_ADMIN_PASSWORD)
for key in "${required[@]}"; do
  if ! grep -qE "^${key}=.+" "$env_file"; then
    echo "Variável obrigatória ausente em docker/.env.production: ${key}" >&2
    exit 2
  fi
done

compose=(docker compose --env-file "$env_file" -f "$compose_file")
"${compose[@]}" up --detach --build
echo "Atlas instalado em $(grep '^APP_URL=' "$env_file" | cut -d= -f2-)"
echo "Migrações, administrador inicial e reindexação rodam automaticamente no entrypoint do serviço backend (docker/backend-entrypoint.sh)."
