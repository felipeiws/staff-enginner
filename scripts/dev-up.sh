#!/usr/bin/env bash

set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
project_dir="$(cd "$script_dir/.." && pwd)"
compose_file="$project_dir/docker/docker-compose.yml"
env_file="$project_dir/.env"

if [[ ! -f "$env_file" ]]; then
  env_file="$project_dir/.env.example"
  printf '%s\n' "Arquivo .env não encontrado; usando os valores de .env.example."
fi

compose=(docker compose --env-file "$env_file" -f "$compose_file")
timeout="$(awk -F= '$1 == "COMPOSE_WAIT_TIMEOUT" { print $2; exit }' "$env_file")"
timeout="${COMPOSE_WAIT_TIMEOUT:-${timeout:-120}}"

"${compose[@]}" up --detach

mapfile -t service_ids < <("${compose[@]}" ps --quiet)
if [[ "${#service_ids[@]}" -ne 5 ]]; then
  printf '%s\n' "A stack não criou todos os cinco serviços esperados." >&2
  exit 1
fi

elapsed=0
while ((elapsed < timeout)); do
  unhealthy=0
  for service_id in "${service_ids[@]}"; do
    status="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$service_id")"
    if [[ "$status" == "unhealthy" || "$status" == "exited" || "$status" == "dead" ]]; then
      printf '%s\n' "Um serviço falhou durante a inicialização (status: $status)." >&2
      "${compose[@]}" logs
      exit 1
    fi
    if [[ "$status" != "healthy" ]]; then
      unhealthy=1
    fi
  done

  if ((unhealthy == 0)); then
    break
  fi

  sleep 2
  ((elapsed += 2))
done

if ((unhealthy != 0)); then
  printf '%s\n' "Os serviços não ficaram saudáveis em ${timeout}s." >&2
  "${compose[@]}" logs
  exit 1
fi

value_for() {
  local key="$1"
  local fallback="$2"
  local value
  value="$(awk -F= -v key="$key" '$1 == key { sub(/^[^=]*=/, ""); print; exit }' "$env_file")"
  printf '%s' "${!key:-${value:-$fallback}}"
}

postgres_port="$(value_for POSTGRES_PORT 5432)"
redis_port="$(value_for REDIS_PORT 6379)"
typesense_port="$(value_for TYPESENSE_PORT 8108)"
minio_api_port="$(value_for MINIO_API_PORT 9000)"
minio_console_port="$(value_for MINIO_CONSOLE_PORT 9001)"
mailpit_smtp_port="$(value_for MAILPIT_SMTP_PORT 1025)"
mailpit_web_port="$(value_for MAILPIT_WEB_PORT 8025)"

printf '\nStack local pronta:\n'
printf '%s\n' "  PostgreSQL: postgresql://localhost:${postgres_port}"
printf '%s\n' "  Redis: redis://localhost:${redis_port}"
printf '%s\n' "  Typesense: http://localhost:${typesense_port}"
printf '%s\n' "  MinIO API: http://localhost:${minio_api_port}"
printf '%s\n' "  MinIO Console: http://localhost:${minio_console_port}"
printf '%s\n' "  Mailpit SMTP: smtp://localhost:${mailpit_smtp_port}"
printf '%s\n' "  Mailpit Web: http://localhost:${mailpit_web_port}"
