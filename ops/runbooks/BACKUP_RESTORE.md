# Runbook — Backup & Restore (gate §13 #3)

**Runbook terkait:** [`DOC_MAP.md`](../../docs/DOC_MAP.md) · status canonical [`temuan2.md`](../../temuan2.md) §13 gate #3

**Target:** MySQL 8 production + `storage/app/private` (artefak privat).

## Kebijakan
- **RPO ≤ 60 menit**: cron backup tiap jam (`5 * * * *` pada host backup)
  memakai `ops/backup/backup_production.sh`.
- **RTO ≤ 4 jam**: restore di drill aktual (lihat evidence JSON); kalau
  lebih dari 4 jam, insiden P1.
- Backup = `mysqldump --single-transaction` → gzip → AES-256-GCM (PBKDF2
  200k iter). Passphrase disimpan di vault terpisah dari host DB.
- Salinan offsite: `BACKUP_REMOTE_CMD` (rclone/s3), retensi lokal 14 hari.

## Drill wajib (sebelum gate bisa ditandai tertutup)
1. `APP_*` DB diarahkan ke staging/duplikat produksi (isolated).
2. `BACKUP_PASSPHRASE=... ./ops/backup/restore_drill.sh` — menghasilkan
   `drill-work/restor-evidence.json` (row-count + CHECKSUM + RTO/RPO).
3. Simpan evidence + hash file backup ke PR / `temuan2.md` §13.
4. Ulangi tiap kuartal dan setiap kali skema berubah besar.

## Restore insiden
```bash
export BACKUP_PASSPHRASE_FILE=/etc/pdam/backup.pass
BACKUP_FILE=/var/backups/pdam/pdam-<ts>.sql.gz.enc \
TARGET_DB=pdam CONFIRM=RESTORE ./ops/backup/restore_production.sh
```
> Restore ke schema `pdam` hanya boleh setelah `maintenance mode` +
> approval pemilik risiko + backup state terkini disimpan.

## Storage privat
`rsync -a /var/www/pdam/backend/storage/app/private/ backup-host:/rsync-target/`
(sebelum restore DB, folder harus ada: artefak laporan/privat dirujuk by-path).
