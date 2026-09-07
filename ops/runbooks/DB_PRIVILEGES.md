# Runbook — Least-privilege DB Production (gate §13 #4 + SECURITY_CHECKLIST A9)

**Runbook terkait:** [`DOC_MAP.md`](../../docs/DOC_MAP.md) · [`verify_privileges.sh`](../../ops/mysql/verify_privileges.sh) · status canonical [`temuan2.md`](../../temuan2.md) §13 gate #4

## Akun (dibuat oleh `ops/mysql/create_users.sh` dari
`backend/database/provisioning/mysql-privileges.sql`)
| Akun | Dipakai oleh | Hak |
|------|--------------|-----|
| `pdam_app` | php-fpm, `queue:work`, `schedule:run` (`DB_USERNAME`) | SELECT/INSERT/UPDATE/DELETE (+EXECUTE) pada `pdam.*` — TANPA DDL |
| `pdam_migrate` | deploy `php artisan migrate --force` (env khusus CI/CD) | DDL penuh, tanpa GRANT_OPTION |
| `pdam_backup` | `backup_production.sh` | read-only dump |
| `pdam_audit` | `DB_AUDIT_USERNAME` → connection `audit` | INSERT+SELECT **hanya** pada `privacy_audit_events` |
| `pdam_restore` | drill insiden | ALL hanya pada `pdam_restore_validation` |

## Append-only privacy_audit_events ( Berlapis)
1. **App layer**: `PrivacyAuditEvent::booted()` menolak update/delete.
2. **DB layer**: migration `2026_09_06_..._append_only_triggers` membuat
   `BEFORE UPDATE/DELETE` trigger (MySQL) — bahkan akun root runtime pun
   gagal mutate.
3. **Grant layer**: bila `pdam_audit` dipakai (env diset), INSERT/SELECT saja
   (SQLite/test tidak punya layer ini — SKIP di command).

## Prosedur deployment
```bash
# sekali per cluster (dari host admin)
APP_PW=... MIGRATE_PW=... BACKUP_PW=... AUDIT_PW=... RESTORE_PW=... \
  ./ops/mysql/create_users.sh <host> <port> root pdam

# .env aplikasi
DB_USERNAME=pdam_app        DB_PASSWORD=<app_pw>
DB_AUDIT_USERNAME=pdam_audit DB_AUDIT_PASSWORD=<audit_pw>   # opsional tapi rekomendasi

# bukti
./ops/mysql/verify_privileges.sh            # destructive probes
cd backend && php artisan pdam:audit-db-privileges   # dari sisi aplikasi
```
Kedua exit 0 → attach output + tanggal → `temuan2.md` §13 #4 tertutup (sebagai
penerapan; verifikasi pentest tetap terpisah).

## Rotasi password
`ALTER USER ... IDENTIFIED BY '<baru>'` → update `.env` → `php artisan
config:cache` → restart php-fpm/worker. Lakukan tiap 180 hari / saat orang
keluar tim.
