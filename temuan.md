# 📋 TEMUAN — Audit Database & Rencana Perbaikan Seeder

> **Arsip snapshot 8 Juli 2026.** Semua jumlah, status, centang, dan kesimpulan di bawah bersifat historis serta tidak mencerminkan 60 migration, tenant/actor FK, artifact, atau hasil suite terbaru. Sumber status saat ini, termasuk enam gate production canonical, adalah [`temuan2.md`](temuan2.md).
>
> **Dokumen terkait:** [`README.md`](README.md) · [`PRD.md`](PRD.md) dan [`02_flow.md`](02_flow.md) sebagai target/desain · [`task.md`](task.md) sebagai arsip fase · [`SECURITY_CHECKLIST.md`](SECURITY_CHECKLIST.md) sebagai baseline internal · [`HANDOVER.md`](HANDOVER.md) · [`backend/DEPLOY.md`](backend/DEPLOY.md) sebagai runbook draft · [`backend/SEED_DATA.md`](backend/SEED_DATA.md)

**Tanggal audit:** 8 Juli 2026
**Database:** `pdam_saas` (MySQL 8.0.30)
**Metode:** Verifikasi langsung ke DB (FK constraint, orphan check, rantai integrasi, hitung baris per tabel)

---

## 1. Ringkasan Eksekutif

> **STATUS SETELAH PERBAIKAN (8 Juli 2026):** 16 seeder baru sudah dibuat & dijalankan.
> `php artisan migrate:fresh --seed` sukses. **146 dari 161 tabel kini terisi**, tersisa 15
> tabel yang memang wajar kosong (tabel sistem Laravel + event-driven). Lihat Section 3 & 4.


| Aspek | Status | Keterangan |
|-------|:------:|-----------|
| Relationship antar tabel | ✅ SEHAT | 117 FK constraint aktif, 0 orphan record |
| Integrasi rantai operasional | ✅ SEHAT | customers → meters → readings → bills → payments → journal tersambung |
| Neraca akuntansi | ✅ BALANCE | DEBIT = KREDIT = Rp 8.265.528.700 |
| Kelengkapan data (awal audit) | ⚠️ PARSIAL | 34 tabel terisi, 127 tabel kosong dari total 161 |
| Kelengkapan data (setelah perbaikan) | ✅ LENGKAP | **146 tabel terisi**, 15 sisanya wajar kosong |


**Kesimpulan awal:** Tidak ada seeder yang gagal jalan. Semua seeder existing (`PlatformAdmin`, `Module`, `RoleTemplate`, `Permission`, `DemoTenant`, `MasterFinance`, `OperationalData`) sudah tereksekusi benar. Tabel kosong adalah **modul enterprise yang belum dibuatkan seeder** — bukan seeder error, tapi memang belum ada seeder-nya — plus tabel event-driven yang wajar kosong.

**Kesimpulan akhir (✅ SELESAI):** Dibuat 16 seeder baru yang mengisi seluruh modul enterprise + master data + data komersial platform. Semua idempotent, tenant-aware (Canada full, Brazil hanya modul aktif), dan tidak mengganggu balance neraca (entri aset/penyusutan/maintenance memakai `journal_entry_id = null`).


---

## 2. Kondisi Relationship (Kesimpulan Historis, Sudah Disupersede)

- **117 foreign key constraint** aktif di level DB (`->constrained()` di migration). Relasi bukan sekadar kolom, tapi FK asli dengan `cascadeOnDelete` / `nullOnDelete`.
- **0 orphan record** pada seluruh tabel inti (`pdam_org_id`, `customer_id`, `zone_id`, `tariff_category_id`, `bill_id` semua punya induk valid).
- **Catatan historis yang tidak lagi berlaku:** rekomendasi lama untuk membiarkan `pdam_org_id` sebagai index biasa telah **disupersede**. Audit aktif menambahkan 97 tenant FK `RESTRICT`; lihat `temuan2.md` M-12.

---

## 3. Tabel Terisi — Inti Operasional ✅

**Kondisi awal audit: 34 tabel inti** (angka baris di bawah adalah snapshot awal).
**Kondisi setelah 16 seeder baru: 146 tabel terisi** dari 161 total.


| Tabel | Baris | Tabel | Baris |
|-------|------:|-------|------:|
| permissions | 201 | journal_entry_lines | 156 |
| role_permissions | 726 | tariff_tiers | 102 |
| roles | 102 | journal_entries | 78 |
| users | 68 | chart_of_accounts | 48 |
| subscription_modules | 54 | bills | 40 |
| meter_readings | 40 | tariff_categories | 34 |
| payments | 34 | modules | 27 |
| bill_items | 203 | customers | 15 |
| meters | 15 | material_stocks | 8 |
| materials | 8 | material_transactions | 8 |
| zones / warehouses / meter_routes | 6 | complaints | 6 |
| reading_periods | 5 | suppliers | 3 |
| pdam_organizations | 2 | billing_settings | 2 |

**Tabel enterprise yang KINI terisi** berkat 16 seeder baru (contoh): `provinces`, `districts`, `villages`, `fixed_assets` (4), `depreciation_entries` (9), `chemicals` (3), `chemical_stocks`, `chemical_usages`, `vendors` (3), `tenders`, `vendor_contracts`, `work_orders` (3), `work_order_logs` (6), `maintenance_schedules` (3), `hr_employees` (3), `attendances`, `leaves`, `payroll_components` (3), `documents`, `gis_features` (3), `gis_network_edges`, `integrations`, `api_keys`, `sensor_readings` (5), `production_logs` (3), `dma_zones`, `nrw_balances`, `ml_predictions` (2), `module_price_tiers` (78), `module_bundles` (3), `promos` (2), `saas_invoices` (6), dst.

---

## 4. Tabel Kosong — Klasifikasi & Rencana Seeder

> **✅ SEBAGIAN BESAR SUDAH DIISI.** Section ini adalah rencana awal. Setelah 16 seeder dibuat,
> hanya **15 tabel** yang masih kosong — semuanya di kategori ⚪ (wajar kosong). Lihat akhir section.

Dikelompokkan berdasarkan modul/domain, diurutkan berdasarkan prioritas perbaikan.


### 🔴 PRIORITAS TINGGI — Master data fondasi (dipakai fitur inti)

**A. Master Alamat Berjenjang ✅ SELESAI** — customers punya `street_id` tapi tabel jalan kosong
- `provinces` (2), `cities` (2), `districts` (6), `villages` (9), `streets` (18) — semua terisi
- **Seeder:** `AddressSeeder` — hierarki alamat Pontianak (Kalbar) & Surabaya (Jatim)
- **Verifikasi:** 15/15 customer punya `street_id` valid, 0 orphan; join provinces→cities→districts→villages→streets→customers berjalan mulus

**B. Master Wilayah GIS & Rute detail ✅ SELESAI**
- `meter_route_streets` (18), `meter_route_assignments` (6) — rute tersambung ke jalan & petugas
- **Seeder:** `AddressSeeder` (bagian `seedRouteStreets` & `seedRouteAssignments`)

### 🟠 PRIORITAS MENENGAH — Modul yang sudah aktif tapi datanya kosong

**C. Modul CRM (aktif di kedua tenant) ✅ SELESAI**
- `complaint_tracks` (12), `customer_feedbacks` (2)
- **Seeder:** `CrmDetailSeeder`


**D. Modul Survey & Lifecycle Pelanggan (SRV aktif) ✅ SELESAI**
- prospek, survey report, jadwal instalasi, status history, `disconnections`, reconnections, `ownership_transfers`
- **Seeder:** `CustomerLifecycleSeeder`

**E. Modul Meter Extended (METX — aktif di Canada) ✅ SELESAI**
- lifecycle events, meter stock, anomali, penggantian meter
- **Seeder:** `MeterExtendedSeeder`

**F. Notifikasi & Chat (APP aktif) ✅ SELESAI**
- notifikasi in-app, `chats` (2), `chat_messages` (4)
- **Seeder:** `NotificationSeeder`

**G. Angsuran & Penyesuaian Tagihan (BILL+ aktif) ✅ SELESAI**
- `installment_plans` (4), `installment_plan_bills` (4), `installment_schedules` (12), `bill_adjustments` (2)
- **Seeder:** `BillingExtraSeeder`

**H. Gudang lanjutan (WH aktif) ✅ SELESAI**
- `purchase_orders` (2), `stock_transfers` (2), `stock_transfer_items` (4), `stock_adjustments` (2), `repair_orders` (2)
- **Seeder:** `WarehouseAdvancedSeeder`


### 🟡 PRIORITAS RENDAH — Modul Enterprise (locked di Brazil, aktif di Canada)

**I. Keuangan lanjutan (FIN+) ✅ SELESAI**
- invoice AR/AP, `bank_accounts`, `budgets`, `tax_records`
- **Seeder:** `FinanceEnterpriseSeeder`

**J. Aset Tetap (AST — Canada full) ✅ SELESAI**
- `fixed_assets` (4), `depreciation_entries` (9), `asset_movements`, `asset_disposals`
- **Seeder:** `AssetSeeder`

**K. Bahan Kimia / Water Treatment (CHEM) ✅ SELESAI**
- `chemicals` (3), `chemical_stocks`, `chemical_usages` + rantai purchase/qc/opname
- **Seeder:** `ChemicalSeeder`

**L. Procurement (PROC) ✅ SELESAI**
- `vendors` (3), `tenders`, `vendor_contracts`, `purchase_requests`
- **Seeder:** `ProcurementSeeder`

**M. Field Service Management (FSM) ✅ SELESAI**
- `work_orders` (3), `work_order_logs` (6), `technician_locations`
- **Seeder:** `FieldServiceSeeder`

**N. Maintenance (MNT) ✅ SELESAI**
- `maintenance_schedules` (3), `maintenance_records` (3)
- **Seeder:** `MaintenanceSeeder`

**O. HR / Kepegawaian (HR) ✅ SELESAI**
- `hr_employees` (3), `attendances`, `leaves`, `payroll_components` (3) + struktur org, shift, training
- **Seeder:** `HrSeeder`

**P. DMS / GIS / Integrasi / Call Center (DMS, GIS, INT, CC) ✅ SELESAI**
- `documents`, `gis_features` (3), `gis_network_edges`, `integrations`, `api_keys`, `call_logs`
- **Seeder:** `DmsGisIntegrationSeeder`

**Q. Tier-3 Smart Utility (IOT, PROD, DIST, NRW, AI) ✅ SELESAI**
- `sensor_readings` (5), `production_logs` (3), `dma_zones`, `nrw_balances`, `ml_predictions` (2)
- **Seeder:** `SmartUtilitySeeder`

**R. Modul Bundle & Promo Platform ✅ SELESAI**
- `module_price_tiers` (78), `module_bundles` (3), `module_bundle_items`, `promos` (2), `promo_targets`, `promo_redemptions`, `saas_invoices` (6)
- **Seeder:** `PlatformCommerceSeeder`
- **Catatan:** `subscriptions`, `tenant_module_overrides`, `price_change_logs` sengaja dibiarkan kosong (kategori ⚪ — event-driven)


### ⚪ TIDAK PERLU SEEDER — Wajar kosong (event-driven / sistem)

- `payment_gateway_logs` (terisi saat ada transaksi gateway real)
- `personal_access_tokens` (terisi saat login API)
- `password_reset_tokens`, `jobs`, `job_batches`, `failed_jobs`, `cache_locks` (tabel sistem Laravel)
- `user_permissions` (override opsional, boleh kosong)
- `activity_logs` (audit trail — terisi otomatis saat ada aktivitas user di aplikasi, bukan via seeder)
- `subscriptions`, `tenant_module_overrides`, `price_change_logs` (event-driven komersial platform)
- `employees` (tidak dipakai — data pegawai memakai `hr_employees`)

**Verifikasi historis (langsung ke DB):** 161 tabel → **146 terisi, 15 kosong**. Pernyataan “tidak ada yang tertinggal” hanya menutup scope pekerjaan **16 seeder lama** pada snapshot 8 Juli, bukan seluruh aplikasi, audit aktif, atau kesiapan production.


---

## 5. Daftar Task Perbaikan

### Fase 1 — Fondasi (Prioritas Tinggi) ✅
- [x] Buat `AddressSeeder` (provinces → districts → villages) untuk Pontianak & Surabaya
- [x] Isi rute pembacaan meter (`reading_routes` / meter_routes) — sudah via OperationalDataSeeder

### Fase 2 — Modul Aktif (Prioritas Menengah) ✅
- [x] `CrmDetailSeeder` — histori & feedback pengaduan
- [x] `CustomerLifecycleSeeder` — prospek, survey, isolir, balik nama
- [x] `MeterExtendedSeeder` — lifecycle, stok, anomali meter (Canada)
- [x] `NotificationSeeder` — notifikasi in-app + chat
- [x] `BillingExtraSeeder` — cicilan (+lines) & penyesuaian tagihan
- [x] `WarehouseAdvancedSeeder` — purchase order, stock transfer, opname, repair order

### Fase 3 — Modul Enterprise (Prioritas Rendah, hanya untuk tenant Canada/full) ✅
- [x] `FinanceEnterpriseSeeder` — invoice, bank, budget, pajak
- [x] `AssetSeeder` — asset_categories, fixed_assets, depreciation, movements, disposals
- [x] `ChemicalSeeder` — chemicals + rantai purchase/receipt/qc/stock/usage/forecast/opname
- [x] `ProcurementSeeder` — vendors, tenders, bids, contracts, evaluations, purchase_requests
- [x] `FieldServiceSeeder` — work_orders, logs, technician_locations
- [x] `MaintenanceSeeder` — maintenance_schedules, maintenance_records
- [x] `HrSeeder` — struktur org, pegawai, shift, absensi, cuti, training, payroll
- [x] `DmsGisIntegrationSeeder` — documents, gis_features, gis_network_edges, integrations, api_keys, call_logs
- [x] `SmartUtilitySeeder` — sensor_readings, production_logs, dma_zones, distribution_readings, nrw_balances, ml_predictions
- [x] `PlatformCommerceSeeder` — module_price_tiers, module_bundles, promos, saas_invoices


### Fase 4 — Integrasi & Verifikasi
- [x] Daftarkan semua seeder baru di `DatabaseSeeder.php` dengan urutan dependency yang benar
- [x] Pastikan semua seeder **idempotent** (aman dijalankan ulang, cek existing sebelum insert)
- [x] Hormati **entitlement modul**: seeder enterprise hanya isi tenant Canada (full); modul non-inti dilewati untuk Brazil
- [x] Entri penyusutan/maintenance/asset di-set `journal_entry_id = null` agar neraca inti tetap balance
- [x] Verifikasi ulang: `php artisan migrate:fresh --seed` sukses (16 seeder baru jalan tanpa error)
- [x] Update `SEED_DATA.md` dengan cakupan data baru (Section 8 — 16 seeder tambahan)



---

## 6. Prinsip yang Harus Dijaga Saat Membuat Seeder

1. **Idempotent** — cek `if (Table::exists()) return;` sebelum insert, seperti `OperationalDataSeeder`.
2. **Tenant-aware** — set `TenantContext::set($orgId)` dan isi `pdam_org_id`.
3. **Hormati entitlement** — modul locked (mis. CHEM di Brazil) jangan diisi datanya.
4. **Lewat service untuk transaksi keuangan** — jangan tulis angka mentah ke `journal_entry_lines`; pakai `JournalService::record` supaya double-entry balance.
5. **Relasi valid** — selalu ambil ID induk yang sudah ada (jangan hardcode ID).

---

**Disusun untuk:** Yusril Eka Mahendra — 2026
