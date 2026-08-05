#!/bin/sh
set -eu
source_dir="${1:?Informe o diretório do backup}"
export PGPASSWORD="${POSTGRES_PASSWORD:?POSTGRES_PASSWORD é obrigatório}"
pg_restore --clean --if-exists --no-owner --host="${POSTGRES_HOST:-postgres}" --username="${POSTGRES_USER:?POSTGRES_USER é obrigatório}" --dbname="${POSTGRES_DB:?POSTGRES_DB é obrigatório}" "$source_dir/postgres.dump"
mc alias set destination "${MINIO_ENDPOINT:?MINIO_ENDPOINT é obrigatório}" "${MINIO_ROOT_USER:?MINIO_ROOT_USER é obrigatório}" "${MINIO_ROOT_PASSWORD:?MINIO_ROOT_PASSWORD é obrigatório}" >/dev/null
mc mirror --overwrite "$source_dir/object-storage" "destination/${AWS_BUCKET:?AWS_BUCKET é obrigatório}"
printf 'Restauração concluída: %s\n' "$source_dir"
