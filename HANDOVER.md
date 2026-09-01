# PDAM SaaS — Dokumen Serah Terima

**Tanggal:** 18 Juli 2026
**Versi:** 3.4
**Status:** Handover pengembangan; belum disetujui untuk production


> **Sumber status saat ini:** [`temuan2.md`](temuan2.md), termasuk enam gate persetujuan production canonical. Peta kepemilikan seluruh dokumentasi ada di [`README.md`](README.md); gunakan [`backend/README.md`](backend/README.md) untuk autentikasi, [`backend/SEED_DATA.md`](backend/SEED_DATA.md) untuk fixture/kredensial demo, dan [`backend/DEPLOY.md`](backend/DEPLOY.md) untuk production.


## 1. Ringkasan Produk

Platform SaaS manajemen PDAM multi-tenant. 27 modul bisnis + 1 fondasi platform.

### Tech Stack
| Komponen | Teknologi |
|----------|-----------|
| Backend | Laravel 13, PHP 8.3 |
| Database | MySQL 8.0, Redis 7 |
| Frontend Web | Vue 3 + Vite + Tailwind + PrimeVue |
| Mobile | Flutter (Clean Architecture — auth, portal, meter reading, survey, OCR, offline sync) |
| Queue | Redis + Laravel Queue |
| Payment Gateway | Midtrans Snap |
| CI/CD | GitHub Actions parsial; bukan gate lengkap lintas komponen |


## 2. Module Coverage

Status historis di bawah berarti schema/API dasar tersedia. Status production setiap modul tetap harus
memenuhi migration MySQL, API contract, UI/mobile integration, test, dan observability sesuai definisi
selesai di `temuan2.md`.

| # | Kode | Status | Catatan |
|---|------|--------|---------|
| 1 | CORE | API diuji | Pelanggan, tagihan, pembayaran, akuntansi, RBAC; web belum lengkap |
| 2 | ZONE | API + UI parsial | Multi-wilayah |
| 3 | SRV | API + kontrak OCR diuji | Multipart KTP, parsing+confidence, editable confirmation wajib |
| 4 | MTR | API + sync diuji | Baca meter, rute, OCR parse; sync failure/retry per client UUID diuji |
| 5 | WH | API parsial | Gudang, PO, transfer |
| 6 | METX | API parsial | Anomali meter |
| 7 | AST | API + contract web parsial | Endpoint dan route web `/assets` tersedia |
| 8 | FIN+ | API parsial | AR/AP/Tax/Budget/Bank |
| 9 | CRM | API parsial | Pengaduan dan SLA |
| 10 | C360 | API parsial | Customer 360 |
| 11 | BILL+ | API parsial | Adjustment bill |
| 12 | APP | API contract diuji | Portal/account endpoint, token/refresh, typed endpoint builders, HTTPS/cleartext/pins/logging tercakup suite mobile |
| 13 | CHEM | API parsial | Bahan kimia IPA |
| 14 | PROC | API parsial | Tender dan vendor |
| 15 | FSM | API parsial | Work order |
| 16 | MNT | API parsial | Maintenance |
| 17 | HR | API parsial | SDM; UI belum lengkap |
| 18 | DMS | API + private file guard | Dokumen privat; UI belum lengkap |
| 19 | GIS | API parsial | Pipa dan pelanggan |
| 20 | CC | API parsial | Call center |
| 21 | BI | Security query + schedule diuji | Allowlist/entitlement report builder; CRUD/run/history/download privat dan dispatcher terjadwal tersedia |
| 22 | INT | API parsial | Integrasi dan API key |
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
| Dashboard role | `/dashboard/{director|finance|technical|warehouse}` | Tenant |

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

`php artisan migrate --seed` mengisi **2 tenant demo terintegrasi**. Audit terbaru menjalankan **60
migration dan 24 seeder** pada MySQL 8.4.9 disposable dan SQLite: 167 tabel dibuat. MySQL rollback
`000004`-`000010` lalu migrate ulang lulus. Metadata terbaru mencatat 234 FK total, termasuk 97 tenant
batch FK dan 8 actor FK, dengan preflight orphan/cross-tenant actor nol. Angka 8 Juli di `temuan.md`
tetap arsip historis.


Fixture transaksi inti pelanggan, billing, pembayaran, jurnal, dan stok dasar digerakkan lewat service aplikasi dan menghasilkan neraca demo balance. Fixture enterprise tambahan tidak seluruhnya membuat transaksi/jurnal penuh; sejumlah record memang referensial atau memakai `journal_entry_id = null`.

| Tenant | Kode login | Data | Modul aktif | Pelanggan | Tagihan |
|--------|-----------|------|-------------|-----------|---------|
| PDAM Canada | `pdam-canada` | Pontianak | 27 (LENGKAP) | 10 | 30 |
| PDAM Brazil | `pdam-brazil` | Surabaya | 10 (SEBAGIAN) | 5 | 10 |

> "Canada/Brazil" hanya label anti-copyright; isi datanya Indonesia.

**Contoh login tenant demo:** `pdam-canada` / `admin_tenant@gmail.com` / `12345678`.
**Contoh platform demo:** `superadmin@gmail.com` / `12345678`, tanpa kode PDAM. Daftar lengkap 35 role +
email & password per PDAM (Canada & Brazil) dipelihara di [`README.md`](README.md#demo-credentials-akun--role-demo)
dan [`backend/SEED_DATA.md`](backend/SEED_DATA.md). `DatabaseSeeder` dilarang di production.

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

Worker queue `default` wajib aktif agar run scheduled report diproses. Setiap schedule menyimpan timezone,
sedangkan `next_run_at` dihitung UTC; artifact CSV/HTML disimpan privat dan diunduh melalui endpoint berizin.

## 5. Draft Deployment Commands

Bagian ini adalah ringkasan handover, bukan runbook final atau persetujuan production. Ikuti [`backend/DEPLOY.md`](backend/DEPLOY.md) dan jangan merilis sebelum enam gate canonical di [`temuan2.md`](temuan2.md) ditutup.

```bash
cd backend
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:work --tries=3 --timeout=90 --queue=default,notifications,payments
# Cron host: * * * * * cd /var/www && php artisan schedule:run
sudo systemctl restart php8.3-fpm nginx
```

SSL: Certbot + Let's Encrypt. Konfig: `docker/nginx.production.conf`

## 6. Security Status

- Terverifikasi backend: module entitlement, tenant isolation regression tests, private-file ownership,
  service authentication ML, BI/export identifier hardening, MFA, encrypted fields, rate limiting,
  privacy purge dual-control, dan kontrak OCR KTP multipart.
- Verifikasi terbaru: backend SQLite 89/89 (664 assertions), MySQL 88 pass + 1 intentional SQLite-only
  skip (756 assertions), 358 route + route cache, frontend 5/5 + build 322 modules, Flutter analyze +
  44/44 tests, dan Python 3.11 compile + 25/25 tests tanpa skip.
- Parsial/deployment-dependent: TLS/HSTS aktual, append-only privilege database untuk privacy audit,
  backup/restore sebelum purge, dan external penetration testing.
- Tersedia: web Sanctum session HttpOnly+CSRF tanpa auth localStorage; mobile bearer token per perangkat.
- Tersedia: release SPKI pinning primary+backup fail-closed; endpoint TLS dan drill rotasi tetap gate deployment.

## 7. Monitoring

- `/up` — health endpoint (Laravel default)
- `pdam:health-check` — DB/Redis/Disk/Queue check (setiap 5 menit)
- Nginx monitoring proxy: `docker/monitoring.conf`

## 8. Yang Belum Selesai

Enam blocking gate authoritative hanya yang tercantum pada `temuan2.md`: production TLS endpoint, external pentest, tested backup/restore, production DB least privileges, certificate primary/backup rotation drill, dan representative-data ML calibration/acceptance. Item operasional lain di bawah adalah rekomendasi/roadmap dan tidak menambah daftar gate canonical.

| Item | Prioritas | Keterangan |
|------|-----------|------------|
| HTTPS/TLS production | High | Butuh domain + SSL |
| Export native PDF/XLSX/DOC | Roadmap | Endpoint aktual jujur mendukung CSV/HTML; format native belum diimplementasikan |
| Material stock-out pemasangan | Medium | API menyatakan `planning_only`; reservasi, stock-out, dan jurnal belum tersedia |
| TLS endpoint + rotasi pin | High | Pinning aplikasi selesai; verifikasi endpoint production dan drill primary/backup sebelum rilis |
| Queue/scheduler report | High | Pastikan worker queue `default` dan `schedule:run` per menit aktif; cek artifact privat |
| Load test production | Medium | Gunakan `tests/load/k6-load-test.js` |
| Penetration test | Medium | Audit eksternal |
| ML production-calibrated models | High | Fixture-validation tersedia; latih/evaluasi dengan data representatif 1-2 tahun dan acceptance threshold |
| IOT/PROD/DIST hardware | Low | Butuh sensor/SCADA |


## 9. Support Contact

- **Developer:** Yusril Eka Mahendra
- **Repo:** `/pdam`
- **Docs:** gunakan documentation map di `README.md`; `task.md` dan `temuan.md` hanya arsip historis


---

**Disetujui,**

_________________________
Tanggal: _______________
