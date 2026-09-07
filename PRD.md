# PRD — Platform SaaS Manajemen PDAM (Modular Multi-Tenant)

**Versi:** 3.7 (v3.5 + Chemical Management `CHEM`, pemecahan god-module jadi modul single-responsibility, pemetaan tegas Modul Default vs Berbayar saat provisioning tenant, standar UI/UX chart & tabel detail-menarik, template dashboard backend/frontend/mobile easy-to-use)

**Disiapkan oleh:** Zhou Shi | **Diperbarui:** Juli 2026
**Basis:** Rencana Aplikasi PDAM v2.3 + review teknis + modularisasi + keuangan Accurate-like + feedback tech stack & routing baca meter

> **Dokumen terkait:** [`temuan2.md`](temuan2.md) sebagai sumber status saat ini · [`README.md`](README.md) · [`02_flow.md`](02_flow.md) sebagai desain flow · [`task.md`](task.md) dan [`temuan.md`](temuan.md) sebagai arsip · [`HANDOVER.md`](HANDOVER.md) · [`SECURITY_CHECKLIST.md`](SECURITY_CHECKLIST.md) sebagai baseline internal · [`backend/DEPLOY.md`](backend/DEPLOY.md) sebagai runbook draft · [`backend/SEED_DATA.md`](backend/SEED_DATA.md)
>
> **Status implementasi:** PRD ini mendeskripsikan target produk, bukan bukti fitur selesai. Status audit
> aktual ada di [`temuan2.md`](temuan2.md); `task.md` adalah catatan implementasi historis. Data demo ada
> di [`backend/SEED_DATA.md`](backend/SEED_DATA.md).
>
> **Catatan implementasi (15 Juli 2026 — arsip snapshot):** 39/39 temuan audit aplikasi selesai, termasuk
> 60 migration/24 seeder + rollback/suite pada MySQL 8.4.9 disposable, tenant/actor FK, mobile 44/44, ML
> Python 3.11 25/25 fixture-validation. **Snapshot terkini (7 Sep 2026):** 65 migration/28 seeder/109 test/
> 368 endpoint (angka live: [`docs/COUNTS.json`](docs/COUNTS.json)); peta & status di
> [`temuan2.md`](temuan2.md) & [`docs/DOC_MAP.md`](docs/DOC_MAP.md). Ini tidak menjadikan produk
> production-ready: TLS/external/operational deployment gates dan model ML production-calibrated dari data
> historis representatif tetap menunggu bukti environment nyata (`temuan2.md` §13).


---

## Daftar Isi
1. [Ringkasan & Visi Produk](#1-ringkasan--visi-produk)
2. [Perubahan Kunci (v2.3 → v3.1)](#2-perubahan-kunci-v23--v31)
3. [Model Bisnis Modular (SaaS)](#3-model-bisnis-modular-saas)
4. [Katalog Modul & Paket Harga](#4-katalog-modul--paket-harga)
4.B [Modul Enterprise & Peta Fitur A–AC](#4b-modul-enterprise--peta-fitur-aac) ⭐baru
4.C [Katalog Lengkap 27 Modul + Harga (Konsolidasi)](#4c-katalog-lengkap-27-modul--harga-konsolidasi) ⭐baru
4.D [Super-Admin: Manajemen Harga + Lock/Unlock Modul](#4d-super-admin-manajemen-harga--lockunlock-modul) ⭐baru
4.E [Marketplace Modul + Promo/Diskon Musiman](#4e-marketplace-modul--promodiskon-musiman) ⭐baru
5. [Arsitektur Multi-Tenant](#5-arsitektur-multi-tenant)
6. [Aktor, Role & RBAC Dinamis](#6-aktor-role--rbac-dinamis)
7. [Alur Bisnis Inti (Revisi)](#7-alur-bisnis-inti-revisi)
8. [Struktur Alamat & Plotting Area Baca Meter](#8-struktur-alamat--plotting-area-baca-meter) ⭐baru
9. [Modul Keuangan: Basic vs Advance (ala Accurate)](#9-modul-keuangan-basic-vs-advance-ala-accurate)
10. [Logic Tarif & Penagihan](#10-logic-tarif--penagihan)
11. [Logic Baca Meter & Edge Cases](#11-logic-baca-meter--edge-cases)
12. [Logic Persediaan & Akuntansi Material](#12-logic-persediaan--akuntansi-material)
13. [Payment Gateway (Detail)](#13-payment-gateway-detail) ⭐baru
14. [Aplikasi Mobile Flutter (Role, Bottom Nav, Fitur Pelanggan)](#14-aplikasi-mobile-flutter-role-bottom-nav-fitur-pelanggan) ⭐baru
15. [Design System & UI/UX (Biru Lembut)](#15-design-system--uiux-biru-lembut) ⭐baru
15.B [Keamanan Aplikasi (Target Requirements)](#keamanan-aplikasi-target-requirements) 🔒⭐baru
16. [ERD Lengkap (Per Domain)](#16-erd-lengkap-per-domain)
17. [Matriks Modul → Tabel → Fitur](#17-matriks-modul--tabel--fitur)
18. [Cron Jobs & Automasi](#18-cron-jobs--automasi)
19. [Tech Stack (Final)](#19-tech-stack-final) ⭐diperbarui
20. [Struktur Proyek & Arsitektur Kode](#20-struktur-proyek--arsitektur-kode) ⭐baru
21. [Roadmap Pengembangan](#21-roadmap-pengembangan)
22. [Risiko & Mitigasi](#22-risiko--mitigasi)
23. [Hal yang Perlu Dikonfirmasi](#23-hal-yang-perlu-dikonfirmasi)

---

## 1. Ringkasan & Visi Produk

**Visi:** Platform SaaS multi-tenant paling lengkap untuk PDAM di Indonesia. Setiap PDAM (BUMD milik Pemda tiap kota/kabupaten) bisa berlangganan dan hanya membayar modul yang dipakai. Mencakup operasional, penagihan, akuntansi lengkap ala Accurate, gudang, survey, hingga baca meter digital dengan **plotting rute per petugas**.

**Prinsip produk:**
- **Multi-tenant:** satu instance melayani banyak PDAM, data terisolasi total.
- **Modular berbayar:** fitur dijual per modul. Paket Dasar wajib; sisanya add-on.
- **Paling lengkap:** menutup seluruh siklus PDAM dari calon pelanggan sampai laporan keuangan & pajak.
- **Mobile + Web:** Web (Laravel + Vue) untuk staf/manajemen; Mobile (Flutter) untuk pelanggan & petugas lapangan.

---

## 2. Perubahan Kunci (v2.3 → v3.1)

| # | Area | Perubahan |
|---|------|-----------|
| 1 | **Arsitektur** | Multi-tenant (`pdam_organizations`) + RBAC dinamis. |
| 2 | **Model bisnis** | SaaS modular berbayar per modul. |
| 3 | **Akuntansi material** | KOREKSI: material = Persediaan (ASET), bukan biaya langsung. |
| 4 | **Keuangan Advance** | Modul `FIN+` ala Accurate (AR/AP, fixed asset, pajak/e-Faktur, budgeting, dll). |
| 5 | **Tarif** | Tambah abonemen, pemeliharaan meter, admin, pemakaian minimum, denda. |
| 6 | **Baca meter** | Handling rollover, ganti meter, konsumsi negatif, true-up estimasi, anomali. |
| 7 | **Tabel hilang** | complaints, reading_periods, notifications, suppliers, materials, activity_logs, bill_items. |
| 8 | **Lifecycle pelanggan** | Isolir, sambung kembali, tutup sementara, balik nama, tunggakan. |
| 9 | **⭐ Struktur alamat** | Hierarki Provinsi → Kota → Kecamatan → Kelurahan → Jalan (master, dropdown bertingkat). |
| 10 | **⭐ Plotting area baca meter** | Rute/blok baca (`meter_routes`) — 1 petugas baca hanya area/jalan yang di-assign. |
| 11 | **⭐ Tech stack final** | Backend Laravel 13 + MySQL 8; Web Vue 3 + Vite + Tailwind + Pinia; Mobile Flutter clean architecture. |
| 12 | **⭐ Payment gateway** | Detail integrasi Midtrans (+alternatif) — VA, QRIS, e-wallet, webhook, idempotency, split 2 jenis pembayaran. |
| 13 | **⭐ Aplikasi Flutter** | Multi-role (pelanggan, baca meter, survey) + bottom nav per role + fitur pelanggan cek pemakaian/tagihan. |
| 14 | **⭐ Design system** | Tema terang, tone biru lembut khas PDAM. Token warna, komponen, tipografi. |
| 15 | **Keamanan** | Enkripsi PII/KTP, audit trail, idempotency webhook, lock periode. |

---

## 3. Model Bisnis Modular (SaaS)

PDAM berlangganan, memilih modul, bayar lisensi per modul per tahun. Semua modul terintegrasi dalam satu sistem.

```
┌─────────────────────────────────────────────────────────────┐
│ PAKET DASAR (WAJIB)                                           │
│  • Manajemen Pelanggan + Struktur Alamat & Golongan           │
│  • Penagihan Dasar (tiered + komponen tetap)                  │
│  • Pembayaran Online (Payment Gateway)                        │
│  • Keuangan Dasar (Kas/Bank, jurnal double-entry, 5 laporan)  │
│  • Manajemen Pengguna & RBAC dinamis                          │
└─────────────────────────────────────────────────────────────┘
                          +  MODUL ADD-ON (bayar terpisah)
  1. Gudang & Inventory (WH)         5. Pengaduan & CRM (CRM)
  2. Baca Meter Digital + Route (MTR) 6. Aset Tetap & Penyusutan (AST)
  3. Survey & Pemasangan (SRV)        7. Multi-Wilayah/Cabang (ZONE)
  4. Keuangan Advance/Accurate (FIN+) 8. Portal & Mobile Pelanggan (APP)
```

**Aturan aktivasi:** tiap modul punya dependency; nonaktif = data disimpan tapi menu & API modul 403. Entitlement dicek di **middleware Laravel** (bukan hanya UI Vue).

---

## 4. Katalog Modul & Paket Harga

> Harga placeholder ilustrasi, per tahun per tenant, bisa di-tier by jumlah pelanggan.

| Kode | Modul | Dependency | Harga/thn (ilustrasi) |
|------|-------|------------|----------------------|
| `CORE` | Paket Dasar (pelanggan, alamat, penagihan, bayar online, keuangan dasar, RBAC) | — | Rp 15–50 jt |
| `WH` | Gudang & Inventory multi-lokasi | CORE | Rp 8–15 jt |
| `MTR` | Baca Meter Digital + Plotting Route + OCR | CORE | Rp 10–20 jt |
| `SRV` | Survey & Pemasangan (OCR KTP, workflow) | CORE | Rp 8–15 jt |
| `FIN+` | Keuangan Advance (Accurate-like) ⭐ | CORE | Rp 20–40 jt |
| `CRM` | Pengaduan & CRM | CORE | Rp 5–10 jt |
| `AST` | Aset Tetap & Penyusutan | CORE (bundle FIN+) | Rp 6–12 jt |
| `ZONE` | Multi-Wilayah/Cabang | CORE | Rp 6–12 jt |
| `APP` | Portal & Mobile Pelanggan | CORE | Rp 8–15 jt |

---

<a id="4b-modul-enterprise--peta-fitur-aac"></a>
## 4.B Modul Enterprise & Peta Fitur A–AC ⭐

Kamu mengirim daftar fitur "PDAM Enterprise" (A–AC). Ini bagus banget sebagai **visi jangka panjang** — tapi jujur, ini bukan lagi "aplikasi manajemen PDAM", ini **platform enterprise water utility penuh** setara produk vendor besar (gabungan CIS + SCADA + AMI/IoT + GIS + ERP + BI + AI). Sebagian butuh **hardware, sensor, tim spesialis, dan integrasi pemerintah** — tidak realistis dikerjakan sekaligus oleh tim kecil.

Karena arsitektur kita **modular SaaS**, semua ambisi ini ditampung sebagai **modul add-on bertingkat (tier)**. PDAM beli sesuai kebutuhan & kemampuan. Aku kelompokkan jadi 3 tier realistis:

### Tier 1 — CORE OPERATIONS (realistis, prioritas bangun dulu)
Mayoritas sudah ada di PRD (CORE/WH/MTR/SRV/FIN+/CRM/APP/ZONE), ditambah beberapa modul baru yang wajar & tidak butuh hardware.

### Tier 2 — ENTERPRISE (butuh tim & waktu lebih, tanpa hardware khusus)
GIS, Field Service lengkap, Procurement/Tender, Asset & Maintenance, HR, Document Management, BI, Call Center, Integration Platform.

### Tier 3 — SMART UTILITY (butuh hardware/IoT/SCADA + investasi besar, roadmap jangka panjang)
IoT/Smart Meter (AMR/AMI), Produksi Air (SCADA IPA), Distribusi (DMA/pressure), NRW, AI/ML. **Ini tidak bisa dibangun hanya dengan software** — butuh sensor, gateway, dan integrasi lapangan.

---

### Peta Fitur A–AC → Modul & Tier

> Kolom status berikut adalah klasifikasi **target PRD** (cakupan lama/baru/parsial), bukan bukti implementasi. Bukti aktual mengikuti [`temuan2.md`](temuan2.md).

| Blok | Nama Blok Fitur | Modul (kode) | Tier | Status di PRD |
|------|-----------------|--------------|:----:|---------------|
| **A** | Customer Information System (CIS) + Customer 360 | `CORE` (+`C360`) | 1 | Sebagian ADA — Customer 360 ditambah |
| **B** | Customer Portal & Mobile App | `APP` | 1 | ADA (diperluas: WA/SMS/live-track/chat/loyalti) |
| **C** | Billing Management (dasar + advanced) | `CORE` (+`BILL+`) | 1 | ADA (billing+); prediction masuk `AI` |
| **D** | Payment Management | `CORE`/payment | 1 | ADA (tambah loket, minimarket, dispute, refund) |
| **E** | Meter Management + Analytics | `MTR` (+`METX`) | 1 | ADA (tambah meter DB lengkap + analytics anomali) |
| **F** | Smart Meter IoT (AMR/AMI, sensor, valve) | `IOT` | **3** | BARU — butuh hardware |
| **G** | Water Production (sumber air, IPA, pompa) | `PROD` | **3** | BARU — SCADA/telemetri |
| **H** | Distribution Management (zona, DMA, reservoir) | `DIST` | **3** | BARU — telemetri jaringan |
| **I** | NRW Management (kehilangan air, water balance) | `NRW` | **3** | BARU — tergantung DIST/IOT |
| **J** | GIS Water Network (peta pipa/valve/hydrant) | `GIS` | 2 | BARU — bisa software (PostGIS/Leaflet) |
| **K** | Field Service Management (teknisi mobile) | `FSM` | 2 | Sebagian (repair_orders) — diperluas |
| **L** | Work Order Management (WO, SLA, eskalasi) | `FSM` | 2 | BARU (bagian dari FSM) |
| **M** | Complaint / CRM (ticketing, SLA) | `CRM` | 1 | ADA (diperluas ticketing penuh) |
| **N** | Call Center (IVR, rekaman, agent) | `CC` | 2 | BARU |
| **O** | Inventory & Warehouse | `WH` | 1 | ADA |
| **P** | Procurement & Tender & Vendor | `PROC` | 2 | Sebagian (PO) — tender/vendor ditambah |
| **Q** | Asset Management (lifecycle, tracking) | `AST` | 2 | Sebagian (fixed asset) — diperluas |
| **R** | Maintenance (preventive/predictive) | `MNT` | 2 | BARU (predictive → butuh `AI`/IOT) |
| **S** | Finance & Accounting ERP | `FIN+` | 1 | ADA |
| **T** | HR Management (pegawai, payroll, absensi) | `HR` | 2 | BARU |
| **U** | Document Management System | `DMS` | 2 | BARU |
| **V** | Dashboard Executive Management | `CORE`/`BI` | 1–2 | Sebagian — KPI dashboard ditambah |
| **W** | Business Intelligence (data warehouse, report builder) | `BI` | 2 | BARU |
| **X** | Artificial Intelligence (prediksi, chatbot, fraud) | `AI` | **3** | BARU — butuh data matang dulu |
| **Y** | Security & Governance | `CORE` (bab Keamanan) | 1 | ADA |
| **Z** | Integration Platform (API gateway, gov, WA/SMS) | `INT` | 2 | Sebagian — diperluas |
| **AA** | Multi Cabang / Multi Wilayah | `ZONE` | 1 | ADA |
| **AB** | Reporting Super Lengkap (export PDF/Excel/CSV) | `CORE`/`BI` | 1–2 | Sebagian — report builder di `BI` |
| **AC** | Mobile Internal App (teknisi/supervisor/collector) | `FSM`/`APP` | 1–2 | Sebagian — role internal ditambah |

---

### Modul BARU yang ditambahkan ke katalog (di luar 9 modul awal)

| Kode | Modul | Tier | Dependency | Catatan realistis |
|------|-------|:----:|------------|--------------------|
| `C360` | Customer 360 View | 1 | CORE | Agregasi read-only dari data yang ada. Murah dibuat. |
| `BILL+` | Advanced Billing (adjustment, rekonsiliasi, audit) | 1 | CORE | Perluasan billing. Layak. |
| `METX` | Meter Analytics (deteksi rusak/manipulasi/anomali) | 1 | MTR | Rule-based dulu, ML kemudian. |
| `FSM` | Field Service + Work Order (teknisi mobile, WO, SLA) | 2 | CORE | Sangat berguna. Prioritaskan setelah Tier 1. |
| `PROC` | Procurement, Tender, Vendor Management | 2 | WH/FIN+ | Perluasan PO. |
| `MNT` | Maintenance (preventive; predictive butuh AI/IOT) | 2 | AST | Preventive dulu. |
| `HR` | HR Management (pegawai, absensi, payroll) | 2 | CORE | Bisa pakai modul HR terpisah / integrasi. |
| `DMS` | Document Management System | 2 | CORE | Arsip + approval + e-sign. |
| `GIS` | GIS Water Network | 2 | CORE | Software murni (PostGIS/Leaflet). |
| `CC` | Call Center (IVR, rekaman, agent dashboard) | 2 | CRM | Sering pakai vendor telephony (integrasi). |
| `BI` | Business Intelligence + Report Builder | 2 | CORE | Data warehouse + Metabase/dbt. |
| `INT` | Integration Platform (API gateway, gov, WA/SMS/email) | 2 | CORE | Fondasi integrasi lintas sistem. |
| `IOT` | Smart Meter / IoT (AMR/AMI, sensor, valve) | **3** | MTR | ⚠️ Butuh HARDWARE + gateway + tim IoT. |
| `PROD` | Water Production (sumber, IPA, pompa) | **3** | — | ⚠️ Butuh SCADA/telemetri. |
| `DIST` | Distribution (DMA, pressure, reservoir) | **3** | — | ⚠️ Butuh telemetri jaringan. |
| `NRW` | Non-Revenue Water Management | **3** | DIST/IOT | ⚠️ Bergantung data telemetri. |
| `AI` | AI/ML (prediksi tunggakan/konsumsi/bocor, chatbot, fraud) | **3** | BI + data historis | ⚠️ Butuh data matang & MLOps. |

---

### ⚠️ Catatan jujur soal scope (baca ini, bro)


1. **Jangan bangun semua sekaligus.** Kalau semua A–AC dikejar barengan, proyek nggak akan pernah selesai. Bangun **Tier 1 dulu sampai matang & dipakai PDAM nyata**, baru naik tier.
2. **Tier 3 (IoT/SCADA/Produksi/Distribusi/NRW/AI) bukan proyek software biasa.** Butuh sensor, meter pintar, gateway LoRa/NB-IoT, integrasi SCADA, dan tim elektro/instrumentasi. Software kita hanya *menampilkan & mengolah* data — datanya datang dari hardware yang harus dibeli & dipasang PDAM. Posisikan modul ini sebagai **"integration-ready"**: kita sediakan skema tabel + API ingest, implementasi hardware menyusul.
3. **AI (X) butuh data dulu.** Prediksi tunggakan/kebocoran/konsumsi baru akurat setelah ada **data historis 1–2 tahun**. Bangun fondasi data (BI/data warehouse) lebih dulu; AI belakangan.
4. **Beberapa blok lebih baik integrasi daripada bangun sendiri:** Call Center (pakai vendor telephony), HR/Payroll (pakai sistem HR yang ada), e-Faktur (DJP). Fokus energi di core domain air.
5. **Nilai jual terbesar tetap di Tier 1** (CIS, billing, payment, baca meter, keuangan). Itu yang bikin PDAM mau bayar dari hari pertama. Tier 2–3 adalah upsell.

> Ringkas: **semua ide kamu diterima dan dipetakan**, tapi diberi tier & urutan realistis supaya bisa benar-benar jadi, bukan cuma daftar keinginan. Tabel ERD untuk modul baru Tier 1–2 ada di Bagian 16.13.

---

<a id="4c-katalog-lengkap-27-modul--harga-konsolidasi"></a>
## 4.C Katalog Lengkap 27 Modul + Harga (Konsolidasi) ⭐

Ini jawaban langsung untuk pertanyaanmu: **semua modul dalam satu tabel + harganya**. Harga = ilustrasi per tenant per tahun (bisa di-tier berdasarkan jumlah pelanggan PDAM). Harga ini **bukan hardcode** — Super-Admin bisa ubah kapan saja (lihat Bagian 4.D).

**Total: 1 paket dasar wajib (`CORE`) + 26 modul add-on termasuk `CHEM` = 27 modul.** Tabel awal 26 modul di bawah dipertahankan urutannya dan `CHEM` ditambahkan sebagai modul ke-27.

### Tier 1 — CORE OPERATIONS (siap dibangun sekarang)
| # | Kode | Modul | Dependency | Harga/thn (ilustrasi) |
|---|------|-------|------------|----------------------|
| 1 | `CORE` | Paket Dasar (pelanggan, alamat, penagihan, bayar online, keuangan dasar, RBAC) | — (wajib) | Rp 15–50 jt |
| 2 | `WH` | Gudang & Inventory multi-lokasi | CORE | Rp 8–15 jt |
| 3 | `MTR` | Baca Meter Digital + Plotting Route + OCR | CORE | Rp 10–20 jt |
| 4 | `SRV` | Survey & Pemasangan (OCR KTP, workflow) | CORE | Rp 8–15 jt |
| 5 | `FIN+` | Keuangan Advance (Accurate-like) | CORE | Rp 20–40 jt |
| 6 | `CRM` | Pengaduan & CRM (ticketing, SLA) | CORE | Rp 5–10 jt |
| 7 | `AST` | Aset Tetap & Penyusutan | CORE | Rp 6–12 jt |
| 8 | `ZONE` | Multi-Wilayah / Cabang | CORE | Rp 6–12 jt |
| 9 | `APP` | Portal & Mobile Pelanggan | CORE | Rp 8–15 jt |
| 10 | `C360` | Customer 360 View | CORE | Rp 4–8 jt |
| 11 | `BILL+` | Advanced Billing (adjustment, rekonsiliasi, audit) | CORE | Rp 5–10 jt |
| 12 | `METX` | Meter Analytics (deteksi rusak/manipulasi/anomali) | MTR | Rp 5–10 jt |

### Tier 2 — ENTERPRISE (butuh tim & waktu lebih, tanpa hardware)
| # | Kode | Modul | Dependency | Harga/thn (ilustrasi) |
|---|------|-------|------------|----------------------|
| 13 | `FSM` | Field Service + Work Order (teknisi mobile, SLA) | CORE | Rp 12–25 jt |
| 14 | `PROC` | Procurement, Tender & Vendor Management | WH/FIN+ | Rp 8–18 jt |
| 15 | `MNT` | Maintenance (preventive) | AST | Rp 8–15 jt |
| 16 | `HR` | HR Management (pegawai, absensi, payroll) | CORE | Rp 10–20 jt |
| 17 | `DMS` | Document Management System | CORE | Rp 6–12 jt |
| 18 | `GIS` | GIS Water Network (peta pipa/valve/hydrant) | CORE | Rp 15–30 jt |
| 19 | `CC` | Call Center (IVR, rekaman, agent) | CRM | Rp 10–20 jt |
| 20 | `BI` | Business Intelligence + Report Builder | CORE | Rp 15–30 jt |
| 21 | `INT` | Integration Platform (API gateway, gov, WA/SMS/email) | CORE | Rp 10–25 jt |

### Tier 3 — SMART UTILITY (butuh hardware/IoT/SCADA + investasi besar)
| # | Kode | Modul | Dependency | Harga/thn (ilustrasi) |
|---|------|-------|------------|----------------------|
| 22 | `IOT` | Smart Meter / IoT (AMR/AMI, sensor, valve) | MTR | Rp 40–150 jt+ (⚠️ + hardware) |
| 23 | `PROD` | Water Production (sumber, IPA, pompa) | — | Rp 30–100 jt+ (⚠️ + SCADA) |
| 24 | `DIST` | Distribution (DMA, pressure, reservoir) | — | Rp 30–100 jt+ (⚠️ + telemetri) |
| 25 | `NRW` | Non-Revenue Water Management | DIST/IOT | Rp 20–50 jt+ |
| 26 | `AI` | AI/ML (prediksi, chatbot, fraud detection) | BI | Rp 25–60 jt+ |
| 27 | `CHEM` | Chemical Management (batch, QC/COA, FEFO, dosis, HPP) | WH | Rp 10–25 jt |

> **Model harga fleksibel yang direkomendasikan:** kombinasi **harga dasar per modul** + **komponen per jumlah pelanggan** (mis. +Rp X per 1.000 pelanggan/tahun). PDAM kecil (5rb pelanggan) bayar lebih murah dari PDAM besar (200rb pelanggan) untuk modul yang sama. Semua angka di atas diatur di tabel `modules.base_price_year` + `module_price_tiers` dan bisa diubah Super-Admin tanpa deploy ulang.

---

<a id="4d-super-admin-manajemen-harga--lockunlock-modul"></a>
## 4.D Super-Admin: Manajemen Harga + Lock/Unlock Modul ⭐

Bagian ini menjawab 3 permintaanmu: **(1)** Super-Admin ubah harga modul, **(2)** Super-Admin lock/unlock modul manual per tenant (jalur bayar cash, bukan cuma payment gateway), **(3)** Admin-Tenant buat user dengan permission granular per modul (CRUD vs Read-only).

### 4.D.1 Fitur Super-Admin — Kelola Harga Modul
Halaman **"Katalog Modul & Harga"** di dashboard Super-Admin:
- Tabel semua modul (27 modul) → kolom harga bisa di-edit inline.
- Set harga dasar (`base_price_year`) + tier harga per jumlah pelanggan (`module_price_tiers`).
- Set harga khusus per tenant (`tenant_module_overrides`) — mis. kasih diskon ke PDAM tertentu.
- Riwayat perubahan harga (audit) — siapa ubah, kapan, dari berapa ke berapa.
- Perubahan harga **tidak** mengubah kontrak yang sedang berjalan; berlaku untuk pembelian/perpanjangan berikutnya.

```
Tabel:
module_price_tiers (id, module_code, min_customers, max_customers, price_year)
tenant_module_overrides (id, pdam_org_id, module_code, custom_price, note, set_by, valid_until)
price_change_logs (id, module_code, old_price, new_price, changed_by, changed_at)
```

### 4.D.2 Fitur Super-Admin — Lock/Unlock Modul per Tenant (DUA JALUR)
Ini inti permintaanmu. Modul bisa diaktifkan lewat **DUA jalur**:

```
JALUR A — OTOMATIS via Payment Gateway (self-service)
  Admin-Tenant pilih modul di dashboard → bayar via Midtrans →
  webhook sukses → sistem auto-UNLOCK modul → status: active

JALUR B — MANUAL oleh Super-Admin (bayar cash/transfer langsung ke kamu) ⭐
  PDAM transfer/bayar tunai ke Super-Admin di luar sistem →
  Super-Admin buka dashboard → pilih tenant → toggle UNLOCK modul →
  isi: metode bayar (cash/transfer manual), nominal, bukti, masa berlaku →
  modul langsung aktif untuk tenant tsb → tercatat di audit
```

Halaman **"Kelola Langganan Tenant"** di Super-Admin:
- Pilih 1 PDAM → lihat daftar 27 modul dengan toggle **Lock 🔒 / Unlock 🔓**.
- Saat unlock manual: wajib isi `activation_method` (cash/manual_transfer/gateway/free_trial/promo), nominal dibayar, tanggal mulai & berakhir, catatan/bukti.
- Bisa **lock kembali** (mis. tenant nunggak / kontrak habis) → menu & API modul langsung 403, tapi data tetap aman (tidak dihapus).
- Set masa aktif (expiry) → cron `subscription_check` auto-lock saat lewat tempo (opsional kasih grace period).

```
Tabel (perluasan subscription_modules):
subscription_modules (id, pdam_org_id, module_code, status,
   activation_method, amount_paid, payment_proof_url,
   activated_by, activated_at, expires_at, locked_by, locked_at, note)
   status: locked | active | expired | trial
   activation_method: gateway | cash | manual_transfer | free_trial | promo
```

**Enforcement:** middleware `CheckModuleAccess` cek `subscription_modules.status = active` DAN belum expired. Kalau `locked`/`expired` → 403. Ini satu-satunya gerbang, jadi mau unlock lewat jalur A atau B hasilnya sama di sisi enforcement.

### 4.D.3 Fitur Admin-Tenant — Buat User + Permission Granular per Modul ⭐
Ini permintaanmu soal "buat user sampai detail CRUD vs Read-only, tapi hanya di modul yang dibeli".

**Konsep permission:** tiap modul punya set **permission granular** dengan pola `modul.resource.aksi`:
```
Contoh permission modul Gudang (WH):
  wh.material.view      wh.material.create   wh.material.update   wh.material.delete
  wh.stock.view         wh.stock.adjust      wh.transfer.create   wh.transfer.approve
  wh.po.view            wh.po.create         wh.po.approve
```
Aksi standar: `view` (Read), `create`, `update`, `delete` (= CRUD), plus aksi khusus (`approve`, `adjust`, `export`, dll).

**Alur Admin-Tenant buat user:**
```
1. Admin-Tenant → menu "Kelola User" → "Tambah User"
2. Isi data pegawai (nama, email, jabatan, zona)
3. Pilih ROLE (template siap pakai) ATAU custom:
     - Role default sudah ADA (Kepala Gudang, Staf Gudang, dll)
       dengan preset permission masuk akal.
4. Sistem HANYA menampilkan modul yang DIBELI/aktif tenant.
     → Kalau tenant tidak beli modul GIS, permission GIS tidak muncul sama sekali.
5. Di dalam modul yang aktif, Admin-Tenant centang permission per resource:
     Modul Gudang [aktif]:
       Material   : ☑ View  ☑ Create  ☑ Update  ☐ Delete
       Stok       : ☑ View  ☐ Adjust
       Transfer   : ☑ View  ☐ Create  ☐ Approve
     → user ini bisa CRUD material (tanpa hapus), tapi stok cuma bisa lihat.
6. Simpan → user login → hanya lihat menu & tombol sesuai permission.
```

**Contoh yang kamu sebut persis:** di modul Gudang, User A dikasih `wh.material.view/create/update/delete` (full CRUD) sedangkan User B cuma `wh.material.view` (read-only). Keduanya di modul yang sama, beda hak.

**Aturan penting:**
- **Default sudah ditetapkan:** tiap role bawaan punya preset permission (Admin-Tenant tinggal pakai, atau sesuaikan).
- **Dibatasi modul yang dibeli:** permission dari modul yang belum di-unlock **tidak muncul** di UI dan **ditolak** di backend (double gate: entitlement modul + permission RBAC).
- **Dua lapis pengecekan tiap request:** (1) `CheckModuleAccess` — tenant punya modul ini? (2) `CheckPermission` — user punya izin aksi ini? Dua-duanya harus lolos.

```
Tabel (perluasan RBAC Bagian 16.2):
permissions (id, code, module_code, resource, action, description)
   ← contoh: code="wh.material.create", module_code="WH", resource="material", action="create"
roles (id, pdam_org_id, name, is_system_default)  ← role bawaan + custom per tenant
role_permissions (role_id, permission_id)
user_roles (user_id, role_id)
user_permissions (user_id, permission_id, granted)  ← opsional: override per user (allow/deny spesifik)
```

**Kenapa desain ini bagus (rekomendasi):** granular per resource+action memberi Admin-Tenant kontrol detail tanpa nulis kode, preset default bikin gampang dipakai, dan pembatasan by-modul memastikan tenant tidak bisa kasih akses ke fitur yang belum dibayar. Enforcement di backend (bukan cuma sembunyikan tombol) mencegah bypass.

---

<a id="4e-marketplace-modul--promodiskon-musiman"></a>
## 4.E Marketplace Modul + Promo/Diskon Musiman ⭐

Dua permintaanmu: **(1)** satu menu "Marketplace Modul" tempat tenant PDAM lihat & beli modul, lengkap dengan info keterkaitan/dependency antar modul, dan **(2)** sistem promo/diskon musiman (Kemerdekaan, hari raya, dll) yang berlaku pada rentang tanggal tertentu. Sebelumnya **belum ada** — sekarang ditambahkan.

### 4.E.1 Menu "Marketplace Modul" (sisi Admin-Tenant)
Halaman etalase modul di dashboard tenant. Layout kartu (card grid), dikelompokkan per tier (CORE OPERATIONS / ENTERPRISE / SMART UTILITY).

**Tiap kartu modul menampilkan:**
- Nama, kode, ikon, tier, deskripsi singkat + daftar fitur utama.
- **Harga** (harga dasar / sesuai tier jumlah pelanggan tenant). Kalau ada promo aktif → tampil **harga coret + harga diskon + badge promo** (mis. "PROMO KEMERDEKAAN -17%").
- **Status modul untuk tenant ini:** `Aktif` (sudah punya) / `Tersedia` (bisa beli) / `Terkunci — butuh modul lain dulu`.
- **Info keterkaitan/integrasi** (ini yang kamu minta): badge "Butuh: CORE" atau "Terintegrasi dengan: MTR". Contoh: kartu `METX` (Meter Analytics) menampilkan **"⚠️ Perlu beli MTR dulu — modul ini membaca data dari Baca Meter Digital"**.
- Tombol: **Beli / Aktifkan** (→ payment gateway), **Coba (trial)** bila diaktifkan, atau **Ajukan ke Super-Admin** (untuk pembayaran manual/cash).

**Enforcement dependency (kaitan antar modul):**
```
Contoh: tenant mau beli METX (Meter Analytics)
  → sistem cek: METX.dependency = ["MTR"]
  → tenant belum punya MTR aktif?
       → tombol "Beli" METX DINONAKTIFKAN
       → tampil: "Beli & aktifkan MTR dulu — METX bekerja di atas data MTR"
       → opsi: "Beli sepaket (MTR + METX)" dengan 1 klik
  → tenant sudah punya MTR aktif? → METX bisa langsung dibeli
```
Aturan ini pakai kolom `modules.dependencies` (json) yang sudah ada. Marketplace membaca daftar itu untuk memvalidasi & menampilkan pesan. Contoh peta keterkaitan (dari katalog 4.C):
- `WH, MTR, SRV, FIN+, CRM, AST, ZONE, APP, C360, BILL+, HR, DMS, GIS, BI, INT` → butuh **CORE**.
- `METX` → butuh **MTR**.
- `PROC` → butuh **WH** & **FIN+**.
- `MNT` → butuh **AST**.
- `CC` → butuh **CRM**.
- `IOT` → butuh **MTR** (+hardware). `NRW` → butuh **DIST/IOT**. `AI` → butuh **BI**.

> Diagram dependency antar modul ini juga ditampilkan visual di Marketplace (mini graph) supaya PDAM paham "kalau mau X, harus punya Y".

### 4.E.2 Promo/Diskon Musiman (sisi Super-Admin)
Halaman **"Promo & Diskon"** di dashboard Super-Admin untuk bikin kampanye diskon berbasis **rentang tanggal**.

**Yang bisa diatur per promo:**
- Nama promo (mis. "Diskon HUT RI ke-81", "Promo Idul Fitri", "Promo Nataru").
- **Periode berlaku: `starts_at` s/d `ends_at`** (tanggal + jam mulai & berakhir). Ini jawaban langsung pertanyaanmu — promo punya tanggal dari–sampai yang jelas.
- Jenis diskon: **persen** (mis. 17%) atau **nominal** (mis. potong Rp 2 jt).
- Cakupan (`scope`): semua modul, tier tertentu (mis. semua Tier 1), atau modul tertentu (pilih spesifik).
- Target: semua tenant, atau tenant tertentu saja.
- Batas: maksimal pemakaian (kuota), maksimal diskon (cap nominal), kode promo (opsional, mis. `MERDEKA81`).
- Status: `scheduled` (belum mulai) / `active` (sedang berjalan) / `expired` (lewat) / `disabled` (dimatikan manual).

**Cara kerja (otomatis by tanggal):**
```
Cron harian `promo_activation` (00:05):
  → set promo yang starts_at ≤ hari ini ≤ ends_at  →  status = active
  → set promo yang ends_at < hari ini              →  status = expired

Saat tenant buka Marketplace / checkout:
  → sistem cari promo aktif yang cocok (scope modul + target tenant + tanggal)
  → hitung harga akhir = harga - diskon (persen/nominal), hormati cap & kuota
  → tampilkan harga coret + harga promo + badge + hitung mundur "berakhir dalam 3 hari"
  → saat bayar: catat promo_id & besar diskon di transaksi (untuk audit & laporan)
```

**Contoh nyata:**
```
Promo  : "Diskon HUT RI ke-81"
Periode: 2026-08-01 00:00  s/d  2026-08-31 23:59
Diskon : 17% (semangat kemerdekaan 🇮🇩)
Scope  : semua modul Tier 1
Target : semua tenant
Efek   : selama Agustus 2026, modul MTR yang Rp 15 jt → tampil Rp 12,45 jt di Marketplace.
         Lewat 31 Agustus → otomatis kembali harga normal (cron set expired).
```

### 4.E.3 Tabel pendukung (ERD — masuk Bagian 16.1)
```
promos (id, code, name, description,
   discount_type,            ← percent | fixed
   discount_value,           ← 17 (persen) atau 2000000 (nominal)
   scope_type,               ← all | tier | modules | tenant
   scope_value json,         ← mis. {"tier":1} atau {"modules":["MTR","METX"]}
   target_type,              ← all_tenants | specific
   max_discount_cap,         ← batas maksimal potongan (opsional)
   usage_quota, used_count,  ← batas & pemakaian
   starts_at, ends_at,       ← ⭐ RENTANG TANGGAL promo berlaku
   status,                   ← scheduled | active | expired | disabled
   created_by, created_at)

promo_targets (id, promo_id, pdam_org_id)     ← jika target specific
promo_redemptions (id, promo_id, pdam_org_id, module_code,
   original_price, discount_amount, final_price, redeemed_at)  ← audit pemakaian
```

**Cron baru (masuk Bagian 18):** `promo_activation` — 00:05 harian — aktif/kadaluarsa-kan promo sesuai `starts_at`/`ends_at`.

> Catatan jujur: diskon dihitung & divalidasi **di backend saat checkout** (bukan cuma tampilan di frontend), supaya tenant tidak bisa manipulasi harga. Frontend hanya menampilkan; harga final selalu dihitung ulang server.

---

## 4.F Pemecahan Modul (Single-Responsibility) + Modul Chemical (`CHEM`) ⭐

Permintaanmu: **modul yang kegemukan dipecah** biar tidak numpuk di satu tempat, nanti tinggal diintegrasikan. Setuju — ini prinsip *single responsibility* yang bikin tiap modul lebih ringan, gampang di-maintain, gampang dijual terpisah, dan gampang dikasih harga sendiri.

### 4.F.1 Prinsip pemecahan
- **1 modul = 1 domain jelas.** Jangan satu modul mengerjakan banyak urusan tak berkaitan.
- **Modul saling terhubung lewat kontrak (API/event), bukan lewat tabel campur aduk.** Tiap modul punya tabel & service sendiri; komunikasi antar modul via service interface / domain event.
- **Bisa dijual & diaktifkan terpisah.** Karena tiap potongan berdiri sendiri, Super-Admin bisa kasih harga & lock/unlock per potongan.

### 4.F.2 God-module yang dipecah
Beberapa modul awal terlalu besar. Dipecah begini (lama → jadi):

| Modul lama (gemuk) | Dipecah jadi | Alasan |
|--------------------|--------------|--------|
| **FIN+** (keuangan advance raksasa) | `FIN-AR` (Piutang/AR), `FIN-AP` (Utang/AP), `FIN-TAX` (Pajak/e-Faktur), `FIN-FA` (Fixed Asset — = `AST`), `FIN-BUD` (Budgeting), `FIN-BNK` (Kas/Bank & Rekonsiliasi) | AR/AP/pajak/aset/budget adalah domain berbeda; PDAM bisa beli sebagian saja |
| **WH** (gudang + pengadaan + perbaikan) | `WH` (stok & transfer), `PROC` (pengadaan/PO/tender/vendor), `REP` (repair/perbaikan pakai material) | Pengadaan & perbaikan beda alur dari stok murni |
| **MTR** (baca meter + analytics + master meter) | `MTR` (baca+rute), `METX` (analytics anomali), `MTR-AST` (master meter fisik) | Analytics & aset meter opsional |
| **CRM** (pengaduan + feedback + loyalti + call center) | `CRM` (tiket/pengaduan), `CC` (call center), `LOY` (feedback & loyalti) | Call center & loyalti opsional |
| **APP** (portal pelanggan + notifikasi + chat) | `APP` (portal & mobile), `NOTIF` (engine notifikasi lintas kanal), `CHAT` (live chat/WA) | Engine notifikasi dipakai semua modul → jadi layanan sendiri |

> Catatan: `AST` (Fixed Asset) = sama dengan `FIN-FA`. Untuk penjualan, tetap boleh **membundel** (mis. paket "FINANCE COMPLETE" = FIN-AR+AP+TAX+FA+BUD+BNK) — bundle hanya cara jual, di dalam sistem tetap modul-modul kecil yang independen.

**Efek ke `modules` & katalog:** kolom `dependencies` (json) di tiap sub-modul menunjuk ke induk/`CORE`. Marketplace (4.E) otomatis paham keterkaitan ini. Bundle disimpan di tabel baru `module_bundles` + `module_bundle_items`.

### 4.F.3 ⭐ Modul BARU: Chemical Management (`CHEM`)
Ini yang sempat hilang, sekarang dimasukkan lengkap. PDAM mengolah air baku jadi air bersih di IPA (Instalasi Pengolahan Air) — butuh **bahan kimia**: tawas/PAC (koagulan), kaporit/klorin (desinfektan), kapur/soda ash (pengatur pH), polymer (flokulan), reagen laboratorium. Ini **beda dari material gudang teknik** (pipa/valve) karena:
- **Punya tanggal kedaluwarsa** → wajib **FEFO** (First-Expired-First-Out), bukan cuma FIFO.
- **Dipakai berdasar dosis per m³ air** yang diolah → jadi komponen **HPP (Harga Pokok Produksi) air**.
- **Butuh QC/COA** (Certificate of Analysis) tiap batch dari supplier — ada barang lulus, ditolak, atau dikarantina.

**Fitur `CHEM` (Tier 2, dependency: `WH`; analisa dosis lebih akurat bila ada `PROD`):**
1. Master bahan kimia (jenis, satuan, dosis standar, batas aman, MSDS).
2. Supplier kimia + kontrak harga.
3. Purchase Request → PO kimia (nyambung ke `PROC`).
4. Penerimaan + **batch & expired date** + nomor COA.
5. **QC/lab test** tiap batch: `passed | rejected | quarantine`.
6. Gudang kimia **FEFO** (keluarkan yang paling dekat expired dulu).
7. Pemakaian harian di IPA + dosis per m³ air olahan.
8. Forecast kebutuhan kimia (berdasar tren produksi).
9. **Cost per m³ air** (biaya kimia masuk HPP produksi).
10. Dashboard: stok, mendekati expired, konsumsi vs produksi.
11. Stock opname kimia.

**Akuntansi (konsisten prinsip persediaan Bagian 12):**
```
① Beli kimia:        DEBIT Persediaan Bahan Kimia | KREDIT Kas/Bank atau Utang
② Dipakai di IPA:    DEBIT Beban Produksi Air     | KREDIT Persediaan Bahan Kimia
③ Rusak/expired:     DEBIT Kerugian Persediaan    | KREDIT Persediaan Bahan Kimia
```
ERD `CHEM` ada di Bagian 16.14.

> Catatan jujur: (1) **FEFO wajib** untuk kimia karena ada expired — beda dari material teknik biasa. (2) Analisa dosis & efisiensi paling akurat kalau modul `PROD` (produksi air) aktif; sementara `PROD` belum ada, volume air olahan bisa diinput manual harian dulu.

---

## 4.G ⭐ Modul DEFAULT (gratis saat provisioning) vs Modul BERBAYAR

Ini permintaan inti kamu: saat **PDAM baru bergabung (provisioning tenant)**, sistem otomatis kasih **beberapa modul default yang langsung bisa dipakai gratis** (bagian dari langganan dasar), sedangkan modul lanjutan **harus beli**. Aku petakan tegas.

### 4.G.1 Prinsip pemisahan
- **DEFAULT = yang tanpanya PDAM tidak bisa beroperasi sama sekali.** Kelola pelanggan, tagih air, terima pembayaran sambungan baru & tagihan bulanan, catat kas masuk/keluar dasar, kelola user. Ini **sudah termasuk** saat berlangganan platform (di dalam `CORE`).
- **BERBAYAR = yang menambah kemampuan/efisiensi/kelengkapan.** Gudang, keuangan lengkap (AR/AP/pajak/aset), survey digital, analytics, GIS, HR, dll. Beli sesuai kebutuhan.

Persis seperti contohmu: **keuangan biasa (terima bayar sambungan baru + tagihan bulanan) = DEFAULT**; tapi **keuangan lengkap (AR/AP, pajak, aset, budgeting) = BERBAYAR**. **Gudang = BERBAYAR.**

### 4.G.2 Peta tegas
```
✅ MODUL DEFAULT (otomatis aktif saat PDAM join — bagian dari CORE)
   ├── Manajemen Pelanggan + Struktur Alamat berjenjang + Golongan tarif
   ├── Penagihan Dasar (tiered + abonemen + admin + denda)
   ├── Pembayaran Online (payment gateway): sambungan baru + tagihan bulanan
   ├── Keuangan DASAR:
   │     • Kas/Bank masuk-keluar
   │     • Jurnal double-entry otomatis (bayar sambungan, bayar tagihan)
   │     • 5 laporan standar (Buku Besar, Neraca Saldo, L/R, Neraca, Arus Kas)
   │     • Piutang dasar (tagihan belum bayar)
   │     ❌ TIDAK termasuk: AR/AP lengkap, pajak/e-Faktur, fixed asset,
   │        budgeting, multi-currency → itu FIN+ (berbayar)
   ├── Manajemen User + RBAC dinamis (buat pegawai, atur role & permission)
   └── Notifikasi dasar (in-app + email)

💰 MODUL BERBAYAR (harus beli / di-unlock)
   Gudang (WH), Pengadaan (PROC), Baca Meter Digital+Rute (MTR),
   Meter Analytics (METX), Survey & Pemasangan (SRV), Chemical (CHEM),
   Keuangan Advance (FIN-AR/AP/TAX/FA/BUD/BNK), CRM/Call Center,
   GIS, HR, DMS, BI, Field Service (FSM), Portal Mobile Pelanggan lanjutan (APP),
   dan semua Tier 3 (IOT/PROD/DIST/NRW/AI)
```

> ⚠️ Keputusan yang perlu kamu tegaskan: **baca meter (MTR)** aku taruh sebagai **berbayar**. Alasannya baca meter digital + OCR + plotting rute itu fitur canggih (nilai jual tinggi). TAPI kalau kamu mau PDAM tetap bisa jalan minimal, kita bisa sediakan **input pemakaian manual sederhana di CORE** (petugas/admin ketik angka meter tanpa OCR/rute), lalu MTR berbayar untuk versi digital+rute+OCR. Ini bikin CORE tetap "bisa operasi penuh" tanpa maksa beli MTR. **Rekomendasiku: ya, sediakan input manual di CORE.**

### 4.G.3 Yang terjadi saat provisioning (otomatis)
Saat Super-Admin daftarkan PDAM baru, sistem seed otomatis:
```
1. Buat pdam_organizations (tenant baru)
2. AKTIFKAN modul default: subscription_modules untuk CORE
      → status=active, activation_method=free_default, amount_paid=0, expires_at=null
3. Seed data awal:
      • Chart of Accounts default (template PDAM) — bisa diubah tenant
      • Golongan tarif template (17 golongan Pontianak) — bisa diubah
      • Role bawaan + preset permission (Direktur, Kabag Keuangan, dll)
      • 1 akun Admin-Tenant pertama
4. Modul berbayar → status=locked (muncul di Marketplace, tinggal beli)
```
Kolom `modules.is_default` (boolean) menandai modul yang masuk paket dasar. `activation_method` dapat nilai baru `free_default`. Enforcement tetap lewat `CheckModuleAccess` (default = selalu lolos).

### 4.G.4 Ringkas keputusan
| Kebutuhan PDAM | Modul | Default/Berbayar |
|----------------|-------|:----------------:|
| Kelola pelanggan & alamat | CORE | ✅ Default |
| Tagih air (tiered) | CORE | ✅ Default |
| Terima bayar sambungan baru | CORE | ✅ Default |
| Terima bayar tagihan bulanan | CORE | ✅ Default |
| Kas/bank + 5 laporan dasar | CORE | ✅ Default |
| Kelola user + RBAC | CORE | ✅ Default |
| Input pemakaian manual | CORE | ✅ Default (rekomendasi) |
| Baca meter digital + OCR + rute | MTR | 💰 Berbayar |
| Gudang & stok | WH | 💰 Berbayar |
| Keuangan lengkap (AR/AP/pajak/aset) | FIN-* | 💰 Berbayar |
| Survey digital + OCR KTP | SRV | 💰 Berbayar |
| Bahan kimia IPA | CHEM | 💰 Berbayar |
| GIS, HR, BI, dll | (masing2) | 💰 Berbayar |

---

## 5. Arsitektur Multi-Tenant

**Strategi target:** shared MySQL database, `pdam_org_id` pada tabel bisnis tenant-owned yang relevan + **scoping di level aplikasi (Laravel Global Scope)**. Tabel global/platform, pivot, framework, dan audit tertentu merupakan pengecualian yang disengaja.

> Catatan: MySQL tidak punya Row-Level Security native seperti PostgreSQL. Runtime saat ini memakai **Laravel Global Scope** pada model tenant-owned (`BelongsToTenant`) dan middleware yang menurunkan tenant dari user Sanctum terautentikasi. Web memakai session/cookie stateful; mobile/API device memakai bearer token Sanctum per perangkat. Opsi database-per-tenant dapat dipertimbangkan untuk tenant besar.

```
pdam_organizations (TENANT)
├── provinces/cities/... (alamat)   ← master, bisa shared
├── zones → warehouses              ← modul ZONE/WH
├── users / employees / roles
├── customers / bills / payments
├── chart_of_accounts               ← COA unik per PDAM
├── tariff_categories               ← golongan unik per PDAM
└── meter_routes                    ← rute baca per petugas
```

**Entitlement middleware (Laravel):**
```
Request → auth Sanctum (session web atau bearer perangkat)
        → tenant diturunkan dari user terautentikasi
       → set tenant scope (current_org_id)
       → CheckModuleAccess middleware:
            punya langganan aktif modul X? ya→lanjut / tidak→403
```

### 5.4 Hierarki Tenant (Super-Admin → Admin-Tenant)
Sistem ini **multi-tenant** dengan dua level administrasi yang tegas:

```
👑 SUPER-ADMIN (Platform Owner — KAMU)
    │  Level tertinggi, lintas semua PDAM. Tidak terikat 1 tenant.
    │  Tanggung jawab:
    │   • Provisioning PDAM baru (buat tenant + seed COA default,
    │     golongan template, akun Admin-Tenant pertama)
    │   • Kelola langganan & tagihan SaaS (aktif/nonaktif modul per tenant)
    │   • Monitoring kesehatan sistem lintas tenant
    │   • Suspend/aktifkan tenant, lihat metrik agregat
    │   • TIDAK ikut campur operasional harian PDAM
    │
    └──► 🛡️ ADMIN-TENANT (Admin per PDAM)
              Level tertinggi DI DALAM satu PDAM. Scope: 1 pdam_org_id saja.
              Tanggung jawab:
               • Kelola user & role internal PDAM (buat akun pegawai,
                 assign role: Direktur, Keuangan, Gudang, dll)
               • Konfigurasi data master PDAM-nya (tarif, golongan,
                 wilayah, gudang, rute baca, alamat)
               • Atur modul yang aktif (dari yang sudah dilanggan)
               • Lihat semua data & laporan PDAM-nya
               • TIDAK bisa lihat data PDAM lain
                 │
                 └──► Role operasional (Direktur, Kabag Keuangan,
                      Staf, Kepala Gudang, Petugas, Pelanggan, dst)
```

**Prinsip pemisahan:**
- Super-Admin **tidak menyimpan data di dalam tenant** dan idealnya **tidak bisa membaca data bisnis sensitif** tenant (mis. detail pelanggan/keuangan) tanpa jejak audit — untuk menjaga privasi & kepercayaan antar PDAM. Aksesnya terbatas pada provisioning, billing, dan metrik agregat/teknis.
- Admin-Tenant **selalu ter-scope** ke `pdam_org_id`-nya sendiri (Global Scope) — tidak ada jalan untuk mengakses tenant lain.
- Setiap tindakan Super-Admin yang menyentuh tenant (mis. impersonate untuk support) **wajib tercatat di audit log** dan idealnya butuh izin/consent.

**Tabel pendukung:** `platform_admins` (super-admin, terpisah dari `users` tenant), `users.is_tenant_admin` (flag) atau role sistem `admin_tenant` per tenant.

---

## 6. Aktor, Role & RBAC Dinamis

RBAC berbasis tabel (`roles`, `permissions`, `role_permissions`, `user_roles`) per tenant — 1 user bisa banyak role, PDAM bisa bikin role custom.

| Aktor | Platform | Modul |
|-------|----------|-------|
| **Super-Admin** (Platform Owner) | Web | Lintas tenant (provisioning, billing, modul) |
| **Admin-Tenant** (Admin PDAM) | Web | Semua modul aktif tenant-nya (kelola user, master data) |
| Pelanggan | **Mobile (Flutter)** | APP |
| Petugas Baca Meter | **Mobile (Flutter)** | MTR |
| Petugas Survey | **Mobile (Flutter)** / Web | SRV |
| Kepala Survey | Web (Vue) | SRV |
| Direktur | Web | CORE + view all |
| Kabag Keuangan | Web | CORE, FIN+ |
| Staf Keuangan | Web | CORE, FIN+ |
| Kepala Teknik | Web | WH, SRV |
| Kepala Gudang | Web | WH |
| Kepala Hublang | Web | SRV, CRM |
| Bagian Baca Meter (Kantor) | Web | MTR (kelola rute, verifikasi) |



---

## 6.B Katalog Role Lengkap per Modul ⭐

Bagian ini menjawab: **tiap modul melahirkan role apa saja, siapa saja, dan tugasnya apa.** Ini jadi acuan tunggal sebelum flow di `02_flow.md`. Role bukan hardcode — ini **template role bawaan** (`roles.is_system_default = true`) yang otomatis di-seed saat modul diaktifkan; tiap PDAM boleh menyesuaikan/menambah role custom.

> Prinsip: 1 orang bisa memegang >1 role. PDAM kecil sering merangkap (mis. Kabag Keuangan merangkap Staf Keuangan). Role di sini adalah **fungsi/tanggung jawab**, bukan jumlah orang.

### 6.B.0 Registry Role (Master — semua role unik)

| Kode Role | Nama | Level | Platform | Modul Asal |
|-----------|------|-------|----------|------------|
| `super_admin` | Super-Admin (Platform Owner) | Platform | Web | PLATFORM |
| `admin_tenant` | Admin PDAM | Tenant | Web | PLATFORM/CORE |
| `director` | Direktur | Manajemen | Web | CORE |
| `finance_head` | Kabag Keuangan | Manajemen | Web | CORE |
| `finance_staff` | Staf Keuangan | Staf | Web | CORE |
| `cashier` | Kasir / Loket Pembayaran | Staf | Web | CORE |
| `customer_service` | Customer Service (CS) | Staf | Web | CORE/CRM |
| `customer` | Pelanggan | Eksternal | Mobile | CORE/APP |
| `hublang_head` | Kepala Hublang | Manajemen | Web | SRV |
| `survey_officer` | Petugas Survey | Lapangan | Mobile/Web | SRV |
| `survey_head` | Kepala Survey | Manajemen | Web | SRV |
| `technical_head` | Kepala Teknik | Manajemen | Web | SRV/WH |
| `installer_technician` | Teknisi Pemasangan | Lapangan | Mobile | SRV |
| `meter_office` | Koordinator Baca Meter (Kantor) | Manajemen | Web | MTR |
| `meter_officer` | Petugas Baca Meter | Lapangan | Mobile | MTR |
| `warehouse_head` | Kepala Gudang | Manajemen | Web | WH |
| `warehouse_staff` | Staf Gudang / Staf Wilayah | Staf | Web | WH |
| `procurement_staff` | Staf/Panitia Pengadaan | Staf | Web | PROC |
| `production_head` | Kepala Produksi / Operator IPA | Manajemen | Web | CHEM |
| `lab_analyst` | Analis Lab / QC | Staf | Web | CHEM |
| `accountant` | Akuntan / Staf Akuntansi | Staf | Web | FIN+ |
| `tax_officer` | Staf Pajak | Staf | Web | FIN+ |
| `asset_manager` | Manajer Aset | Manajemen | Web | AST |
| `maintenance_technician` | Teknisi Pemeliharaan | Lapangan | Mobile | MNT |
| `field_dispatcher` | Dispatcher Lapangan | Staf | Web | FSM |
| `field_supervisor` | Supervisor Lapangan | Manajemen | Web | FSM |
| `field_technician` | Teknisi Lapangan (WO) | Lapangan | Mobile | FSM |
| `hr_staff` | Staf HR | Staf | Web | HR |
| `hr_head` | Kepala HR | Manajemen | Web | HR |
| `gis_operator` | Operator GIS | Staf | Web | GIS |
| `dms_officer` | Petugas Arsip/Dokumen | Staf | Web | DMS |
| `call_agent` | Agent Call Center | Staf | Web | CC |
| `call_supervisor` | Supervisor Call Center | Manajemen | Web | CC |
| `data_analyst` | Analis Data / BI | Manajemen | Web | BI |

> **Total 35 role template**, termasuk `compliance_officer`. Tidak semua aktif sekaligus; role operasional hanya dipakai sesuai modul dan kebutuhan tenant.

---

### 6.B.1 PLATFORM — role & tugas
**`super_admin` (Super-Admin / Platform Owner):**
- Provisioning PDAM baru (buat tenant, seed data awal, akun Admin-Tenant pertama).
- Kelola katalog & harga modul, tier harga, override harga per tenant.
- Lock/unlock modul per tenant (jalur pembayaran manual/cash).
- Buat & kelola promo/diskon musiman.
- Monitor kesehatan sistem & metrik agregat lintas tenant; suspend/aktifkan tenant.
- TIDAK mengurus operasional harian PDAM; akses data tenant selalu ber-audit.

**`admin_tenant` (Admin PDAM):**
- Setup master data PDAM (alamat berjenjang, tarif, wilayah, gudang, rute).
- Kelola user & role internal + permission granular per modul.
- Beli/atur modul aktif via Marketplace.
- Lihat seluruh data & laporan PDAM-nya (ter-scope 1 tenant).

---

### 6.B.2 CORE (Paket Dasar) — role & tugas
**`director` (Direktur):** dashboard eksekutif (KPI, keuangan, operasional — view only); ACC pengadaan; ACC cicilan nominal besar; keputusan strategis.
**`finance_head` (Kabag Keuangan):** generate tagihan; konfigurasi tarif & komponen; validasi laporan keuangan; approve anggaran pengeluaran; buat/approve cicilan; hasilkan 5 laporan.
**`finance_staff` (Staf Keuangan):** eksekusi pengeluaran dana yang sudah di-approve; kelola buku besar; input jurnal manual; rekonsiliasi dasar.
**`cashier` (Kasir/Loket):** terima pembayaran tunai di loket (tagihan, biaya pemasangan, termin cicilan); cetak kuitansi; setor kas harian.
**`customer_service` (CS):** bantu pelanggan yang datang ke kantor (daftar sambungan baru, tanya tagihan, buat pengaduan); input data prospek manual.
**`customer` (Pelanggan):** lihat & bayar tagihan; pantau grafik pemakaian; ajukan sambungan baru; buat pengaduan; kelola profil.

---

### 6.B.3 SRV (Survey & Pemasangan) — role & tugas
**`hublang_head` (Kepala Hublang):** review pendaftaran calon pelanggan; assign Petugas Survey; monitor & eskalasi pembayaran pemasangan (Hubungi Manual); notifikasi pelanggan yang ditolak; konfirmasi pemasangan; kelola pengaduan.
**`survey_officer` (Petugas Survey):** datangi lokasi calon pelanggan; isi form survey (foto rumah, jarak pipa, kondisi, aksesibilitas, estimasi material, GPS); beri rekomendasi layak/tidak; re-survey bila diminta.
**`survey_head` (Kepala Survey):** review laporan survey; putuskan Approve / Reject / Re-Survey dengan catatan.
**`technical_head` (Kepala Teknik):** buat order material pemasangan; jadwalkan pemasangan & assign teknisi; validasi pengadaan (tembusan ke Direktur); konfirmasi penerimaan material.
**`installer_technician` (Teknisi Pemasangan):** laksanakan pemasangan di lapangan; catat material terpakai; update status `installed` + foto hasil.

---

### 6.B.4 MTR (Baca Meter) — role & tugas
**`meter_office` (Koordinator Baca Meter/Kantor):** buka/tutup periode baca; CRUD rute; assign jalan ke rute; assign petugas ke rute (penugasan tetap); verifikasi pembacaan (anomali/flag/koreksi); monitor progress per rute; deteksi jalan tanpa rute.
**`meter_officer` (Petugas Baca Meter):** baca meter hanya di rute yang di-assign; foto rumah + foto meter; OCR/konfirmasi/koreksi; input estimasi + alasan bila tak terbaca; submit (offline→sync).

---

### 6.B.5 WH (Gudang) — role & tugas
**`warehouse_head` (Kepala Gudang):** dashboard stok multi-gudang; CRUD material; ajukan PO; putuskan sumber material 3-level (buffer→lateral→utama); buat transfer order; review material masuk; update stok; stock opname.
**`warehouse_staff` (Staf Gudang/Wilayah):** konfirmasi penerimaan transfer di gudang/buffer wilayah; catat selisih rusak; keluarkan material untuk pemasangan/perbaikan; bantu opname fisik.

---

### 6.B.6 PROC (Pengadaan) — role & tugas
**`procurement_staff` (Staf/Panitia Pengadaan):** kelola vendor & kontrak; buat tender; kelola bid vendor; konversi PR→PO; evaluasi vendor.
> Approval PO tetap melibatkan `technical_head` → `director` → `finance_head` → `finance_staff` (lintas modul).

---

### 6.B.7 CHEM (Bahan Kimia/Produksi) — role & tugas
**`production_head` (Kepala Produksi/Operator IPA):** kelola master kimia & dosis standar; ajukan PR kimia; catat pemakaian harian di IPA + volume air; kelola stok FEFO; forecast kebutuhan; pantau HPP air.
**`lab_analyst` (Analis Lab/QC):** uji QC tiap batch penerimaan kimia; tetapkan hasil `passed/quarantine/rejected`; catat parameter uji & COA.

---

### 6.B.8 FIN+ (Keuangan Advance) — role & tugas
**`accountant` (Akuntan):** kelola AR/AP, faktur, aging; jurnal penyesuaian; rekonsiliasi bank; tutup & lock periode; laporan lanjutan/konsolidasi.
**`tax_officer` (Staf Pajak):** kelola PPN/PPh; ekspor e-Faktur (CSV/XML DJP); helper SPT Masa.
> `asset_manager` (register aset & penyusutan) — lihat AST; bisa dibundel di FIN+.

---

### 6.B.9 CRM & CC — role & tugas
**`customer_service` (CS):** terima & assign tiket pengaduan; update status; komunikasi ke pelanggan. (dipakai bersama CORE)
**`call_agent` (Agent Call Center):** terima/lakukan panggilan; catat call log & disposisi; buat tiket dari telepon.
**`call_supervisor` (Supervisor Call Center):** monitor SLA & performa agent; eskalasi; rekaman panggilan.

---

### 6.B.10 AST & MNT (Aset & Pemeliharaan) — role & tugas
**`asset_manager` (Manajer Aset):** register aset (IPA, pompa, reservoir, kendaraan, jaringan); jadwal pemeliharaan preventive; kelola penyusutan; disposal aset.
**`maintenance_technician` (Teknisi Pemeliharaan):** eksekusi jadwal maintenance; catat sparepart terpakai & hasil; lapor kondisi aset.

---

### 6.B.11 FSM (Field Service & Work Order) — role & tugas
**`field_dispatcher` (Dispatcher):** buat & assign Work Order (bocor, ganti meter, isolir, sambung); atur prioritas & SLA.
**`field_supervisor` (Supervisor Lapangan):** monitor WO real-time (peta, GPS teknisi); approve/eskalasi; evaluasi SLA.
**`field_technician` (Teknisi Lapangan):** terima WO di mobile; kerjakan; update progress + foto + e-sign pelanggan; pakai material.

---

### 6.B.12 HR — role & tugas
**`hr_head` (Kepala HR):** kebijakan SDM; approve cuti; kelola penggajian & appraisal.
**`hr_staff` (Staf HR):** kelola data pegawai; absensi & shift; hitung payroll; kelola pelatihan/sertifikasi.

---

### 6.B.13 GIS, DMS, BI — role & tugas
**`gis_operator` (Operator GIS):** pemetaan aset jaringan (pipa/valve/hydrant); update geometri & atribut; kelola insiden spasial.
**`dms_officer` (Petugas Arsip):** kelola dokumen (surat masuk/keluar, kontrak, SOP); versi & approval; e-sign.
**`data_analyst` (Analis Data/BI):** bangun dashboard & report builder; analisa tren; sajikan KPI ke manajemen.

---

### 6.B.14 Ringkasan Role Minimum vs Lengkap
| Skenario PDAM | Modul aktif | Jumlah role | Role inti |
|---------------|-------------|:-----------:|-----------|
| **Minimum** (baru gabung) | CORE | ~8 | admin_tenant, director, finance_head, finance_staff, cashier, customer_service, customer, (+meter manual) |
| **Standar** | CORE+SRV+MTR+WH+CRM | ~18 | + hublang, survey×2, technical, installer, meter×2, warehouse×2, CS |
| **Lengkap/Enterprise** | semua | ~34 | + produksi, lab, akuntan, pajak, aset, maintenance, FSM×3, HR×2, GIS, DMS, CC×2, BI |

---

## 7. Alur Bisnis Inti (Revisi)


### 7.1 Siklus penuh
```
[SRV] Daftar (OCR KTP + alamat berjenjang) → Survey → Approval Kepala Survey
   ↓
[CORE] Notif biaya pemasangan → Bayar (payment gateway, timer eskalasi)
   ↓  Jurnal: DEBIT Kas/Bank | KREDIT Pendapatan Pemasangan
[WH] Ambil material (buffer→lateral→gudang utama)
   ↓  Jurnal saat dipakai: DEBIT Aset Jaringan/Beban | KREDIT Persediaan
[SRV] Pemasangan → aktif (meter 0 m³) → pelanggan di-assign ke meter_route sesuai jalannya
   ↓
[MTR] Petugas baca meter HANYA di route-nya → OCR + foto rumah → verifikasi kantor
   ↓
[CORE] Generate tagihan (tiered + abonemen + admin + pemeliharaan meter)
   ↓  Jurnal: DEBIT Piutang | KREDIT Pendapatan Air (+ komponen)
[CORE] Notif (tgl 23–25) → Bayar (payment gateway)
   ↓  Jurnal: DEBIT Kas/Bank | KREDIT Piutang
[CORE/FIN+] Laporan keuangan
```

### 7.2 Lifecycle pelanggan
`active → suspended_temporary | isolated → reconnected | ownership_transfer | terminated`. Tabel: `customer_status_history`, `disconnections`, `reconnections`, `ownership_transfers`, `installment_plans`.

### 7.3 Perbaikan/kebocoran
`repair_orders` → jadwal teknisi → pakai material gudang → selesai.

---

<a id="8-struktur-alamat--plotting-area-baca-meter"></a>
## 8. Struktur Alamat & Plotting Area Baca Meter ⭐

Ini bagian yang kamu minta khusus. Dua hal: **(A)** alamat pelanggan harus terstruktur berjenjang, **(B)** petugas baca meter di-plot per area/jalan.

### 8.1 (A) Struktur alamat berjenjang
Saat pendaftaran/pemasangan baru, alamat **tidak diketik bebas** tapi dipilih berjenjang (dropdown bertingkat) + detail jalan:

```
Provinsi  →  Kota/Kabupaten  →  Kecamatan  →  Kelurahan/Desa  →  Jalan (street)  →  No. Rumah / RT / RW
```

Master data alamat **diinput manual** oleh Admin-Tenant (bukan import dataset Kemendagri) — tiap PDAM mendata sendiri provinsi/kota/kecamatan/kelurahan/jalan di wilayah layanannya, sehingga hanya berisi area yang relevan (lebih ringkas & terkontrol):
```
provinces (id, code, name)
cities (id, province_id, name, type: kota|kabupaten)
districts (id, city_id, name)                 ← kecamatan
villages (id, district_id, name)              ← kelurahan/desa
streets (id, village_id, name)                ← jalan (mis. "Jl. Ahmad Yani")
```
> Keputusan: **input manual**. Admin-Tenant menambah master jalan sebelum/seiring pendaftaran pelanggan. Bisa ditambah kapan saja tanpa ubah kode.

Pelanggan menyimpan FK sampai `street_id` + `house_number`, `rt`, `rw`, dan **koordinat GPS** (`latitude`, `longitude`) yang diambil petugas survey untuk akurasi lokasi meter.

**Two-Stage GPS (penandaan lokasi rumah dua tahap):** koordinat rumah tidak diketik bebas tapi dipilih di peta dalam dua tahap agar akurat:
- **Tahap 1 (saat calon pelanggan daftar):** tombol "Tandai Lokasi Rumah" -> peta terbuka (Leaflet/Google Maps) -> sistem auto-capture GPS HP pelanggan sebagai titik AWAL (dugaan) -> pelanggan bisa GESER pin bila meleset. Disimpan: latitude, longitude, location_source=customer_pin, location_accuracy (meter).
- **Tahap 2 (saat petugas survey ke lapangan):** peta menampilkan pin pelanggan sebagai dugaan -> karena surveyor FISIK di depan rumah, tekan "Ambil Titik GPS Sekarang" (akurat) ATAU geser pin manual bila GPS meleset (kolong/pohon/sinyal). Koordinat di-OVERRIDE, disimpan location_source=surveyor_verified -> INI koordinat FINAL yang dipakai peta GIS, plotting rute baca meter, dan node rumah berwarna.

Manfaat: titik pelanggan hanya tebakan awal, titik surveyor jadi kebenaran (diambil di lokasi fisik); location_source bikin bisa di-audit mana lokasi yang belum diverifikasi. Konsep ini nyambung dengan aturan RE-SURVEY (koordinat salah -> dikembalikan ke surveyor).

**Manfaat:** data rapi, bisa difilter per wilayah, dan jadi dasar plotting rute baca meter.

### 8.2 (B) Plotting area/route baca meter
Konsep: petugas baca meter tidak baca acak. Mereka di-assign ke **rute (route/blok)** yang berisi kumpulan jalan/area tertentu.

```
Contoh:
  Route "AY-01" (Jl. Ahmad Yani + Jl. Ahmad Yani Gg. 1–5)
     → Petugas A (bulan ini)
  Route "SDM-01" (Jl. Sudirman + sekitarnya)
     → Petugas B

Pak Udin tinggal di Jl. Sudirman  → masuk Route SDM-01 → dibaca Petugas B
Bu Ani tinggal di Jl. Ahmad Yani  → masuk Route AY-01  → dibaca Petugas A
```

**Model data:**
```
meter_routes                 ← definisi rute/blok baca
├── id, pdam_org_id, code (AY-01), name, zone_id (opsional)
├── description, is_active

meter_route_streets          ← jalan mana saja masuk rute ini (M:N)
├── route_id (FK), street_id (FK)
    (1 rute bisa banyak jalan; 1 jalan idealnya 1 rute agar tidak tumpang tindih)

meter_route_assignments      ← petugas mana pegang rute apa
├── id, route_id (FK), officer_id (FK → users/employees)
├── effective_from, assigned_by, assigned_at, is_active
    (KEPUTUSAN: penugasan bersifat TETAP — 1 rute dipegang petugas yang sama
     terus-menerus, tidak dirotasi otomatis. Perubahan hanya manual oleh
     Admin-Tenant/Bagian Baca Meter bila ada mutasi/petugas keluar.)
```

**Cara pelanggan masuk ke rute:**
- Otomatis: pelanggan → punya `street_id` → jalan itu ada di `meter_route_streets` → berarti masuk route tsb.
- Bisa juga override manual (mis. rumah di ujung jalan lebih dekat rute lain) via kolom `override_route_id` di `customers`.

**Alur harian petugas (Flutter):**
```
Login petugas baca meter
  → sistem ambil route yang di-assign ke dia bulan ini
  → tampilkan "Daftar Tugas Hari Ini" = semua pelanggan aktif di route tsb
     yang belum dibaca periode ini
  → urutkan by jalan + no rumah (biar rute jalan kaki efisien)
  → opsional: tampilkan di peta (marker koordinat GPS pelanggan)
```

**Fitur untuk Bagian Baca Meter (Kantor) di Web:**
- CRUD rute, assign jalan ke rute, assign petugas ke rute (penugasan **tetap**, diubah manual bila ada mutasi).
- Dashboard progress baca per rute (mis. Route AY-01: 120/150 sudah dibaca).
- Deteksi jalan yang belum masuk rute manapun (biar tidak ada pelanggan terlewat).
- Peta sebaran pelanggan + rute (opsional, pakai Leaflet/Google Maps).

**Kenapa ini penting (rekomendasi):**
- Efisiensi: petugas tidak bolak-balik antar wilayah jauh.
- Akuntabilitas: jelas siapa bertanggung jawab baca meter mana — karena penugasan tetap, petugas hafal medan & pelanggan di rutenya.
- Konsistensi: pelanggan dilayani petugas yang sama tiap bulan (lebih familiar).
- Basis SPK/insentif: bisa hitung produktivitas per petugas per rute.


---

## 9. Modul Keuangan: Basic vs Advance (ala Accurate)

### 9.1 Keuangan DASAR (CORE)
COA per tenant, jurnal double-entry (auto + manual, wajib balance), kas/bank, 5 laporan standar (Buku Besar, Neraca Saldo, Laba Rugi, Neraca, Arus Kas), piutang dasar.

### 9.2 Keuangan ADVANCE (`FIN+` — ala Accurate) ⭐
- **AR:** faktur non-air, sales/delivery order (air tangki), AR aging, uang muka, retur/nota kredit.
- **AP:** purchase invoice, utang usaha, AP aging, payment voucher, uang muka supplier, retur beli.
- **Inventory accounting:** valuasi FIFO/Average, nilai persediaan di Neraca, stock adjustment berjurnal.
- **Fixed asset:** register aset, depresiasi (garis lurus/saldo menurun) otomatis bulanan, disposal, kapitalisasi material pemasangan.
- **Pajak:** PPN keluaran/masukan, PPh 21/23/4(2), ekspor e-Faktur (CSV/XML DJP), helper SPT Masa.
- **Kas/Bank:** rekonsiliasi bank, giro/cek, transfer antar kas.
- **Multi-currency:** kurs, revaluasi, selisih kurs.
- **Budgeting:** anggaran per akun/periode, laporan realisasi vs anggaran.
- **Project/Cost Center:** alokasi & laba rugi per proyek/departemen.
- **Recurring:** jurnal/tagihan berulang.
- **Approval & audit:** workflow approval, audit trail, lock periode.
- **Laporan lanjutan:** komparatif antar periode, konsolidasi multi-cabang, GL detail, sub-ledger, ekspor PDF/Excel + kop surat PDAM.

---

## 10. Logic Tarif & Penagihan

### 10.1 Tiered (progresif — dipertahankan)
Golongan 2A3, 35 m³:
```
Tier 1 (0–10):  10×3.200 = 32.000
Tier 2 (>10–20):10×5.500 = 55.000
Tier 3 (>20):   15×6.200 = 93.000
Subtotal air             = 180.000
```

### 10.2 Komponen tagihan lengkap
```
amount_due = pemakaian_air (tiered) + abonemen + pemeliharaan_meter
           + admin + denda − uang_muka
```
Tiap komponen → baris di `bill_items`. Ada `minimum_charge_m3` per golongan.

### 10.3 Denda & jurnal (multi-line)
`penalty_amount` di `bills`, aturan di `billing_settings`. Jurnal generate:
```
DEBIT Piutang Pelanggan
  KREDIT Pendapatan Air / Abonemen / Jasa Meter / Administrasi
```

### 10.4 ⭐ Cicilan Tunggakan (Restrukturisasi dengan Approval)
Prinsip yang kamu minta: **secara default sistem menuntut pelunasan penuh (cash/sekali bayar)**. Tapi kenyataannya ada pelanggan yang benar-benar kesulitan bayar tunggakan menumpuk. Untuk itu ada **jalur kemanusiaan**: pelanggan datang ke kantor, mengajukan cicilan, dan **hanya role berwenang (Kabag Keuangan / Kepala Hublang) atau Direktur yang boleh menyetujui** serta menetapkan **berapa kali cicilan**. Pelanggan **tidak bisa** mengaktifkan cicilan sendiri dari aplikasi — ini keputusan manusia, bukan otomatis.

**Prinsip kunci:**
- **Default = lunas penuh.** Opsi cicilan tidak muncul otomatis; harus di-*grant* oleh petugas berwenang.
- **Butuh persetujuan role terkait.** Pengajuan cicilan wajib di-approve. Nominal besar / termin panjang naik ke Direktur.
- **Pendekatan emosional/manual.** Pelanggan datang atau dihubungi kantor → petugas menilai kondisi → menawarkan skema cicilan yang manusiawi.
- **Selama nyicil & tertib bayar → tidak diisolir.** Kalau menunggak cicilan → plan gagal → bisa lanjut proses isolir.

**Alur lengkap:**
```
1. Pelanggan menunggak (mis. 4 bulan = Rp 720.000) → status tunggakan
        ↓
2. Pelanggan datang ke kantor / dihubungi Hublang → minta keringanan cicil
        ↓
3. Petugas berwenang (Kabag Keuangan / Kepala Hublang) buat DRAFT rencana cicilan:
     - pilih tagihan (bills) mana saja yang digabung ke dalam plan
     - jumlah kali cicilan (mis. 3x) → sistem hitung nominal per termin
     - tanggal jatuh tempo tiap termin
     - uang muka / DP (opsional)
     - alasan & kondisi pelanggan (catatan untuk audit)
   Status plan: pending_approval
        ↓
4. APPROVAL berjenjang (ambang batas diatur di billing_settings, bukan hardcode):
     ┌──────────────────────────────┬─────────────────────────────┐
     │ Nominal ≤ batas (mis. ≤1jt)   │ Nominal > batas /            │
     │ & termin ≤ batas (mis. ≤3x)   │ termin panjang (mis. >6x)    │
     │        ↓                      │        ↓                     │
     │ Cukup approval Kabag Keuangan │ WAJIB naik ke DIREKTUR        │
     │ / Kepala Hublang              │ untuk ACC                     │
     └──────────────────────────────┴─────────────────────────────┘
   Approve → status: active   |   Reject → status: rejected (tetap harus lunas penuh)
        ↓
5. Cicilan aktif → tagihan tunggakan lama "dibekukan" (status bills → in_installment,
   tidak digabung dengan tagihan berjalan):
     - Tiap termin menjadi item bayar sendiri (installment_schedules)
     - Pelanggan bayar termin via payment gateway / loket (cash)
     - Tagihan bulan berjalan TETAP jalan normal & dibayar terpisah
        ↓
6. Tiap termin dibayar → status termin: paid → sisa tunggakan berkurang
   Semua termin lunas → status plan: completed → bills terkait → paid
        ↓
7. Kalau termin telat / mangkir (cron installment_reminder):
     - ingatkan pelanggan H-3 & hari-H
     - lewat grace period → plan: defaulted
     - plan defaulted → tunggakan aktif kembali → bisa lanjut proses isolir
```

**Sisi akuntansi (penting — jangan double-count):**
- Saat tagihan awal digenerate, piutang **sudah** diakui (DEBIT Piutang | KREDIT Pendapatan). Membuat rencana cicilan **tidak** menciptakan pendapatan baru — hanya **menjadwal ulang** piutang yang sudah ada. Jadi **tidak ada jurnal saat plan dibuat**.
- Saat pelanggan **bayar termin**: `DEBIT Kas/Bank | KREDIT Piutang Pelanggan` (persis seperti bayar tagihan biasa). Piutang berkurang sesuai nominal termin.
- Denda yang terlanjur ada bisa **diputihkan sebagian** sebagai bagian kesepakatan (butuh approval khusus) → jurnal `DEBIT Beban Keringanan/Pengurang Pendapatan | KREDIT Piutang`. Dicatat terpisah agar auditable.

**Permission baru (RBAC, Bagian 4.D.3):**
```
core.installment.view              ← lihat rencana cicilan
core.installment.create            ← buat draft cicilan (Kabag Keuangan, Kepala Hublang)
core.installment.approve           ← setujui cicilan dalam batas nominal (Kabag Keuangan / Kepala Hublang)
core.installment.approve_director  ← ACC cicilan nominal besar / termin panjang (Direktur)
core.installment.waive_penalty     ← putihkan denda (hak khusus)
```

> Rekomendasi: ambang nominal & jumlah termin yang butuh ACC Direktur disimpan di `billing_settings` agar tiap PDAM set kebijakannya sendiri. Semua tindakan create/approve/reject/waive wajib masuk `activity_logs`.

---

## 11. Logic Baca Meter & Edge Cases

### 11.1 Tipe: `ocr_confirmed | manual_corrected | estimated` (+ alasan).

### 11.2 Edge cases
- **Rollover:** jika `current<previous` → `konsumsi=(10^digit−previous)+current`, flag verifikasi.
- **Ganti meter:** `meter_replacements` (final lama + awal baru), konsumsi digabung.
- **Negatif/nol mencurigakan:** selalu flag, tidak auto-tagih.
- **True-up estimasi:** koreksi selisih estimasi vs riil di periode berikutnya.
- **Anomali:** >2× rata-rata 3 bulan, <30% rata-rata, lompatan 0→besar → flag.

### 11.3 Periode + rute
`reading_periods` (open|reading|verifying|closed). Tagihan hanya bila periode `closed`. Progres dipantau **per rute** (lihat bagian 8.2).

---

## 12. Logic Persediaan & Akuntansi Material

Material = **Persediaan (ASET)**, bukan biaya langsung.
```
① Beli:       DEBIT Persediaan | KREDIT Kas/Bank atau Utang Usaha
② Perbaikan:  DEBIT Beban Pemeliharaan | KREDIT Persediaan
③ Pemasangan: DEBIT Aset Jaringan | KREDIT Persediaan  (kapitalisasi → disusutkan)
④ Selisih opname/rusak: DEBIT Kerugian Persediaan | KREDIT Persediaan
```
Transfer antar gudang = pindah lokasi aset, tidak ada jurnal L/R (hanya `material_transactions`).

---

<a id="13-payment-gateway-detail"></a>
## 13. Payment Gateway (Detail) ⭐

Kamu betul, sebelumnya kurang detail. Berikut rancangan lengkap.

### 13.1 Provider
- **Utama: Midtrans** (Snap API) — mendukung QRIS, Virtual Account (BCA/BNI/BRI/Mandiri/Permata), e-wallet (GoPay/ShopeePay/DANA/OVO via QRIS), kartu, retail (Indomaret/Alfamart).
- **Alternatif/backup:** Xendit atau DOKU (arsitektur dibuat provider-agnostic via interface `PaymentGatewayInterface` agar bisa ganti/menambah provider tanpa ubah logika bisnis).

### 13.2 Dua jenis pembayaran (dipisah tegas)
| Jenis | `payment_type` | Sumber | Jurnal saat sukses |
|-------|----------------|--------|--------------------|
| Biaya pemasangan baru | `installation_fee` | Prospek/calon pelanggan | DEBIT Kas/Bank \| KREDIT Pendapatan Pemasangan |
| Tagihan bulanan | `monthly_bill` | Pelanggan aktif | DEBIT Kas/Bank \| KREDIT Piutang Pelanggan |

### 13.3 Alur pembayaran (flow)
```
1. App/Web minta "buat pembayaran" ke backend Laravel
2. Backend buat record payments (status=pending) + panggil Midtrans → dapat snap_token / VA number / QRIS string
3. Backend simpan midtrans_order_id (UNIQUE) → kirim ke client
4. Pelanggan bayar (scan QRIS / transfer VA / e-wallet)
5. Midtrans kirim WEBHOOK (HTTP notification) ke endpoint backend
6. Backend:
     - verifikasi signature (SHA512: order_id+status_code+gross_amount+server_key)
     - cek idempotency (order_id sudah diproses? skip)
     - update payments.status = success/failed/expired
     - jika success → buat journal_entry otomatis (double-entry)
     - update bills.status / prospect.status
     - kirim notifikasi (FCM + in-app)
7. Client polling / realtime → tampilkan status "Lunas"
```

### 13.4 Hal kritis (rekomendasi wajib)
- **Verifikasi signature webhook** — jangan percaya payload mentah. Hitung ulang signature key.
- **Idempotency** — webhook bisa datang berkali-kali. `midtrans_order_id`/`transaction_id` UNIQUE + cek status sebelum insert jurnal, agar tidak dobel catat uang.
- **Jangan andalkan callback client** — status resmi hanya dari webhook server-to-server. Callback di app hanya untuk UX.
- **Reconciliation harian** — cron cocokkan status Midtrans vs DB (jaga-jaga webhook hilang).
- **Handle expired** — VA/QRIS punya masa berlaku; set `expiry` dan status `expired` otomatis.
- **Refund** — untuk kasus salah bayar (opsional, via API Midtrans, catat jurnal balik).
- **Environment** — Sandbox saat dev, Production key disimpan di `.env` (jangan commit).

### 13.5 Tabel
`payments` (sudah ada) + `payment_gateway_logs` (raw request/response & webhook untuk audit/debug).

---

<a id="14-aplikasi-mobile-flutter-role-bottom-nav-fitur-pelanggan"></a>
## 14. Aplikasi Mobile Flutter (Role, Bottom Nav, Fitur Pelanggan) ⭐

### 14.1 Flutter untuk role apa saja?
Satu aplikasi Flutter dengan **tampilan berbeda per role** (role-based routing setelah login). Tiga role utama di mobile:

1. **Pelanggan** (paling banyak dipakai)
2. **Petugas Baca Meter** (lapangan)
3. **Petugas Survey** (lapangan, dual-mode — juga bisa web)

> Manajemen (Direktur, Keuangan, Gudang, dll) memakai **Web (Vue)**, bukan mobile.

### 14.2 Bottom Navigation per role

**A. Pelanggan (5 tab):**
```
[🏠 Beranda] [🧾 Tagihan] [💧 Pemakaian] [🔔 Notifikasi] [👤 Akun]
```
- **Beranda:** ringkasan tagihan bulan ini, status (lunas/belum), tombol bayar cepat, info pengumuman.
- **Tagihan:** daftar tagihan + status, detail komponen (air/abonemen/admin/denda), tombol bayar (payment gateway), riwayat pembayaran, unduh bukti.
- **Pemakaian:** ⭐ **grafik konsumsi air per bulan** (6–12 bulan), perbandingan bulan lalu, rata-rata, indikator boros/hemat, estimasi tagihan berjalan.
- **Notifikasi:** pengingat tagihan (tgl 23–25), info gangguan, status pengaduan.
- **Akun:** profil, ubah data, daftar pengaduan (buat/tracking), bantuan, logout.

**B. Petugas Baca Meter (3 tab):**
```
[📋 Tugas Rute] [📷 Baca Meter] [👤 Akun]
```
- **Tugas Rute:** daftar pelanggan di **route yang di-assign** hari ini (urut jalan+no rumah), progress (mis. 40/150), opsi peta.
- **Baca Meter:** foto rumah (bukti hadir) → foto meter → OCR → konfirmasi/koreksi → atau input estimasi + alasan → submit (support offline, sync saat online).
- **Akun:** profil, statistik baca hari ini, logout.

**C. Petugas Survey (3 tab):**
```
[📋 Tugas Survey] [📝 Form Survey] [👤 Akun]
```
- **Tugas Survey:** daftar calon pelanggan yang di-assign.
- **Form Survey:** foto rumah (min 2), jarak pipa, kondisi bangunan, aksesibilitas, estimasi material, GPS lokasi, rekomendasi layak/tidak, submit.
- **Akun:** profil, riwayat survey, logout.

### 14.3 Target fitur pelanggan cek pemakaian & tagihan

Target produk untuk tab **Pemakaian** dan **Tagihan**:
- Grafik konsumsi air bulanan (m³) — bisa lihat naik/turun tiap bulan.
- Rincian tagihan per komponen tiap bulan.
- Riwayat pembayaran lengkap + bukti bayar.
- Estimasi tagihan berjalan (berdasarkan tren pemakaian).
- Notifikasi otomatis tgl 23–25 dengan tombol bayar langsung.

### 14.4 Clean Architecture (Flutter)
```
lib/
├── core/              (error, network, usecase base, constants, theme)
├── features/
│   ├── auth/
│   │   ├── data/         (datasource, model, repository_impl)
│   │   ├── domain/       (entity, repository, usecase)
│   │   └── presentation/ (page, widget, riverpod provider/notifier)
│   ├── customer_billing/
│   ├── customer_usage/
│   ├── meter_reading/
│   └── survey/
└── main.dart
```
- **State:** Riverpod. **HTTP:** Dio. **Routing:** Go Router. **DI:** get_it/injectable.
- **Offline:** cache tugas rute (drift/hive) + queue submit yang sync saat online (untuk petugas lapangan sinyal jelek).
- **Kamera + OCR:** Flutter Camera + Google ML Kit / Cloud Vision.
- **Push:** Firebase Messaging.

---

<a id="15-design-system--uiux-biru-lembut"></a>
## 15. Design System & UI/UX (Biru Lembut) ⭐

Tema **terang/cerah**, tone **biru lembut** khas PDAM (air), profesional & nyaman dipandang lama.

### 15.1 Palet warna (token)
```
PRIMARY (biru lembut air):
  primary-50   #EEF6FB   (background lembut / hover)
  primary-100  #D6EAF6
  primary-200  #AFD6EE
  primary-300  #7FBEE2
  primary-400  #4FA3D4
  primary-500  #2E8BC7   ← warna utama (tombol, header aktif)
  primary-600  #2374AB
  primary-700  #1B5C88

SECONDARY (teal/hijau air — aksen segar):
  secondary-500 #17A2B8

NETRAL:
  bg-app        #F7FAFC   (latar aplikasi, sangat terang)
  surface       #FFFFFF   (kartu)
  border        #E3E8EF
  text-primary  #1F2D3D
  text-muted    #64748B

STATUS:
  success #22A06B   warning #E8A317   danger #E5484D   info #2E8BC7
```
Prinsip: dominan putih & biru sangat muda, aksen biru medium untuk aksi. Hindari biru pekat/gelap sebagai background besar (biar tidak berat).

### 15.2 Tipografi
- Font: **Inter** (atau Plus Jakarta Sans — nuansa Indonesia modern).
- Skala: h1 28/600, h2 22/600, h3 18/600, body 14–16/400, caption 12/400.

### 15.3 Komponen (Web — Vue + Tailwind)
- Layout: **sidebar kiri** (menu per modul, collapsible) + **topbar** (search, notif, profil, switch tenant untuk platform admin).
- Kartu statistik dashboard (KPI): rounded-xl, shadow lembut, ikon biru muda.
- Tabel data: zebra ringan, sticky header, filter, pagination, export.
- Form: label atas, input rounded-lg border lembut, focus ring biru-300.
- Chart: Recharts/ApexCharts dengan gradasi biru.
- Badge status: hijau (lunas), kuning (jatuh tempo), merah (overdue/isolir).
- **Rekomendasi UI kit:** karena stack Vue, pakai **PrimeVue** atau **Element Plus** + Tailwind untuk konsistensi & kecepatan (ganti shadcn yang React-only).

### 15.4 Mobile (Flutter)
- Material 3, `ColorScheme.fromSeed(seedColor: Color(0xFF2E8BC7))`, `brightness: light`.
- Kartu tagihan besar & jelas, tombol bayar menonjol, grafik pemakaian dengan gradasi biru.
- Bottom nav biru aktif + abu inaktif, ikon rounded.

---

## 15.C ⭐ Standar UI/UX Chart & Tabel + Template Dashboard (Wajib)

Permintaanmu: **semua data yang butuh chart/tabel wajib dibuat detail & menarik**, dan **template dashboard (backend/web + frontend + mobile) harus easy-to-use & enak dilihat**. Bagian ini jadi **standar wajib** yang mengikat seluruh modul — bukan sekadar saran. Setiap modul yang menampilkan data harus mengikuti standar di bawah.

### 15.C.1 Aturan umum (mengikat semua modul)
- **Setiap data agregat/tren WAJIB punya visualisasi**, bukan cuma angka mentah. Kalau ada data time-series (konsumsi, pendapatan, tunggakan) → wajib chart.
- **Setiap tabel data WAJIB pakai komponen `DataTable` shared** dengan: kolom sortable, filter, search, empty-state ramah, loading skeleton, dan **pagination WAJIB**.
- **Standar pagination (mengikat semua modul):** default **25 baris per halaman**, dan user bisa mengubah ke **50, 75, atau 100** baris per halaman (dropdown pilihan). Pagination server-side untuk dataset besar.
- **Standar export (mengikat semua modul):** setiap DataTable WAJIB punya tombol export dengan pilihan format **DOC (Word), Excel (XLSX), dan PDF** — user memilih mau export ke format mana. Export menghormati filter/sort yang sedang aktif + kop surat PDAM.

- **Konsisten**: semua chart & tabel pakai komponen shared (satu sumber), bukan bikin sendiri-sendiri per modul.
- **Responsif**: layout adaptif desktop → tablet → mobile.
- **Aksesibilitas**: kontras warna cukup, label jelas, jangan hanya mengandalkan warna untuk membedakan status (tambah ikon/teks).

### 15.C.2 Standar Chart (detail & menarik)
| Kebutuhan data | Jenis chart | Catatan desain |
|----------------|-------------|----------------|
| Tren konsumsi air pelanggan (bulanan) | Area/line + gradasi biru | Tooltip m³, garis rata-rata, marker bulan tertinggi |
| Pendapatan vs target | Bar + line combo | Bar pendapatan, line target |
| Komposisi tunggakan per golongan | Donut/pie | Legend + persentase, klik → drill-down tabel |
| Status tagihan (lunas/belum/overdue) | Stacked bar | Warna status konsisten (hijau/kuning/merah) |
| Progress baca meter per rute | Progress bar/gauge | Persentase + jumlah (120/150) |
| Arus kas / L-R | Line + area | Filter periode, komparasi antar bulan |
| Stok material/kimia | Horizontal bar | Garis minimum stock (threshold), highlight kritis merah |
| Sebaran pelanggan | Peta (Leaflet) | Marker cluster, warna per status |

Ketentuan chart:
- **Library:** ApexCharts (Vue) / fl_chart (Flutter). Gradasi biru sesuai token 15.1.
- **Wajib ada:** judul, legend, tooltip interaktif, format angka Indonesia (Rp, ribuan pakai titik), sumbu berlabel, state kosong ("Belum ada data").
- **Interaktif:** hover tooltip, filter periode (7h/30h/6bln/1thn/custom), klik legend untuk toggle seri, drill-down bila relevan.
- **Animasi halus** saat load (jangan berlebihan).

### 15.C.3 Standar Tabel (detail & fungsional)
Komponen `DataTable` shared dengan fitur wajib:
- Sortable per kolom, filter per kolom (dropdown/date range), global search.
- **Pagination WAJIB** server-side (untuk data besar) — **default 25 baris/halaman**, dropdown ubah ke **50 / 75 / 100**.
- Sticky header + kolom aksi sticky kanan.
- Row selection (bulk action bila perlu), row expand untuk detail.
- Badge status berwarna + ikon.
- **Export WAJIB dengan pilihan format DOC (Word) / Excel (XLSX) / PDF** (user pilih) + cetak dengan kop surat PDAM; export menghormati filter & sort aktif.

- Loading **skeleton** (bukan spinner polos), empty-state ilustratif, error-state dengan tombol retry.
- Density toggle (rapat/nyaman), kolom bisa di-show/hide.

### 15.C.4 Template Dashboard per platform
**A. Web Admin (Vue) — layout standar:**
```
┌───────────┬─────────────────────────────────────────────┐
│  SIDEBAR  │  TOPBAR (search · notif · profil · tenant)   │
│ (menu per ├─────────────────────────────────────────────┤
│  modul,   │  [KPI] [KPI] [KPI] [KPI]  ← stat cards       │
│ collapsi- │  ┌────────────────┐ ┌────────────────┐       │
│  ble)     │  │  Chart tren     │ │  Donut/komposisi│      │
│           │  └────────────────┘ └────────────────┘       │
│           │  ┌─────────────────────────────────────┐     │
│           │  │  DataTable (filter, sort, export)   │     │
│           │  └─────────────────────────────────────┘     │
└───────────┴─────────────────────────────────────────────┘
```
- **Dashboard per role berbeda**: Direktur (KPI eksekutif + grafik keuangan/operasional), Kabag Keuangan (pendapatan, tunggakan, arus kas), Kepala Gudang (stok kritis, transfer), Bagian Baca Meter (progress rute), dst. Widget = modular, tampil sesuai modul aktif + permission.
- **Komponen shared wajib:** `StatCard`, `ChartCard`, `DataTable`, `FilterBar`, `PageHeader`, `EmptyState`, `SkeletonLoader`.

**B. Mobile (Flutter) — pelanggan & petugas:**
- Home = kartu ringkas (tagihan/tugas) + 1–2 chart ringkas (konsumsi) + quick actions.
- Kartu besar, angka jelas, warna status, grafik gradasi biru (fl_chart), pull-to-refresh, skeleton saat load.
- Navigasi bottom nav (lihat 14.2), transisi halus, gesture natural.

**C. Super-Admin (Platform) — dashboard SaaS:**
- KPI: jumlah tenant aktif, MRR/ARR, modul terpopuler, tenant mendekati expired.
- Chart: pertumbuhan tenant, pendapatan langganan per bulan, adopsi modul.
- Tabel tenant (status langganan, modul aktif, aksi lock/unlock).

### 15.C.5 Rekomendasi implementasi (biar cepat & rapi)
- **Web:** pertimbangkan basis **admin template** (mis. layout ala Vuestic/PrimeVue Sakai) lalu re-theme ke token biru lembut — hemat waktu, konsisten. Bangun **design tokens** di Tailwind config sekali, pakai di semua modul.
- **Flutter:** buat `AppTheme` terpusat + widget library internal (`AppCard`, `AppChart`, `AppStatTile`) supaya konsisten lintas fitur.
- **Storybook/katalog komponen** (opsional) untuk mendokumentasikan komponen shared.

> Prinsip penutup: **konsistensi > kreativitas per halaman**. Satu set komponen shared yang dipoles baik, dipakai di semua modul, jauh lebih "menarik & easy to use" daripada tiap modul bikin gaya sendiri. Standar ini mengikat semua modul yang punya data.

---

<a id="keamanan-aplikasi-target-requirements"></a>
## 🔒 Keamanan Aplikasi (Target Requirements)

Karena aplikasi ini multi-tenant, memegang **data pribadi (NIK/KTP)** dan **uang**, keamanan wajib serius. Bagian ini menetapkan requirement pertahanan berlapis, bukan bukti kontrol telah diverifikasi atau sertifikasi. Evidence aktual mengikuti [`temuan2.md`](temuan2.md) dan baseline internal [`SECURITY_CHECKLIST.md`](SECURITY_CHECKLIST.md).

### A. Anti SQL Injection (yang kamu tanyakan)
- **Selalu pakai Eloquent ORM / Query Builder Laravel** — otomatis parameterized query (prepared statements), input tidak pernah digabung langsung ke SQL string.
- **Larang raw query tanpa binding.** Jika terpaksa `DB::raw()`, WAJIB pakai binding: `DB::select('... WHERE id = ?', [$id])`, jangan pernah `"... WHERE id = $id"`.
- **Jangan percaya input `orderBy`/`whereColumn` dari user** — whitelist nama kolom yang boleh (mencegah injeksi via nama kolom).
- **Code review + static analysis** (Larastan/PHPStan) untuk deteksi pola berbahaya sebelum merge.

### B. Validasi & Sanitasi Input
- **Semua input divalidasi via Form Request** (Laravel) + **Zod-like validation** di frontend (sekunder, UX saja — validasi utama tetap di backend).
- Batasi tipe, panjang, format (mis. NIK 16 digit, email valid, angka meter numerik).
- **Mass assignment protection:** pakai `$fillable` eksplisit, jangan `$guarded = []`.
- **File upload:** validasi MIME type asli (bukan hanya ekstensi), batasi ukuran, rename file (jangan pakai nama asli user), scan malware, simpan di luar webroot / bucket privat.

### C. Anti XSS & CSRF
- **XSS:** Vue auto-escape output (`{{ }}`). Hindari `v-html`; jika perlu, sanitasi dengan DOMPurify. Set **Content-Security-Policy (CSP)** header ketat.
- **CSRF:** web/browser memakai token CSRF Laravel dan cookie session stateful. Hanya client mobile/API
  device yang mengirim `device_name` memakai bearer token stateless; bearer bukan pengganti universal
  untuk autentikasi web.
- Header keamanan: `X-Frame-Options: DENY` (anti clickjacking), `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Strict-Transport-Security (HSTS)`.

> **Implementasi saat ini:** web memakai Sanctum stateful/session dengan cookie `HttpOnly` dan CSRF;
> response login web tidak mengandung token dan auth tidak disimpan di `localStorage`. Bearer Sanctum
> dipakai client mobile/API device yang mengirim `device_name`.

### D. Autentikasi & Otorisasi
- **Password:** hashing **bcrypt/argon2** (default Laravel), kebijakan password kuat, cek terhadap daftar password bocor (opsional).
- **MFA/2FA** untuk role sensitif (Keuangan, Direktur, Platform Admin) — TOTP (Google Authenticator) atau OTP SMS/email.
- **Sanctum:** web memakai session/cookie stateful `HttpOnly` + CSRF; mobile/API device memakai bearer token per `device_name`, disimpan pada secure storage, dapat di-refresh/revoke, dan tidak diletakkan di URL atau `localStorage` web.
- **Otorisasi ganda:** cek RBAC permission **di setiap endpoint** (Laravel Policy/Gate) — jangan hanya sembunyikan tombol di UI. Prinsip **least privilege**.
- **Isolasi tenant:** setiap query wajib ter-scope `pdam_org_id` (Global Scope) — cegah *IDOR* (akses data tenant lain dengan tebak ID).
- **Anti brute force:** rate limit login (mis. 5x gagal → lock sementara / captcha), log percobaan gagal.

### E. Perlindungan Data (Data Protection)
- **Enkripsi at-rest:** NIK, foto KTP, data sensitif dienkripsi di DB (Laravel `encrypted` cast / field-level encryption). Kunci disimpan di secret manager, bukan di kode.
- **Enkripsi in-transit:** **HTTPS/TLS wajib** di semua endpoint (web, API, mobile). No plain HTTP.
- **Foto KTP:** simpan di bucket privat, akses via **signed URL** berdurasi pendek, bukan URL publik.
- **Masking:** tampilkan NIK/rekening sebagian (mis. `3201••••••••1234`) kecuali role berwenang.
- **Kepatuhan UU PDP** (UU No. 27/2022): consent saat daftar, hak hapus data, retensi data jelas, minimalisasi data yang dikumpulkan.

### F. Keamanan API
- **Rate limiting** per user/IP (Laravel throttle) — cegah abuse & DoS.
- **Idempotency key** untuk operasi pembayaran/transaksi (cegah dobel).
- **Versioning** (`/api/v1/`) + validasi ketat payload.
- **CORS** dibatasi ke domain resmi saja.
- **Webhook (Midtrans):** verifikasi signature SHA512, whitelist IP Midtrans, HTTPS only.
- **Jangan bocorkan info di error** — pesan error generik ke user, detail hanya ke log internal.

### G. Audit, Logging & Monitoring
- **Audit trail lengkap** (`activity_logs`): siapa, kapan, aksi apa, nilai lama→baru, IP — terutama transaksi keuangan & perubahan data pelanggan.
- **Log terpusat** (mis. ELK/Grafana Loki) + alert anomali (login tengah malam, akses massal data, transaksi janggal).
- **Immutable log** untuk keuangan (append-only) + lock periode akuntansi.
- **Monitoring uptime & intrusion detection.**

### H. Keamanan Infrastruktur & DevOps
- **Secret management:** kredensial di `.env`/vault, **tidak pernah di-commit** ke Git. Scan repo dengan git-secrets.
- **Dependency scanning:** `composer audit`, `npm audit`, Dependabot — tambal library rentan.
- **Prinsip least privilege** di server, database user terpisah (app user tidak boleh DROP TABLE).
- **Firewall + WAF** (Cloudflare/AWS WAF) — filter serangan umum (OWASP Top 10).
- **Backup terenkripsi** + uji restore berkala + rencana disaster recovery.
- **Environment terpisah:** dev/staging/production, data production tidak dipakai di dev.
- **CI/CD target:** automated test, dependency audit, SAST/DAST, secret scan, migration MySQL, serta build lintas komponen sebelum deploy. Implementasi CI saat ini parsial; hasil manual terbaru ada di `temuan2.md`.

### I. Keamanan Mobile (Flutter)
- **Secure storage** untuk token (`flutter_secure_storage`).
- **Certificate pinning** untuk cegah man-in-the-middle.
- **Root/jailbreak detection** (opsional untuk app petugas).
- **Obfuscation** kode Dart (`flutter build --obfuscate --split-debug-info`).
- Jangan simpan data sensitif di cache/log device.

### I-B. Hardening & Obfuscation Web (Vue) — WAJIB
Obfuscation bukan cuma di Flutter; frontend web juga harus di-harden karena kode Vue berjalan di browser dan bisa diinspeksi siapa saja.
- **Minifikasi + obfuscation build:** build production Vite otomatis minify; tambahkan plugin obfuscator (mis. `javascript-obfuscator` / `vite-plugin-obfuscator`) untuk bundle sensitif.
- **Source map production dimatikan** (`build.sourcemap: false`) agar kode asli tidak terekspos.
- **JANGAN taruh secret di frontend** — API key, server key Midtrans, kredensial hanya di backend. Frontend hanya pegang token sesi berumur pendek.
- **Logika bisnis & validasi kritis di backend**, bukan di JS (frontend gampang dibongkar). Frontend hanya untuk UX.
- **Content-Security-Policy (CSP)** ketat + Subresource Integrity (SRI) untuk script pihak ketiga.
- **Disable Vue devtools** di production.
- **Anti-tampering:** hash/integrity check aset, deteksi modifikasi bundle bila perlu.
- **Prinsip zero-trust frontend:** anggap semua yang di browser bisa dilihat/diubah user — otorisasi & keputusan penting selalu diverifikasi ulang di server.


### J. Proses & Kepatuhan
- **Penetration testing** berkala (internal + pihak ketiga) sebelum go-live nasional.
- **Security checklist OWASP Top 10 & OWASP ASVS** sebagai acuan.
- **Bug bounty / responsible disclosure** (jangka panjang).
- **Pelatihan security awareness** untuk tim dev & pegawai PDAM (anti phishing).
- **Incident response plan** — prosedur jika terjadi kebocoran.

### Ringkas prioritas (must-have sebelum go-live)
1. HTTPS/TLS di semua endpoint.
2. Eloquent/prepared statement (anti SQLi) + validasi input.
3. RBAC + tenant scope di setiap endpoint (anti IDOR).
4. Enkripsi NIK/KTP + signed URL foto.
5. Rate limit + anti brute force login + MFA role sensitif.
6. Webhook signature verify + idempotency.
7. Audit trail + backup terenkripsi.
8. Dependency & secret scanning di CI.

---

## 16. ERD Lengkap (Per Domain)

> Semua tabel bisnis punya `pdam_org_id`. MySQL 8, Eloquent, `snake_case`.

### 16.1 Platform & Langganan
```
platform_admins (id, email, full_name, is_active)   ← Super-Admin, TERPISAH dari users tenant
pdam_organizations (id, code, name, city, province, logo_url, letterhead_config json, timezone, subscription_status, created_at)
modules (id, code, name, description, tier, base_price_year, dependencies json, is_active)
module_price_tiers (id, module_code, min_customers, max_customers, price_year)   ← harga per skala pelanggan
tenant_module_overrides (id, pdam_org_id, module_code, custom_price, note, set_by, valid_until)   ← harga khusus per tenant
price_change_logs (id, module_code, old_price, new_price, changed_by, changed_at)   ← audit ubah harga
subscriptions (id, pdam_org_id, plan_tier, start_date, end_date, status, billing_cycle)
subscription_modules (id, pdam_org_id, module_code, status, activation_method, amount_paid,
   payment_proof_url, activated_by, activated_at, expires_at, locked_by, locked_at, note)
   ← status: locked|active|expired|trial ; activation_method: gateway|cash|manual_transfer|free_trial|promo
   ← INTI lock/unlock 2 jalur (payment gateway ATAU manual oleh Super-Admin)
saas_invoices (id, pdam_org_id, period, amount, status, paid_at)

-- Marketplace & Promo (Bagian 4.E)
promos (id, code, name, description, discount_type, discount_value,
   scope_type, scope_value json, target_type, max_discount_cap,
   usage_quota, used_count, starts_at, ends_at, status, created_by, created_at)
   ← discount_type: percent|fixed ; scope_type: all|tier|modules|tenant
   ← status: scheduled|active|expired|disabled ; starts_at/ends_at = RENTANG TANGGAL promo
promo_targets (id, promo_id, pdam_org_id)   ← jika target specific
promo_redemptions (id, promo_id, pdam_org_id, module_code,
   original_price, discount_amount, final_price, redeemed_at)   ← audit pemakaian
```


### 16.2 Users & RBAC (granular per modul)
```
users (id, pdam_org_id, email, phone, full_name, is_active, is_tenant_admin, fcm_token)
roles (id, pdam_org_id, name, is_system_default)   ← role bawaan + custom per tenant
permissions (id, code, module_code, resource, action, description)
   ← contoh: code="wh.material.create", module_code="WH", resource="material", action="create"
   ← action: view|create|update|delete|approve|adjust|export ...  (CRUD + aksi khusus)
role_permissions (role_id, permission_id)
user_roles (user_id, role_id)
user_permissions (user_id, permission_id, granted)   ← override per user (allow/deny spesifik), opsional
employees (id, user_id, employee_number, position, zone_id)
```
> Double gate tiap request: (1) CheckModuleAccess — tenant punya modul aktif? (2) CheckPermission — user punya izin aksi? Permission dari modul yang belum di-unlock tidak muncul di UI & ditolak backend.


### 16.3 ⭐ Struktur Alamat
```
provinces (id, code, name)
cities (id, province_id, name, type)
districts (id, city_id, name)
villages (id, district_id, name)
streets (id, village_id, name)
```

### 16.4 Wilayah & Gudang
```
zones (id, pdam_org_id, code, name, office_address, office_phone, is_main, is_active)
warehouses (id, pdam_org_id, zone_id, warehouse_type, address, is_active)
material_stocks (id, material_id, warehouse_id, current_stock, minimum_stock, average_cost)
```

### 16.5 ⭐ Rute Baca Meter
```
meter_routes (id, pdam_org_id, code, name, zone_id, description, is_active)
meter_route_streets (id, route_id, street_id)
meter_route_assignments (id, route_id, officer_id, effective_from, assigned_by, assigned_at, is_active)
   ← penugasan TETAP; diubah manual saat mutasi petugas
```


### 16.6 Tarif
```
tariff_categories (id, pdam_org_id, code, name, description, abonemen, meter_maintenance_fee, admin_fee, minimum_charge_m3)
tariff_tiers (id, category_id, min_usage, max_usage, price_per_m3, effective_date)
billing_settings (id, pdam_org_id, penalty_type, penalty_value, isolir_after_months, due_day)
```

### 16.7 Pelanggan & Pendaftaran
```
customer_prospects (id, pdam_org_id, user_id, nik ENCRYPTED, full_name, birth_place, birth_date,
   gender [L|P], religion, marital_status [belum_kawin|kawin|cerai_hidup|cerai_mati], occupation,
   blood_type NULL, nationality DEFAULT 'WNI',
   street_id, house_number, rt, rw, latitude, longitude, location_source [customer_pin|surveyor_verified], location_accuracy, installation_address,
   email, phone, ktp_photo_url, ktp_file_type [jpg|png|pdf], ktp_ocr_raw,
   status, assigned_surveyor_id, rejection_reason)

survey_reports (id, prospect_id, surveyor_id,
   photo_house_urls json,          ← WAJIB min. 2 foto (bukti hadir + bahan pertimbangan approval)
   distance_to_main_pipe, building_condition, accessibility, land_status,
   estimated_materials json, latitude, longitude, surveyor_notes,
   recommendation [feasible|not_feasible],
   reviewed_by, review_status [approved|rejected|re_survey], review_notes)

customers (id, pdam_org_id, user_id, prospect_id, customer_number,
   zone_id, tariff_category_id, street_id, house_number, rt, rw, latitude, longitude, location_source,
   override_route_id, meter_serial_number, installation_date, status)
customer_status_history (id, customer_id, old_status, new_status, reason, changed_by, changed_at)
ownership_transfers (id, customer_id, old_owner_data json, new_owner_data json, transfer_date, approved_by)
disconnections / reconnections (id, customer_id, type, reason, fee, date, executed_by)

-- ⭐ Cicilan Tunggakan dengan approval (Bagian 10.4)
installment_plans (id, pdam_org_id, customer_id, total_arrears, down_payment,
   installment_count, remaining_amount, reason, status,
   created_by, approved_by, approved_at, approval_level, director_approved_by,
   rejected_reason, completed_at, defaulted_at, created_at)
   ← status: pending_approval | active | completed | rejected | defaulted
   ← approval_level: finance_head | hublang_head | director (siapa yang meng-ACC)
installment_plan_bills (id, plan_id, bill_id)   ← tagihan mana saja yang digabung ke plan
installment_schedules (id, plan_id, sequence_no, amount, due_date,
   status, payment_id, paid_at)
   ← status: unpaid | paid | overdue ; payment_id → FK ke payments (bayar per termin)
```


### 16.8 Baca Meter & Tagihan
```
reading_periods (id, pdam_org_id, period, zone_id, status, opened_by, opened_at, closed_by, closed_at)
meter_readings (id, customer_id, period_id, route_id, reading_value, reading_date,
   photo_house_url, photo_meter_url, reading_type, unreadable_reason, is_flagged, flag_reason,
   is_rollover, read_by, verified_by)
meter_replacements (id, customer_id, old_serial, new_serial, old_final_reading, new_initial_reading, replaced_at)
bills (id, pdam_org_id, customer_id, period, previous_reading_id, current_reading_id,
   consumption, amount_due, penalty_amount, status, due_date, journal_entry_id)
bill_items (id, bill_id, item_type, description, amount)
payments (id, bill_id, prospect_id, payment_type, amount, payment_method,
   midtrans_order_id UNIQUE, midtrans_transaction_id, status, paid_at, expiry, journal_entry_id)
payment_gateway_logs (id, payment_id, direction, payload json, created_at)
```

### 16.9 Gudang & Pengadaan
```
materials (id, pdam_org_id, code, name, category, unit, standard_price, is_active)
suppliers (id, pdam_org_id, name, contact, address, npwp, payment_terms)
purchase_orders (id, pdam_org_id, po_number, supplier_id, requested_by, items json,
   total_estimated_price, urgency, status, tech_approved_by/at, dir_approved_by/at,
   fin_approved_by/at, purchased_at, received_at, rejection_reason)
stock_transfers (id, pdam_org_id, transfer_number, transfer_type, from_warehouse_id,
   to_warehouse_id, reason, status, reference_order_id, requested_by, approved_by, received_by, notes)
stock_transfer_items (id, transfer_id, material_id, quantity_requested, quantity_received)
material_transactions (id, material_id, warehouse_id, transaction_type, quantity, unit_cost,
   reference_type, reference_id)
stock_adjustments (id, warehouse_id, material_id, system_qty, physical_qty, difference,
   reason, journal_entry_id, adjusted_by, adjusted_at)
repair_orders (id, customer_id, zone_id, description, status, assigned_technician, materials_used json, completed_at)
```

### 16.10 Akuntansi Dasar
```
chart_of_accounts (id, pdam_org_id, code, name, type, normal_balance, parent_id)
journal_entries (id, pdam_org_id, entry_date, description, reference_type, reference_id, period_locked, created_by)
journal_entry_lines (id, journal_id, account_id, type, amount, project_id, cost_center_id)
accounting_periods (id, pdam_org_id, period, status, closed_by, closed_at)
⚠️ SUM(DEBIT)=SUM(KREDIT) per journal_id atau ditolak.
```

### 16.11 Keuangan Advance (FIN+)
```
sales_invoices / sales_invoice_items
purchase_invoices / purchase_invoice_items
accounts_receivable / accounts_payable
ar_ap_payments
fixed_assets (id, pdam_org_id, name, category, acquisition_cost, acquisition_date,
   useful_life_years, depreciation_method, accumulated_depreciation, book_value, status)
depreciation_entries
tax_records (id, tax_type, base_amount, tax_amount, reference, period)
budgets / budget_lines
projects / cost_centers
recurring_transactions
bank_reconciliations
currencies / exchange_rates
```

### 16.12 Pengaduan, Notifikasi, Audit
```
complaints (id, pdam_org_id, customer_id, category, description, photo_urls,
   status, sla_due_at, assigned_to, resolution_notes, created_at)
notifications (id, pdam_org_id, user_id, type, title, body, data json, is_read, sent_via, created_at)
activity_logs (id, pdam_org_id, user_id, action, entity_type, entity_id, old_value json, new_value json, ip_address, created_at)
```

### 16.13 ⭐ Modul Enterprise Baru (Tier 1–2) — ERD Ringkas
Tabel untuk modul hasil pemetaan Bagian 4.B. Semua punya `pdam_org_id`.

```
-- FSM: Field Service & Work Order (Tier 2) — blok K, L, AC
work_orders (id, pdam_org_id, wo_number, type, priority, status, customer_id,
   related_ref_type, related_ref_id, sla_due_at, assigned_technician_id,
   created_by, approved_by, escalated_at, completed_at, notes)
   type: new_connection | leak_repair | meter_replacement | disconnection |
         reconnection | water_quality_check
work_order_logs (id, work_order_id, status, note, photo_urls, latitude, longitude,
   signature_url, created_by, created_at)   ← progress + e-sign pelanggan
technician_locations (id, technician_id, latitude, longitude, recorded_at)  ← GPS tracking

-- METX: Meter Analytics (Tier 1) — blok E
meters (id, pdam_org_id, serial_number, brand, install_year, condition, location_note,
   customer_id, status, replaced_at)   ← master meter fisik (lengkapi E)
meter_anomalies (id, meter_id, reading_id, anomaly_type, detail, detected_at, resolved)
   anomaly_type: broken | tampered | abnormal_usage | zero_consecutive

-- PROC: Procurement & Tender & Vendor (Tier 2) — blok P
vendors (id, pdam_org_id, name, contact, address, npwp, rating, is_active)
tenders (id, pdam_org_id, tender_number, title, description, status, budget,
   opened_at, closed_at, awarded_vendor_id)
tender_bids (id, tender_id, vendor_id, bid_amount, documents json, score, status)
vendor_contracts (id, vendor_id, contract_number, start_date, end_date, value, status)
vendor_evaluations (id, vendor_id, po_id, quality_score, timeliness_score, notes)

-- AST/MNT: Asset & Maintenance (Tier 2) — blok Q, R
assets (id, pdam_org_id, code, name, category, location, acquisition_date,
   acquisition_cost, status, parent_id)
   category: ipa | pump | reservoir | building | vehicle | pipe_network
maintenance_schedules (id, asset_id, type, interval_days, next_due_date, checklist json)
   type: preventive | predictive | corrective
maintenance_records (id, asset_id, schedule_id, performed_at, technician_id,
   parts_used json, cost, result_note, work_order_id)

-- HR: HR Management (Tier 2) — blok T
hr_employees (id, pdam_org_id, user_id, nip, position, department, join_date, status)
attendances (id, hr_employee_id, date, check_in, check_out, shift_id, status)
shifts (id, pdam_org_id, name, start_time, end_time)
leaves (id, hr_employee_id, type, start_date, end_date, status, approved_by)
payrolls (id, hr_employee_id, period, basic_salary, allowances json, deductions json, net_pay)
trainings / certifications (id, hr_employee_id, name, issued_date, expires_at)
performance_appraisals (id, hr_employee_id, period, score, notes, appraised_by)

-- DMS: Document Management (Tier 2) — blok U
documents (id, pdam_org_id, doc_number, title, category, file_url, version,
   owner_id, is_confidential, created_at)
   category: incoming_letter | outgoing_letter | contract | sop | customer_doc
document_approvals (id, document_id, approver_id, status, signed_at, signature_url)

-- GIS: Water Network (Tier 2) — blok J
gis_features (id, pdam_org_id, feature_type, name, geometry json/POINT/LINESTRING/POLYGON,
   properties json, zone_id, status)
   feature_type: pipe | valve | hydrant | reservoir | pump | customer_point | incident
   (opsi: pakai kolom spatial MySQL 8 / PostGIS bila pindah Postgres)
   pipe → geometry LINESTRING (jalur pipa mengikuti jalan/tanah, dirender GARIS BIRU)
   properties pipe: diameter, material(PVC|HDPE|GI), panjang, tahun_pasang, tekanan
gis_network_edges (id, pdam_org_id, from_node_type, from_node_id, to_node_id,
   pipe_feature_id, status)
   ← TOPOLOGI jaringan: pipa menyambung node↔node (rumah↔rumah, rumah↔pipa induk,
     valve, dll) → untuk telusur rumah terdampak saat pipa rusak / isolir area
gis_service_connections (id, pdam_org_id, customer_id, pipe_feature_id,
   connection_point json, connected_at)   ← sambungan rumah dari pipa distribusi ke titik rumah

-- ⭐ Node rumah pelanggan berwarna per status tunggakan (dihitung dari bills, bukan disimpan)
-- CustomerMapStatusService → hitung arrears_months per pelanggan → warna:
--   🟢 hijau  = LUNAS (tak ada tunggakan bulan ini)
--   ⚪ putih  = belum bayar bulan berjalan (1 bulan)
--   🟡 kuning = menunggak 2 bulan
--   🔴 merah  = menunggak 3 bulan (atau lebih)
--   ⚫ hitam  = sudah diputus/isolir (dari disconnections)
-- API GeoJSON: GET /gis/customers?bbox=&zone=&status= → FeatureCollection
--   (koordinat customers.latitude/longitude + properti warna_status, arrears_months, tunggakan)
-- Filter peta multi-select per warna + aksi massal (mis. semua merah → WO isolir / notif)


-- CC: Call Center (Tier 2) — blok N
call_logs (id, pdam_org_id, caller_phone, customer_id, agent_id, direction,
   started_at, ended_at, recording_url, complaint_id, disposition)

-- INT: Integration Platform (Tier 2) — blok Z
integrations (id, pdam_org_id, provider, type, config json ENCRYPTED, is_active)
   provider: whatsapp | sms_gateway | email | government_api | erp | gis | iot
integration_logs (id, integration_id, direction, payload json, status, created_at)
api_keys (id, pdam_org_id, name, key_hash, scopes json, last_used_at, revoked_at)

-- CRM perluasan (Tier 1) — blok B, M
complaint_tracks (id, complaint_id, status, note, changed_by, created_at)
customer_feedbacks (id, pdam_org_id, customer_id, source, rating, comment, created_at)
loyalty_points (id, customer_id, points, reason, ref_type, ref_id, created_at)
```

> Tier 3 (IOT/PROD/DIST/NRW/AI) sengaja BELUM di-ERD-kan detail di sini — bergantung keputusan hardware & arsitektur telemetri. Disediakan nanti sebagai skema "integration-ready" (tabel `sensor_readings`, `dma_zones`, `production_logs`, `ml_predictions`) saat modul itu benar-benar dibangun.

### 16.14 ⭐ Chemical Management (CHEM) + Bundle Modul — ERD
Tabel untuk modul `CHEM` (Bagian 4.F.3) & bundle modul (Bagian 4.F.2). Semua tabel CHEM punya `pdam_org_id`.

```
-- CHEM: Chemical Management (Tier 2)
chemicals (id, pdam_org_id, code, name, type, unit, standard_dose_per_m3,
   safe_min, safe_max, msds_url, is_active)
   type: coagulant(tawas/PAC) | disinfectant(kaporit/klorin) |
         ph_adjuster(kapur/soda_ash) | flocculant(polymer) | lab_reagent | other
chemical_suppliers (id, pdam_org_id, name, contact, address, npwp, payment_terms, rating)
chemical_purchase_requests (id, pdam_org_id, pr_number, requested_by, items json,
   urgency, status, po_id, created_at)   ← status: draft|approved|converted_to_po|rejected
   ← po_id → FK ke purchase_orders (pakai alur PROC)
chemical_receipts (id, pdam_org_id, receipt_number, po_id, supplier_id,
   received_by, received_at, notes)
chemical_receipt_items (id, receipt_id, chemical_id, batch_number, quantity,
   manufacture_date, expired_date, coa_number, coa_url)   ← batch + expired + COA
chemical_qc_tests (id, receipt_item_id, tested_by, tested_at, parameters json,
   result, notes)   ← result: passed | rejected | quarantine
chemical_stocks (id, pdam_org_id, chemical_id, batch_number, warehouse_id,
   current_qty, expired_date, qc_status)
   ← STOK PER BATCH (bukan agregat) → wajib untuk FEFO (keluarkan yg paling dekat expired)
chemical_transactions (id, chemical_id, batch_number, warehouse_id, transaction_type,
   quantity, unit_cost, reference_type, reference_id, created_at)
   ← transaction_type: stock_in | stock_out | adjustment | disposal
chemical_usages (id, pdam_org_id, usage_date, chemical_id, batch_number, quantity,
   water_volume_m3, dose_per_m3, ipa_location, recorded_by, journal_entry_id)
   ← pemakaian harian di IPA → hitung dosis & biaya per m³
chemical_forecasts (id, pdam_org_id, chemical_id, period, projected_need, basis_note)
chemical_stock_opnames (id, pdam_org_id, chemical_id, batch_number, warehouse_id,
   system_qty, physical_qty, difference, reason, journal_entry_id, adjusted_by, adjusted_at)

-- Bundle modul (cara jual paket, Bagian 4.F.2) — masuk domain Platform (16.1)
module_bundles (id, code, name, description, bundle_price_year, is_active)
   ← contoh: code="FINANCE_COMPLETE", isi FIN-AR+AP+TAX+FA+BUD+BNK
module_bundle_items (id, bundle_id, module_code)   ← modul apa saja dalam bundle
```

Akun COA tambahan untuk CHEM: `Persediaan Bahan Kimia` (ASSET, DEBIT), `Beban Produksi Air` (EXPENSE, DEBIT), `Kerugian Persediaan` (dipakai bersama material).

> Catatan integrasi: CHEM **memakai ulang** alur `purchase_orders`/`suppliers` dari domain gudang bila `PROC` aktif — jadi tidak menduplikasi logika pengadaan. Yang khas CHEM hanya batch, expired, COA, QC, FEFO, dosis, dan HPP air.

---

## 17. Matriks Modul → Tabel → Fitur

> Matriks ini adalah **target desain per modul**, bukan daftar fitur yang telah diverifikasi selesai. Status aktual mengikuti `temuan2.md`.

| Modul | Default/Bayar | Tabel Utama | Fitur Kunci |
|-------|:---:|-------------|-------------|
| **CORE** | Default (target) | users, roles, permissions, provinces..streets, customers, bills, bill_items, payments, payment_gateway_logs, tariff_*, chart_of_accounts, journal_*, accounting_periods, notifications, installment_* | Pelanggan+alamat berjenjang, penagihan tiered, payment gateway, keuangan dasar, RBAC, 5 laporan, input pemakaian manual, cicilan |
| **WH** | 💰 Bayar | materials, suppliers, warehouses, material_stocks, purchase_orders, stock_transfers*, material_transactions, stock_adjustments, repair_orders | Multi-gudang, PO 4-level, transfer 3-level, opname |
| **MTR** | 💰 Bayar | reading_periods, meter_readings, meter_replacements, meter_routes, meter_route_streets, meter_route_assignments | ⭐ Plotting rute baca, mobile petugas, OCR, verifikasi, edge cases |
| **SRV** | 💰 Bayar | customer_prospects, survey_reports, disconnections, reconnections, ownership_transfers | OCR KTP, survey+GPS, approval, pemasangan, lifecycle |
| **FIN+** (FIN-AR/AP/TAX/FA/BUD/BNK) | 💰 Bayar | sales/purchase invoices, AR/AP, fixed_assets, depreciation, tax_records, budgets, projects, cost_centers, recurring, bank_reconciliations, currencies | AR/AP aging, fixed asset, pajak/e-Faktur, budgeting, bank recon |
| **CHEM** | 💰 Bayar | chemicals, chemical_suppliers, chemical_receipts*, chemical_qc_tests, chemical_stocks, chemical_usages, chemical_forecasts, chemical_stock_opnames | Bahan kimia IPA, batch+expired, QC/COA, FEFO, dosis per m³, HPP air |
| **CRM** | 💰 Bayar | complaints, complaint_tracks, customer_feedbacks | Tiket, SLA, tracking |
| **AST** | 💰 Bayar | fixed_assets, depreciation_entries, assets, maintenance_* | Register aset, depresiasi, maintenance |
| **ZONE** | 💰 Bayar | zones, warehouses | Wilayah dinamis, buffer per wilayah |
| **APP** | 💰 Bayar | notifications, portal pelanggan (Flutter) | Mobile pelanggan: tagihan, pemakaian, bayar, pengaduan |
| **FSM/PROC/HR/DMS/GIS/CC/BI/INT** | 💰 Bayar | (lihat ERD 16.13) | Modul Tier 2 enterprise |
| **IOT/PROD/DIST/NRW/AI** | 💰 Bayar | (integration-ready, 16.13 catatan) | Modul Tier 3 smart utility (butuh hardware/data) |

---

## 18. Cron Jobs & Automasi

Dijalankan via **Laravel Scheduler** yang diregistrasikan pada lokasi bootstrap/console sesuai versi Laravel aktif. Host production tetap harus memanggil `php artisan schedule:run` setiap menit; status aktual mengikuti `temuan2.md` dan runbook `backend/DEPLOY.md`.

| Job | Jadwal | Modul | Fungsi |
|-----|--------|-------|--------|
| billing_notification | 08:00 tgl 23–25 | CORE | Notif tagihan belum bayar |
| hublang_escalation | tiap jam | SRV | Calon pelanggan >8 jam belum bayar → Hublang |
| stock_check | 07:00 harian | WH | Stok < minimum → Kepala Gudang |
| overdue_bills | 06:00 tgl 26 | CORE | Tagihan lewat tempo → overdue + denda |
| auto_isolir_flag | 06:00 tgl 1 | CORE | Tunggakan ≥ N bulan → flag isolir |
| depreciation_run | 02:00 tgl 1 | FIN+/AST | Jurnal penyusutan otomatis |
| recurring_journal | 03:00 harian | FIN+ | Transaksi berulang |
| payment_reconciliation | 01:00 harian | CORE | Cocokkan status Midtrans vs DB |
| subscription_check | 01:00 harian | Platform | Langganan expired → suspend modul |
| promo_activation | 00:05 harian | Platform | Aktif/kadaluarsa-kan promo sesuai starts_at/ends_at |
| installment_reminder | 08:00 harian | CORE | Ingatkan termin cicilan H-3 & hari-H; termin lewat grace → plan defaulted |

---

<a id="19-tech-stack-final"></a>
## 19. Tech Stack (Final) ⭐

### Backend
- **Laravel 13** (PHP 8.3) — REST API + Sanctum untuk auth.
- **MySQL 8** — database utama.
- **Laravel Scheduler** (cron), **Queue** (Redis) untuk job berat (OCR, notifikasi, generate tagihan massal).
- **Multi-tenancy:** trait `BelongsToTenant` (Global Scope) atau paket `stancl/tenancy`.
- **Form Request + Validation** untuk validasi input.

### Web Admin/Dashboard
- **Vue 3** + **Vite** + JavaScript sebagai source of truth runtime saat ini; duplikat TypeScript telah dihapus untuk mencegah drift.
- **Tailwind CSS** + **PrimeVue** (atau Element Plus) untuk komponen.
- **Pinia** (state), **TanStack Query (Vue Query)** (server state), **Vue Router**.
- **ApexCharts/Recharts-vue** untuk grafik.

### Mobile
- **Flutter 3** + **Dart 3**, **Clean Architecture** (data/domain/presentation).
- **Riverpod** (state), **Dio** (HTTP), **Go Router**, **get_it/injectable** (DI).
- **Hive/Drift** (offline cache + sync queue), **Flutter Camera**, **Google ML Kit** (OCR), **Firebase Messaging**.

### Integrasi
- **Midtrans** (payment, provider-agnostic interface) — VA, QRIS, e-wallet, retail.
- **Google Cloud Vision / ML Kit** — OCR meter & KTP.
- **Firebase FCM** — push notification.
- **e-Faktur DJP** — ekspor CSV/XML (FIN+).
- **Peta:** Leaflet/Google Maps (opsional, untuk sebaran pelanggan & rute).

### Storage & DevOps
- **Storage:** S3-compatible (MinIO/AWS S3) atau storage lokal untuk foto (KTP akses restricted + enkripsi).
- **Docker + Docker Compose**, **Nginx**, dan target hosting production. GitHub Actions/CI tersedia parsial dan belum menjadi evidence lengkap semua gate lintas komponen.
- **Redis** (cache + queue).

### Keamanan
- Enkripsi NIK/KTP at-rest, audit trail (`activity_logs`), signature verification webhook, idempotency, lock periode akuntansi, rate limiting API.

> Perubahan dari v3.0: stack pindah dari Node/Hono/Supabase (PostgreSQL) ke **Laravel/MySQL**. Konsekuensi: isolasi tenant via Global Scope aplikasi (bukan RLS database) — perlu disiplin agar setiap query ter-scope tenant. shadcn/ui (React) diganti PrimeVue/Element Plus (Vue).

---

<a id="20-struktur-proyek--arsitektur-kode"></a>
## 20. Struktur Proyek & Arsitektur Kode ⭐

### 20.1 Backend Laravel (modular / domain-oriented)
```
app/
├── Modules/                    (opsional: modularisasi via nwidart/laravel-modules)
│   ├── Core/
│   ├── Warehouse/
│   ├── MeterReading/           (termasuk Routes/plotting)
│   ├── Survey/
│   ├── FinanceAdvance/
│   └── ...
├── Models/                     (Eloquent + BelongsToTenant trait)
├── Http/
│   ├── Controllers/Api/
│   ├── Middleware/             (SetTenant, CheckModuleAccess)
│   └── Requests/               (validasi)
├── Services/                   (business logic: BillingService, JournalService,
│                                PaymentService, MeterRouteService)
├── Repositories/
└── Console/                    (cron jobs / scheduler)
database/migrations, seeders (COA default, golongan template, alamat)
routes/api.php
```

### 20.2 Web Vue
```
src/
├── modules/ (core, warehouse, meter, survey, finance, ...)
│   └── <module>/ (views, components, composables, api, store)
├── components/ (ui shared: Table, Card, StatWidget, Chart)
├── layouts/ (SidebarLayout, AuthLayout)
├── router/, stores/, composables/, api/ (axios instance + Sanctum session/CSRF)
└── assets/theme (tailwind config token biru lembut)
```

### 20.3 Flutter — lihat bagian 14.4.

---

## 21. Roadmap Pengembangan

| Fase | Fokus | Modul | Durasi |
|------|-------|-------|--------|
| 0 | Fondasi multi-tenant (Laravel+MySQL), RBAC dinamis, entitlement middleware, billing SaaS, seed alamat | Platform | ~4 mgg |
| 1 | Master data, alamat berjenjang, pelanggan, tarif+komponen, penagihan, **payment gateway**, keuangan dasar | CORE | ~5 mgg |
| 2 | OCR KTP, survey+GPS, approval, eskalasi, payment pemasangan, lifecycle | SRV | ~3 mgg |
| 3 | OCR meter, **plotting rute baca meter**, periode, edge cases, verifikasi, mobile petugas | MTR | ~4 mgg |
| 4 | Material, supplier, multi-gudang, PO, transfer 3-level, opname, jurnal persediaan benar | WH | ~3 mgg |
| 5 | Keuangan Advance ala Accurate (AR/AP, fixed asset, pajak/e-Faktur, budgeting, bank recon) | FIN+ | ~6 mgg |
| 6 | Pengaduan/CRM, notifikasi, **mobile pelanggan (tagihan+pemakaian+bayar)** | CRM, APP | ~4 mgg |
| 7 | Dashboard per role, laporan konsolidasi, design polish, testing, hardening, deploy, docs | Semua | ~4 mgg |
| | **TOTAL** | | **~33 mgg (~7.5–8 bln)** |

> MVP jual cepat: **Fase 0+1+3 (Platform+CORE+MTR)** ~13 minggu (sudah bisa: pelanggan, tagihan, bayar, baca meter berbasis rute, mobile pelanggan+petugas dasar).

---

## 22. Risiko & Mitigasi

| Risiko | Kmk | Dampak | Mitigasi |
|--------|:---:|:---:|----------|
| Isolasi tenant bocor (Global Scope terlewat) | Sedang | Sangat Tinggi | Trait wajib di semua model; test; code review; hindari query raw tanpa scope |
| Jurnal persediaan salah | Rendah | Sangat Tinggi | Terapkan bagian 12; review akuntan |
| OCR meter/KTP tidak akurat | Sedang | Tinggi | Wajib konfirmasi/koreksi + estimasi terstruktur |
| Jurnal tidak balance | Rendah | Sangat Tinggi | Validasi backend wajib |
| Webhook payment dobel/hilang | Sedang | Tinggi | Signature verify + idempotency + reconciliation harian |
| Rute baca tumpang tindih/pelanggan terlewat | Sedang | Sedang | 1 jalan = 1 rute; dashboard deteksi jalan tanpa rute |
| Scope FIN+ terlalu besar | Tinggi | Tinggi | Jual bertahap; MVP dulu |
| Keamanan PII/KTP (UU PDP) | Sedang | Tinggi | Enkripsi, akses restricted, audit log |
| Offline sync konflik (petugas lapangan) | Sedang | Sedang | Queue + timestamp + resolusi konflik server-authoritative |

---

## 23. Hal yang Perlu Dikonfirmasi

**Bisnis:** harga tiap modul & tier? tagihan SaaS tahunan/bulanan? trial berapa lama?
**Operasional:** timer eskalasi (8/12 jam)? batas expired pemasangan? denda overdue (flat/persen)? isolir setelah N bulan + biaya buka isolir? digit meter (5/6)?
**Baca meter:** ✅ rute penugasan TETAP (sudah diputuskan). ✅ master alamat input manual (sudah diputuskan). Perlu tampilan peta GPS sebaran pelanggan?

**Akuntansi:** COA baku Pemda atau sendiri? valuasi FIFO/Average? material pemasangan dikapitalisasi (rekomendasi: ya)? e-Faktur sungguhan atau ekspor CSV? kop surat per PDAM (sudah diakomodasi)?
**Payment:** provider Midtrans saja atau multi (Xendit/DOKU)? perlu fitur refund?
**Teknis:** target jumlah PDAM & pelanggan tahun 1? tingkat offline mode petugas?

---

*Living document. v3.1 memfinalkan tech stack (Laravel + Vue + MySQL + Flutter clean architecture), menambah plotting rute baca meter, struktur alamat berjenjang, detail payment gateway, aplikasi mobile per role dengan fitur pemakaian & tagihan pelanggan, serta design system biru lembut.*

**Yusril Eka Mahendra - 2026**
