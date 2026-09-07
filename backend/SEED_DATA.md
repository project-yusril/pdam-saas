# SEED DATA - Dokumentasi Data Demo PDAM

**Diperbarui:** 7 September 2026 | **Seeder inti:** `database/seeders/OperationalDataSeeder.php` | **Seeder tambahan:** 17 seeder (16 seeder modul + `SambasTenantSeeder`; lihat Section 8) | **Router seed:** `DatabaseSeeder` + `DemoSeeder` + `ProductionKernelSeeder` (lihat catatan di bawah)

Dokumen ini menjelaskan seluruh data demo yang dibuat otomatis saat `php artisan db:seed`
(atau `php artisan migrate --seed`). Data dirancang **saling terintegrasi** — dari pelanggan →
baca meter → tagihan → pembayaran → jurnal → neraca, plus gudang → stok → jurnal persediaan.

> **Development/demo only.** Dokumen ini adalah satu-satunya sumber operasional kredensial fixture.
> Sejak pemisahan seeder (temuan2.md P1), `DatabaseSeeder` menjadi router: pada environment
> **production** hanya `ProductionKernelSeeder` (permission, katalog modul, role template, admin
> platform opsional via env `PLATFORM_ADMIN_*` dengan tolak-password-demo) yang berjalan — semua
> fixture demo dan password `12345678` **diblokir mutlak** oleh `DemoGuard` (tanpa env escape;
> bukti `ProductionSeederIsolationTest` 9/9). Pada environment non-production, `DatabaseSeeder`
> menjalankan kernel + `DemoSeeder` (seluruh fixture di dokumen ini). Jalur production tetap
> mengikuti [`DEPLOY.md`](DEPLOY.md).

> **Snapshot live (7 Sep 2026):** 65 migration / 28 class seeder (lihat [`../docs/COUNTS.json`](../docs/COUNTS.json))
> membangun 167 tabel; dua migration September menambah `installation_material_orders` (reservasi
> material pemasangan) dan trigger append-only `privacy_audit_events`. MySQL rollback `000004`-`000010`
> lalu migrate ulang lulus. Sejak audit 15 Juli dataset demo **ditambah 1 seeder tenant**
> (`SambasTenantSeeder`) → total **25 seeder demo** (dirutekan `DemoSeeder`) + kernel/guard, membangun
> **3 tenant demo** (lihat Section 1) — tidak mengubah angka 167 tabel pada snapshot audit.
> Snapshot lama tetap ada di `../temuan.md`; bukti aktif ada di `../temuan2.md`.

> Audit aktif ada di `../temuan2.md`; `../temuan.md` adalah arsip snapshot 8 Juli 2026.
>
> **Dokumen terkait:** peta dokumen & fact sheet [`../docs/DOC_MAP.md`](../docs/DOC_MAP.md) · angka live [`../docs/COUNTS.json`](../docs/COUNTS.json) · [`../README.md`](../README.md) · [`README.md`](../README.md) · [`../PRD.md`](../PRD.md) dan [`../02_flow.md`](../02_flow.md) sebagai target/desain · [`../task.md`](../task.md) dan [`../temuan.md`](../temuan.md) sebagai arsip · [`../SECURITY_CHECKLIST.md`](../SECURITY_CHECKLIST.md) sebagai baseline internal · [`DEPLOY.md`](DEPLOY.md) sebagai runbook deployment · [`../HANDOVER.md`](../HANDOVER.md). Enam gate persetujuan production canonical hanya didefinisikan di [`../temuan2.md`](../temuan2.md); tooling gate [`../ops/README.md`](../ops/README.md).



---

> **Tautan wajib:** [`README.md`](../README.md) · [`temuan2.md`](../temuan2.md) · [`02_flow.md`](../02_flow.md) · [`HANDOVER.md`](../HANDOVER.md) · [`SECURITY_CHECKLIST.md`](../SECURITY_CHECKLIST.md) · [`docs/DOC_MAP.md`](../docs/DOC_MAP.md) · [`docs/COUNTS.json`](../docs/COUNTS.json) · [`ops/README.md`](../ops/README.md) · [`backend/README.md`](../README.md) · [`backend/DEPLOY.md`](DEPLOY.md) · [`backend/SEED_DATA.md`](SEED_DATA.md) · [`ml/README.md`](../ml/README.md) · [`docs/ML_CALIBRATION.md`](../docs/ML_CALIBRATION.md) · [`docs/BUSINESS_DECISIONS.md`](../docs/BUSINESS_DECISIONS.md)
> <!-- doc-sync:links -->
<!-- doc-sync:start verifikasi 7 Sept 2026 -->
> **Verifikasi terintegrasi:** 117/117 (117 test backend, 812 assertions) · Vitest 13 · Flutter analyze 0 issue + 46/46 · ML 32 (CI) · 369 method /api/v1 (293 path registry) · 65 migration · 28 seeder · 21 command · 167 tabel statis · 136 model · 2026-09-07. Kanoni angka: [`docs/COUNTS.json`](../docs/COUNTS.json) · status resmi: [`temuan2.md`](../temuan2.md) §13 · peta dokumen: [`docs/DOC_MAP.md`](../docs/DOC_MAP.md) · tooling: [`ops/README.md`](../ops/README.md) · CI: [`.github/workflows/ci.yml`](../.github/workflows/ci.yml) + `nightly-ops.yml`.
<!-- doc-sync:end -->

## 1. Dua Tenant Demo

> **Catatan penamaan:** "Canada" & "Brazil" hanya label agar tidak terkena copyright.
> Isi datanya adalah data Indonesia (Pontianak & Surabaya).

| Tenant | Kode login | Sumber data | Modul aktif | Skala data |
|--------|-----------|-------------|-------------|------------|
| **PDAM Canada** | `pdam-canada` | PDAM Pontianak | **27 modul (LENGKAP)** | 4 zona, 10 pelanggan, 5 material |
| **PDAM Brazil** | `pdam-brazil` | PDAM Surabaya | **10 modul (SEBAGIAN)** | 2 zona, 5 pelanggan, 3 material |
| **PDAM Sambas** | `pdam-sambas` | Kabupaten Sambas (Kalimantan Barat) | **27 modul (LENGKAP)** | 7 zona, **3.000 pelanggan**, 8 material |

> **PDAM Sambas** (`SambasTenantSeeder`) memakai alamat nyata Kabupaten Sambas (kode BPS 6101):
> 19 kecamatan, desa & jalan asli yang membentuk rute meter. Simulasi berjalan **1 tahun** (Sep 2025 →
> bulan penuh terakhir), pelanggan baru mulai `initial_reading = 0` dan di-baca tiap bulan (nilai akumulatif).
> Setiap pembacaan punya **foto rumah + foto meter** dan **`read_by` (petugas rute)** sehingga pertanggungjawaban jelas.
> Pola pembayaran dibuat realistis: **1, 2, 3 bulan menunggak**, serta pelanggan **diisolir** karena >6 bulan
> tidak bayar. Gudang menghubungkan **permintaan wilayah → gudang pusat**, pengadaan **tawas** (PO, PR, tender, vendor),
> dan seluruh 27 modul saling terintegrasi (tagihan → jurnal → neraca BALANCE).

**Modul aktif PDAM Brazil (partial):** `CORE, ZONE, WH, MTR, SRV, FIN+, CRM, BILL+, C360, APP`.
Sisanya (`AST, METX, FSM, PROC, MNT, HR, DMS, GIS, CC, BI, INT, CHEM, IOT, PROD, DIST, NRW, AI`)
berstatus `locked` — persis skenario PDAM kecil yang belum berlangganan modul enterprise.

---

## 2. Kredensial Login

Semua user demo memakai password: **`12345678`**

Email user demo mengikuti pola **`{role_code}@gmail.com`**. Karena email unik **per tenant**, email yang
sama dipakai di ketiga PDAM; yang membedakan saat login adalah **kode PDAM**
(`pdam-canada` / `pdam-brazil` / `pdam-sambas`).

**Atribusi Admin PDAM per tenant:**

| PDAM | Kode login | Admin PDAM (email) | Password |
|------|-----------|--------------------|----------|
| **PDAM Canada** | `pdam-canada` | `admin_tenant@gmail.com` | `12345678` |
| **PDAM Brazil** | `pdam-brazil` | `admin_tenant@gmail.com` | `12345678` |
| **PDAM Sambas** | `pdam-sambas` | `admin_tenant@gmail.com` | `12345678` |

> **Akun tambahan tenant Sambas:** `SambasTenantSeeder` membuat **1 petugas baca meter per rute**
> (`meter_officer1@gmail.com` … `meter_officer7@gmail.com`, password `12345678`) yang di-assign ke
> masing-masing rute — selain user per role standar (35 role). Total akun user demo saat ini:
> **(35 role × 3 tenant) + 7 petugas Sambas + 1 super-admin platform = 113 akun**.

Tabel di bawah adalah daftar **lengkap** 35 role yang di-clone ke
**masing-masing** tenant oleh `RoleTemplateSeeder` + `TenantProvisioningService`.
Karena **email & password identik** di ketiga tenant (unik per tenant, hanya dibedakan oleh `pdam_code`),
tabel berikut berlaku untuk **PDAM Canada, PDAM Brazil, dan PDAM Sambas**
(35 role × 3 tenant = 105 akun; belum termasuk 7 petugas baca meter per rute milik tenant Sambas).

#### PDAM Canada (`pdam-canada`)

| # | Role Code | Nama Role | Email | Password |
|---|-----------|-----------|-------|----------|
| 1 | `super_admin` | Super-Admin (Platform Owner)* | `super_admin@gmail.com` | `12345678` |
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
| 1 | `super_admin` | Super-Admin (Platform Owner)* | `super_admin@gmail.com` | `12345678` |
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

> \* Role `super_admin` di-clone ke tiap tenant hanya untuk kelengkapan template; Super-Admin platform
> yang sesungguhnya adalah akun terpisah di tabel `platform_admins` (lihat tabel di bawah), bukan user tenant.

> **Catatan permission:** hanya `admin_tenant` yang otomatis mendapat **seluruh** permission (akses penuh
> dalam tenant-nya). Role lain sudah dibuat tetapi pemetaan permission detail diisi bertahap per fase
> modul (lihat `app/Support/RolePermissionPresets.php`).

Super-admin platform (lintas tenant) di-seed oleh `PlatformAdminSeeder`:

| Mode | Email | Password | Catatan |
|------|-------|----------|---------|
| Super Admin platform | `superadmin@gmail.com` | `12345678` | Pilih mode **Super Admin**; tanpa kode PDAM |

Role tenant `super_admin@gmail.com` berbeda dari akun platform di atas dan bukan identitas platform.
Daftar role beserta contoh body login juga tersedia di [`backend/README.md`](../README.md) Section 6 &
[`../README.md`](../README.md).

---

## 3. Rantai Data Terintegrasi

Seeder tidak menulis angka mentah ke tabel keuangan. Transaksi digerakkan lewat **service asli** agar
jurnal double-entry selalu balance. **Pengecualian (perf):** `SambasTenantSeeder` (tenant Sambas, 3.000
pelanggan) menulis tagihan/pembayaran/jurnal secara **langsung** (`insertJournal`) dengan **guard
`DEBIT == KREDIT`** (throw bila tidak balance), karena `BillingService`/`PaymentService` pada skala 3.000
pelanggan memakan ±36 menit. Hasil akhir tetap jurnal ber-format sama & neraca BALANCE.

```
Modal awal ──▶ JournalService  (DEBIT Kas 1-001 | KREDIT Modal 3-001)
                     │
Beli material ─▶ StockService (stok masuk) + JournalService
                     │          (DEBIT Persediaan 1-003 | KREDIT Kas 1-001)
                     │
Pelanggan ─▶ Meter fisik ─▶ MeterReading (baca bertingkat 3/2 periode)
                     │
Generate tagihan ─▶ BillingService (tiered tariff)
                     │          (DEBIT Piutang 1-002 | KREDIT Pendapatan Air 4-001)
                     │
Bayar tagihan ─▶ PaymentService
                            (DEBIT Kas 1-001 | KREDIT Piutang 1-002)
```

### Detail per langkah

| # | Data | Cara dibuat | Dampak akuntansi |
|---|------|-------------|------------------|
| 1 | **Zona & Gudang** | `Zone` + `Warehouse` (1 utama + buffer) | — |
| 2 | **Modal awal** | `JournalService::record` | Kas & Ekuitas naik (dasar neraca) |
| 3 | **Supplier & Material** | `Supplier`, `Material` | — |
| 4 | **Pembelian stok** | `StockService::stockIn` + jurnal | Persediaan naik, Kas turun |
| 5 | **Rute baca meter** | `MeterRoute` per zona | — |
| 6 | **Periode baca** | `ReadingPeriod` (status `closed`) | prasyarat generate tagihan |
| 7 | **Pelanggan + Meter** | `Customer` + `Meter` (terpasang) | — |
| 8 | **Baca meter** | `MeterReading` (nilai bertingkat) | sumber konsumsi tagihan |
| 9 | **Tagihan** | `BillingService::generateForCustomer` | Piutang & Pendapatan naik |
| 10 | **Pembayaran** | `PaymentService::markPaid` | Kas naik, Piutang turun |
| 11 | **Pengaduan (CRM)** | `Complaint` (open/in_progress/resolved) | — |

Pola pembayaran sengaja dibuat realistis: tagihan periode lama dibayar lunas, sebagian
tagihan periode terakhir **sengaja menunggak** agar laporan piutang & aging tidak kosong.

---

## 4. Ringkasan Hasil Seed (terverifikasi)

Diverifikasi setelah `php artisan migrate:fresh --seed`:

| Metrik | PDAM Canada | PDAM Brazil | PDAM Sambas |
|--------|------------:|------------:|------------:|
| Pelanggan | 10 | 5 | 3.000 |
| Tagihan | 30 (26 lunas, 4 nunggak) | 10 (8 lunas, 2 nunggak) | 36.000 (33.600 lunas, 2.400 nunggak) |
| Jenis material | 5 | 3 | 8 |
| Total stok fisik | 1.800 unit | 700 unit | 7.600 unit (beli awal) |
| Baca meter (foto+petugas) | — (fokus inti) | — (fokus inti) | 36.000 |
| Pelanggan isolir (putus) | — | — | 150 |
| Jurnal (entries) | 58 | 20 | 69.602 |
| Modul aktif | **27** | **10** | **27** |
| Total DEBIT | Rp 5.183.181.700 | Rp 3.082.347.000 | Rp 117.524.114.700 |
| Total KREDIT | Rp 5.183.181.700 | Rp 3.082.347.000 | Rp 117.524.114.700 |
| **Neraca** | ✅ **BALANCE** | ✅ **BALANCE** | ✅ **BALANCE** |

Karena `SUM(DEBIT) == SUM(KREDIT)` di ketiga tenant, **neraca (balance sheet) pasti seimbang**
dan laporan keuangan langsung punya isi. Khusus Sambas, angka pemakaian (m³) pada tagihan = selisih
baca meter (kini − lalu) sehingga integrasi **baca meter → tarif → tagihan → jurnal** terbukti nyambung.

---

## 5. Workflow Database Demo

### Update schema non-destruktif

```bash
php artisan migrate
```

### Bootstrap fixture demo

```bash
php artisan migrate --seed
```

Seeder dirancang idempotent; sebagian data yang sudah ada akan di-skip atau di-update. Periksa output dan
jumlah data sebelum mengandalkan rerun pada database development yang telah dimodifikasi manual.

### Reset penuh demo

> **DATA HILANG:** perintah berikut drop seluruh tabel. Jangan pernah jalankan pada database user,
> staging bersama, atau production.

```bash
php artisan migrate:fresh --seed
```

- **Menjalankan server (Windows):** `backend/start.bat` (klik 2x) — hanya menjalankan `php artisan serve`.
  Setup awal (`.env`, `composer install`, `key:generate`, `migrate --seed`) dilakukan sekali secara manual.


---

## 6. Golongan Tarif & COA

- **17 golongan tarif** (data PDAM Pontianak, PRD 10.2) di-seed `MasterFinanceSeeder` untuk
  kedua tenant (Canada & Brazil), lengkap dengan tier bertingkat (0–10 / 10–20 / >20 m³).
  Tenant **Sambas** mendapat COA + 17 golongan tarif yang sama dari `SambasTenantSeeder`.
- **Chart of Accounts** standar PDAM (Kas, Piutang, Persediaan, Aset Jaringan, Utang, Modal,
  Pendapatan Air/Pemasangan/Denda, Beban, + akun Aset Tetap & Penyusutan) juga dari
  `MasterFinanceSeeder` (untuk Canada/Brazil) dan `SambasTenantSeeder` (untuk Sambas).

Pemetaan akun yang dipakai transaksi otomatis:

| Kode | Nama | Dipakai untuk |
|------|------|---------------|
| 1-001 | Kas / Bank | modal, beli material, terima pembayaran |
| 1-002 | Piutang Pelanggan | tagihan terbit & pelunasan |
| 1-003 | Persediaan Material | pembelian stok gudang |
| 3-001 | Modal / Ekuitas | setoran modal awal |
| 4-001 | Pendapatan Air | tagihan air |

---

## 7. Katalog Modul (`ModuleSeeder`)

`ModuleSeeder` mengisi **27 modul** (1 CORE wajib + 26 add-on) ke tabel `modules`, lengkap dengan:

- `code`, `name`, `tier` (1=Core Operations, 2=Enterprise, 3=Smart Utility)
- `base_price_year` — harga langganan per tahun
- `dependencies` — kode modul prasyarat (mis. `METX` butuh `MTR`, `PROC` butuh `WH`+`FIN+`, `AI` butuh `BI`)
- `description` — penjelasan isi tiap modul (dipakai kartu di Marketplace Modul super admin)
- `is_default` — `CORE` = gratis saat provisioning tenant

Katalog ini menjadi sumber data endpoint `GET /platform/modules` dan halaman **Marketplace Modul**
(`/platform/modules`) yang menampilkan deskripsi + relasi dependency antar-modul.

> Jika kartu modul menampilkan "Tidak ada deskripsi", jalankan ulang `php artisan db:seed --class=ModuleSeeder`
> untuk mengisi kolom `description` (idempotent — aman dijalankan berulang).

---

## 8. Seeder Modul Tambahan (17 seeder)

Selain seeder inti (Section 3), ada **16 seeder modul** + **`SambasTenantSeeder`** yang mengisi master data
fondasi, modul aktif, dan modul enterprise. Semuanya **idempotent** dan **tenant-aware**: data enterprise
hanya diisi untuk tenant yang modulnya `active` (PDAM Canada & SAMBAS full 27 modul; PDAM Brazil hanya modul aktif).

Urutan eksekusi di `DatabaseSeeder.php` sudah menghormati dependency (mis. `AssetSeeder`
sebelum `MaintenanceSeeder`), dan `SambasTenantSeeder` berada paling akhir (punya cakupan lintas modul sendiri).

### 8.1 Fondasi & Modul Aktif (ketiga tenant)

| Seeder | Tabel utama yang diisi | Catatan integrasi |
|--------|------------------------|-------------------|
| `AddressSeeder` | `provinces`, `cities`, `districts`, `villages`, `streets`, `meter_route_streets`, `meter_route_assignments` | Menyambungkan `customers.street_id` (15/15 valid, 0 orphan) + rute ↔ jalan ↔ petugas. Data alamat berjenjang (Provinsi→Kota→Kecamatan→Desa→Jalan) adalah **global reference** (`pdam_org_id` null) agar dipakai semua PDAM; tiap tenant bisa menambah data **privat** sendiri (`pdam_org_id` = tenant) melalui halaman **Master Alamat**. |
| `CrmDetailSeeder` | `complaint_tracks`, `customer_feedbacks` | Histori penanganan + feedback tiap pengaduan yang selesai |
| `CustomerLifecycleSeeder` | prospek, survey, jadwal instalasi, `disconnections`, `ownership_transfers` | Siklus hidup pelanggan (calon → aktif → isolir → balik nama) |
| `NotificationSeeder` | notifikasi in-app, `chats`, `chat_messages` | Komunikasi pelanggan ↔ petugas |
| `BillingExtraSeeder` | `installment_plans`, `installment_plan_bills`, `installment_schedules`, `bill_adjustments` | Cicilan tagihan + penyesuaian |

### 8.2 Modul Enterprise (utamanya PDAM Canada)

> Seeder enterprise di bawah mengisi **PDAM Canada** (tenant Sambas mengisi modul enterprisenya sendiri
> lewat `SambasTenantSeeder` — lihat 8.4).

| Seeder | Modul | Tabel utama yang diisi |
|--------|-------|------------------------|
| `MeterExtendedSeeder` | METX | lifecycle events, meter stock, `meter_anomalies`, penggantian meter |
| `WarehouseAdvancedSeeder` | WH | `purchase_orders`, `stock_transfers` (+items), `stock_adjustments`, `repair_orders` |
| `FinanceEnterpriseSeeder` | FIN+ | invoice AR/AP, `bank_accounts`, `budgets`, `tax_records` |
| `AssetSeeder` | AST | `fixed_assets`, `depreciation_entries`, `asset_movements`, `asset_disposals` |
| `ChemicalSeeder` | CHEM | `chemicals`, `chemical_stocks`, `chemical_usages` + rantai purchase/qc/opname |
| `ProcurementSeeder` | PROC | `vendors`, `tenders`, `vendor_contracts`, `purchase_requests` |
| `FieldServiceSeeder` | FSM | `work_orders`, `work_order_logs`, `technician_locations` |
| `MaintenanceSeeder` | MNT | `maintenance_schedules`, `maintenance_records` |
| `HrSeeder` | HR | `hr_employees`, `attendances`, `leaves`, `payroll_components` + struktur org, shift, training |
| `DmsGisIntegrationSeeder` | DMS/GIS/INT/CC | `documents`, `gis_features`, `gis_network_edges`, `integrations`, `api_keys`, `call_logs` |
| `SmartUtilitySeeder` | IOT/PROD/DIST/NRW/AI | `sensor_readings`, `production_logs`, `dma_zones`, `nrw_balances`, `ml_predictions` |

### 8.3 Data Komersial Platform (level super admin)

| Seeder | Tabel utama yang diisi | Catatan |
|--------|------------------------|---------|
| `PlatformCommerceSeeder` | `module_price_tiers`, `module_bundles` (+items), `promos` (+targets/redemptions), `saas_invoices` | Harga berjenjang per skala pelanggan, paket bundling, promo, tagihan langganan SaaS |

### 8.4 Seeder Tenant Sambas (tenant demo ke-3)

| Seeder | Tenant | Yang diisi | Catatan |
|--------|--------|-----------|---------|
| `SambasTenantSeeder` | PDAM Sambas (`pdam-sambas`) | Provisioning tenant + 27 modul aktif + 35 role; COA & 17 golongan tarif; alamat nyata Kab. Sambas (19 kecamatan/desa/jalan; kode BPS 6101); 7 zona + 7 gudang + rute + petugas baca per rute; **3.000 pelanggan + meter + 36.000 baca (foto + `read_by`)** → tagihan → pembayaran → jurnal; tunggakan 1/2/3 bulan & 150 isolir; gudang (transfer wilayah→pusat, PO/PR tawas, opname); procurement (vendor/tender/kontrak); modul enterprise (METX, AST, FIN+, CHEM, FSM, MNT, HR, DMS/GIS/INT/CC, IOT/PROD/DIST/NRW/AI, BILL+, CRM, SRV, APP, C360) | Idempotent (dilewati bila tenant sudah punya pelanggan; `SEED_SAMBAS_FORCE=true` untuk reseed). Memakai data alamat & jalan asli Sambas sehingga rute meter punya nama jalan nyata. |

> **Catatan balance:** entri di `depreciation_entries`, maintenance, dan asset di-set
> `journal_entry_id = null` agar **tidak mengganggu neraca inti** yang sudah balance (Section 4).

### 8.5 Tabel yang sengaja dibiarkan kosong (⚪ wajar)

Bukan karena seeder gagal — memang tidak perlu di-seed karena terisi otomatis saat runtime:
`payment_gateway_logs`, `personal_access_tokens`, `password_reset_tokens`, `jobs`, `job_batches`,
`failed_jobs`, `cache`/`cache_locks`, `sessions`, `user_permissions`, `subscriptions`,
`tenant_module_overrides`, `price_change_logs`, `activity_logs`, `employees` (dipakai `hr_employees`).

---

## 9. Laporan Baca Meter & Perubahan Terkait

### 9.1 Fitur Laporan Baca Meter

Ditambahkan untuk membuktikan rantai **petugas → rute → pelanggan → baca meter → tarif → tagihan** hingga
dapat dipertanggungjawabkan (foto + petugas).

| Komponen | Lokasi | Keterangan |
|----------|--------|-----------|
| Endpoint | `GET /api/v1/meter-readings/report?period=YYYY-MM&route_id=` | `MeterReadingController@report` — izin `mtr.reading.view` |
| Halaman web | `#/meter-reading-report` (menu **Baca Meter & Metering → Laporan Baca Meter**) | `resources/js/views/meter/MeterReadingReportView.vue` |
| Isi laporan | per pelanggan | No. Pelanggan, Nama, Jalan, Golongan, **Baca Lalu**, **Baca Kini**, **Pemakaian (m³)**, **Biaya**, foto meter & rumah, **Petugas (read_by)**, Verifikator, Status |

> **Bukti integrasi untuk tenant Sambas (rute RT-01, periode 2026-08):** `SMBS-00001` baca lalu `84` →
> baca kini `91` → **pemakaian 7 m³** → tarif `2A1` → biaya `Rp 28.000`, dan `pemakaian == bill.consumption`
> (**BENAR**). Foto meter & rumah ada, petugas `Rahmat Hidayat` + verifikator kantor tercatat.

### 9.2 Perubahan lain yang menyertai

- **Assign Petugas → Rute** (halaman **Rute Baca Meter**, kolom *Petugas Baca*): tombol **Petugas**
  membuka modal untuk menetapkan user berperan `meter_officer` ke rute
  (`POST /api/v1/meter-routes/{route}/officer`, izin `mtr.route.assign`); daftar petugas difilter via
  `GET /users?role=meter_officer`. Respons `GET /meter-routes` kini menyertakan petugas aktif.
- **`CustomerListView.vue`** → tabel pelanggan kini **server-side pagination** (default 25/halaman,
  `Show X entries` + Previous/Next); footer menampilkan total benar (mis. `Showing 1 to 25 of 3.000 entries`).
- **`DataTable.vue`** (shared) → fallback `from`/`to` pada mode server agar list yang tidak mengirim
  `:from`/`:to` (mis. Pegawai) tidak menampilkan `0 to 0`.
- **`Customer`** → relasi `street(): BelongsTo` ditambahkan agar laporan baca meter bisa menampilkan jalan.

---

**Yusril Eka Mahendra — 2026**


