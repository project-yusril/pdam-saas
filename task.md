# 📋 TASK — Rencana Eksekusi Pembangunan Aplikasi PDAM
## Platform SaaS Manajemen PDAM (Modular Multi-Tenant) — **1 Modul = 1 Fase**

**Versi:** 3.4 (catatan implementasi historis) | **Diperbarui:** 15 Juli 2026
> **Status dokumen:** arsip implementasi per fase. Sumber status saat ini dan enam gate persetujuan production canonical adalah [`temuan2.md`](temuan2.md).
>
> **Dokumen terkait:** [`README.md`](README.md) · [`PRD.md`](PRD.md) dan [`02_flow.md`](02_flow.md) sebagai target/desain · [`temuan.md`](temuan.md) sebagai arsip audit lama · [`SECURITY_CHECKLIST.md`](SECURITY_CHECKLIST.md) sebagai baseline internal · [`HANDOVER.md`](HANDOVER.md) · [`backend/DEPLOY.md`](backend/DEPLOY.md) sebagai runbook draft · [`backend/SEED_DATA.md`](backend/SEED_DATA.md)

---

## ⚠️ INSTRUKSI WAJIB (BACA SEBELUM MULAI KERJA)

> **Status audit aktif dan prioritas perbaikan berada di `temuan2.md`.** Checklist lama pada dokumen ini
> merekam implementasi per fase dan tidak otomatis berarti fitur production-ready.
>
> **ATURAN STATUS:**
> - `[ ]` = **BELUM** selesai.
> - `🚧` = **SEBAGIAN** selesai (backend jadi tapi UI/integrasi eksternal belum, dst) — rinci di catatan baris.
> - `✅` = **SUDAH** selesai **dan terverifikasi** (lolos test / berjalan). Jangan ✅ kalau sekadar "kode ditulis".
>
> **ATURAN UPDATE (WAJIB):**
> - Begitu task selesai → **langsung ubah** `[ ]`/`🚧` jadi `✅` pada barisnya.
> - Fase besar baru ✅ **setelah semua sub-task-nya ✅**.
> - Kerjakan **berurutan** (Fase 0 → 28). Boleh loncat HANYA jika dependency modul sudah ✅ (lihat kolom Dependency).
> - Nemu task baru di tengah jalan → **tambahkan** ke fase yang sesuai dengan `[ ]`.
>
> **CARA BACA CEPAT:** cari `[ ]` pertama dari atas → itu task berikutnya.

---

## 🧭 PEMETAAN HISTORIS 27 MODUL → FASE

> Katalog terkonsolidasi = **27 modul** (1 CORE wajib + 26 add-on termasuk CHEM), sesuai seeder `modules`. Tiap modul dipetakan ke satu fase historis. **Fase 0** = fondasi platform SaaS (bukan modul). **Fase 28** = finalisasi aplikasi, bukan persetujuan production.

| Fase | Modul | Kode | Tier | Dep | Status |
|------|-------|------|------|-----|--------|
| 0 | Fondasi & Platform SaaS | — | — | — | ✅ |
| 1 | Paket Dasar (CIS/Billing/Payment/RBAC) | `CORE` | 1 | — | ✅ |
| 2 | Zone (Multi-wilayah) | `ZONE` | 1 | CORE | ✅ |
| 3 | Survey & Pemasangan Baru (OCR KTP) | `SRV` | 1 | CORE | ✅ |
| 4 | Baca Meter Digital + Rute + OCR | `MTR` | 1 | CORE | ✅ |
| 5 | Gudang & Inventory Multi-Lokasi | `WH` | 1 | CORE | ✅ |
| 6 | Meter Analytics (anomali/manipulasi) | `METX` | 1 | MTR | ✅ |
| 7 | Aset Tetap & Penyusutan | `AST` | 1 | CORE | ✅ |
| 8 | Keuangan Advance (Accurate-like) | `FIN+` | 1 | CORE | ✅ |
| 9 | Pengaduan & CRM (ticketing, SLA) | `CRM` | 1 | CORE | ✅ |
| 10 | Customer 360 View | `C360` | 1 | CORE | ✅ |
| 11 | Advanced Billing (adjustment/rekonsiliasi) | `BILL+` | 1 | CORE | ✅ |
| 12 | Portal & Mobile Pelanggan | `APP` | 1 | CORE | ✅ |
| 13 | Chemical Management (IPA) | `CHEM` | 2 | WH | ✅ |
| 14 | Procurement, Tender & Vendor | `PROC` | 2 | WH/FIN+ | ✅ |
| 15 | Field Service + Work Order | `FSM` | 2 | CORE | ✅ |
| 16 | Maintenance (preventive) | `MNT` | 2 | AST | ✅ |
| 17 | HR Management | `HR` | 2 | CORE | ✅ |
| 18 | Document Management System | `DMS` | 2 | CORE | ✅ |
| 19 | GIS Water Network | `GIS` | 2 | CORE | ✅ |
| 20 | Call Center (IVR, agent) | `CC` | 2 | CRM | ✅ |
| 21 | Business Intelligence + Report Builder | `BI` | 2 | CORE | ✅ |
| 22 | Integration Platform (gateway, WA/SMS) | `INT` | 2 | CORE | ✅ |
| 23 | Smart Meter / IoT (AMR/AMI) | `IOT` | 3 | MTR | ✅ ⚠️ hardware |
| 24 | Water Production (SCADA IPA) | `PROD` | 3 | — | ✅ migration + API ⚠️ SCADA |
| 25 | Distribution (DMA/pressure) | `DIST` | 3 | — | ✅ migration + API ⚠️ telemetri |
| 26 | Non-Revenue Water | `NRW` | 3 | DIST/IOT | ✅ migration + API |
| 27 | AI/ML (prediksi, chatbot, fraud) | `AI` | 3 | BI | ✅ skema migration ⚠️ butuh data |
| 28 | Finalisasi aplikasi (Marketplace, Dashboard, Hardening, dokumen deploy) | — | — | semua | ✅ aplikasi; bukan approval production |

---

## FASE 0 — FONDASI & PLATFORM SaaS

### 0.1 Repo & Environment
- ✅ Laravel + PHP 8.3 + MySQL
- ✅ Docker + Docker Compose (app, MySQL 8, Redis, Nginx, queue, scheduler)
- 🚧 GitHub Actions CI tersedia parsial; belum mengotomasi seluruh matriks MySQL/web/mobile/ML/security gate
- ✅ Branch & PR template

### 0.2 Database & Multi-Tenancy
- ✅ Trait `BelongsToTenant` + Middleware `SetTenant` + `TenantContext`
- ✅ Migration: `platform_admins`, `pdam_organizations`, `modules`, `subscriptions`, dll
- ✅ Seeder modules (27 modul + tier + dependency)

### 0.3 Auth & RBAC
- ✅ Sanctum (login/logout/refresh) + MFA TOTP untuk role sensitif
- ✅ RBAC: users, roles, permissions (120+), role_permissions
- ✅ 35 role + preset permission, termasuk `compliance_officer`
- ✅ Rate limit + lock akun (5x gagal = 15 menit) + TrackFailedLogin
- ✅ Middleware `CheckModuleAccess` + `CheckPermission`

### 0.4 Provisioning Tenant
- ✅ `TenantProvisioningService` — seed tenant baru otomatis
- ✅ Marketplace super-admin: activate/lock modul per tenant + catalog API

### 0.5 Kontrak API
- ✅ `ApiResponse` standar + exception handler terpusat + `/api/v1/`
- ✅ OpenAPI/Swagger + koleksi Postman berisi 95 request; bukan hitungan authoritative endpoint (registry aktual 358 route)
- ✅ Pagination, filter, sort (`ListQueryParams`)
- ✅ Upload file privat + signed URL (`FileUploadService`)
- ✅ Refresh token mobile

### 0.6 Scaffold Web Vue 3
- ✅ Vue 3 + Vite + Tailwind + PrimeVue + Pinia + Vue Router + axios
- ✅ Layout: SidebarLayout (menu modul) + AuthLayout
- ✅ Komponen shared: `StatCard`, `DataTable`, `PageHeader`, `LeafletMap`
- ✅ Halaman login web + Dashboard (Director, Finance, Warehouse, Technical)

### 0.7 Keamanan Fondasi
- ✅ Enkripsi at-rest (`Encrypted` cast) + `SensitiveData` masking
- ✅ Storage privat foto + signed URL
- ✅ `SecureHeaders` + `ContentSecurityPolicy` + `CorsWhitelist` + `WafMiddleware`
- ✅ 8 middleware chain aktif di production stack
- 🚧 Konfigurasi HTTPS/TLS tersedia; sertifikat dan server target belum diverifikasi

---

## FASE 1 — CORE (Paket Dasar)

### 1.1 Master Alamat
- ✅ Migration: provinces, cities, districts, villages, streets
- ✅ CRUD API berjenjang + dropdown endpoint
- ✅ UI web kelola alamat
- ✅ API mobile dropdown alamat (5 endpoint ringan — provinces/cities/districts/villages/streets)

### 1.2 Wilayah & Gudang
- ✅ Migration: zones, warehouses + auto-create buffer
- ✅ CRUD API + UI wilayah & gudang

### 1.3 Master Tarif
- ✅ Migration: tariff_categories, tariff_tiers, billing_settings
- ✅ 17 golongan + tier + abonemen + admin + denda — struktur, seeder, CRUD API

### 1.4 Pelanggan
- ✅ Migration: customers, customer_status_history
- ✅ CRUD API + nomor otomatis + input pemakaian manual

### 1.5 Generate Tagihan
- ✅ `BillingService`: kalkulasi tiered + komponen
- ✅ API generate batch + cron

### 1.6 Akuntansi Dasar
- ✅ Double-entry: COA, journal_entries, journal_entry_lines
- ✅ `JournalService` — validasi DEBIT=KREDIT + auto-jurnal
- ✅ 5 laporan API + lock periode
- ✅ Manual journal (`POST /journal/manual`)

### 1.7 Payment Gateway
- ✅ `PaymentGatewayInterface` + Midtrans Snap (QRIS, VA, e-wallet)
- ✅ Webhook SHA512 + idempotency + auto-jurnal
- ✅ Pembayaran tunai loket + kuitansi
- ✅ Payment reconciliation cron

### 1.8 Cicilan
- ✅ Migration + API draft/approve + bayar termin + auto-jurnal
- ✅ UI web cicilan list

### 1.9 Notifikasi & Cron
- ✅ `NotificationChannelService` — in-app/push/email
- ✅ Preferensi notifikasi + chat API
- ✅ Cron: overdue_bills, auto_isolir, billing_notification, installment_reminder

### 1.10 API Mobile Pelanggan
- ✅ Profil/dashboard + grafik konsumsi + notifikasi
- ✅ API riwayat pembayaran + receipt download

---

## FASE 2 — ZONE
- ✅ Migration zones (hirarki + is_main) + warehouses (main/buffer)
- ✅ CRUD API + UI + dashboard ringkas per wilayah
- ✅ Permission `zone.*`

---

## FASE 3 — SRV (Survey & Pemasangan)
- ✅ `KtpOcrService` — NIK/nama/TTL/alamat + confidence; endpoint multipart `/prospects/upload-ktp` diuji
- ✅ Migration: customer_prospects (encrypted NIK) + survey_reports
- ✅ API: daftar → survey → approve → bayar → pasang → aktif + lifecycle
- ✅ Cron `hublang_escalation`
- [✅] UI mobile survey_officer

---

## FASE 4 — MTR (Baca Meter)
- ✅ Migration: meter_routes, reading_periods, meter_readings
- ✅ API CRUD rute + assign + periode + pembacaan + OCR parsing
- ✅ Edge cases (rollover, konsumsi negatif) + verifikasi kantor
- ✅ Dashboard progress per rute
- [✅] Offline cache sync

---

## FASE 5 — WH (Gudang)
- ✅ `StockService` atomik (lock, no minus, audit log)
- ✅ PO multi-level approval + auto-jurnal (material = ASET)
- ✅ Transfer 3-level + stok keluar (pemasangan/repair) + opname
- ✅ Cron `stock_check` + dashboard stok multi-gudang
- ✅ UI per role warehouse (WarehouseDashboard.vue — stok, inventori, transfer)

---

## FASE 6 — METX (Meter Analytics)
- ✅ Migration meters + meter_anomalies + lifecycle events
- ✅ Detection rules: spike (>2x), drop (<50%), zero_streak, permanent_drop, category_mismatch, repeated_estimate
- ✅ Cron `scan-anomalies` + dashboard + rekomendasi ganti meter
- ✅ Source pipeline ML, feature alignment, artifact contract, dan empat fixture-validation artifact tersedia; model production-calibrated tetap membutuhkan data historis representatif

---

## FASE 7 — AST (Aset Tetap)
- ✅ Migration: asset_categories, fixed_assets, depreciation_entries, asset_movements, asset_disposals
- ✅ Depreciation: straight-line + declining balance + cron + auto-jurnal
- ✅ Capitalization jaringan + CIP → aktivasi
- ✅ Revaluation + stock opname + disposal + jurnal laba/rugi
- ✅ Dashboard + laporan + UI web

---

## FASE 8 — FIN+ (Keuangan Advance)
- ✅ AR: sales_invoices + items + aging report API
- ✅ AP: purchase_invoices + items
- ✅ TAX: tax_records + PPN/PPh21/PPh23 calculation + e-Faktur CSV
- ✅ BUDGET: budgets, budget_lines, projects, cost_centers, recurring_transactions
- ✅ BANK: bank_accounts, bank_reconciliations, currencies, bank transfer
- ✅ Inventory valuation FIFO/Average + balance sheet
- ✅ Recurring journal cron

---

## FASE 9 — CRM (Pengaduan)
- ✅ Migration: complaints, complaint_tracks, customer_feedbacks
- ✅ API: buat/assign/resolve tiket + SLA + dashboard
- ✅ Cron `sla_escalation`
- ✅ Permission `crm.*`

---

## FASE 10 — C360 (Customer 360)
- ✅ API agregasi (profil, tagihan, pembayaran, konsumsi, pengaduan, WO, cicilan)
- ✅ Timeline aktivitas + quick search
- ✅ Permission `c360.view`

---

## FASE 11 — BILL+ (Advanced Billing)
- ✅ Migration: bill_adjustments
- ✅ API adjustment + approve/reject + post + auto-jurnal koreksi
- ✅ Bill reconciliation API + audit log
- ✅ Permission `bill.adjust`, `bill.audit`

---

## FASE 12 — APP (Portal & Mobile)
- ✅ NOTIF: in-app + push (FCM) + email via `NotificationChannelService`
- ✅ CHAT: migration chats + chat_messages + API
- ✅ Preferensi notifikasi per user
- [✅] Flutter mobile pages (15 task)

---

## FASE 13–16 — CHEM, PROC, FSM, MNT
- ✅ CHEM: 11 tables + FEFO + QC test + usage + dashboard
- ✅ PROC: vendors, tenders, tender_bids, vendor_contracts + evaluasi + API
- ✅ FSM: work_orders + work_order_logs + technician_locations + API + dashboard
- ✅ MNT: maintenance_schedules + records + API + auto next_due_date

---

## FASE 17 — HR Management
- ✅ Migration: hr_employees, job_positions, organization_units, employee_grades (19 tables)
- ✅ API CRUD pegawai + dashboard
- ✅ Absensi: check-in/check-out + shifts + overtime + attendance API
- ✅ Cuti: leave_types + leaves + validasi kuota + approve + auto-mark attendance
- ✅ Payroll: engine + BPJS Kesehatan (1%) + JHT (2%) + JP (1%) + PPh21 + batch run + auto-jurnal
- ✅ Training: trainings + participants + certifications + expiring reminder + API
- ✅ Appraisal: performance_appraisals + KPI + approve/reject
- ✅ Kontrak: employee_contracts + terminating-soon + API
- ✅ Terminasi: employment_terminations + pesangon UU Cipta Kerja + auto-jurnal

---

## FASE 18–22 — DMS, GIS, CC, BI, INT
- ✅ DMS: documents + document_approvals + signed URL + approval API
- ✅ GIS: gis_features + GeoJSON API + CustomerMapStatusService + LeafletMap + pipes CRUD
- ✅ CC: call_logs + log/disposisi + create ticket + dashboard
- ✅ BI: KPI eksekutif + report builder API dengan allowlist identifier, permission, dan entitlement dataset
- ✅ INT: integrations + integration_logs + api_keys + webhook + test_send API

---

## FASE 23–27 — IOT, PROD, DIST, NRW, AI
- ✅ IOT: sensor_readings + ingest batch + dashboard + alert
- ✅ PROD: production_logs + metrics + dashboard
- ✅ DIST: distribution_readings + dma_zones + DMA dashboard
- ✅ NRW: nrw_balances + calculate + dashboard
- ✅ AI: ml_predictions skema migration

---

## FASE 28 — FINALISASI APLIKASI (BUKAN PERSETUJUAN PRODUCTION)

### 28.1 Marketplace & Promo
- ✅ Marketplace API: catalog + purchase + dependency enforcement
- ✅ Super-admin: kelola harga + price_change_logs + dashboard SaaS (MRR/ARR)
- ✅ Promo CRUD + redemption + target modules
- ✅ Bundle modul API CRUD + migration
- ✅ Cron `subscription_check`
- ✅ `MarketplaceController` — 8 endpoint platform

### 28.2 Dashboard & Standar UI
- ✅ Dashboard per role: Director, Finance, Warehouse, Technical (Vue pages)
- ✅ Komponen standar: StatCard, DataTable, PageHeader, LeafletMap
- ✅ Export laporan aktual memakai kontrak jujur CSV/HTML; native PDF/XLSX/DOC tetap roadmap PRD
- ✅ Route halaman web yang memiliki endpoint nyata terdaftar; route phantom prospek/installment dihapus
- ✅ Error API web ditampilkan melalui banner global; source JS/TS duplikat dihapus
- ✅ **Landing page publik** (`LandingView.vue`, route `/`) — hero + mock dashboard beranimasi, stats bar, section Tentang, Keunggulan (6 fitur), Modul (3 tier paket), Cara Kerja (4 langkah), CTA, footer. Tema biru muda/cyan lembut (identik air). Tombol CTA → `/login`. Catch-all router diarahkan ke `/` (bukan lagi `/dashboard`).
- ✅ **Panel Super Admin** (`PlatformLayout.vue`) — sidebar cerah berkelompok (UTAMA/SISTEM) + topbar (toggle, badge peran, notifikasi, profil admin, logout).
- ✅ **Marketplace Modul** (`ModuleCatalogView.vue`) — grid kartu 27 modul: ikon + badge tier (Core/Enterprise/Smart Utility), deskripsi lengkap tiap modul, kotak dependency ("butuh modul prasyarat aktif dulu") + relasi "menjadi prasyarat untuk", harga/tahun, filter per tier. Data dari `GET /platform/modules`.
- ✅ **Marketplace Tenant** (`/marketplace`) — katalog mengikuti session tenant, pricing/entitlement/dependency server-side; purchase idempotent membuat order pending + URL Snap dan settlement Midtrans signed+amount-matched baru mengaktifkan `subscription_modules`.
- ✅ **Scheduled Reports** — CRUD/run/history/download privat, dataset allowlist+reauth permission/module, CSV/HTML, timezone ke UTC, queue, dispatcher tiap menit, dan slot run unik.
- ✅ **Deskripsi modul** — `ModuleSeeder` kini mengisi kolom `description` untuk seluruh 27 modul (sebelumnya kosong → kartu tampil "Tidak ada deskripsi").

### 28.3 Keamanan & Hardening
- ✅ MFA TOTP + lock akun + rate-limit + throttleApi
- ✅ CSP + CORS whitelist + SecureHeaders + WAF (8 middleware)
- ✅ Enkripsi NIK + signed URL + input sanitizer
- ✅ Vite: sourcemap off prod, drop console/debugger
- 🚧 `SECURITY_CHECKLIST.md` — kontrol backend utama tersedia; gap aktif dilacak di `temuan2.md`
- ✅ UU PDP: export/delete/retention + purge dual-control dan audit khusus
- ✅ Data consent tracking
- ✅ Obfuscation Flutter + release SPKI pinning primary/backup fail-closed; endpoint TLS dan rotation drill wajib saat deployment
- ✅ Web memakai Sanctum session HttpOnly cookie + CSRF tanpa bearer auth di `localStorage`; mobile tetap token per `device_name`
- 🚧 Konfigurasi HTTPS/SSL tersedia di `docker/nginx.production.conf`; endpoint TLS production belum diverifikasi
- 🚧 Review source/security test internal tersedia; penetration test eksternal belum dilakukan

### 28.4 Testing & Deploy
- ✅ Backend test terbaru: SQLite **89/89 (664 assertions)**; MySQL **88 passed + 1 intentional SQLite-only skip (756 assertions), zero failures**, termasuk commerce, module gate,
  private file, BI/export security, OCR/mobile contract, offline sync, privacy purge, dan tenant isolation.
- ✅ Frontend: **5/5 Vitest** dan production build 322 modules lulus.
- ✅ Flutter: analyze no issues dan 44/44 tests lulus, termasuk sync failure/retry, auth, endpoint builders, KTP review, HTTPS/cleartext, pins, dan logging.
- ✅ ML: Python 3.11 x64 compile dan 25/25 tests lulus tanpa skip; no dummy, 5-tuple, feature alignment, model controls, CLI failure, dan artifact contract tercakup. Empat artifact fixture-validation tersedia lokal/ignored; artifact production-calibrated tetap gate data-quality.
- ✅ Test isolasi tenant (anti IDOR/Global Scope) — `TenantIsolationTest` (4 test: cross-tenant read, direct IDOR, create in wrong tenant, prospect isolation)
  > ⚠️ Catatan audit (6 Jul 2026): 5 test di `E2ECustomerJourneyTest` & `TenantIsolationTest` sebelumnya **tidak pernah jalan** (nama method tanpa prefix `test_` sehingga di-skip diam-diam oleh PHPUnit). Sudah diperbaiki + fixture dilengkapi (Zone, ReadingPeriod closed, Chart of Accounts) + assertion diselaraskan dengan kontrak API nyata (`bills/generate` butuh `customer_id/period/previous_reading/current_reading` → 201; pembayaran tunai langsung melunasi bill). Semua kini benar-benar dieksekusi & hijau.

- ✅ Load test config (`tests/load/k6-load-test.js` — ramp 10→50, p95<500ms, 7 endpoint)
- ✅ Serah terima & training checklist (`HANDOVER.md` — 9 section komplit)

---

## STATUS AUDIT TERKINI

| Status | Count | Keterangan |
|--------|-------|------------|
| ✅ | 39/39 temuan audit | Application-complete dan diverifikasi |
| ✅ | MySQL 8.4.9 audit | 60 migration + 24 seeder, rollback/migrate, metadata constraints, dan suite backend lulus |
| 🚧 | Gate production | TLS/pins, external pentest, operational deployment, dan data ML representatif tetap terbuka di luar temuan |

Prioritas berikutnya: tutup **enam gate persetujuan production canonical** yang didefinisikan tepat di `temuan2.md`. Queue/scheduler, integrasi eksternal, pemisahan seeder demo, serta observability/load tetap rekomendasi operasional tambahan, bukan tambahan gate canonical.

---

## 📂 DOKUMEN TERKAIT (semua terintegrasi)

| File | Isi |
|------|-----|
| `temuan2.md` | **SUMBER STATUS AUDIT AKTIF** — temuan, prioritas, dan verifikasi terbaru |
| `task.md` | Catatan implementasi historis semua fase |
| `README.md` | Quick start, arsitektur, daftar modul, testing, API |
| `SECURITY_CHECKLIST.md` | Baseline internal OWASP/ASVS; bukan compliance atau sertifikasi |
| `backend/DEPLOY.md` | Runbook draft; bukan persetujuan production |
| `HANDOVER.md` | Serah terima — kredensial, status modul, cron, kontak |
| `PRD.md` | Product Requirement Document v3.7 |
| `02_flow.md` | Flow diagram bisnis PDAM v2.0 |
| `backend/SEED_DATA.md` | 🌱 Data demo 2 tenant (Canada=Pontianak 27 modul, Brazil=Surabaya sebagian) — terintegrasi & neraca balance |
| `backend/start.bat` | Launcher backend Windows (klik 2x: menjalankan `php artisan serve`) |

| `backend/PDAM_SaaS_API.postman_collection.json` | 95 request Postman; bukan endpoint count authoritative |

| `backend/.env.production.example` | Template env production |
| `backend/docker/nginx.production.conf` | Nginx SSL production |
| `backend/docker/nginx.conf` | Nginx dev/local |
| `backend/docker/monitoring.conf` | Nginx monitoring proxy |
| `tests/load/k6-load-test.js` | Load test K6 — ramp 10→50 user |

**Yusril Eka Mahendra — 2026**
