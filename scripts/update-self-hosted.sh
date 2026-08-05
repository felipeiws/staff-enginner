#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
env_file="$root_dir/docker/.env.production"
compose=(docker compose --env-file "$env_file" -f "$root_dir/docker/docker-compose.production.yml")

[[ -f "$env_file" ]] || { echo 'docker/.env.production não encontrado.' >&2; exit 2; }
"${compose[@]}" pull --ignore-buildable || true
"${compose[@]}" up --detach --build --remove-orphans
"${compose[@]}" exec --no-TTY backend php artisan migrate --force
"${compose[@]}" exec --no-TTY backend php artisan search:reindex-published
"${compose[@]}" ps
