-- ============================================================================
-- PDAM SaaS — Production database least privileges (temuan2.md §13 gate #4)
-- Jalankan sebagai MySQL admin (root) SEKALI per cluster, setelah schema dibuat.
-- Ganti <...> dengan nilai aktual; jangan commit password riil.
-- ============================================================================

-- 0) Buat schema bila belum ada
CREATE DATABASE IF NOT EXISTS pdam CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 1) AKUN RUNTIME APLIKASI (php-fpm, queue worker, scheduler) — tanpa DDL.
--    Tidak ada CREATE/ALTER/DROP/INDEX/GRANT OPTION; DELETE di semua tabel
--    bisnis diizinkan, tetapi trigger `privacy_audit_events_no_delete/update`
--    (migration 2026_09_06_000002) membuat privacy_audit_events append-only.
CREATE USER IF NOT EXISTS 'pdam_app'@'%' IDENTIFIED BY '<APP_PASSWORD_STRONG_MIN_20>';
ALTER USER 'pdam_app'@'%' IDENTIFIED BY '<APP_PASSWORD_STRONG_MIN_20>';
REVOKE ALL, GRANT OPTION FROM 'pdam_app'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON `pdam`.* TO 'pdam_app'@'%';
-- Trigger append-only dibuat oleh pdam_migrate saat migration; runtime tidak perlu TRIGGER.

-- 2) AKUN MIGRATION/DDL — dipakai HANYA saat deploy `php artisan migrate --force`,
--    tidak boleh dipakai oleh php-fpm/worker.
CREATE USER IF NOT EXISTS 'pdam_migrate'@'%' IDENTIFIED BY '<MIGRATE_PASSWORD_STRONG_MIN_20>';
ALTER USER 'pdam_migrate'@'%' IDENTIFIED BY '<MIGRATE_PASSWORD_STRONG_MIN_20>';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES,
      CREATE TEMPORARY TABLES, LOCK TABLES, EXECUTE, TRIGGER, EVENT
ON `pdam`.* TO 'pdam_migrate'@'%';
GRANT CREATE ON `pdam\_restore\_validation`.* TO 'pdam_migrate'@'%';

-- 3) AKUN BACKUP — read-only minimum utk mysqldump single-db (gate §13 #4):
--    TANPA global scope, TANPA PROCESS/REPLICATION, TANPA DML. Cukup read
--    definisi (SELECT) + SHOW VIEW + TRIGGER (untuk membaca definisi trigger,
--    bukan membuat). backup_production.sh tidak memakai --databases lagi.
CREATE USER IF NOT EXISTS 'pdam_backup'@'%' IDENTIFIED BY '<BACKUP_PASSWORD_STRONG_MIN_20>';
ALTER USER 'pdam_backup'@'%' IDENTIFIED BY '<BACKUP_PASSWORD_STRONG_MIN_20>';
GRANT SELECT, SHOW VIEW, TRIGGER, EVENT ON `pdam`.* TO 'pdam_backup'@'%';

-- 4) AKUN AUDIT (append-only writer untuk privacy_audit_events, opsional
--    via DB_AUDIT_*). HANYAINSERT+SELECT pada SATU tabel — tidak bisa
--    membaca tabel lain (termasuk PII), tidak bisa mengubah/menghapus.
CREATE USER IF NOT EXISTS 'pdam_audit'@'%' IDENTIFIED BY '<AUDIT_PASSWORD_STRONG_MIN_20>';
ALTER USER 'pdam_audit'@'%' IDENTIFIED BY '<AUDIT_PASSWORD_STRONG_MIN_20>';
GRANT INSERT, SELECT ON `pdam`.`privacy_audit_events` TO 'pdam_audit'@'%';

-- 5) Akun validasi restore (drill) — terpisah, boleh di-drop setelah drill.
CREATE DATABASE IF NOT EXISTS pdam_restore_validation CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'pdam_restore'@'%' IDENTIFIED BY '<RESTORE_PASSWORD_STRONG_MIN_20>';
ALTER USER 'pdam_restore'@'%' IDENTIFIED BY '<RESTORE_PASSWORD_STRONG_MIN_20>';
GRANT ALL PRIVILEGES ON `pdam\_restore\_validation`.* TO 'pdam_restore'@'%';

-- Hardening host: ganti '%' dengan subnet/IP aplikasi bila memungkinkan,
-- mis.: 'pdam_app'@'10.10.0.%'. Lalu:
FLUSH PRIVILEGES;

-- Verifikasi (jalankan sebagai root):
--   SHOW GRANTS FOR 'pdam_app'@'%';      → tanpa DROP/ALTER/CREATE
--   SHOW GRANTS FOR 'pdam_audit'@'%';    → INSERT, SELECT pada 1 tabel saja
--   SHOW GRANTS FOR 'pdam_backup'@'%';   → tanpa INSERT/UPDATE/DELETE
-- Otomasi: `php artisan pdam:audit-db-privileges` dari sisi aplikasi.
