# PDAM SaaS — Dokumen Serah Terima

**Tanggal:** 7 September 2026
**Versi:** 3.5
**Status:** Tooling & CI self-drill gate §13 selesai; rilis resmi masih menunggu bukti environment produksi (TLS/endpoint, pentest vendor, backup/restore + least-priv di host produksi, drill rotasi pin, data ML riil)


> **Sumber status saat ini:** [`temuan2.md`](temuan2.md), termasuk enam gate persetujuan production canonical.
> **Peta semua dokumentasi + fact sheet:** [`docs/DOC_MAP.md`](docs/DOC_MAP.md). Tooling gate: [`ops/README.md`](ops/README.md). CI lintas komponen: [`.github/workflows/ci.yml`](.github/workflows/ci.yml). Knob keputusan bisnis: [`docs/BUSINESS_DECISIONS.md`](docs/BUSINESS_DECISIONS.md) (`backend/config/business.php`). Angka live: [`docs/COUNTS.json`](docs/COUNTS.json). Gunakan [`backend/README.md`](backend/README.md) untuk autentikasi, [`backend/SEED_DATA.md`](backend/SEED_DATA.md) untuk fixture/kredensial demo, dan [`backend/DEPLOY.md`](backend/DEPLOY.md) untuk production.


## 1. Ringkasan Produk

Platform SaaS manajemen PDAM multi-tenant. 27 modul bisnis + 1 fondasi platform.

### Tech Stack
| Komponen | Teknologi |
|----------|-----------|
| Backend | Laravel 13, PHP 8.3 |
| Database | MySQL 8.4.x (target produksi), Redis 7 |
| Frontend Web | Vue 3 + Vite + Tailwind + PrimeVue |
| Mobile | Flutter (Clean Architecture — auth, portal, meter reading, survey, OCR, offline sync) |
| Queue | Redis + Laravel Queue |
| Payment Gateway | Midtrans Snap |
| CI/CD | ✅ Gate lengkap di root `.github/workflows/ci.yml`: pint+route/view/event/config cache, PHPUnit 109, MySQL migrate+seed+privileges drill+backup/restore drill, vitest 13+build, Flutter 44, ml pytest, gitleaks, composer/npm/pip audit, counts-sync |


## 2. Module Coverage

Status historis di bawah berarti schema/API dasar tersedia. Status production setiap modul tetap harus
memenuhi migration MySQL, API contract, UI/mobile integration, test, dan observability sesuai definisi
selesai di `temuan2.md`.

| # | Kode | Status | Catatan |
|---|------|--------|---------|
| 1 | CORE | API diuji | Pelanggan, tagihan, pembayaran, akuntansi, RBAC; web lengkap (halaman khusus + workbench) |
| 2 | ZONE | API + UI | Multi-wilayah: list, form CRUD, drill-down |
| 3 | SRV | API + kontrak OCR diuji | Multipart KTP, parsing+confidence, editable confirmation wajib |
| 4 | MTR | API + sync diuji | Baca meter, rute, OCR parse; sync failure/retry per client UUID diuji |
| 5 | WH | API+UI (workbench) | Gudang, PO, transfer |
| 6 | METX | API+UI (workbench) | Anomali meter |
| 7 | AST | API + UI | Endpoint + halaman assets (tabel+filter) + contract test |
| 8 | FIN+ | API+UI (workbench) | AR/AP/Tax/Budget/Bank |
| 9 | CRM | API+UI (workbench) | Pengaduan dan SLA |
| 10 | C360 | API+UI (workbench) | Customer 360 |
| 11 | BILL+ | API+UI (workbench) | Adjustment bill |
| 12 | APP | API contract diuji | Portal/account endpoint, token/refresh, typed endpoint builders, HTTPS/cleartext/pins/logging tercakup suite mobile |
| 13 | CHEM | API+UI (workbench) | Bahan kimia IPA |
| 14 | PROC | API+UI (workbench) | Tender dan vendor |
| 15 | FSM | API+UI (workbench) | Work order |
| 16 | MNT | API+UI (workbench) | Maintenance |
| 17 | HR | API+UI (workbench) | SDM (UI workbench/pegawai) |
| 18 | DMS | API + private file guard | Dokumen privat (UI workbench DMS + upload modal) |
| 19 | GIS | API+UI (workbench) | Pipa dan pelanggan |
| 20 | CC | API+UI (workbench) | Call center |
| 21 | BI | Security query + schedule diuji | Allowlist/entitlement report builder; CRUD/run/history/download privat dan dispatcher terjadwal tersedia |
| 22 | INT | API+UI (workbench) | Integrasi dan API key |
| 23 | IOT | Schema/API parsial | Membutuhkan hardware dan integration test |
| 24 | PROD | Schema/API parsial | Membutuhkan SCADA |
| 25 | DIST | Schema/API parsial | Membutuhkan telemetri |
| 26 | NRW | Schema/API parsial | Membutuhkan data DIST |
| 27 | AI | Pipeline + fixture validation | Auth, no-dummy, feature/artifact contract, dan empat fixture-validation artifact terverifikasi; production-calibrated model menunggu data representatif |

## 2.1 Antarmuka Web (Vue 3)

| Halaman | Route | Akses |
|---------|-------|-------|
| Landing page (profil produk, katalog modul, CTA) | `/` | Publik |
| Login (tenant / super admin) | `/login` | Publik |
| Dashboard tenant (per role) | `/dashboard` | Tenant |
| Panel super admin (kelola tenant, dashboard SaaS) | `/platform` | Super admin |
| Marketplace modul (27 modul + deskripsi + dependency) | `/platform/modules` | Super admin |
| Marketplace pembelian modul | `/marketplace` | Tenant |
| Operasional tenant | `/zones`, `/prospects`, `/meter-routes`, `/complaints`, `/assets` | Tenant |
| Enterprise tenant | `/gis`, `/employees`, `/employee-self-service`, `/call-center`, `/tenders` | Tenant |
| Workbench modul generik | `/modules/:code` (WH, BILL+, METX, FSM, MNT, DMS, INT, CC, APP, CHEM, PROC…; KPI IOT/PROD/DIST/NRW; info AI) | Tenant |
| Dashboard role | `/dashboard/{director|finance|technical|warehouse}` | Tenant |
| Halaman modul generik (workbench) | `/modules/:code` (WH, METX, BILL+, MNT, CHEM, PROC, APP, INT, DMS, FSM, CC, GIS, KPI IOT/PROD/DIST/NRW, info CORE/ZONE/AI) | Tenant | Tabel + filter + search + pagination server-side, form create & aksi status per resource (approve PO, purchase, receive, cancel, …) dari `resources/js/config/resources.js`; kartu KPI/dashboard object |

> Landing page bertema biru muda/cyan lembut (identik air). Katalog modul menampilkan deskripsi
> tiap modul + relasi dependency ("butuh modul prasyarat aktif dulu"). Tenant memakai katalog dari
> session, order pending, dan URL pembayaran Snap; platform tetap memiliki katalog/manual flow sendiri. Build asset: `npm run build`.

## 3. Credentials & Access

| Komponen | Lokasi |
|----------|--------|
| .env | `backend/.env` (tidak di-commit) |
| .env production | `backend/.env.production.example` |
| Midtrans keys | `config/services.php` |
| FCM key | `config/services.php` |
| API docs | `/api/documentation` (Swagger) |
| Postman | `backend/PDAM_SaaS_API.postman_collection.json` |

Laravel route registry adalah sumber endpoint aktual. Swagger/OpenAPI dan Postman saat ini referensi
parsial yang wajib dibandingkan dengan registry; Postman dibagi menurut web session, mobile bearer, dan
platform, tetapi tidak boleh dianggap sebagai inventaris endpoint lengkap.

### Data Demo (Seeder) — detail di `backend/SEED_DATA.md`

`php artisan migrate --seed` pada environment development/demo mengisi **3 tenant demo terintegrasi** (Canada,
Brazil, Sambas). Snapshot 15 Juli: **60 migration dan 24 seeder** pada MySQL 8.4.9 disposable & SQLite (167
tabel). Kondisi saat ini (lihat [`docs/COUNTS.json`](docs/COUNTS.json)): **65 migration, 28 class seeder** —
jalur production hanya `ProductionKernelSeeder` (Permission→Module→RoleTemplate + admin via env `PLATFORM_ADMIN_*`
dengan tolak password demo) dan `DemoGuard` **memblokir mutlak** seed demo di production (tanpa env escape).
Tenant **pdam-sambas** mendukung `SEED_CUSTOMER_COUNT` (default 3.000 → 5 untuk CI). MySQL rollback
`000004`-`000010` lalu migrate ulang lulus. Metadata: 234 FK total, 97 tenant batch FK + 8 actor FK,
preflight orphan/cross-tenant nol — detail [`temuan2.md`](temuan2.md).


Fixture transaksi inti pelanggan, billing, pembayaran, jurnal, dan stok dasar digerakkan lewat service aplikasi dan menghasilkan neraca demo balance. Fixture enterprise tambahan tidak seluruhnya membuat transaksi/jurnal penuh; sejumlah record memang referensial atau memakai `journal_entry_id = null`.

| Tenant | Kode login | Data | Modul aktif | Pelanggan | Tagihan |
|--------|-----------|------|-------------|-----------|---------|
| PDAM Canada | `pdam-canada` | Pontianak | 27 (LENGKAP) | 10 | 30 |
| PDAM Brazil | `pdam-brazil` | Surabaya | 10 (SEBAGIAN) | 5 | 10 |
| PDAM Sambas | `pdam-sambas` | Kabupaten Sambas (Kalbar) | 27 (LENGKAP) | 3.000 | 36.000 |

> "Canada/Brazil" hanya label anti-copyright; isi datanya Indonesia. Tenant **pdam-sambas** di-seed
> `SambasTenantSeeder` memakai alamat nyata Kab. Sambas (BPS 6101) + simulasi 1 tahun, tunggakan 1/2/3 bulan,
> 150 pelanggan diisolir, dan rantai baca meter (foto + petugas → tarif → tagihan → jurnal).

**Contoh login tenant demo:** `pdam-canada` / `admin_tenant@gmail.com` / `12345678` (juga berlaku untuk
`pdam-brazil` & `pdam-sambas` — beda hanya kode PDAM-nya).
**Contoh platform demo:** `superadmin@gmail.com` / `12345678`, tanpa kode PDAM. Daftar lengkap 35 role +
email & password per PDAM (Canada, Brazil & Sambas) dipelihara di [`README.md`](README.md#demo-credentials-akun--role-demo)
dan [`backend/SEED_DATA.md`](backend/SEED_DATA.md). `DatabaseSeeder` di production otomatis direduksi ke `ProductionKernelSeeder`; `DemoGuard` memblokir fixture demo **secara mutlak** saat APP_ENV=production (tanpa env escape; bukti `ProductionSeederIsolationTest`).

Login web memakai urutan CSRF cookie -> login -> session cookie; login mobile menambah `device_name`
dan menerima bearer token. Diagnosis `localhost` versus `127.0.0.1`, 419, 401, dan data fixture kosong
ada di [`backend/README.md`](backend/README.md#53-web-session-cookie--csrf).


## 4. Cron Jobs dan Dispatcher

| Command | Schedule |
|---------|----------|
| `pdam:send-billing-notifications` | 08:00 tgl 23-25 |
| `pdam:mark-overdue-bills` | 06:00 tgl 26 |
| `pdam:auto-isolir` | 02:00 tgl 1 |
| `pdam:installment-reminder` | 07:00 harian |
| `pdam:payment-reconciliation` | Tiap 6 jam |
| `pdam:recurring-journal` | 03:00 harian |
| `pdam:subscription-check` | 01:00 harian |
| `pdam:sla-escalation` | Tiap jam |
| `pdam:health-check` | Tiap 5 menit |
| Dispatcher scheduled report | Tiap menit melalui Laravel scheduler; membuat job pada queue `default` |

Worker queue `default` wajib aktif agar run scheduled report diproses; produksi direkomendasikan
`backend/docker/supervisord.production.conf` (worker 2 proc + loop scheduler + alarm `pdam:queue-health`
+ eventlistener `ops/supervisor/crash_alert.py` — protokol supervisor yang benar). Setiap schedule menyimpan
timezone, sedangkan `next_run_at` dihitung UTC; artifact **CSV/HTML/XLSX/PDF** disimpan privat dan diunduh
melalui endpoint berizin. Pengaturan knob jadwal (jam expire pembayaran dll.) lewat `backend/config/business.php`.

## 5. Draft Deployment Commands

Bagian ini adalah ringkasan handover, bukan runbook final atau persetujuan production. Ikuti [`backend/DEPLOY.md`](backend/DEPLOY.md) dan jangan merilis sebelum enam gate canonical di [`temuan2.md`](temuan2.md) ditutup.

```bash
cd backend
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan db:seed --class=ProductionKernelSeeder --force   # katalog platform; demo TIDAK pernah jalan di production
# (opsional) admin platform awal via env PLATFORM_ADMIN_EMAIL/NAME/PASSWORD — DemoGuard menolak kredensial demo
php artisan optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
# least-privilege DB (sekali per cluster): lihat backend/database/provisioning/mysql-privileges.sql
bash ../ops/mysql/create_users.sh ... && bash ../ops/mysql/verify_privileges.sh
php artisan pdam:audit-db-privileges
# worker+scheduler produksi: salin docker/supervisord.production.conf ke /etc/supervisor/conf.d/ + crash_alert.py
supervisorctl reread && supervisorctl update && supervisorctl status
php artisan integrations-health
php artisan queue-health
php artisan pdam:health-check
sudo systemctl restart php8.3-fpm nginx
```

SSL: Certbot + Let's Encrypt. Konfig: `docker/nginx.production.conf`. Verifikasi gate produksi memakai tooling:
`ops/tls/verify_production_tls.sh` (TLS/HSTS/chain/endpoint + evidence JSON), `ops/tls/verify_spki_pins.sh`
(drill rotasi — runbook `ops/runbooks/TLS_PINNING_ROTATION.md`), `ops/mysql/{create_users,verify_privileges}.sh`
+ `php artisan pdam:audit-db-privileges` (least-privilege), `ops/backup/backup_production.sh` +
`ops/backup/restore_drill.sh` (bukti RPO/RTO terenkripsi). Urutan eksekusi: `ops/README.md` + `docs/DOC_MAP.md`.

## 6. Security Status

- Terverifikasi backend: module entitlement, tenant isolation regression tests, private-file ownership,
  service authentication ML, BI/export identifier hardening, MFA, encrypted fields, rate limiting,
  privacy purge dual-control, kontrak OCR KTP multipart, seeder production-safe (kernel routing + DemoGuard
  blokade mutlak), reserve/stock-out material+idempotensi, dan kontrak export XLSX/PDF native yang jujur.
- Snapshot 7 September 2026 (lengkap di `docs/COUNTS.json`/`docs/DOC_MAP.md`): backend SQLite **109/109
  (768 assertions)**; frontend Vitest **7 file/13 tests** + build lulus (workbench, API errors, auth flow,
  resources config, router); Flutter 44/44 + analyze; ML **29** test (CI); route registry live: **368** API
  endpoint method rows. CI `mysql-production-gates` sudah menjalankan migration+seed, privileges drill,
  backup→restore drill sebagai bukti otomatis.
- Deployment-dependent (tooling lengkap — bukti target masih terbuka): TLS/HSTS + pin verify/rotation di
  endpoint riil, backup cron+drill restore di host produksi, external pentest, privilege provisioning di
  cluster produksi (`pdam:audit-db-privileges`), integrasi live (`pdam:integrations-health --ping`).
  Definitional: gate canonical enam-enamnya di `temuan2.md` §13.
- Tersedia: web Sanctum session HttpOnly+CSRF tanpa auth localStorage; mobile bearer token per perangkat
  + SPKI pinning primary/backup fail-closed (drill rotasi = gate).

## 7. Monitoring / Evidence Command / Runbook

| Komponen | Perintah / runbook |
|----------|--------------------|
| Health endpoint | `GET /up` (Laravel default) |
| Health app | `php artisan pdam:health-check` (DB/Redis/Disk/Queue — tiap 5 menit di production) |
| Queue alert | `php artisan pdam:queue-health [--max-queued --max-failed --max-failed-window --max-failed-age-hours]` — jalan otomatis loop di supervisord; exit!=0 = alert syslog |
| Integrasi | `php artisan pdam:integrations-health [--ping]` |
| Privilege DB | `php artisan pdam:audit-db-privileges` + `bash ops/mysql/verify_privileges.sh` (CI `mysql-production-gates`) |
| Backup/drill | `ops/backup/*.sh` |
| TLS+pin | `ops/tls/verify_production_tls.sh`, `ops/tls/verify_spki_pins.sh`, drill `ops/runbooks/` |
| Load/capacity | `ops/load/run_load_test.sh` → `docs/CAPACITY_BASELINE.md` |
| Inventaris & drift | `php artisan pdam:counts` (check) / `--write` |
| Alerting insiden | `ops/runbooks/OBSERVABILITY.md` |
| Nginx monitoring proxy | `docker/monitoring.conf` |

Runbook lengkap: `ops/README.md` + `ops/runbooks/*`; peta dokumen `docs/DOC_MAP.md`.

## 8. Yang Belum Selesai

Enam blocking gate authoritative hanya yang tercantum pada `temuan2.md`: production TLS endpoint, external pentest, tested backup/restore, production DB least privileges, certificate primary/backup rotation drill, dan representative-data ML calibration/acceptance. Item operasional lain di bawah adalah rekomendasi/roadmap dan tidak menambah daftar gate canonical.

| Item | Prioritas | Keterangan |
|------|-----------|------------|
| HTTPS/TLS production | High | `ops/tls/verify_production_tls.sh` siap jalankan — masih butuh domain/SSL riil |
| Export native PDF/XLSX | ✅ selesai | `ReportExportService` kini menghasilkan XLSX (PhpSpreadsheet) & PDF (dompdf, kop surat PDAM); `format=pdf/xlsx` via `/export` + scheduled reports; DOC surat masih roadmap PRD |
| Material stock-out pemasangan | ✅ selesai | Reservasi stok → `complete()` = stock-out gudang utama + jurnal DEBIT kapitalisasi / KREDIT `1-003`; idempoten; lihat `InstallationMaterialStockOutTest` |
| TLS endpoint + rotasi pin | High | Pinning aplikasi selesai; `ops/tls/verify_spki_pins.sh` + `ops/runbooks/TLS_PINNING_ROTATION.md` — drill riil menunggu endpoint |
| Queue/scheduler report | High | `backend/docker/supervisord.production.conf` (worker 2 procs + scheduler loop + `pdam:queue-health` alarm via syslog). Wajib dipasang & diuji pada target. |
| Load test production | Medium | Runner parameterisasi `ops/load/run_load_test.sh`; `tests/load/k6-load-test.js` kini menolak kredensial hardcode; baseline dicatat ke runbook observability |
| Penetration test | Medium | `ops/PENTEST_SCOPE.md` — butuh vendor eksternal |
| ML production-calibrated models | High | Harness `ml/scripts/calibrate_production.py` + gerbang threshold config + `php artisan pdam:ml-export-training-data`; CI menguji gerbang dgn data sintetis 24 bln — data riil 1-2 th + approval masih dibutuhkan |
| IOT/PROD/DIST hardware | Low | Butuh sensor/SCADA |


## 9. Support Contact & Dokumen

- **Developer:** Yusril Eka Mahendra
- **Repo:** `/pdam`
- **Peta dokumentasi / fact sheet:** [`docs/DOC_MAP.md`](docs/DOC_MAP.md) (hub navigasi + angka live)
- **Tooling gate & runbook:** [`ops/README.md`](ops/README.md) · **CI:** [`.github/workflows/ci.yml`](.github/workflows/ci.yml)
  · **Keputusan bisnis:** [`docs/BUSINESS_DECISIONS.md`](docs/BUSINESS_DECISIONS.md) · **Kalibrasi ML:** [`docs/ML_CALIBRATION.md`](docs/ML_CALIBRATION.md)
- `task.md` dan `temuan.md` hanya arsip historis; status = `temuan2.md`


---

**Disetujui,**

_________________________
Tanggal: _______________
