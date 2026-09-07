#!/usr/bin/env bash
# ============================================================================
# backup_production.sh — backup DB terenkripsi (gate §13 #3).
#
# Config via environment (set di host backup, simpan passphrase di vault):
#   DB_HOST=127.0.0.1 DB_PORT=3306 DB_NAME=pdam
#   DB_BACKUP_USER=pdam_backup DB_BACKUP_PASSWORD=...      (akun read-only)
#   BACKUP_PASSPHRASE=...  → wajib (AES-256-CBC+PBKDF2 via openssl)
#   BACKUP_DIR=/var/backups/pdam  RETENTION_DAYS=14  OPTIONAL_TARGET=rsync://... 
#
# Cron reference (RPO ≤ 1 jam):
#   5 * * * *  BACKUP_PASSPHRASE_FILE=/etc/pdam/backup.pass ./ops/backup/backup_production.sh
# ============================================================================
set -euo pipefail
umask 077   # artefak backup PII: hanya owner yg boleh baca

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-pdam}"
DB_BACKUP_USER="${DB_BACKUP_USER:-pdam_backup}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/pdam}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"
PASS="${BACKUP_PASSPHRASE:-}"
if [ -z "$PASS" ] && [ -n "${BACKUP_PASSPHRASE_FILE:-}" ]; then
  PASS="$(cat "$BACKUP_PASSPHRASE_FILE")"
fi
[ -z "$PASS" ] && { echo "FATAL: set BACKUP_PASSPHRASE / BACKUP_PASSPHRASE_FILE (AES key)" >&2; exit 1; }

# kredensial tidak lewat argv (ps/proc leak): mysqldump membaca MYSQL_PWD dari env,
# openssl reading kunci via `-pass env:` — bukan `-pass pass:`.
export MYSQL_PWD="${DB_BACKUP_PASSWORD:-$PASS}"
export PDAM_BACKUP_PASS="$PASS"

TS=$(date -u +%Y%m%dT%H%M%SZ)
mkdir -p "$BACKUP_DIR"
OUT="$BACKUP_DIR/$DB_NAME-$TS.sql.gz.enc"
TMP="$OUT.part"
trap 'rm -f "$TMP" "$TMP.sha256" ; unset MYSQL_PWD PDAM_BACKUP_PASS; PASS="";' EXIT

START=$(date +%s)
# ONE database (TANPA --databases): stream tidak lagi memuat CREATE/USE `schema`
# sehingga restore bisa menentukan target sendiri (lihat restore_production.sh).
mysqldump --single-transaction --quick --routines --triggers --set-gtid-purged=OFF \
  -h "$DB_HOST" -P "$DB_PORT" -u "$DB_BACKUP_USER" "$DB_NAME" \
  | gzip -6 \
  | openssl enc -aes-256-cbc -pass env:PDAM_BACKUP_PASS -pbkdf2 -iter 200000 -salt > "$TMP"
END=$(date +%s)
unset MYSQL_PWD PDAM_BACKUP_PASS

# sidecar sha256: hanya HASH (bukan 'hash  filename', karena sed '.*  ' di kode lama
# menelan hash & bikin restore selalu mismatch). Restore memakai awk $1.
sha256sum "$TMP" | awk '{print $1}' > "$TMP.sha256"
mv "$TMP" "$OUT"
mv "$TMP.sha256" "$OUT.sha256"

SIZE=$(stat -c%s "$OUT" 2>/dev/null || stat -f%z "$OUT")
echo "{\"type\":\"backup\",\"db\":\"$DB_NAME\",\"ts\":\"$TS\",\"file\":\"$OUT\",\"size_bytes\":$SIZE,\"duration_sec\":$((END-START)),\"cipher\":\"aes-256-cbc+pbkdf2_200k\"}" > "$OUT.meta"

# retensi lokal
find "$BACKUP_DIR" -name "$DB_NAME-*.sql.gz.enc" -mtime +"$RETENTION_DAYS" -delete
find "$BACKUP_DIR" -name "$DB_NAME-*.sha256" -mtime +"$RETENTION_DAYS" -delete
find "$BACKUP_DIR" -name "$DB_NAME-*.meta" -mtime +"$RETENTION_DAYS" -delete

# replikasi opsional (rclone/aws s3/rsync) — isi sesuai target offsite
if [ -n "${BACKUP_REMOTE_CMD:-}" ]; then
  $BACKUP_REMOTE_CMD "$OUT" "$OUT.sha256" "$OUT.meta"
fi

echo "OK: $OUT ($SIZE bytes, $((END-START))s)"
