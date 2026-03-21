#!/bin/bash
# Sincroniza DB y archivos S3 de produccion al entorno local.
# Requiere: mc (brew install minio/stable/mc)
# Uso: ./scripts/sync-prod.sh

set -e

PROJECT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
ENV_FILE="$PROJECT_DIR/.env"

if [ ! -f "$ENV_FILE" ]; then
    echo "No se encontro .env en $PROJECT_DIR"
    exit 1
fi

env_val() { grep "^$1=" "$ENV_FILE" | head -1 | cut -d= -f2- | tr -d '"'; }

# --- SSH produccion ---
SSH_KEY="$(env_val SYNC_SSH_KEY)"
SSH_USER="$(env_val SYNC_SSH_USER)"
SSH_HOST="$(env_val SYNC_SSH_HOST)"
SSH_PORT="$(env_val SYNC_SSH_PORT)"
SSH_PORT="${SSH_PORT:-22}"

# --- DB remota ---
REMOTE_DB_USER="$(env_val SYNC_DB_USER)"
REMOTE_DB_NAME="$(env_val SYNC_DB_NAME)"
REMOTE_DB_CONTAINER="$(env_val SYNC_DB_CONTAINER)"

# --- DB local (del docker compose) ---
LOCAL_DB_USER="$(env_val DB_USERNAME)"
LOCAL_DB_PASS="$(env_val DB_PASSWORD)"
LOCAL_DB_HOST="$(env_val DB_HOST)"
LOCAL_DB_PORT="$(env_val DB_PORT)"
LOCAL_DB_NAME="$(env_val DB_DATABASE)"

# --- S3 produccion (Contabo) ---
CONTABO_ENDPOINT="$(env_val SYNC_S3_ENDPOINT)"
CONTABO_BUCKET="$(env_val SYNC_S3_BUCKET)"
CONTABO_KEY="$(env_val SYNC_S3_KEY)"
CONTABO_SECRET="$(env_val SYNC_S3_SECRET)"

# --- MinIO local (del docker compose) ---
MINIO_PORT="$(env_val FORWARD_MINIO_PORT)"
MINIO_PORT="${MINIO_PORT:-9000}"
MINIO_ENDPOINT="http://localhost:$MINIO_PORT"
MINIO_BUCKET="$(env_val AWS_BUCKET)"

# --- Validaciones ---
MISSING=""
[ -z "$SSH_KEY" ] && MISSING="$MISSING SYNC_SSH_KEY"
[ -z "$SSH_HOST" ] && MISSING="$MISSING SYNC_SSH_HOST"
[ -z "$SSH_USER" ] && MISSING="$MISSING SYNC_SSH_USER"
[ -z "$REMOTE_DB_USER" ] && MISSING="$MISSING SYNC_DB_USER"
[ -z "$REMOTE_DB_NAME" ] && MISSING="$MISSING SYNC_DB_NAME"
[ -z "$CONTABO_KEY" ] && MISSING="$MISSING SYNC_S3_KEY"
[ -z "$CONTABO_BUCKET" ] && MISSING="$MISSING SYNC_S3_BUCKET"
[ -z "$MINIO_BUCKET" ] && MISSING="$MISSING AWS_BUCKET"

if [ -n "$MISSING" ]; then
    echo "Variables faltantes en .env:$MISSING"
    exit 1
fi

if ! command -v mc &> /dev/null; then
    echo "mc no encontrado. Instala con: brew install minio/stable/mc"
    exit 1
fi

BACKUP_FILE="/tmp/grandes_sync_$$.sql"

echo ""
echo "=== 1/4 Descargando backup de DB ==="

CONTAINER=$(ssh -p "$SSH_PORT" -i "$SSH_KEY" "$SSH_USER@$SSH_HOST" \
  "docker ps --filter name=${REMOTE_DB_CONTAINER:-database} --format '{{.Names}}' | head -1")

if [ -z "$CONTAINER" ]; then
    echo "Container de postgres no encontrado en produccion"
    exit 1
fi

ssh -p "$SSH_PORT" -i "$SSH_KEY" "$SSH_USER@$SSH_HOST" \
  "docker exec $CONTAINER pg_dump --no-owner --no-acl -U $REMOTE_DB_USER $REMOTE_DB_NAME" > "$BACKUP_FILE"

echo "Descargado: $(du -sh "$BACKUP_FILE" | cut -f1)"

echo ""
echo "=== 2/4 Restaurando DB local ==="

if [ "$LOCAL_DB_HOST" != "127.0.0.1" ] && [ "$LOCAL_DB_HOST" != "localhost" ]; then
    echo "ERROR: DB_HOST ($LOCAL_DB_HOST) no es localhost. Abortando para no borrar produccion."
    rm "$BACKUP_FILE"
    exit 1
fi

PGPASSWORD="$LOCAL_DB_PASS" psql -U "$LOCAL_DB_USER" -h "$LOCAL_DB_HOST" -p "$LOCAL_DB_PORT" -d postgres -c \
  "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '$LOCAL_DB_NAME' AND pid <> pg_backend_pid();" -q
PGPASSWORD="$LOCAL_DB_PASS" dropdb -U "$LOCAL_DB_USER" -h "$LOCAL_DB_HOST" -p "$LOCAL_DB_PORT" --if-exists "$LOCAL_DB_NAME"
PGPASSWORD="$LOCAL_DB_PASS" createdb -U "$LOCAL_DB_USER" -h "$LOCAL_DB_HOST" -p "$LOCAL_DB_PORT" "$LOCAL_DB_NAME"
PGPASSWORD="$LOCAL_DB_PASS" psql -U "$LOCAL_DB_USER" -h "$LOCAL_DB_HOST" -p "$LOCAL_DB_PORT" -d "$LOCAL_DB_NAME" -f "$BACKUP_FILE" -q

rm "$BACKUP_FILE"
echo "DB restaurada"

echo ""
echo "=== 3/4 Migraciones pendientes ==="

cd "$PROJECT_DIR"
php artisan migrate --force --no-interaction

echo ""
echo "=== 4/4 Sincronizando S3 (Contabo -> MinIO local) ==="

mc alias set contabo "$CONTABO_ENDPOINT" "$CONTABO_KEY" "$CONTABO_SECRET" --api S3v4 -q
mc alias set local-minio "$MINIO_ENDPOINT" "$(env_val AWS_ACCESS_KEY_ID)" "$(env_val AWS_SECRET_ACCESS_KEY)" --api S3v4 -q

mc mb "local-minio/$MINIO_BUCKET" 2>/dev/null || true
mc mirror --overwrite "contabo/$CONTABO_BUCKET" "local-minio/$MINIO_BUCKET"

echo ""
echo "Listo. Entorno local sincronizado con produccion."
