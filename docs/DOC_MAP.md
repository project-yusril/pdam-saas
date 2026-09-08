| Baris route registry | **391 method rows** (295 API `/api/v1` + 19 web + refund routes) |# Documentation Map & Fact Sheet — PDAM SaaS

**Diperbarui:** 7 September 2026 · **Pemilik:** seluruh tim · **Dokumen ini = indeks & fakta bersama.**
Bila ada angka/status yang berbeda antar dokumen, dokumen ini yang jadi penengah — dan
semuanya harus mengarah ke [`../temuan2.md`](../temuan2.md) sebagai **sumber status/audit
authoritative** (enam gate production canonical ada di `temuan2.md` §13).

<!-- doc-sync:start verifikasi 7 Sept 2026 -->
> **Verifikasi terintegrasi:** 129/129 (129 test backend, 853 assertions) · Vitest 13 · Flutter analyze 0 issue + 46/46 · ML 32 (CI) · 371 method /api/v1 (295 path registry) · 65 migration · 28 seeder · 21 command · 167 tabel statis · 136 model · 2026-09-07. Kanoni angka: [`docs/COUNTS.json`](COUNTS.json) · status resmi: [`temuan2.md`](../temuan2.md) §13 · peta dokumen: [`docs/DOC_MAP.md`](DOC_MAP.md) · tooling: [`ops/README.md`](../ops/README.md) · CI: [`.github/workflows/ci.yml`](../.github/workflows/ci.yml) + `nightly-ops.yml`.
<!-- doc-sync:end -->

## 1. Kepemilikan dokumen (satu topik = satu pemilik)

| Topik | Pemilik (source of truth) | Konsumen/dirujuk oleh |
|-------|---------------------------|------------------------|
| Status implementasi, severity, checklist, **enam gate production** | [`../temuan2.md`](../temuan2.md) | README, HANDOVER, SECURITY_CHECKLIST, DEPLOY, SEED_DATA, ml/README, OWASP, ops/README |
| Target produk, harga, role, modul (spesifikasi) | [`../PRD.md`](../PRD.md) (+ [`../02_flow.md`](../02_flow.md) untuk alur/ERD/cron/layanan) | README "Arsip historis", task/temuan, HANDOVER §1 |
| Setup backend, autentikasi, session/CSRF/device, troubleshooting | [`../backend/README.md`](../backend/README.md) | README, HANDOVER §3, DEPLOY, SEED_DATA |
| Fixture & seluruh kredensial demo | [`../backend/SEED_DATA.md`](../backend/SEED_DATA.md) | README, HANDOVER §3, temuan2, task |
| Runbook deployment (pre-flight, worker, backup, TLS, pin, least-priv) | [`../backend/DEPLOY.md`](../backend/DEPLOY.md) | README "Runbook Produksi", HANDOVER §5, ops/README |
| Baseline keamanan internal (OWASP Top10/ASVS) | [`../SECURITY_CHECKLIST.md`](../SECURITY_CHECKLIST.md) + [`../tests/security/OWASP_ASVS_AUDIT.md`](../tests/security/OWASP_ASVS_AUDIT.md) (assessment) | README "Checklist Keamanan", HANDOVER §6, temuan2 |
| Tooling operasional & gate (script TLS/backup/privilege/load/pentest scope) | [`../ops/README.md`](../ops/README.md) & runbook `../ops/runbooks/*` | temuan2 §13, DEPLOY, HANDOVER §8, README |
| Keputusan bisnis PRD §23 yang menunggu konfirmasi | [`BUSINESS_DECISIONS.md`](BUSINESS_DECISIONS.md) (`config/business.php`) | README, HANDOVER §8, DEPLOY |
| Angka inventaris live (endpoint/model/tabel/seeder/test) | [`COUNTS.json`](COUNTS.json) — digenerasi `php artisan pdam:counts` | CI gate drift-check, run hijau `2909807` (6/6), README, HANDOVER, temuan2 |
| Kalibrasi & gerbang acceptance ML production | [`ML_CALIBRATION.md`](ML_CALIBRATION.md) | ml/README, ops/README, temuan2 §13 #6, DEPLOY |
| Capacity baseline load test | [`CAPACITY_BASELINE.md`](CAPACITY_BASELINE.md) diisi dari runner `../ops/load/run_load_test.sh` | ops/runbooks/OBSERVABILITY, HANDOVER |
| Arsip historis (JANGAN dipakai sebagai status) | [`../task.md`](../task.md), [`../temuan.md`](../temuan.md) | hanya untuk riwayat |

## 2. Snapshot verifikasi fact sheet (7 September 2026, lokal Windows + CI definition)

| Metrik | Nilai | Sumber |
|---|---|---|
| Baris route registry | **390 method rows** (372 API `/api/v1` + 19 web) | `php artisan pdam:openapi`/counts + `COUNTS.json` |
| URI API unik | **293** | `route:list --json` |
| OpenAPI kanonik (drift CI) | **293 path/371 method** di `docs/openapi.json` | `php artisan pdam:openapi --out=../docs/openapi.json` && CI `git diff --exit-code` |
| Models | **136** | `COUNTS.json` |
| Migrations | **65** | `COUNTS.json` (SQL 000004–000010 + `2026_09_06` orders/triggers) |
| Seeder classes | **28** (production path: 4 — kernel+permission/module/role) | `COUNTS.json`, `SEED_DATA.md` |
| Tabel (statis dari migration) | **168** | `COUNTS.json` |
| Command artisan `pdam:*` | **22** (baru: openapi, mobile-coverage, audit-db-privileges, queue/integrations-health, ml export, counts; 21 total) | `COUNTS.json` |
| Test backend (SQLite) | **134/134** (853 assertions) (8 Sep; +Tier3/ops/sim contracts) | `php artisan test` |
| Test frontend Vitest | **7 files / 13 tests** + `npm run build` OK | `npm run test -- --run` |
| Test Flutter | **50** (2 + dio client) + analyze **0 issue** | `flutter test` |
| Test Python (ml) | **32 test methods** (29 sebelumnya + 3 contract simulator tier-3) — dieksekusi CI `ml` (xgboost tak tersedia di runner Win-ARM64 lokal) | `test_*.py` + workflow |
| CI root | `.github/workflows/ci.yml` (run 2909807 lulus 6/6): `backend-sqlite` (pint, PHPUnit, counts-drift, openapi drift, mobile-coverage, YAML validation), `mysql-production-gates`, `frontend`, `mobile`, `ml`, `security`. Nightly ops: `.github/workflows/nightly-ops.yml` (k6 + simulator tier-3) — hanya jalan bila `LOAD_ENABLED=true` vars repo |

## 3. Status enam gate canonical (§13 temuan2 — selalu cek di sana untuk angka final)

Semua gate **masih terbuka** sampai bukti environment nyata; tooling-nya sudah ada + CI
sudah menjalankan drill privileges & backup/restore otomatis di CI. Detail lengkap:
[`../temuan2.md`](../temuan2.md) §13 & [`../ops/README.md`](../ops/README.md).

1. TLS endpoint — `ops/tls/verify_production_tls.sh` (evidence JSON otomatis).
2. Pentest eksternal — `ops/PENTEST_SCOPE.md`; internal pre-scan `tests/security/*`.
3. Backup/restore — `ops/backup/*` (AES-256-GCM + drill end-to-end; **CI job
   `mysql-production-gates`** menghasilkan `evidence.json`).
4. Least-privilege DB — `backend/database/provisioning/mysql-privileges.sql` + trigger
   append-only + `ops/mysql/{create_users,verify_privileges}.sh` + `pdam:audit-db-privileges`.
5. Rotasi pin — `ops/tls/verify_spki_pins.sh` + `ops/runbooks/TLS_PINNING_ROTATION.md`.
6. ML production-calibrated — `pdam:ml-export-training-data` → `ml/scripts/calibrate_production.py`
   (gerbang data & threshold; lihat [`ML_CALIBRATION.md`](ML_CALIBRATION.md)); 4 artifact tetap
   `fixture_validation` sampai data riil + `--promote`.

## 4. Kesepakatan integrasi (apa yang dilakukan tiap edit dokumen)

1. **Angka** hanya diambil dari `COUNTS.json` (regenerasi: `php artisan pdam:counts --write` dari folder `backend/`).
2. **Status** hanya dinyatakan di `temuan2.md`; dokumen lain menunjuk ke sana, tidak mengarang status baru.
3. **Klaim keamanan** = kontrol source/test internal + (`SECURITY_CHECKLIST.md`/`OWASP_ASVS_AUDIT.md`), **bukan** sertifikasi/pentest (pentest = gate §13).
4. **Deployment-dependent**: setiap item yang butuh environment nyata wajib menyebut script/runbook ops-nya.
5. **Anchor link**: heading target harus benar-benar ada (diperiksa `python /tmp/linkcheck.py` / job docs CI lokal).
6. **Format dokumentasi**: Indonesia untuk dokumen produk; istilah teknis/CLI dibiarkan apa adanya; jangan pakai emoji klaim “selesai” bila evidence-nya belum ada.