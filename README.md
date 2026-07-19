# PDAM SaaS

Sistem Manajemen PDAM Multi-Tenant berbasis Laravel + Vue 3.

> **Versi:** 3.4 | **Status:** Dalam hardening, belum production-ready | **Diperbarui:** 18 Juli 2026

>
> **Sumber status saat ini:** [`temuan2.md`](temuan2.md), termasuk enam gate persetujuan production canonical.

## Documentation Map

| Kebutuhan | Sumber utama |
|-----------|--------------|
| Mulai proyek, pilihan URL, dan arsitektur ringkas | Dokumen ini |
| Setup backend dan mekanisme login web/mobile/platform | [`backend/README.md`](backend/README.md) |
| Data fixture dan seluruh kredensial demo | [`backend/SEED_DATA.md`](backend/SEED_DATA.md) |
| Deployment dan bootstrap database production | [`backend/DEPLOY.md`](backend/DEPLOY.md) |
| Status implementasi, bukti audit, dan gate production | [`temuan2.md`](temuan2.md) |
| Target produk dan alur bisnis | [`PRD.md`](PRD.md), [`02_flow.md`](02_flow.md) |
| Baseline dan assessment keamanan | [`SECURITY_CHECKLIST.md`](SECURITY_CHECKLIST.md), [`tests/security/OWASP_ASVS_AUDIT.md`](tests/security/OWASP_ASVS_AUDIT.md) |
| Serah terima pengembangan | [`HANDOVER.md`](HANDOVER.md) |
| Layanan ML | [`ml/README.md`](ml/README.md) |
| Catatan historis, bukan panduan aktif | [`task.md`](task.md), [`temuan.md`](temuan.md) |

Jika informasi bertentangan, gunakan pemilik topik pada tabel di atas. `temuan2.md` selalu menang untuk
status verifikasi dan kesiapan production. Laravel route registry (`php artisan route:list --path=api`)
adalah sumber endpoint aktual; Swagger/OpenAPI dan Postman saat ini referensi parsial yang wajib divalidasi
terhadap registry sebelum digunakan.


## Requirements

- PHP 8.3+
- MySQL 8.0+
- Redis 7+
- Node.js 20+
- Composer 2.x

## Quick Start (Docker)

```bash
cd backend
cp .env.example .env
docker compose up -d
```

App running di `http://localhost:8080` | API di `/api/v1` | Swagger di `/api/documentation`

Docker startup tidak boleh diasumsikan otomatis mengisi data demo. Periksa migration dengan
`docker compose exec app php artisan migrate:status`; untuk environment demo saja, jalankan workflow
bootstrap di [`backend/SEED_DATA.md`](backend/SEED_DATA.md). Jalur production mengikuti
[`backend/DEPLOY.md`](backend/DEPLOY.md) dan tidak menjalankan demo seeder.

## Environment URL Matrix

| Konteks | URL/alamat | Catatan |
|---------|------------|---------|
| Docker, browser host | `http://localhost:8080` | Web, API `/api/v1`, Swagger `/api/documentation` |
| Artisan, browser host | `http://127.0.0.1:8000` atau `http://localhost:8000` | Pilih satu hostname secara konsisten selama sesi login |
| Android emulator ke Artisan host | `http://10.0.2.2:8000/api/v1` | Alias host khusus emulator; bukan URL browser desktop |
| ML service | bind `0.0.0.0:8100`, akses host `http://localhost:8100` | Detail di `ml/README.md` |
| MySQL lokal aplikasi | `127.0.0.1:3306` | Bukan URL HTTP |
| MySQL disposable audit | `127.0.0.1:3307` | Hanya snapshot audit `temuan2.md`, bukan default development |

Untuk Sanctum stateful web, jangan berganti antara `localhost` dan `127.0.0.1` di tengah sesi karena
cookie terikat hostname. Bila login tampak kembali ke halaman login, hapus cookie host lama, buka ulang
URL yang dipilih, lalu lakukan bootstrap CSRF/login kembali.

## Local Development

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
# Terminal kedua: npm install && npm run dev
```

> **Windows:** setelah `.env`, `composer install`, `key:generate`, dan `migrate --seed` sekali di awal,
> cukup klik 2x `backend/start.bat` untuk menjalankan server (`php artisan serve`).


## Demo Data (Seeder)

Untuk environment development/demo, `php artisan migrate --seed` mengisi **2 tenant demo terintegrasi** (pelanggan → baca meter →
tagihan → pembayaran → jurnal → neraca, plus gudang → stok → jurnal persediaan). Neraca kedua
tenant terverifikasi **balance**.

| Tenant | Kode login | Sumber data | Modul aktif |
|--------|-----------|-------------|-------------|
| PDAM Canada | `pdam-canada` | PDAM Pontianak | 27 modul (LENGKAP) |
| PDAM Brazil | `pdam-brazil` | PDAM Surabaya | 10 modul (SEBAGIAN) |

> "Canada/Brazil" hanya label agar tidak terkena copyright; isi datanya Indonesia.

**Contoh login demo:** kode `pdam-canada`, email `admin_tenant@gmail.com`, password `12345678`.
Daftar kredensial lengkap dan workflow reset/non-destruktif hanya dipelihara di
[`backend/SEED_DATA.md`](backend/SEED_DATA.md). Kredensial ini dilarang pada production.

**Snapshot database terbaru:** MySQL 8.4.9 disposable dan SQLite sama-sama lulus 60 migration + 24
seeder dan menghasilkan 167 tabel; lima migration terbaru hanya menambah constraint. MySQL rollback
`000004`-`000010` lalu migrate ulang juga lulus. Detail metadata FK/index dan batasan audit ada di
`temuan2.md`; snapshot 8 Juli di `temuan.md` tetap arsip historis.



## Web Interface

| Halaman | Route | Akses | Keterangan |
|---------|-------|-------|------------|
| Landing page | `/` | Publik | Profil produk: hero, tentang PDAM SaaS, keunggulan, katalog modul (3 tier), cara kerja, CTA. Tema biru muda/cyan lembut (identik air). Tombol CTA → `/login`. |
| Login | `/login` | Publik | Login tenant (kode PDAM + email) atau super admin. Setelah login diarahkan ke `/dashboard` (tenant) atau `/platform` (super admin). |
| Dashboard tenant | `/dashboard` | Tenant | Dashboard per role (Director, Finance, Warehouse, Technical). |
| Operasional tenant | `/zones`, `/prospects`, `/meter-routes`, `/complaints`, `/assets` | Tenant | Halaman operasional yang terhubung ke endpoint backend nyata. |
| Enterprise tenant | `/gis`, `/employees`, `/employee-self-service`, `/call-center`, `/tenders` | Tenant | UI modul enterprise; backend tetap menegakkan entitlement dan permission. |
| Marketplace tenant | `/marketplace` | Tenant | Katalog/harga/dependency dari session tenant; purchase membuat order pending + URL Snap dan aktivasi menunggu settlement Midtrans terverifikasi. |
| Dashboard role | `/dashboard/director`, `/dashboard/finance`, `/dashboard/technical`, `/dashboard/warehouse` | Tenant | Dashboard khusus role; kegagalan API ditampilkan melalui banner global. |
| Panel super admin | `/platform` | Super admin | Sidebar cerah + topbar. Kelola tenant, dashboard SaaS (MRR/ARR). |
| Marketplace platform | `/platform/modules` | Super admin | Katalog dan pengelolaan commerce/manual platform tetap tersedia terpisah dari checkout tenant. |

## Architecture

| Layer | Tech |
|-------|------|
| Backend | Laravel 13, PHP 8.3, Sanctum |
| Database | MySQL 8.0, Redis 7 |
| Frontend | Vue 3, Vite, Tailwind CSS, PrimeVue |
| Mobile | Flutter (Clean Architecture: auth, portal, meter reading, survey, OCR, offline sync) |
| Payment | Midtrans Snap |
| Queue | Redis |
| Testing | PHPUnit 11, Vitest |


## Module Coverage

Katalog berisi 27 modul bisnis dan satu fase fondasi. Status di tabel berikut menunjukkan keberadaan
domain dalam katalog/schema, bukan jaminan bahwa API, UI, mobile, integrasi eksternal, dan test semuanya
production-ready. Detail gap per area ada di `temuan2.md`.

| # | Code | Name | Tier |
|---|------|------|------|
| 1 | CORE | Paket Dasar (Customer, Billing, Payment, Accounting, RBAC) | 1 |
| 2 | ZONE | Multi-Wilayah | 1 |
| 3 | SRV | Survey & Pemasangan Baru (OCR KTP) | 1 |
| 4 | MTR | Baca Meter Digital + Rute + OCR | 1 |
| 5 | WH | Gudang & Inventory | 1 |
| 6 | METX | Meter Analytics | 1 |
| 7 | AST | Aset Tetap & Penyusutan | 1 |
| 8 | FIN+ | Keuangan Advance | 1 |
| 9 | CRM | Pengaduan & CRM | 1 |
| 10 | C360 | Customer 360 View | 1 |
| 11 | BILL+ | Advanced Billing | 1 |
| 12 | APP | Portal & Mobile | 1 |
| 13 | CHEM | Chemical Management | 2 |
| 14 | PROC | Procurement & Tender | 2 |
| 15 | FSM | Field Service + WO | 2 |
| 16 | MNT | Maintenance Preventive | 2 |
| 17 | HR | HR Management | 2 |
| 18 | DMS | Document Management | 2 |
| 19 | GIS | GIS Water Network | 2 |
| 20 | CC | Call Center | 2 |
| 21 | BI | Business Intelligence | 2 |
| 22 | INT | Integration Platform | 2 |
| 23 | IOT | IoT/AMR | 3 |
| 24 | PROD | Water Production | 3 |
| 25 | DIST | Distribution DMA | 3 |
| 26 | NRW | Non-Revenue Water | 3 |
| 27 | AI | AI/ML | 3 |

## API

Registry snapshot 15 Juli memuat 358 route. API tenant menggunakan prefix `/api/v1`; angka aktual harus
dihitung ulang melalui `php artisan route:list --path=api`. Swagger/OpenAPI dan Postman belum mencakup
seluruh registry dan tidak boleh dipakai sendiri sebagai kontrak final.

Autentikasi web memakai Sanctum stateful/session dengan cookie `HttpOnly` dan CSRF; response login web
tidak mengirim token dan auth tidak disimpan di `localStorage`. Mobile mengirim `device_name` saat login
dan memakai `Authorization: Bearer {token}` Sanctum per perangkat.

## Testing

```bash
php artisan test                    # Unit + integration tests
npm test -- --run                   # Vitest frontend
npm run build                       # Build production Vue
cd ../mobile && flutter analyze && flutter test --no-pub
cd ../ml && python -m unittest discover -s tests -v
```

Status verifikasi 15 Juli 2026: backend SQLite **89/89, 664 assertions**; MySQL **88 passed, 1 intentionally
SQLite-only skipped, 756 assertions, zero failures**; **358 routes** dan route cache PASS. Frontend **5/5**
dan build **322 modules** PASS. Flutter analyze no issues + **44/44** PASS. Python 3.11 compile + **25/25**
PASS tanpa skip; empat fixture-validation artifact lolos checksum/manifest/smoke. Composer/npm audit PASS.
Seluruh temuan audit **39/39 application-complete**, tetapi enam gate persetujuan production di `temuan2.md` tetap terbuka.

Export generik saat ini mendukung artifact `csv` dan `html` melalui `POST /api/v1/export`. Native
PDF/XLSX/DOC tetap requirement roadmap dan tidak diklaim tersedia.

## Security

- [x] Entitlement modul berbayar dan tenant isolation diuji pada backend
- [x] MFA TOTP untuk role sensitif
- [x] Encrypted at-rest (NIK)
- [x] WAF (SQLi, XSS, Path Traversal)
- [x] CSP, CORS, HSTS, Secure Headers
- [x] Rate limit + lock akun
- [x] File privat memakai resource ownership + tenant/purpose path guard
- [x] Privacy purge memakai dry-run dan approval dua pengguna
- [x] BI/export dinamis memakai allowlist identifier, permission dataset, dan entitlement modul sumber
- [x] Release mobile mewajibkan primary+backup SPKI SHA-256 pin dan fail-closed pada konfigurasi invalid
- [x] Web auth memakai cookie session HttpOnly+CSRF tanpa bearer token di localStorage
- [ ] Enam gate persetujuan production canonical pada `temuan2.md` belum ditutup

Detail baseline internal: `SECURITY_CHECKLIST.md`. Checklist tersebut bukan sertifikasi.

## Deploy Production

```bash
# Build frontend assets
npm run build

# Set production env
APP_ENV=production
APP_DEBUG=false

# Build Flutter release: dua pin SPKI SHA-256 wajib, tanpa prefix sha256/
flutter build apk --release \
  --dart-define=CERT_SPKI_SHA256_PRIMARY="$CERT_SPKI_SHA256_PRIMARY" \
  --dart-define=CERT_SPKI_SHA256_BACKUP="$CERT_SPKI_SHA256_BACKUP"

# Optimize
php artisan optimize

# Nginx config: see docker/nginx.conf
# SSL: certbot + Let's Encrypt
# Supervisor: docker/supervisord.conf
```

Queue worker wajib memproses queue `default` dan cron wajib menjalankan `php artisan schedule:run`
setiap menit agar scheduled report didispatch. Verifikasi endpoint TLS production terhadap kedua pin dan
lakukan drill rotasi primary/backup sebelum rilis mobile; detail ada di `backend/DEPLOY.md`.

## License

Proprietary — PDAM SaaS © 2026
