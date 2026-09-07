# PDAM SaaS

Sistem Manajemen PDAM Multi-Tenant berbasis Laravel + Vue 3.

> **Versi:** 3.5 | **Status:** Tooling gate §13 + CI self-drill selesai; bukti environment produksi & decision PRD §23 masih terbuka | **Diperbarui:** 7 September 2026

>
> **Sumber status saat ini:** [`temuan2.md`](temuan2.md), termasuk enam gate persetujuan production canonical. **Peta dokumentasi + fact sheet angka live:** [`docs/DOC_MAP.md`](docs/DOC_MAP.md). Inventaris tergenerasi: [`docs/COUNTS.json`](docs/COUNTS.json) (`php artisan pdam:counts`).

<!-- doc-sync:start verifikasi 7 Sept 2026 -->
> **Verifikasi terintegrasi:** 117/117 (117 test backend, 812 assertions) · Vitest 13 · Flutter analyze 0 issue + 46/46 · ML 32 (CI) · 369 method /api/v1 (293 path registry) · 65 migration · 28 seeder · 21 command · 167 tabel statis · 136 model · 2026-09-07. Kanoni angka: [`docs/COUNTS.json`](docs/COUNTS.json) · status resmi: [`temuan2.md`](temuan2.md) §13 · peta dokumen: [`docs/DOC_MAP.md`](docs/DOC_MAP.md) · tooling: [`ops/README.md`](ops/README.md) · CI: [`.github/workflows/ci.yml`](.github/workflows/ci.yml) + `nightly-ops.yml`.
<!-- doc-sync:end -->

## Documentation Map

| Kebutuhan | Sumber utama |
|-----------|--------------|
| Mulai proyek, pilihan URL, dan arsitektur ringkas | Dokumen ini |
| Peta dokumen + fact sheet angka verifikasi | [`docs/DOC_MAP.md`](docs/DOC_MAP.md) |
| Setup backend dan mekanisme login web/mobile/platform | [`backend/README.md`](backend/README.md) |
| Data fixture dan seluruh kredensial demo | [`backend/SEED_DATA.md`](backend/SEED_DATA.md) |
| Deployment dan bootstrap database production | [`backend/DEPLOY.md`](backend/DEPLOY.md) |
| Status implementasi, bukti audit, dan gate production | [`temuan2.md`](temuan2.md) |
| Tooling operasional gate (TLS, backup/restore drill, least-priv, load test, pin) | [`ops/README.md`](ops/README.md) + `ops/runbooks/*` |
| CI gates lintas komponen + drill MySQL | [`.github/workflows/ci.yml`](.github/workflows/ci.yml) (job `mysql-production-gates`) |
| Nightly k6 capacity + simulator Tier-3 | [`.github/workflows/nightly-ops.yml`](.github/workflows/nightly-ops.yml) (gerbang `vars.LOAD_ENABLED=true`) |
| Keputusan bisnis PRD §23 dan knobnya | [`docs/BUSINESS_DECISIONS.md`](docs/BUSINESS_DECISIONS.md) (`backend/config/business.php`) |
| Kalibrasi ML production + gerbang threshold | [`docs/ML_CALIBRATION.md`](docs/ML_CALIBRATION.md), [`ml/README.md`](ml/README.md) |
| Capacity baseline load test | [`docs/CAPACITY_BASELINE.md`](docs/CAPACITY_BASELINE.md) (`ops/load/run_load_test.sh`) |
| Target produk dan alur bisnis | [`PRD.md`](PRD.md), [`02_flow.md`](02_flow.md) |
| Baseline dan assessment keamanan | [`SECURITY_CHECKLIST.md`](SECURITY_CHECKLIST.md), [`tests/security/OWASP_ASVS_AUDIT.md`](tests/security/OWASP_ASVS_AUDIT.md) |
| Serah terima pengembangan | [`HANDOVER.md`](HANDOVER.md) |
| Catatan historis, bukan panduan aktif | [`task.md`](task.md), [`temuan.md`](temuan.md) |

Jika informasi bertentangan, gunakan pemilik topik pada tabel [Dokumentasi Peta] di atas (penengah: [`docs/DOC_MAP.md`](docs/DOC_MAP.md) §1). `temuan2.md` selalu menang untuk status verifikasi dan kesiapan production. Laravel route registry (`php artisan route:list --path=api`) adalah sumber endpoint aktual; Swagger/OpenAPI dan Postman saat ini referensi parsial yang wajib divalidasi terhadap registry sebelum digunakan.


## Requirements

- PHP 8.3+
- MySQL **8.4.x** (target production; CI memakai `mysql:8.4`)
- Redis 7+
- Node.js 20+
- Composer 2.x
- Flutter stable (mobile) · Python 3.11 (ML)

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

Untuk environment development/demo, `php artisan migrate --seed` mengisi **3 tenant demo terintegrasi** (pelanggan → baca meter →
tagihan → pembayaran → jurnal → neraca, plus gudang → stok → jurnal persediaan). Neraca ketiga
tenant terverifikasi **balance**. Detail lengkap di [`backend/SEED_DATA.md`](backend/SEED_DATA.md) Section 4.

| Tenant | Kode login | Sumber data | Modul aktif | Skala |
|--------|-----------|-------------|-------------|-------|
| PDAM Canada | `pdam-canada` | PDAM Pontianak | 27 modul (LENGKAP) | 4 zona, 10 pelanggan |
| PDAM Brazil | `pdam-brazil` | PDAM Surabaya | 10 modul (SEBAGIAN) | 2 zona, 5 pelanggan |
| PDAM Sambas | `pdam-sambas` | Kabupaten Sambas (Kalimantan Barat) | 27 modul (LENGKAP) | 7 zona, **3.000 pelanggan** |

> "Canada/Brazil" hanya label agar tidak terkena copyright; isi datanya Indonesia. Tenant **pdam-sambas**
> di-seed oleh `SambasTenantSeeder` memakai alamat nyata Kab. Sambas (BPS 6101), simulasi 1 tahun penuh
> (Sep 2025 → bulan penuh terakhir), 3.000 pelanggan, tunggakan 1/2/3 bulan + 150 pelanggan diisolir
> (>6 bulan tidak bayar), dan rantai baca meter (foto + petugas → tarif → tagihan → jurnal) yang terbukti
> nyambung — lihat [`backend/SEED_DATA.md`](backend/SEED_DATA.md) Section 9.

**Contoh login demo:** kode `pdam-canada`, email `admin_tenant@gmail.com`, password `12345678`.
Daftar kredensial lengkap dan workflow reset/non-destruktif hanya dipelihara di
[`backend/SEED_DATA.md`](backend/SEED_DATA.md). Kredensial ini dilarang pada production.

**Snapshot database terbaru:** MySQL 8.4.9 disposable dan SQLite sama-sama lulus membangun 167 tabel;
jalur seed sekarang router: pada **production hanya `ProductionKernelSeeder`** yang berjalan — seluruh
fixture demo dan password `12345678` **diblokir mutlak** oleh `DemoGuard` (lihat
[`docs/COUNTS.json`](docs/COUNTS.json): 65 migration / 28 class seeder (4 jalur production) / 136 model
/ 167 tabel — dan bukti [`ProductionSeederIsolationTest`](backend/tests/Feature/ProductionSeederIsolationTest.php)).
Tenant **pdam-sambas** (3.000 pelanggan via `SEED_CUSTOMER_COUNT`; simulasi 1 tahun penuh) dan
metadata FK/rollback ada di [`temuan2.md`](temuan2.md); snapshot 8 Juli di [`temuan.md`](temuan.md) tetap arsip.


## Demo Credentials (Akun & Role Demo)

> **Development/demo only.** Kredensial di bawah **dilarang** untuk production. Daftar lengkap &
> workflow reset non-destruktif hanya dipelihara di [`backend/SEED_DATA.md`](backend/SEED_DATA.md) dan
> referensi role backend di [`backend/README.md`](backend/README.md).

Ada **3 tenant demo**. Setiap tenant memakai **35 role yang sama**, namun karena email unik **per tenant**,
ketiganya memiliki **113 akun terpisah** (35 role × 3 tenant + 7 petugas baca meter tenant Sambas + 1 super-admin
platform). Untuk login, cukup pilih kode PDAM yang sesuai — tabel role di bawah disajikan **terpisah per PDAM**.

| Tenant | Kode login | Sumber data | Jumlah role | User |
|--------|-----------|-------------|-------------|------|
| PDAM Canada | `pdam-canada` | PDAM Pontianak | 35 role | 35 user |
| PDAM Brazil | `pdam-brazil` | PDAM Surabaya | 35 role | 35 user |
| PDAM Sambas | `pdam-sambas` | Kabupaten Sambas (Kalbar) | 35 role | 35 user + 7 petugas baca meter |

### Login tenant (web & mobile/API)

Semua user demo memakai password **`12345678`**. Email = **`{role_code}@gmail.com`**.

Karena email unik **per tenant**, `admin_tenant@gmail.com` dipakai sebagai admin di **kedua** PDAM.
Yang membedakan adalah **kode PDAM** saat login — sehingga atribusi admin per tenant adalah:

| PDAM | Kode login | Admin PDAM (email) | Password |
|------|-----------|--------------------|----------|
| **PDAM Canada** | `pdam-canada` | `admin_tenant@gmail.com` | `12345678` |
| **PDAM Brazil** | `pdam-brazil` | `admin_tenant@gmail.com` | `12345678` |
| **PDAM Sambas** | `pdam-sambas` | `admin_tenant@gmail.com` | `12345678` |

| Field | PDAM Canada | PDAM Brazil | PDAM Sambas |
|-------|-------------|-------------|-------------|
| `pdam_code` | `pdam-canada` | `pdam-brazil` | `pdam-sambas` |

Contoh body login sebagai Direktur di PDAM Canada (`POST /api/v1/login`):

```json
{
  "pdam_code": "pdam-canada",
  "email": "director@gmail.com",
  "password": "12345678"
}
```

Untuk mobile/API ditambah `"device_name": "<nama-perangkat>"`.

### Daftar role lengkap per PDAM

Karena **email & password sama persis** di ketiga tenant (unik per tenant, hanya dibedakan oleh `pdam_code` saat
login), daftar di bawah berlaku untuk **masing-masing** PDAM (Canada, Brazil, & Sambas) — 35 role × 3 tenant = **105 akun user**
(ditambah 7 petugas baca meter `meter_officer1..7@gmail.com` milik tenant Sambas).

#### PDAM Canada (`pdam-canada`)

| # | Role Code | Nama Role | Email | Password |
|---|-----------|-----------|-------|----------|
| 1 | `super_admin` | Super-Admin (Platform Owner)\* | `super_admin@gmail.com` | `12345678` |
| 2 | `admin_tenant` | Admin PDAM | `admin_tenant@gmail.com` | `12345678` |
| 3 | `compliance_officer` | Petugas Kepatuhan / Perlindungan Data | `compliance_officer@gmail.com` | `12345678` |
| 4 | `director` | Direktur | `director@gmail.com` | `12345678` |
| 5 | `finance_head` | Kabag Keuangan | `finance_head@gmail.com` | `12345678` |
| 6 | `finance_staff` | Staf Keuangan | `finance_staff@gmail.com` | `12345678` |
| 7 | `cashier` | Kasir / Loket Pembayaran | `cashier@gmail.com` | `12345678` |
| 8 | `customer_service` | Customer Service | `customer_service@gmail.com` | `12345678` |
| 9 | `customer` | Pelanggan | `customer@gmail.com` | `12345678` |
| 10 | `hublang_head` | Kepala Hublang | `hublang_head@gmail.com` | `12345678` |
| 11 | `survey_officer` | Petugas Survey | `survey_officer@gmail.com` | `12345678` |
| 12 | `survey_head` | Kepala Survey | `survey_head@gmail.com` | `12345678` |
| 13 | `technical_head` | Kepala Teknik | `technical_head@gmail.com` | `12345678` |
| 14 | `installer_technician` | Teknisi Pemasangan | `installer_technician@gmail.com` | `12345678` |
| 15 | `meter_office` | Koordinator Baca Meter (Kantor) | `meter_office@gmail.com` | `12345678` |
| 16 | `meter_officer` | Petugas Baca Meter | `meter_officer@gmail.com` | `12345678` |
| 17 | `warehouse_head` | Kepala Gudang | `warehouse_head@gmail.com` | `12345678` |
| 18 | `warehouse_staff` | Staf Gudang / Staf Wilayah | `warehouse_staff@gmail.com` | `12345678` |
| 19 | `procurement_staff` | Staf/Panitia Pengadaan | `procurement_staff@gmail.com` | `12345678` |
| 20 | `production_head` | Kepala Produksi / Operator IPA | `production_head@gmail.com` | `12345678` |
| 21 | `lab_analyst` | Analis Lab / QC | `lab_analyst@gmail.com` | `12345678` |
| 22 | `accountant` | Akuntan / Staf Akuntansi | `accountant@gmail.com` | `12345678` |
| 23 | `tax_officer` | Staf Pajak | `tax_officer@gmail.com` | `12345678` |
| 24 | `asset_manager` | Manajer Aset | `asset_manager@gmail.com` | `12345678` |
| 25 | `maintenance_technician` | Teknisi Pemeliharaan | `maintenance_technician@gmail.com` | `12345678` |
| 26 | `field_dispatcher` | Dispatcher Lapangan | `field_dispatcher@gmail.com` | `12345678` |
| 27 | `field_supervisor` | Supervisor Lapangan | `field_supervisor@gmail.com` | `12345678` |
| 28 | `field_technician` | Teknisi Lapangan (WO) | `field_technician@gmail.com` | `12345678` |
| 29 | `hr_staff` | Staf HR | `hr_staff@gmail.com` | `12345678` |
| 30 | `hr_head` | Kepala HR | `hr_head@gmail.com` | `12345678` |
| 31 | `gis_operator` | Operator GIS | `gis_operator@gmail.com` | `12345678` |
| 32 | `dms_officer` | Petugas Arsip/Dokumen | `dms_officer@gmail.com` | `12345678` |
| 33 | `call_agent` | Agent Call Center | `call_agent@gmail.com` | `12345678` |
| 34 | `call_supervisor` | Supervisor Call Center | `call_supervisor@gmail.com` | `12345678` |
| 35 | `data_analyst` | Analis Data / BI | `data_analyst@gmail.com` | `12345678` |

#### PDAM Brazil (`pdam-brazil`)

| # | Role Code | Nama Role | Email | Password |
|---|-----------|-----------|-------|----------|
| 1 | `super_admin` | Super-Admin (Platform Owner)\* | `super_admin@gmail.com` | `12345678` |
| 2 | `admin_tenant` | Admin PDAM | `admin_tenant@gmail.com` | `12345678` |
| 3 | `compliance_officer` | Petugas Kepatuhan / Perlindungan Data | `compliance_officer@gmail.com` | `12345678` |
| 4 | `director` | Direktur | `director@gmail.com` | `12345678` |
| 5 | `finance_head` | Kabag Keuangan | `finance_head@gmail.com` | `12345678` |
| 6 | `finance_staff` | Staf Keuangan | `finance_staff@gmail.com` | `12345678` |
| 7 | `cashier` | Kasir / Loket Pembayaran | `cashier@gmail.com` | `12345678` |
| 8 | `customer_service` | Customer Service | `customer_service@gmail.com` | `12345678` |
| 9 | `customer` | Pelanggan | `customer@gmail.com` | `12345678` |
| 10 | `hublang_head` | Kepala Hublang | `hublang_head@gmail.com` | `12345678` |
| 11 | `survey_officer` | Petugas Survey | `survey_officer@gmail.com` | `12345678` |
| 12 | `survey_head` | Kepala Survey | `survey_head@gmail.com` | `12345678` |
| 13 | `technical_head` | Kepala Teknik | `technical_head@gmail.com` | `12345678` |
| 14 | `installer_technician` | Teknisi Pemasangan | `installer_technician@gmail.com` | `12345678` |
| 15 | `meter_office` | Koordinator Baca Meter (Kantor) | `meter_office@gmail.com` | `12345678` |
| 16 | `meter_officer` | Petugas Baca Meter | `meter_officer@gmail.com` | `12345678` |
| 17 | `warehouse_head` | Kepala Gudang | `warehouse_head@gmail.com` | `12345678` |
| 18 | `warehouse_staff` | Staf Gudang / Staf Wilayah | `warehouse_staff@gmail.com` | `12345678` |
| 19 | `procurement_staff` | Staf/Panitia Pengadaan | `procurement_staff@gmail.com` | `12345678` |
| 20 | `production_head` | Kepala Produksi / Operator IPA | `production_head@gmail.com` | `12345678` |
| 21 | `lab_analyst` | Analis Lab / QC | `lab_analyst@gmail.com` | `12345678` |
| 22 | `accountant` | Akuntan / Staf Akuntansi | `accountant@gmail.com` | `12345678` |
| 23 | `tax_officer` | Staf Pajak | `tax_officer@gmail.com` | `12345678` |
| 24 | `asset_manager` | Manajer Aset | `asset_manager@gmail.com` | `12345678` |
| 25 | `maintenance_technician` | Teknisi Pemeliharaan | `maintenance_technician@gmail.com` | `12345678` |
| 26 | `field_dispatcher` | Dispatcher Lapangan | `field_dispatcher@gmail.com` | `12345678` |
| 27 | `field_supervisor` | Supervisor Lapangan | `field_supervisor@gmail.com` | `12345678` |
| 28 | `field_technician` | Teknisi Lapangan (WO) | `field_technician@gmail.com` | `12345678` |
| 29 | `hr_staff` | Staf HR | `hr_staff@gmail.com` | `12345678` |
| 30 | `hr_head` | Kepala HR | `hr_head@gmail.com` | `12345678` |
| 31 | `gis_operator` | Operator GIS | `gis_operator@gmail.com` | `12345678` |
| 32 | `dms_officer` | Petugas Arsip/Dokumen | `dms_officer@gmail.com` | `12345678` |
| 33 | `call_agent` | Agent Call Center | `call_agent@gmail.com` | `12345678` |
| 34 | `call_supervisor` | Supervisor Call Center | `call_supervisor@gmail.com` | `12345678` |
| 35 | `data_analyst` | Analis Data / BI | `data_analyst@gmail.com` | `12345678` |

> \* Role `super_admin` ada sebagai template di tiap tenant untuk kelengkapan; Super-Admin platform
> yang sesungguhnya adalah akun terpisah (lihat tabel berikut), bukan user tenant.

### Super-Admin platform (lintas tenant)

Login lewat `POST /api/v1/platform/login` **tanpa** kode PDAM.

| Mode | Email | Password | Catatan |
|------|-------|----------|---------|
| Super Admin platform | `superadmin@gmail.com` | `12345678` | Pilih mode **Super Admin**; tanpa kode PDAM |

**Catatan permission:** hanya `admin_tenant` yang otomatis mendapat seluruh permission (akses penuh
dalam tenant-nya); role lain diisi bertahap per fase modul.


## Web Interface

| Halaman | Route | Akses | Keterangan |
|---------|-------|-------|------------|
| Landing page | `/` | Publik | Profil produk: hero, tentang PDAM SaaS, keunggulan, katalog modul (3 tier), cara kerja, CTA. Tema biru muda/cyan lembut (identik air). Tombol CTA → `/login`. |
| Login | `/login` | Publik | Login tenant (kode PDAM + email) atau super admin. Setelah login diarahkan ke `/dashboard` (tenant) atau `/platform` (super admin). |
| Dashboard tenant | `/dashboard` | Tenant | Dashboard per role (Director, Finance, Warehouse, Technical). |
| Operasional tenant | `/zones`, `/prospects`, `/meter-routes`, `/meter-routes/dashboard`, `/complaints`, `/assets` | Tenant | Halaman operasional yang terhubung ke endpoint backend nyata. |
| Laporan Baca Meter | `/meter-reading-report` | Tenant | Rincian baca meter per rute & periode: baca lalu vs kini, pemakaian (m³), golongan tarif, biaya, foto meter & rumah, serta petugas (read_by) & verifikator — di-backend `MeterReadingController@report` (`GET /api/v1/meter-readings/report`, izin `mtr.reading.view`). |
| Master data tenant | `/streets`, `/address` (Master Alamat), `/tariffs` | Tenant | CRUD master: Jalan, Master Alamat berjenjang (Provinsi→Kota→Kecamatan→Desa/Kelurahan), Golongan Tarif. Data alamat bersifat *global reference + override per tenant* (soft-delete + restore). |
| Pelanggan | `/customers`, `/tariffs` | Tenant | Daftar & tambah pelanggan; golongan tarif. Form pelanggan memiliki cascade Wilayah→Rute→Jalan. |
| Enterprise tenant | `/gis`, `/employees`, `/employee-self-service`, `/call-center`, `/tenders` | Tenant | UI modul enterprise; backend tetap menegakkan entitlement dan permission. |
| Marketplace tenant | `/marketplace` | Tenant | Katalog/harga/dependency dari session tenant; purchase membuat order pending + URL Snap dan aktivasi menunggu settlement Midtrans terverifikasi. |
| Dashboard role | `/dashboard/director`, `/dashboard/finance`, `/dashboard/technical`, `/dashboard/warehouse` | Tenant | Dashboard khusus role; kegagalan API ditampilkan melalui banner global. |
| Laporan keuangan | `/finance/general-ledger`, `/finance/trial-balance`, `/finance/income-statement`, `/finance/balance-sheet`, `/finance/cash-flow` | Tenant | Buku Jurnal, Neraca Saldo, Laba Rugi, Neraca, Arus Kas; di-backend oleh `AccountingReportController` (`permission:core.report.view`). |
| Alur Bisnis | `/flows`, `/flows/:key` (mis. `/flows/pemasangan-baru`) | Tenant | Halaman diagram alur per proses: Pemasangan Baru, Baca Meter, Penagihan & Pembayaran, Pengaduan, Lifecycle, Gudang & Pengadaan, Keuangan & Akuntansi. Di-render dari `resources/js/config/flows.js` (sumber `02_flow.md`). |
| Halaman modul (workbench generik) | `/modules/:code` (mis. `/modules/WH`, `/modules/METX`, `/modules/CHEM`, `/modules/BILL+`, `/modules/INT`) | Tenant | Workbench data-driven dari `resources/js/config/resources.js`: list+search+pagination server-side, modal create (validasi field + file untuk DMS), aksi row (approve/purchase/receive/issue/cancel/toggle) sesuai endpoint asli; kartu KPI untuk IoT/Produksi/DMA/NRW. Menguipkan 14 modul tanpa halaman khusus. |
| Panel super admin | `/platform` | Super admin | Sidebar cerah + topbar. Kelola tenant, dashboard SaaS (MRR/ARR). |
| Marketplace platform | `/platform/modules` | Super admin | Katalog dan pengelolaan commerce/manual platform tetap tersedia terpisah dari checkout tenant. |

> **Sidebar dinamis (domain bisnis):** menu di `AppLayout.vue` dibangun dari `resources/js/config/sidebar.js`
> yang dikelompokkan **per domain bisnis** (Pelanggan & Layanan, Master Data, Keuangan, Baca Meter & Metering,
> Gudang & Aset, Teknis Lapangan & GIS, SDM & Dokumen, Produksi Air, Integrasi & Analitik, Alur Bisnis) —
> bukan per tier. Sub-menu tampil hanya bila **modul related-nya aktif** (`active_modules`):
> untuk `admin_tenant` PDAM Canada & SAMBAS (27 modul) hampir semua domain tampil; PDAM Brazil (10 modul) hanya
> domain yang modulnya aktif. Menu Dashboard, Alur Bisnis, dan Marketplace selalu tampil.

> **Tampilan admin (setup UI):** shell tenant (`AppLayout.vue`) & super-admin (`PlatformLayout.vue`) memakai
> design system ala **Vuexy** — font **Public Sans**, primary indigo **#7367F0**, sidebar putih dengan menu
> ber-grup (item aktif = pill indigo), topbar search/theme/bell/avatar, dan kartu KPI dengan avatar ikon
> tonal. Tetap dibangun dengan Tailwind CSS + PrimeVue (Tidak memakai Vuetify), sehingga seluruh halaman
> yang sudah ada tetap berjalan.

## Architecture

| Layer | Tech |
|-------|------|
| Backend | Laravel 13, PHP 8.3, Sanctum |
| Database | MySQL 8.0, Redis 7 |
| Frontend | Vue 3, Vite, Tailwind CSS, PrimeVue (design system admin: **Public Sans + indigo `#7367F0` ala Vuexy**) |
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

Snapshot route registry: **388 baris route** (369 method endpoint `/api/v1` + 19 web; 293 URI
API unik) — angka kanonis ada di [`docs/COUNTS.json`](docs/COUNTS.json), regenerasi lewat
`php artisan pdam:counts --write`. Spek kontrak endpoint dari registry: `php artisan pdam:openapi`
→ **`docs/openapi.json`** (293 path; CI `pdam:openapi --json` diff-gate, H-10). Swagger anotasi & Postman
tetap referensi tambahan; kontrak `mobile/lib/core/network/endpoints.dart` dipaksa sama oleh
CI `php artisan pdam:mobile-coverage`. API tenant memakai prefix `/api/v1`.

Autentikasi web memakai Sanctum stateful/session dengan cookie `HttpOnly` dan CSRF; response login web
tidak mengirim token dan auth tidak disimpan di `localStorage`. Mobile mengirim `device_name` saat login
dan memakai `Authorization: Bearer {token}` Sanctum per perangkat.

Export `POST /api/v1/export` kini berformat **csv | html | xlsx | pdf** (XLSX riil PhpSpreadsheet, PDF
riil dompdf dengan kop surat PDAM; payload base64 utk format biner) — scheduled report menerima keempat
format yang sama. DOC surat-menyurat tetap roadmap.

## Testing

```bash
php artisan test                    # Unit + integration tests (SQLite in-memory)
php artisan pdam:counts             # drift-check angka inventaris vs docs/COUNTS.json
npm test -- --run                   # Vitest frontend
npm run build                       # Build production Vue
cd ../mobile && flutter analyze && flutter test --no-pub
cd ../ml && python -m pytest        # atau unittest discover -s tests
bash -n ops/**/*.sh                 # syntax ops; CI menjalankan drill ops/backup & ops/mysql
```

Status verifikasi 7 September 2026 (lokal Windows; CI menjalankan semuanya — termasuk MySQL):
backend SQLite **117/117, 812 assertions** (termasuk seeder-isolation ProductionSeederIsolationTest,
material stock-out, native export XLSX/PDF/DOCX, command `pdam:counts`, `pdam:openapi` & `pdam:mobile-coverage`
H-10 kontrak endpoint mobile); frontend Vitest **7 file / 13 tests** (helper workbench, resources config,
errors, auth flow, router, contract) + `npm run build` lulus; Flutter **44/44** + analyze **0 issue**
(duplikat konstan endpoints.dart dibersihkan); ML **32 tests** (pytest; inc. `test_telemetry_simulator` contract
di CI; runner Win-ARM64 lokal tanpa wheel xgboost → compile-all saja); `php artisan pdam:counts`
sinkron dengan `docs/COUNTS.json`. CI root [`.github/workflows/ci.yml`](.github/workflows/ci.yml) — **run pertama 2909807: 6/6 hijau** — job: `backend-sqlite`, `mysql-production-gates` (migrate+seed MySQL 8.4, provisioning
least-priv + destructive-probe verify, audit privilege dari sisi app, backup→decrypt→restore drill
dengan evidence artifact), `frontend`, `mobile`, `ml`, `security` (gitleaks CLI + composer/npm/pip audit).
Enam gate §13 `temuan2.md` tetap terbuka sampai bukti riil (TLS, pentest vendor, drill di host
produksi, ML data asli) — tooling-nya sudah ada di `ops/`.

Export generik mendukung `csv`, `html`, **`xlsx`** (PhpSpreadsheet riil; formula dinetralkan, sel angka
bertipe number), **`pdf`** (dompdf + kop surat PDAM), dan **`docx`** (PhpWord WordprocessingML asli) —
format biner dikirim sebagai base64.

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
- [x] Seeder demo **diblokir mutlak** pada APP_ENV=production (`DemoGuard`; lihat `backend/SEED_DATA.md`)
- [x] Least-privilege DB + trigger append-only privacy_audit_events; bukti otomatis CI `mysql-production-gates` + `php artisan pdam:audit-db-privileges` (runbook: `ops/runbooks/DB_PRIVILEGES.md`)
- [x] Backup AES-GCM + end-to-end restore drill otomatis di CI (`ops/backup/`; runbook `ops/runbooks/BACKUP_RESTORE.md`)
- [ ] Enam gate persetujuan production canonical pada [`temuan2.md`](temuan2.md) §13 belum ditutup sampai bukti environment nyata (tooling tersedia di [`ops/README.md`](ops/README.md))

Detail baseline internal: `SECURITY_CHECKLIST.md`. Checklist tersebut bukan sertifikasi.

## Deploy Production

Runbook authoritative: [`backend/DEPLOY.md`](backend/DEPLOY.md) + tooling [`ops/README.md`](ops/README.md)
(+ [`docs/DOC_MAP.md`](docs/DOC_MAP.md) untuk peta angka/flag).

```bash
# Build frontend assets
npm run build

# Bootstrap production (migration + kernel-only seeding; demo TIDAK mungkin jalan)
php artisan migrate --force
php artisan db:seed --class=ProductionKernelSeeder --force   # PLATFORM_ADMIN_* env utk admin awal (opsional)
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache

# Least-privilege DB + append-only privacy_audit_events (lihat ops/mysql + DEPLOY)
bash ops/mysql/create_users.sh ... && bash ops/mysql/verify_privileges.sh
php artisan pdam:audit-db-privileges

# Backup terenkripsi + drill restore (cron host backup; RPO/RTO ke evidence)
bash ops/backup/backup_production.sh
bash ops/backup/restore_drill.sh

# Verifikasi pra-rilis
php artisan pdam:health-check
php artisan pdam:queue-health            # alarm worker/failed_jobs (liat supervisord.production.conf)
php artisan pdam:integrations-health     # + --ping utk Midtrans/Vision/ML nyata
../ops/tls/verify_production_tls.sh api.pdam.go.id pdam.go.id
# Build Flutter release: dua pin SPKI SHA-256 wajib, tanpa prefix sha256/
flutter build apk --release \
  --dart-define=CERT_SPKI_SHA256_PRIMARY="$CERT_SPKI_SHA256_PRIMARY" \
  --dart-define=CERT_SPKI_SHA256_BACKUP="$CERT_SPKI_SHA256_BACKUP"
../ops/tls/verify_spki_pins.sh api.pdam.go.id
```

Queue worker wajib memproses queue `default` dan cron wajib menjalankan `php artisan schedule:run`
setiap menit (file terkelola: `backend/docker/supervisord.production.conf` — worker 2 proc, scheduler
loop, alarm `pdam:queue-health`, event-listener `ops/supervisor/crash_alert.py`). Verifikasi endpoint TLS
production terhadap kedua pin dan lakukan drill rotasi primary/backup sebelum rilis mobile; detail runbook
ada di `ops/runbooks/`.

## License

Proprietary — PDAM SaaS © 2026
