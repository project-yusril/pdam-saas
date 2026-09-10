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
> **Verifikasi terintegrasi:** 202/202 (202 test backend, 1179 assertions) · Vitest 13 · Flutter analyze 0 issue + 52/52 · ML 32 (CI) · 379 method /api/v1 (302 path registry; 44 web) · 66 migration · 29 seeder · 22 command · 168 tabel statis · 137 model · 2026-09-09. Kanoni angka: [`docs/COUNTS.json`](../docs/COUNTS.json) · status resmi: [`temuan2.md`](../temuan2.md) §13 · peta dokumen: [`docs/DOC_MAP.md`](../docs/DOC_MAP.md) · tooling: [`ops/README.md`](../ops/README.md) · CI: [`.github/workflows/ci.yml`](../.github/workflows/ci.yml) + `nightly-ops.yml`.
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
| **GIS jaringan (Sambas, 9 Sept)** | (mini Canada/DMS seed) | — | **pipa 3.010** (incl. 3.000 SR Ø20 smua 3.000 rumah tersambung), **edges 3.010**, **node tap 3.000**, valve 9 (hub+zona), hydrant 14, **7 DMA** ber-polygon, reading suplai 5.208 baris (bulan tertutup) → panel `/admin/network` NRW terisi |
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

### Reset ulang jaringan GIS saja (non-destruktif)

```bash
php artisan pdam:seed-network
```

Menghapus + membangun ulang seluruh `SMBS-NET`/tap/pipa/edge/ DMA boundary / reading NRW
bulan lalu dari **koordinat pelanggan terkini** (idempoten, aman dijalankan berulang — lihat §11).

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


---

## 10. Peta Pelanggan OSM (GIS Module)

**Modul:** GIS (`module:GIS` required; enabled untuk tenant Sambas + Canada dalam seed demo).  
**Route web direktur/admin:** `GET /admin/gis/map` (link di nav: *Peta Pelanggan*).  
**Teknologi peta:** Leaflet 1.9.4 + **OpenStreetMap tiles** (gratis) via CDN.  
**Routing & geocoding:** gratis dari server publik dengan kebijakan fair-use:
- **Geocoding (alamat → koordinat):** [Nominatim](https://nominatim.openstreetmap.org) (proxy server-side, User-Agent wajib, maks 1 req/detik); config via `config('services.nominatim')`.
- **Routing (titik A ke B, tour baca meter):** [OSRM demo](https://router.project-osrm.org), profil `driving|cycling|foot` (proxy server-side, bisa dialihkan ke self-host lewat `config('services.osrm.base_url')`).

### Legenda warna rumah di peta (status pembayaran)

| Warna | Label | Kondisi pelanggan |
|---|---|---|
| 🟢 Hijau | Lunas | tidak ada tagihan unpaid/overdue |
| 🔵 Biru muda | Belum jatuh tempo | ada tagihan unpaid tetapi due_date ≥ hari ini |
| 🟡 Kuning | Menunggak 1 bulan | 1 periode lewat jatuh tempo |
| 🟠 Oranye | Menunggak 2 bulan | 2 periode lewat jatuh tempo |
| 🔴 Merah | Menunggak ≥3 bulan | ≥3 periode lewat jatuh tempo |
| ⚫ Hitam | Putus / Isolir | status pelanggan ∈ {isolir, terminated, disconnected} |

Icon rumah (divIcon SVG) diwarnai otomatis per status; cluster ikon Zoom-in memisahkan titik.

### Fitur halaman peta

- **Filter:** zona, rute baca meter, pencarian nama/no. pelanggan.
- **Statistik ringkas:** jumlah pelanggan per warna + jumlah **tanpa koordinat**.
- **Panel "Belum ada koordinat"** — daftar pelanggan yang butuh titik, tiap baris tombol:
  - **Geocode alamat** — isi titik otomatis via Nominatim (proxy, respecting rate limit + sleep).
  - **Tandai di peta** — klik lokasi rumah pada peta, simpan koordinat (PATCH endpoint validasi range Indonesia).
- **Routing:** klik popup marker → tombol **Mulai rute dr sini** / **Akhiri rute di sini**; gambar polyline biru (jarak km, waktu perkiraan menit/jam) dari proxy OSRM.
- **Rute baca meter (tour)** — pilih rute baca meter → render urutan kunjungan (nearest-neighbor via OSRM matrix) sebagai garis ungu putus-putus + penomoran stop; berguna untuk perencanaan petugas.
- **Pencarian alamat (OSM)** — input di toolbar → hasil dropdown (kandidat alamat dari Nominatim) → klik untuk pan peta ke lokasi; opsi **Set titik awal rute** di marker temporer.

### Data demo (Sambas)

Tenant PDAM Kab. Sambas (`SambasTenantSeeder`) sudah menyediakan **3.000 pelanggan** dengan koordinat yang di-klaster **per zona/kecamatan nyata** (Sambas Kota, Pemangkat, Tebas, Jawai, Teluk Keramat, Selakau, Paloh — lihat `DISTRICT_CENTERS`), sesuai bucket:
70% lunas (hijau), 10% menunggak 1 bulan (kuning), 10% 2 bulan (oranye), 5% 3 bulan (merah), 5% isolir (hitam) — sangat cocok untuk verifikasi dashboard visual.

### Self-hosting & kebijakan penggunaan

Server publik OSRM & Nominatim bersifat **gratis untuk fair-use** (maksimal ~1 req/detik, user-agent wajib berisi kontak Anda). Untuk produksi skala tinggi atau beban lebih, disarankan **self-host**:
- OSRM (`docker run osrm/osrm-backend` + dataset Indonesia `.osm.pbf`), Nominatim (Docker Nominatim instance atau Photon), lalu set:
  ```env
  OSRM_BASE_URL=http://localhost:5000
  NOMINATIM_BASE_URL=https://nominatim.yourdomain.com
  NOMINATIM_USER_AGENT=PDAM-SelfHosted/1.0 (support@yourcompany.com)
  ```
Tidak perlu mengubah kode; backend mendeteksi URL dari environment.

### Integrasi API

Fitur juga terpapar melalui endpoint REST internal untuk modul lain:
- `GET /api/v1/gis/customers?zone_id=&meter_route_id=&colors=...&bbox=` (GeoJSON FeatureCollection) — gate `module:GIS`.
- `GET /api/v1/gis/customers/status-summary` — JSON summary counts (warna + meta).
- Backend menggunakan `CustomerMapStatusService::classifyBatch` yang efisien (1 query agregate + 1 subquery) untuk 5000 pelanggan maksimum tanpa N+1.

### Unit test

Tes end-to-end mencakup:
- `tests/Feature/GisMapTest.php`: klasifikasi warna per status, tenant isolation, geocode pacing, routing proxy OSRM, tour nearest-neighbor, dan validasi koordinat manual.

---


## 11. GIS Jaringan Perpipaan (Network GIS)

**Route web:** `GET /admin/network` (menu **Jaringan Pipa** di nav admin — direktur, admin, gis_operator, technical_head, field_dispatcher).
**Endpoint API (mobile/sistem):** blok `gis/network` di `routes/api.php` (gate `module:GIS` + `permission:gis.feature.*`); insiden memakai `fsm.wo.create`.

### Lapisan data (tabel eksisting — TANPA migrasi baru)

| Tabel | Peran |
|---|---|
| `gis_features` | `pipe` (LineString), node: `valve / junction / hydrant / pump / reservoir / intake / treatment` |
| `gis_network_edges` | sambungan node↔node + `pipe_feature_id` (ruas), `length_meters` |
| `dma_zones` | polygon pembatas DMA (`boundary` GeoJSON Polygon) |
| `nrw_balances` | hasil perhitungan NRW per DMA/periode |
| `work_orders` | insiden jaringan (`source_type=gis_feature`, tipe `repair`/`inspection`, prioritas SLA) |

### Fitur halaman

- **Editor Leaflet.draw** (gratis): gambar pipa (auto-wire ujung ke node ≤12 m / junction baru), node titik, polygon DMA; klik fitur → edit properti (material, Ø, status), **toggle valve buka/tutup**, hapus (cascade edge).
- **Isolasi bocor (graf terarah)**: klik ruas pipa → "Isolasi": orientasi aliran BFS dari sumber, simulasi **tutup valve minimal** (`NetworkGraphService::isolate`) → daftar valve, segmen STARVED (kehilangan air; cabang dengan jalur lain & klaster yatim dikecualikan), peringatan bila sumber masih menempel, **polygon terdampak (convex hull)** + daftar pelanggan di dalamnya. Panah arah aliran di peta.
- **Insiden → Work Order**: tombol darurat membuat WO `repair`/`urgent|high|...` + SLA otomatis + log, dan menandai pipa `rusak` (merah di peta).
- **Analisis NRW otomatik per DMA**: suplai = rata-rata `flow_rate_m3h` reading Distribusi × jam periode; terbaca = Σ `consumption` tagihan pelanggan **di dalam polygon DMA** → upsert `nrw_balances` + status baik/waspada/kritis (>20 %/>30 %) + ILI kasar.

### Modul lanjutan GIS Jaringan (sesi 9 Sept b.2 — semua data sudah ada di repo)

| # | Modul | Cara kerja / endpoint |
|---|---|---|
| 1 | **Graf TERARAH** | Arah aliran per edge dihitung BFS multi-sumber; `isolate()` = **simulasi tutup valve MINIMAL**: starved = dulu teraliri & sesudah penutupan tidak — cabang tetap teraliri via jalur lain TIDAK ikut terpotong; klaster yatim tak dihitung; panah ➜ arah di peta (layer `Arah aliran`). |
| 2 | **Petugas LIVE + dispatch** | GPS mobile: `POST /api/v1/field/location` (tombol lapor lokasi) + piggyback submit survey & baca meter → `technician_locations` (upsert/user). `GET /admin/network/technicians.json` (online bila < `PDAM_GIS_OFFICER_STALE_MINUTES`, default 30 mnt; klik-layar refresh 30 dtk). `POST /admin/network/dispatch` → haversine terdekat + rute OSRM → WO `gis_feature` ter-`assigned` + notif in-app/push. |
| 3 | **MNF debit malam** | `GET /admin/network/mnf.json`: rata flow **02:00–04:00** vs `base_demand_m3day` (fallback 0.8 m³/koneksi/hari — `PDAM_MNF_DEFAULT_LPCD`); merah >2× ambang, waspada > ambang (`PDAM_MNF_ALERT_PCT`, def 15 %); polygon DMA ikut berwarna bila layer DMA aktif. |
| 4 | **Tren NRW + cron** | `pdam:nrw-monthly` (tgl 1 04:00, multi-tenant via TenantContext) isi `nrw_balances`; `nrw-trend.json?months=6` → sparkline SVG per DMA di tabel NRW panel. |
| 5 | **Validator kesehatan** | `health.json` / tombol panel "🩺": node menggantung, pipa tanpa edge, klaster tanpa sumber (kasus "4 yatim"), ruas > 400 m kedua ujung non-valve, hydrant < 2/DMA. Skor 100−pembobotan temuan. |
| 6 | **Peta risiko pipa** | `risks.json` skor 0–100: bahan (`Besi Tuang`/`Galvanis`/`Asbes` rawan vs `HDPE` tahan) + umur (`install_year`, cap `PDAM_GIS_RISK_MAX_AGE_YEARS=60`) + jumlah WO repair historis (`source_type=gis_feature`, saturasi eksponensial) + status rusak. Garis kuning→merah + top-5 prioritas ganti di kartu audit. |
| 7 | **GeoJSON & lembar status** | `export.geojson` (unduhan semua fitur/DMA — kompatibel QGIS), `import.geojson` (Point→node, LineString→pipa+auto-wire, Polygon→DMA; `dry_run` untuk simulasi tanpa insert); `GET /admin/network/print` — lembar A4 landscape (skema SVG jaringan terwarnai risiko + tabel NRW/health/prioritas) dicetak via browser → PDF utk direktur. |
| 8 | **Preventif valve/hydrant (MNT↔GIS)** | Jadwal disimpan sbg `maintenance_schedules` (`asset_type='gis_feature'`): panel → pilih device → `POST maintenance` (interval 7–730 hr). Cron harian `pdam:mnt-network` + tombol panel "due→WO": semua due-date → WO `inspection` + next_due maju se-siklus; `…/complete` catat pelaksanaan manual. |
| 9 | **Feasibility pemasangan baru** | `feasibility.json?lat&lng` (tombol 🎯 pilih titik): `nearestPipe()` jarak titik→ruas (planar lokal) + `route_length = jarak × PDAM_SR_ROUTE_FACTOR (1.3)` + biaya = panjang × `PDAM_SR_PIPE_COST_PER_M` (default 0 = belum ditetapkan manajemen → tampil pesan, bukan angka karangan) + status sangat-eligible ≤ 600 m (`PDAM_SR_ELIGIBLE_M`) / perlu persetujuan > `PDAM_SR_APPROVAL_M`. |

Command baru di `routes/console.php`: `NrwMonthly` (tgl 1 04:00) & `MntNetwork` (harian 05:00). Semua threshold = `.env` (lihat `PDAM_MNF_*`, `PDAM_SR_*`, `PDAM_GIS_RISK_*` di `.env.example`). Kontrak lama isolasi/NRW layer TIDAK berubah.

**Integrasi mobile (app Flutter) — closed loop dengan peta kantor:**

- Tombol **Lapor Lokasi** (📍) di AppBar Tugas Survey & Rute Baca Meter → `POST /api/v1/field/location` (GPS asli `GpsHelper`) → titik petugas tampil hidup di `/admin/network` (layer 👷, polling 30 dtk), status online/offline via `PDAM_GIS_OFFICER_STALE_MINUTES`.
- Layar **WO Saya** (`/work-orders`, role `field_technician`) → daftar `GET /work-orders?mine=1` (id dipaksa server-side, tak bisa mengintip WO orang lain), aksi **Mulai** (`/start`) & **Selesai** (`/complete`, resolusi wajib) menulis status + log FSM yang sama dengan web; hasil **dispatch GIS** tiba sebagai notifikasi FCM pada petugas.
- Provider Riverpod: `features/field/presentation/providers/field_provider.dart`; remote source: `location_remote_source.dart` + `work_remote_source.dart`; tes kontrak endpoint: `test/core/network/endpoints_test.dart` (52/52 flutter).
- PDF server-side: `/admin/network/status.pdf` (`NetworkStatusPdfService`, dompdf) — lampiran laporan/surel; lembar SVG tetap lewat `/admin/network/print` (cetak browser).

### Demo data (Sambas)

Koordinat pelanggan Sambas di-seed **di sekitar centroid kecamatan aslinya** (konstanta `DISTRICT_CENTERS` di `SambasTenantSeeder` — Sambas Kota, Pemangkat, Tebas, Jawai, Teluk Keramat, Selakau, Paloh) dengan sebaran spiral golden-angle per rute, sehingga **1 rute baca = 1 klaster rumah** yang nyata. Bucket pembayaran: 70% lunas (hijau), 10% tunggak 1 bln (kuning), 10% 2 bln (oranye), 5% 3 bln (merah), 5% isolir/putus (hitam).


### Seeder jaringan demo

`PipeNetworkDemoSeeder` (dipanggil `DemoSeeder`, idempoten via marker `SMBS-`) **tidak memakai koordinat karangan** — seluruh jaringan diturunkan dari data pelanggan nyata di DB per rute/zona, **menyambung ke setiap rumah** seperti jaringan PDAM asli:
- **SETIAP pelanggan** ber-koordinat = node **tap** (`SMBS-TAP <no-pelanggan>`, tipe `tap`) → **pipa sambungan rumah (SR) Ø20 PE** (`SMBS-SVC <no>`, property `service_for=<customer_id>`) dengan ujung tepat di koordinat rumah; graf dirangkai **MST (Prim)** root = valve cabang zona → tidak ada rumah piatu (verifikasi `php artisan pdam:seed-network`: `3000/3000 rumah tersambung pipa`).
- Percabangan **≥4 rumah** otomatis di-upgrade **VALVE hub** (`SMBS-VLV`) → granularitas isolasi realistis; dua tap terluar tiap zona → **hydrant**.
- Trunk DI Ø400 merantai Pompa IPA → tiap valve zona → Reservoir; pipa cabang dari trunk ke valve zona (Ø250/160).
- `gis_network_edges` dibangun dari node yang sama → analisis isolasi bocor berjalan per-simpul; rebuild idempoten: **`php artisan pdam:seed-network`**.
- `dma_zones.boundary` = convex hull rumah ter-buffer ±300 m per zona (7 DMA); `distribution_readings` suplai per jam untuk **periode bulan tertutup terakhir** (flow = konsumsi nyata × 1.35 → NRW demo realistis ~26%).
- Skenario demo: klik pipa SR di peta → **Isolasi** → valve hub/zona mana yang ditutup + daftar persis rumah terdampak → buat WO → panel NRW terisi angka terintegrasi.

### Permission baru (preset, tanpa migrasi)

| Role | Permission jaringan |
|---|---|
| `director` | `gis.feature.view` (+ preset lama) |
| `gis_operator` | `gis.feature.view/create/update/delete`, `fsm.wo.view`, `fsm.wo.create` |
| `technical_head` | `gis.feature.view/update`, `fsm.wo.*` |
| `field_dispatcher` | `gis.feature.view`, `fsm.wo.view/create/assign` |

Tenant admin selalu tembus RBAC (tetap perlu modul `GIS` aktif — Sambas & Canada: aktif).

### Test

`tests/Feature/GisNetworkTest.php` (20) + `GisMapTest.php` (20) + `FieldOfficerMapTest.php` (9) + `NightFlowTrendTest.php` (10) + `NetworkModuleTest.php` (16) = **75** test GIS (±330 assertion), +PDF download: semantik isolasi terarah (rim minimal, pasokan loop via jalur lain aman, klaster yatim), auto-wire, cascade delete, tenant isolation, incidents+dispatch, petugas LIVE (stale/role/org), MNF window+baseline+command NRW bulanan, validator kesehatan 5 pola, risk scoring, GeoJSON e/impor + print, preventif MNT→WO, feasibility tarif/nol-karangan, geocode proxy, gate auth/permission.

### Rebuild / reset

```bash
php artisan pdam:seed-network                 # idempoten: hapus SMBS-*, bangun ulang pipa-tap-rumah+DMA+reading dari data terkini
php artisan db:seed --class=PipeNetworkDemoSeeder --force   # alternatif (skip jika sudah ada)
```

### Catatan produksi

Server demo OSRM/Nominatim = fair-use publik (lihat §10). **Graf pipa & isolasi murni lokal (database)** — tidak bergantung layanan eksternal, jadi analisis bocor tetap jalan walau offline.

---

**Yusril Eka Mahendra — 2026**


