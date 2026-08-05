#!/bin/sh
set -eu

timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
target="${BACKUP_DIR:-/backups}/${timestamp}"
mkdir -p "$target"
export PGPASSWORD="${POSTGRES_PASSWORD:?POSTGRES_PASSWORD é obrigatório}"
pg_dump --format=custom --no-owner --host="${POSTGRES_HOST:-postgres}" --username="${POSTGRES_USER:?POSTGRES_USER é obrigatório}" --dbname="${POSTGRES_DB:?POSTGRES_DB é obrigatório}" --file="$target/postgres.dump"
mc alias set source "${MINIO_ENDPOINT:?MINIO_ENDPOINT é obrigatório}" "${MINIO_ROOT_USER:?MINIO_ROOT_USER é obrigatório}" "${MINIO_ROOT_PASSWORD:?MINIO_ROOT_PASSWORD é obrigatório}" >/dev/null
mc mirror --overwrite "source/${AWS_BUCKET:?AWS_BUCKET é obrigatório}" "$target/object-storage"
find "${BACKUP_DIR:-/backups}" -mindepth 1 -maxdepth 1 -type d -mtime +"${BACKUP_RETENTION_DAYS:-14}" -exec rm -rf {} \;
printf 'Backup concluído: %s\n' "$target"
