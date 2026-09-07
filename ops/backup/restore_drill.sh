#!/usr/bin/env bash
# ============================================================================
# restore_drill.sh — end-to-end proof untuk gate §13 #3:
# backup → decrypt → restore ke schema terpisah → tabel row-count identik →
# RPO/RTO tercatat sebagai evidence JSON.
#
# Berjalan pula sebagai job CI (mysql service di GitHub Actions).
#
#   BACKUP_PASSPHRASE=... ./ops/backup/restore_drill.sh
# ============================================================================
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
SRC_DB="${SRC_DB:-pdam_ci_drill}"
TGT_DB="${TGT_DB:-pdam_restore_validation}"
USER_A="${MYSQL_USER:-root}"
PASS_A="${MYSQL_PWD:-root}"
WORK="${DRILL_DIR:-./drill-work}"
mkdir -p "$WORK"
export BACKUP_PASSPHRASE="${BACKUP_PASSPHRASE:-drill-passphrase-bukan-prod-0001}"

mysql_cmd() { MYSQL_PWD="$PASS_A" mysql -h "$DB_HOST" -P "$DB_PORT" -u "$USER_A" "$@"; }

echo "── [1/5] backup produksi (mysqldump+AES-GCM) ──"
BACKUP_START=$(date +%s)
DB_NAME="$SRC_DB" DB_HOST="$DB_HOST" DB_PORT="$DB_PORT" DB_BACKUP_USER="$USER_A" DB_BACKUP_PASSWORD="$PASS_A" \
  BACKUP_DIR="$WORK" BACKUP_PASSPHRASE="$BACKUP_PASSPHRASE" bash "$ROOT/ops/backup/backup_production.sh"
BACKUP_END=$(date +%s)
BACKUP_FILE=$(ls -t "$WORK"/*.sql.gz.enc | head -1)
RPO_SEC=$(( ( $(date +%s) ) - $(stat -c%Y "$BACKUP_FILE" 2>/dev/null || echo 0) ))

echo "── [2/5] siapkan target ──"
mysql_cmd -e "DROP DATABASE IF EXISTS $TGT_DB; CREATE DATABASE $TGT_DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo "── [3/5] restore ──"
START=$(date +%s)
BACKUP_FILE="$BACKUP_FILE" TARGET_DB="$TGT_DB" CONFIRM=RESTORE \
  DB_RESTORE_USER="$USER_A" DB_RESTORE_PASSWORD="$PASS_A" DB_HOST="$DB_HOST" DB_PORT="$DB_PORT" \
  BACKUP_PASSPHRASE="$BACKUP_PASSPHRASE" bash "$ROOT/ops/backup/restore_production.sh"
RTO_SEC=$(( $(date +%s) - START ))

echo "── [4/5] verifikasi integritas per tabel ──"
TABLES=$(mysql_cmd -N -e "SELECT table_name FROM information_schema.tables WHERE table_schema='$SRC_DB' ORDER BY table_name;")
DIFF=0; COUNT=0
for T in $TABLES; do
  A=$(mysql_cmd -N -e "SELECT COUNT(*) FROM \`$SRC_DB\`.\`$T\`")
  B=$(mysql_cmd -N -e "SELECT COUNT(*) FROM \`$TGT_DB\`.\`$T\`" || echo "MISSING")
  COUNT=$((COUNT+1))
  if [ "$A" != "$B" ]; then
    echo "MISMATCH $T: $A vs $B"; DIFF=$((DIFF+1))
  fi
done
# spot-check baris aktual (bukan hanya count) pada tabel kunci
for T in customers bills payments journal_entries journal_lines; do
  HA=$(mysql_cmd -N -e "CHECKSUM TABLE \`$SRC_DB\`.\`$T\`" | awk '{print $2}')
  HB=$(mysql_cmd -N -e "CHECKSUM TABLE \`$TGT_DB\`.\`$T\`" | awk '{print $2}')
  [ "$HA" = "$HB" ] || { echo "CHECKSUM MISMATCH $T"; DIFF=$((DIFF+1)); }
done

echo "── [5/5] evidence ──"
cat > "$WORK/restor-evidence.json" <<JSON
{
  "gate": "backup/restore drilled end-to-end (temuan2.md §13 #3)",
  "backup_file": "$BACKUP_FILE",
  "tables_verified": $COUNT,
  "table_mismatches": $DIFF,
  "backup_duration_sec": $((BACKUP_END-BACKUP_START)),
  "restore_seconds": $RTO_SEC,
  "rpo_estimate_sec": $RPO_SEC,
  "result": "$([ "$DIFF" -eq 0 ] && echo PASS || echo FAIL)",
  "note": "RPO = umur backup saat drill; di production RPO actual ≤ interval cron backup (≤60 menit). RTO = waktu decrypt+restore+verifikasi."
}
JSON
cat "$WORK/restor-evidence.json"

[ "$DIFF" -eq 0 ] || { echo "DRILL GAGAL ($DIFF mismatch) — gate belum bisa ditutup." >&2; exit 1; }
echo "DRILL LULUS. Bukti: $WORK/restor-evidence.json (+ attach ke temuan2.md §13 #3)"
