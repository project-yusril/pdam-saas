#!/usr/bin/env bash
# ============================================================================
# restore_production.sh — restore backup terenkripsi ke TARGET_DB eksplisit.
# DESTRUKTIF — wajib CONFIRM=RESTORE. Tidak pernah menyentuh schema sumber:
# dump dibuat TANPA --databases (tidak ada USE `src`) dan client dipaksa
# default-database $TARGET_DB + --one-database.
#
#   BACKUP_FILE=/var/backups/pdam/pdam-<ts>.sql.gz.enc \
#   BACKUP_PASSPHRASE_FILE=/etc/pdam/backup.pass \
#   TARGET_DB=pdam_restore_validation CONFIRM=RESTORE \
#   DB_RESTORE_USER=pdam_restore DB_RESTORE_PASSWORD=... ./ops/backup/restore_production.sh
# ============================================================================
set -euo pipefail

BACKUP_FILE="${BACKUP_FILE:?BACKUP_FILE wajib}"
TARGET_DB="${TARGET_DB:?TARGET_DB wajib eksplisit (mis. pdam_restore_validation)}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_RESTORE_USER="${DB_RESTORE_USER:-pdam_restore}"
CONFIRM="${CONFIRM:-}"
PASS="${BACKUP_PASSPHRASE:-}"
[ -z "$PASS" ] && [ -n "${BACKUP_PASSPHRASE_FILE:-}" ] && PASS="$(cat "$BACKUP_PASSPHRASE_FILE")"

if [ "$CONFIRM" != "RESTORE" ]; then
  echo "Ditolak: set CONFIRM=RESTORE untuk menyatakan paham ini menimpa DB target ($TARGET_DB)." >&2
  exit 1
fi
if [ "$TARGET_DB" = "pdam" ]; then
  echo "Ditolak: restore langsung ke schema produksi 'pdam'. Target validasi dulu." >&2
  exit 1
fi

if [ -f "$BACKUP_FILE.sha256" ]; then
  EXPECTED=$(awk '{print $1}' "$BACKUP_FILE.sha256")
  ACTUAL=$(sha256sum "$BACKUP_FILE" | awk '{print $1}')
  [ "$EXPECTED" = "$ACTUAL" ] || { echo "FAIL checksum ciphertext tidak cocok — JANGAN restore." >&2; exit 2; }
  echo "PASS checksum ciphertext cocok"
fi

export PDAM_RESTORE_PASS="$PASS"
export MYSQL_PWD="${DB_RESTORE_PASSWORD:-}"
trap 'unset PDAM_RESTORE_PASS MYSQL_PWD; PASS=""' EXIT

# decrypt → gunzip → buang klauul DEFINER (pdam_restore tak punya SUPER; dump
# pasca-migration trigger menyertakan DEFINER pembuat). Stream SQL diarahkan ke
# $TARGET_DB sebagai default database (mysql < db argumen) — BUKAN USE dari dump.
echo "Memulai restore $BACKUP_FILE → $TARGET_DB ..."
if ! { openssl enc -d -aes-256-gcm -pass env:PDAM_RESTORE_PASS -pbkdf2 -iter 200000 -in "$BACKUP_FILE" \
      | gunzip \
      | sed -E 's/DEFINER[[:space:]]*=[[:space:]]*[^ ]+//g' \
      | mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_RESTORE_USER" "$TARGET_DB"; }; then
  echo "FAIL restore/error stream (decrypt?, grants?, atau SQL error) — periksa pesan di atas." >&2
  exit 3
fi

echo "Restore selesai ke $TARGET_DB. Jalankan verifikasi (ops/runbooks/BACKUP_RESTORE.md)."
