#!/usr/bin/env bash
# ============================================================================
# create_users.sh — jalankan provisioning least-privilege (gate §13 #4).
# Render backend/database/provisioning/mysql-privileges.sql mengganti
# placeholder password dengan env, lalu eksekusi sebagai admin.
#
#   APP_PW=... MIGRATE_PW=... BACKUP_PW=... AUDIT_PW=... RESTORE_PW=... \
#   MYSQL_PWD=rootpass ./ops/mysql/create_users.sh 127.0.0.1 3306 root pdam
#
# Setelah selesai: JALANKAN verify_privileges.sh simpan buktinya.
# ============================================================================
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
SQL="$ROOT/backend/database/provisioning/mysql-privileges.sql"
HOST="${1:-127.0.0.1}"
PORT="${2:-3306}"
ADMIN="${3:-root}"
DBNAME="${4:-pdam}"

: "${APP_PW:?set APP_PW}" "${MIGRATE_PW:?}" "${BACKUP_PW:?}" "${AUDIT_PW:?}" "${RESTORE_PW:?}"
# koneksi admin: gunakan ADMIN_PWD → MYSQL_PWD (mysql client membaca env, bukan argv)
ADMIN_PWD="${ADMIN_PWD:-${MYSQL_PWD:-}}"
[ -n "$ADMIN_PWD" ] && export MYSQL_PWD="$ADMIN_PWD"

TMP=$(mktemp)
chmod 600 "$TMP"
trap 'rm -f "$TMP"; unset MYSQL_PWD' EXIT
sed -e "s/<APP_PASSWORD_STRONG_MIN_20>/$APP_PW/" \
    -e "s/<MIGRATE_PASSWORD_STRONG_MIN_20>/$MIGRATE_PW/" \
    -e "s/<BACKUP_PASSWORD_STRONG_MIN_20>/$BACKUP_PW/" \
    -e "s/<AUDIT_PASSWORD_STRONG_MIN_20>/$AUDIT_PW/" \
    -e "s/<RESTORE_PASSWORD_STRONG_MIN_20>/$RESTORE_PW/" \
    -e "s/\`pdam\`/\`$DBNAME\`/g" \
    "$SQL" > "$TMP"

mysql -h "$HOST" -P "$PORT" -u "$ADMIN" --comments < "$TMP"
echo "PASS: akun pdam_app / pdam_migrate / pdam_backup / pdam_audit / pdam_restore dibuat pada $HOST:$PORT/$DBNAME"
