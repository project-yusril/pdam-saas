# SEED DATA - Dokumentasi Data Demo PDAM

**Diperbarui:** 18 Juli 2026 | **Seeder inti:** `database/seeders/OperationalDataSeeder.php` | **Seeder modul:** 16 seeder tambahan (lihat Section 8)

Dokumen ini menjelaskan seluruh data demo yang dibuat otomatis saat `php artisan db:seed`
(atau `php artisan migrate --seed`). Data dirancang **saling terintegrasi** — dari pelanggan →
baca meter → tagihan → pembayaran → jurnal → neraca, plus gudang → stok → jurnal persediaan.

> **Development/demo only.** Dokumen ini adalah satu-satunya sumber operasional kredensial fixture.
> `DatabaseSeeder` saat ini tidak production-safe karena masih memuat akun dan password demo. Jalur
> production hanya mengikuti [`DEPLOY.md`](DEPLOY.md).

> **Snapshot terbaru:** MySQL 8.4.9 disposable dan SQLite menjalankan **62 migration dan 24 seeder** hingga
> membangun 167 tabel; migration terbaru menambah `pdam_org_id` + `deleted_at` pada tabel master alamat.
> MySQL rollback `000004`-`000010` lalu migrate ulang lulus. Snapshot lama tetap ada di `../temuan.md`;
> bukti aktif ada di `../temuan2.md`.

> Audit aktif ada di `../temuan2.md`; `../temuan.md` adalah arsip snapshot 8 Juli 2026.
>
> **Dokumen terkait:** [`../README.md`](../README.md) · [`README.md`](README.md) · [`../PRD.md`](../PRD.md) dan [`../02_flow.md`](../02_flow.md) sebagai target/desain · [`../task.md`](../task.md) dan [`../temuan.md`](../temuan.md) sebagai arsip · [`../SECURITY_CHECKLIST.md`](../SECURITY_CHECKLIST.md) sebagai baseline internal · [`DEPLOY.md`](DEPLOY.md) sebagai runbook draft · [`../HANDOVER.md`](../HANDOVER.md). Enam gate persetujuan production canonical hanya didefinisikan di [`../temuan2.md`](../temuan2.md).



---

## 1. Dua Tenant Demo

> **Catatan penamaan:** "Canada" & "Brazil" hanya label agar tidak terkena copyright.
> Isi datanya adalah data Indonesia (Pontianak & Surabaya).

| Tenant | Kode login | Sumber data | Modul aktif | Skala data |
|--------|-----------|-------------|-------------|------------|
| **PDAM Canada** | `pdam-canada` | PDAM Pontianak | **27 modul (LENGKAP)** | 4 zona, 10 pelanggan, 5 material |
| **PDAM Brazil** | `pdam-brazil` | PDAM Surabaya | **10 modul (SEBAGIAN)** | 2 zona, 5 pelanggan, 3 material |

**Modul aktif PDAM Brazil (partial):** `CORE, ZONE, WH, MTR, SRV, FIN+, CRM, BILL+, C360, APP`.
Sisanya (`AST, METX, FSM, PROC, MNT, HR, DMS, GIS, CC, BI, INT, CHEM, IOT, PROD, DIST, NRW, AI`)
berstatus `locked` — persis skenario PDAM kecil yang belum berlangganan modul enterprise.

---

## 2. Kredensial Login

Semua user demo memakai password: **`12345678`**

Email user demo mengikuti pola **`{role_code}@gmail.com`**. Karena email unik **per tenant**, email yang
sama dipakai di kedua PDAM; yang membedakan saat login adalah **kode PDAM**
(`pdam-canada` / `pdam-brazil`).

**Atribusi Admin PDAM per tenant:**

| PDAM | Kode login | Admin PDAM (email) | Password |
|------|-----------|--------------------|----------|
| **PDAM Canada** | `pdam-canada` | `admin_tenant@gmail.com` | `12345678` |
| **PDAM Brazil** | `pdam-brazil` | `admin_tenant@gmail.com` | `12345678` |

Tabel di bawah adalah daftar **lengkap** 35 role yang di-clone ke
**masing-masing** tenant (PDAM Canada & PDAM Brazil) oleh `RoleTemplateSeeder` + `TenantProvisioningService`.
Karena **email & password identik** di kedua tenant (unik per tenant, hanya dibedakan oleh `pdam_code`),
tabel berikut berlaku untuk **keduanya** (35 role × 2 tenant = 70 akun user).

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
Daftar role beserta contoh body login juga tersedia di [`backend/README.md`](README.md) Section 6 &
[`../README.md`](../README.md).

---

## 3. Rantai Data Terintegrasi

Seeder tidak menulis angka mentah ke tabel keuangan. Semua transaksi digerakkan lewat
**service asli** agar jurnal double-entry selalu balance:

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

| Metrik | PDAM Canada | PDAM Brazil |
|--------|------------:|------------:|
| Pelanggan | 10 | 5 |
| Tagihan | 30 (26 lunas, 4 nunggak) | 10 (8 lunas, 2 nunggak) |
| Jenis material | 5 | 3 |
| Total stok fisik | 1.800 unit | 700 unit |
| Jurnal (entries) | 58 | 20 |
| Modul aktif | **27** | **10** |
| Total DEBIT | Rp 5.183.181.700 | Rp 3.082.347.000 |
| Total KREDIT | Rp 5.183.181.700 | Rp 3.082.347.000 |
| **Neraca** | ✅ **BALANCE** | ✅ **BALANCE** |

Karena `SUM(DEBIT) == SUM(KREDIT)` di kedua tenant, **neraca (balance sheet) pasti seimbang**
dan laporan keuangan (Kas, Piutang, Persediaan, Pendapatan, Modal) langsung punya isi.

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
  kedua tenant, lengkap dengan tier bertingkat (0–10 / 10–20 / >20 m³).
- **Chart of Accounts** standar PDAM (Kas, Piutang, Persediaan, Aset Jaringan, Utang, Modal,
  Pendapatan Air/Pemasangan/Denda, Beban, + akun Aset Tetap & Penyusutan) juga dari
  `MasterFinanceSeeder`.

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

## 8. Seeder Modul Tambahan (16 seeder)

Selain seeder inti (Section 3), ada **16 seeder modul** yang mengisi master data fondasi,
modul aktif, dan modul enterprise. Semuanya **idempotent** dan **tenant-aware**: data enterprise
hanya diisi untuk tenant yang modulnya `active` (PDAM Canada full; PDAM Brazil hanya modul aktif).

Urutan eksekusi di `DatabaseSeeder.php` sudah menghormati dependency (mis. `AssetSeeder`
sebelum `MaintenanceSeeder`).

### 8.1 Fondasi & Modul Aktif (kedua tenant)

| Seeder | Tabel utama yang diisi | Catatan integrasi |
|--------|------------------------|-------------------|
| `AddressSeeder` | `provinces`, `cities`, `districts`, `villages`, `streets`, `meter_route_streets`, `meter_route_assignments` | Menyambungkan `customers.street_id` (15/15 valid, 0 orphan) + rute ↔ jalan ↔ petugas. Data alamat berjenjang (Provinsi→Kota→Kecamatan→Desa→Jalan) adalah **global reference** (`pdam_org_id` null) agar dipakai semua PDAM; tiap tenant bisa menambah data **privat** sendiri (`pdam_org_id` = tenant) melalui halaman **Master Alamat**. |
| `CrmDetailSeeder` | `complaint_tracks`, `customer_feedbacks` | Histori penanganan + feedback tiap pengaduan yang selesai |
| `CustomerLifecycleSeeder` | prospek, survey, jadwal instalasi, `disconnections`, `ownership_transfers` | Siklus hidup pelanggan (calon → aktif → isolir → balik nama) |
| `NotificationSeeder` | notifikasi in-app, `chats`, `chat_messages` | Komunikasi pelanggan ↔ petugas |
| `BillingExtraSeeder` | `installment_plans`, `installment_plan_bills`, `installment_schedules`, `bill_adjustments` | Cicilan tagihan + penyesuaian |

### 8.2 Modul Enterprise (utamanya PDAM Canada)

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

> **Catatan balance:** entri di `depreciation_entries`, maintenance, dan asset di-set
> `journal_entry_id = null` agar **tidak mengganggu neraca inti** yang sudah balance (Section 4).

### 8.4 Tabel yang sengaja dibiarkan kosong (⚪ wajar)

Bukan karena seeder gagal — memang tidak perlu di-seed karena terisi otomatis saat runtime:
`payment_gateway_logs`, `personal_access_tokens`, `password_reset_tokens`, `jobs`, `job_batches`,
`failed_jobs`, `cache`/`cache_locks`, `sessions`, `user_permissions`, `subscriptions`,
`tenant_module_overrides`, `price_change_logs`, `activity_logs`, `employees` (dipakai `hr_employees`).

---

**Yusril Eka Mahendra — 2026**


