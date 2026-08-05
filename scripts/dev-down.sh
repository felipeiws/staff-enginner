#!/usr/bin/env bash

set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
project_dir="$(cd "$script_dir/.." && pwd)"
compose_file="$project_dir/docker/docker-compose.yml"
env_file="$project_dir/.env"

if [[ ! -f "$env_file" ]]; then
  env_file="$project_dir/.env.example"
fi

docker compose --env-file "$env_file" -f "$compose_file" down --remove-orphans
