# 02 — Dokumentasi Flow Proses Bisnis (Rinci)
## Platform SaaS Manajemen PDAM (Modular Multi-Tenant)

**Versi:** 2.1 (flow + pointer tooling) | **Diperbarui:** 7 September 2026 — implementasi Tahap 6 (stock-out+jurnal) kini aktif: lihat `temuan2.md` §10 M-10 dan `backend/tests/Feature/InstallationMaterialStockOutTest.php` <!-- doc-sync:note -->
**Pendamping:** PRD.md v3.7 — khususnya Bagian 6.B (Katalog Role Lengkap per Modul)

> **Dokumen terkait:** [`temuan2.md`](temuan2.md) sebagai sumber status saat ini · [`PRD.md`](PRD.md) sebagai target produk · [`README.md`](README.md) · [`task.md`](task.md) dan [`temuan.md`](temuan.md) sebagai arsip · [`HANDOVER.md`](HANDOVER.md) · [`SECURITY_CHECKLIST.md`](SECURITY_CHECKLIST.md) sebagai baseline internal · [`backend/DEPLOY.md`](backend/DEPLOY.md) sebagai runbook draft · [`backend/SEED_DATA.md`](backend/SEED_DATA.md)
>
> **Contoh nyata (data demo seeder):** flow di dokumen ini sudah terwujud pada **3 tenant demo** —
> **PDAM Canada** (data Pontianak, 27 modul lengkap), **PDAM Brazil** (data Surabaya, sebagian modul), dan
> **PDAM Sambas** (`pdam-sambas`, 27 modul lengkap, 3.000 pelanggan).
> Rantai Pelanggan → Baca Meter → Tagihan → Pembayaran → Jurnal → Neraca berjalan penuh & neraca balance.
> Detail: [`backend/SEED_DATA.md`](backend/SEED_DATA.md).
>
> **Batasan:** flow ini adalah target proses bisnis. Tidak semua langkah memiliki UI/API/integrasi yang
> production-ready. Status implementasi dan gap kontrak aktual mengikuti [`temuan2.md`](temuan2.md).
> Simbol seperti `✅ SELESAI` dalam diagram berarti akhir konseptual suatu flow target, bukan verifikasi production.
>
> **Status implementasi 15 Juli 2026:** 39/39 temuan audit aplikasi selesai dan suite MySQL 8.4.9,
> SQLite, Flutter, serta Python 3.11 lulus. Tenant FK `RESTRICT` berarti tenant tidak boleh dihapus fisik;
> lifecycle berakhir melalui workflow decommission. TLS/integrasi eksternal/operasional target dan model ML
> production-calibrated tetap gate di luar flow aplikasi.


---

## Cara Membaca Dokumen Ini

Dokumen ini menjabarkan **alur (flow)** aplikasi secara rinci dari tiga sudut pandang, supaya siapa pun paham cara kerja sistem:

1. **BAGIAN 1 — Flow Keseluruhan (End-to-End):** gambaran besar dari PDAM berlangganan sampai laporan keuangan.
2. **BAGIAN 2 — Flow per Modul (rinci):** tiap modul dijabarkan langkah demi langkah — role terlibat, aksi, kondisi/percabangan, perubahan data (status/tabel), jurnal, dan notifikasi.
3. **BAGIAN 3 — Flow per Role (rinci):** perjalanan tiap peran dari login sampai tugas selesai, plus role apa yang jadi input/output-nya.

**Legenda simbol:**
```
→         alur berlanjut          ↓ langkah berikutnya
├── └──   percabangan (pilihan/kondisi)
[MODUL]   modul penanggung jawab (CORE, SRV, MTR, WH, PROC, CHEM, FIN+, CRM, FSM, PLATFORM)
👤/🧑‍💼/🧑‍🔧 aktor (role)          📱 aksi Mobile (Flutter)   💻 aksi Web (Vue)
⚙️        aksi otomatis sistem (cron/trigger/kalkulasi)
📒        jurnal akuntansi otomatis    🔔 notifikasi terkirim
DB:       perubahan data/tabel/status
```

**Peta role ↔ modul** (ringkas — detail di PRD 6.B):
| Modul | Role utama |
|-------|-----------|
| PLATFORM | super_admin, admin_tenant |
| CORE | director, finance_head, finance_staff, cashier, customer_service, customer |
| SRV | hublang_head, survey_officer, survey_head, technical_head, installer_technician |
| MTR | meter_office, meter_officer |
| WH | warehouse_head, warehouse_staff |
| PROC | procurement_staff (+approval lintas modul) |
| CHEM | production_head, lab_analyst |
| FIN+ | accountant, tax_officer, asset_manager |
| CRM/CC | customer_service, call_agent, call_supervisor |
| FSM | field_dispatcher, field_supervisor, field_technician |
| AST/MNT | asset_manager, maintenance_technician |
| HR/GIS/DMS/BI | hr_head, hr_staff, gis_operator, dms_officer, data_analyst |

---

# BAGIAN 1 — FLOW KESELURUHAN (END-TO-END)

```
╔═══════════════════════════════════════════════════════════════════════╗
║  LEVEL PLATFORM (super_admin)                                           ║
║  PDAM berlangganan → provisioning tenant → seed data → Admin PDAM aktif ║
╚═══════════════════════════════════════════════════════════════════════╝
                                   ↓
╔═══════════════════════════════════════════════════════════════════════╗
║  LEVEL SETUP (admin_tenant)                                             ║
║  Setup alamat berjenjang → tarif → wilayah/gudang → rute → user & role  ║
║  Beli modul tambahan di Marketplace (opsional)                          ║
╚═══════════════════════════════════════════════════════════════════════╝
                                   ↓
╔═══════════════════════════════════════════════════════════════════════╗
║  LEVEL OPERASIONAL (siklus pelanggan)                                   ║
║                                                                         ║
║  [SRV]  Calon daftar → Hublang verifikasi → Survey → Kepala Survey ACC  ║
║             ↓                                                           ║
║  [CORE] Notif biaya pasang → bayar 📒 (Kas/Bank ↔ Pendapatan Pasang)    ║
║             ↓                                                           ║
║  [WH]   Target: sediakan material + stock-out + jurnal persediaan         ║
║             ↓                                                           ║
║  [SRV]  Teknisi pasang → customer AKTIF (meter 0 m³) → masuk rute       ║
║             ↓                                                           ║
║  [MTR]  Petugas baca meter per rute → verifikasi kantor → periode close ║
║             ↓                                                           ║
║  [CORE] Generate tagihan tiered+komponen 📒 (Piutang ↔ Pendapatan Air)  ║
║             ↓                                                           ║
║  [CORE] Notif tgl 23–25 → pelanggan bayar 📒 (Kas/Bank ↔ Piutang)       ║
║             ↓                                                           ║
║  [FIN+/CORE] Semua jurnal → Buku Besar → 5 laporan keuangan             ║
║                                                                         ║
║  PARALEL: [PROC] pengadaan · [CHEM] produksi air · [CRM] pengaduan ·    ║
║           [FSM] work order · lifecycle (isolir/sambung/balik nama)      ║
╚═══════════════════════════════════════════════════════════════════════╝
```

**Tiga domain berjalan bersamaan:** Operasional (daftar→pasang→baca), Keuangan (tagih→bayar→kas), Akuntansi (tiap uang bergerak → jurnal → laporan).

---

# BAGIAN 2 — FLOW PER MODUL (RINCI)

---

## 2.0 [PLATFORM] Flow Provisioning Tenant

**Role terlibat:** `super_admin` (utama), `admin_tenant` (penerima).
**Tujuan:** mendaftarkan PDAM baru agar langsung bisa dipakai.

```
👑 super_admin 💻
 1. Menu "Kelola Tenant" → "Tambah PDAM Baru"
 2. Isi profil PDAM: nama, kota/provinsi, logo, kop surat, kontak,
    email admin pertama, paket langganan awal
 3. Klik "Provisioning"
      ⚙️ DB: INSERT pdam_organizations (subscription_status=active)
      ⚙️ DB: subscription_modules → CORE (status=active,
             activation_method=free_default, amount_paid=0)
      ⚙️ SEED: chart_of_accounts default (template PDAM)
      ⚙️ SEED: tariff_categories + tariff_tiers (17 golongan template)
      ⚙️ SEED: roles bawaan (director, finance_head, ... ) + preset permission
      ⚙️ SEED: billing_settings default (due_day, denda, isolir_after_months)
      ⚙️ DB: users → 1 akun admin_tenant (is_tenant_admin=true)
      ⚙️ modul berbayar → subscription_modules status=locked
      🔔 email undangan ke admin_tenant (link set password)
 4. 🛡️ admin_tenant → set password → login pertama
 ✅ SELESAI — tenant siap disetup
```

**Percabangan penting:** kalau PDAM bayar modul tambahan di muka (cash) → super_admin langsung unlock modul tsb di langkah 3 (activation_method=cash) — lihat 2.13.B.

---

## 2.1 [CORE] Flow Setup Awal (Master Data)

**Role terlibat:** `admin_tenant` (utama).
**Tujuan:** menyiapkan data master sebelum melayani pelanggan. Urutan penting karena saling bergantung.

```
🛡️ admin_tenant 💻
 1. STRUKTUR ALAMAT (berjenjang, wajib dulu):
      Provinsi → Kota → Kecamatan → Kelurahan → Jalan
      DB: provinces, cities, districts, villages, streets
      (tanpa street, pelanggan & rute tidak bisa dibuat)
 2. WILAYAH & GUDANG:
      buat zones (is_main untuk kantor utama)
      ⚙️ tiap zone baru → warehouse buffer otomatis terbuat
      DB: zones, warehouses
 3. TARIF: sesuaikan 17 golongan + tier + abonemen + admin + denda
      DB: tariff_categories, tariff_tiers, billing_settings
 4. RUTE BACA METER:
      buat meter_routes → assign streets ke rute (meter_route_streets)
      DB: meter_routes, meter_route_streets
 5. USER & ROLE:
      buat akun pegawai → pilih role bawaan → sesuaikan permission
      (hanya modul aktif yang muncul; lihat 2.14)
      DB: users, user_roles, (opsional user_permissions)
 6. (opsional) MARKETPLACE: beli modul tambahan (lihat 2.13)
 ✅ SELESAI — PDAM siap operasional
```

**Validasi/kondisi:**
- Jalan (`street`) yang belum masuk rute manapun → ditandai sistem (agar tidak ada pelanggan terlewat saat baca meter).
- COA & golongan hasil seed boleh diubah, tapi akun inti (Kas, Piutang, Pendapatan Air) tidak boleh dihapus (dipakai jurnal otomatis).

---

## 2.2 [SRV+CORE+WH] Flow Pemasangan Baru / Sambungan Baru

**Flow terpanjang & terpenting.** Role terlibat: `customer` (calon), `customer_service`, `hublang_head`, `survey_officer`, `survey_head`, `technical_head`, `warehouse_head`, `warehouse_staff`, `installer_technician`, `cashier`.

### TAHAP 1 — Pendaftaran
```
Jalur A (mandiri):  👤 calon pelanggan 📱
Jalur B (ke kantor):🧑‍💼 customer_service 💻 input atas nama calon

 1. Buka "Daftar Sambungan Baru"
 2. Foto KTP → ⚙️ provider OCR yang dikonfigurasi mengekstrak NIK, nama, alamat
    Provider kosong/tidak siap → fail closed; file tidak disimpan sebagai hasil OCR sukses
 3. Review & koreksi hasil OCR
 4. Pilih alamat berjenjang (Prov→...→Jalan) + no rumah/RT/RW
 4b. Tap "Tandai Lokasi Rumah" → 🗺️ peta terbuka → ⚙️ auto-capture GPS HP
     sebagai titik AWAL → pelanggan boleh GESER pin bila meleset
     DB: latitude, longitude, location_source=customer_pin, location_accuracy
 5. Input email + no HP → Submit
    DB: customer_prospects (status=pending_review, nik ENCRYPTED,
        ktp_photo_url, ktp_ocr_raw, latitude, longitude, location_source)
    🔔 ke hublang_head
```

### TAHAP 2 — Verifikasi & Assign Survey
```
🧑‍💼 hublang_head 💻
 1. Buka antrian status=pending_review
 2. Cek data + foto KTP
    ├── VALID   → pilih survey_officer → assign
    │      DB: status=surveying, assigned_surveyor_id
    │      🔔 ke survey_officer terpilih
    └── INVALID → tolak + alasan
           DB: status=rejected, rejection_reason
           🔔 ke calon (selesai)
```

### TAHAP 3 — Survey Lapangan
```
🧑‍🔧 survey_officer 📱 (lapangan) / 💻 (kantor)
 1. Buka "Tugas Survey" → pilih calon
 2. Tap peta → 🗺️ pin titik pelanggan (customer_pin) tampil sebagai DUGAAN
    ⚙️ surveyor FISIK di depan rumah → "Ambil Titik GPS Sekarang" (akurat)
    ATAU geser pin manual bila GPS meleset (kolong/pohon/sinyal)
    DB: latitude, longitude di-OVERRIDE, location_source=surveyor_verified
        (INI koordinat FINAL — dipakai peta GIS, rute baca, node berwarna)
 3. Isi form: foto rumah (min 2), jarak ke pipa utama, kondisi bangunan,
    aksesibilitas, estimasi material (jsonb),
    rekomendasi (feasible/not_feasible)
 4. Submit
    DB: survey_reports (baru), customer_prospects.status=survey_submitted
    🔔 ke survey_head
```

### TAHAP 4 — Approval Kepala Survey (3 keputusan)
```
🧑‍💼 survey_head 💻 → review survey_reports
 ├── APPROVED (lokasi layak)
 │     DB: prospect.status=survey_approved
 │     → lanjut TAHAP 5
 ├── REJECTED (lokasi TIDAK layak — kerja petugas benar)
 │     DB: prospect.status=rejected; teruskan ke hublang_head
 │     🧑‍💼 hublang_head 🔔 beri tahu calon lokasi tak memenuhi syarat (selesai)
 └── RE-SURVEY (laporan buruk: foto buram/data kurang)
       DB: prospect.status=re_survey_needed, review_notes (alasan spesifik)
       🔔 kembali ke survey_officer → ulangi TAHAP 3
```

### TAHAP 5 — Notifikasi & Pembayaran Biaya Pemasangan
```
[CORE] ⚙️ hitung biaya pemasangan (dari estimasi material + tarif jasa)
       DB: payments (payment_type=installation_fee, status=pending, expiry)
       🔔 ke calon: "Biaya pemasangan Rp X, bayar sebelum <expiry>"
       ⚙️ timer eskalasi jalan (8–12 jam — nilai di config)

 👤 calon 📱 bayar (QRIS/VA/e-wallet)  ATAU  🧑‍💼 cashier 💻 terima tunai di loket
   ├── BAYAR → ⚙️ Midtrans webhook (verifikasi signature + idempotency)
   │     DB: payments.status=success; prospect.status=payment_paid
   │     📒 DEBIT Kas/Bank | KREDIT Pendapatan Pemasangan Baru
   │     🔔 bukti bayar ke calon; notif ke technical_head (siap dijadwalkan)
   └── TIDAK RESPONS > batas → ⚙️ cron hublang_escalation (tiap jam)
         🔔 ke hublang_head: "Calon X belum bayar" + tombol "Hubungi Manual"
         🧑‍💼 hublang_head → telepon
           ├── berhasil → beri waktu tambah (tetap tunggu bayar)
           └── gagal dihubungi → DB: prospect.status=payment_expired (selesai)
```

### TAHAP 6 — Penyediaan Material
> **Status saat ini (7 Sep 2026):** diimplementasikan penuh di `InstallationService::orderMaterials/complete` —
> order = reservasi stok di gudang utama (`mode=reserved`, `stock_reserved=true`), dan saat pemasangan selesai
> (`complete()`) stok di-stock-out + jurnal kapitalisasi (`DEBIT Aset Jaringan` / `KREDIT Persediaan`,
> `accounting_posted=true`). `planning_only` hanya muncul ketika modul WH nonaktif, material tak terselesaikan,
> atau tidak ada gudang utama (payload menyertakan `reason` eksplisit). Bukti: `backend/tests/Feature/InstallationMaterialStockOutTest`.

```
🧑🔧 technical_head 💻 → buat Order Material untuk lokasi pemasangan
 🧑💼 warehouse_head 💻 → cek stok 3-level (detail di 2.5):
   CEK 1 buffer wilayah sendiri cukup? ├ YA → keluarkan
                                         └ TIDAK ↓
   CEK 2 lateral transfer wilayah lain layak? ├ YA → transfer lateral
                                               └ TIDAK ↓
   CEK 3 transfer dari Gudang Utama → buffer wilayah
 🧑‍🔧 warehouse_staff → keluarkan material dari buffer
   DB: material_stocks −, material_transactions (stock_out, ref=installation)
   📒 DEBIT Aset Jaringan | KREDIT Persediaan Material (kapitalisasi)
```

### TAHAP 7 — Pemasangan & Aktivasi (Target)
```
🧑‍🔧 technical_head 💻 → jadwalkan + assign installer_technician
   DB: prospect.status=installation_scheduled
 🧑‍🔧 installer_technician 📱 → pasang di lapangan → foto hasil + material dipakai
   DB: prospect.status=installed
   🔔 ke hublang_head
 🧑‍💼 hublang_head 💻 → konfirmasi ke pelanggan
   ⚙️ buat akun customer + assign ke meter_route (dari street_id)
   ⚙️ catat meter awal 0 m³
   DB: customers (status=active), meter_serial_number, installation_date
       meter_readings awal (reading_value=0)
 ✅ SELESAI — pelanggan resmi aktif, masuk siklus baca meter bulanan
```

---

## 2.3 [MTR] Flow Baca Meter Bulanan

**Role terlibat:** `meter_office` (kantor), `meter_officer` (lapangan).
**Tujuan:** mencatat pemakaian tiap pelanggan per bulan berbasis rute.

```
🏢 meter_office 💻
 1. Buka periode baca
    DB: reading_periods (status=open, period=YYYY-MM, per zone)
    🔔 ke semua meter_officer terkait
─────────────── LAPANGAN ───────────────
🧑‍🔧 meter_officer 📱
 2. "Tugas Rute" → daftar pelanggan di rute yang di-assign
    (⚙️ hanya rutenya; urut jalan + no rumah untuk efisiensi jalan kaki)
 3. Tiba di lokasi → foto rumah (bukti hadir) → foto meter
 4. Kondisi meter?
    ├── TERBACA → ⚙️ OCR angka → petugas konfirmasi/koreksi
    │     DB: reading_type = ocr_confirmed | manual_corrected
    └── TIDAK TERBACA (buram/kolong/dipagar/binatang)
          → input estimasi + pilih alasan (dropdown)
          DB: reading_type = estimated, unreadable_reason
 5. Submit (offline → antre → sync saat online)
    DB: meter_readings (period_id, route_id, reading_value, foto2)
─────────────── KANTOR ───────────────
🏢 meter_office 💻
 6. Verifikasi & tangani edge case:
    ⚙️ current < previous → cek ROLLOVER → konsumsi=(10^digit−prev)+curr, flag
    ⚙️ ganti meter → gabung (final lama + awal baru) via meter_replacements
    ⚙️ konsumsi negatif/nol mencurigakan → is_flagged, tidak auto-tagih
    ⚙️ anomali (>2× rata-rata 3 bln / <30%) → is_flagged, flag_reason
    petugas koreksi bila perlu → set verified_by
 7. Tutup periode
    DB: reading_periods.status=closed
    🔔 ke finance_head (siap generate tagihan)
 ✅ SELESAI
```

**Catatan lintas modul:** kalau modul **METX** aktif, anomali otomatis masuk `meter_anomalies` untuk analitik lebih dalam (deteksi meter rusak/dimanipulasi).

---

## 2.4 [CORE] Flow Penagihan & Pembayaran Bulanan

**Role terlibat:** `finance_head` (generate), `customer` (bayar), `cashier` (loket), sistem (cron).

```
🧑‍💼 finance_head 💻
 1. Generate tagihan (hanya periode status=closed)
    ⚙️ konsumsi = current_reading − previous_reading
    ⚙️ tiered per golongan (ember bertingkat):
         Tier1 (0–10) ×h1 + Tier2 (>10–20) ×h2 + Tier3 (>20) ×h3
    ⚙️ + abonemen + pemeliharaan meter + admin (+ denda jika ada)
    ⚙️ minimum_charge_m3 diterapkan bila konsumsi < minimum
    DB: bills (amount_due, consumption, status=unpaid, due_date)
        bill_items (breakdown tiap komponen)
    📒 DEBIT Piutang Pelanggan | KREDIT Pendapatan Air (+Abonemen +Admin +Jasa Meter)
──────────────── PENAGIHAN ────────────────
⚙️ cron billing_notification (08:00):
    tgl 23 notif1 🔔 · tgl 24 notif2 🔔 · tgl 25 notif3 (jatuh tempo) 🔔
 👤 customer 📱 tap notif → bayar (QRIS/VA/e-wallet)
   ATAU 🧑‍💼 cashier 💻 terima tunai di loket
   ├── SUKSES → ⚙️ webhook (signature+idempotency)
   │     DB: payments.status=success, bills.status=paid
   │     📒 DEBIT Kas/Bank | KREDIT Piutang Pelanggan
   │     🔔 bukti bayar ke pelanggan
   └── BELUM BAYAR s/d tgl 26 → ⚙️ cron overdue_bills (06:00)
         DB: bills.status=overdue, penalty_amount dihitung
 ✅ SELESAI
```

**Escalation tunggakan:** jika overdue menumpuk ≥ N bulan → cron `auto_isolir_flag` menandai untuk isolir (lihat 2.9). Pelanggan bisa ajukan cicilan (lihat 2.8).

---

## 2.5 [WH+PROC] Flow Gudang & Pengadaan Material

**Role terlibat:** `warehouse_head`, `warehouse_staff`, `procurement_staff`, `technical_head`, `director`, `finance_head`, `finance_staff`.

### 2.5.A Pengadaan (Supplier → Gudang Utama) — multi-level approval
```
🧑‍💼 warehouse_head 💻 → buat PO         DB: purchase_orders (status=draft)
        ↓ (bila PROC aktif: 🧑‍💼 procurement_staff kelola vendor/tender dulu)
🧑‍🔧 technical_head → validasi kebutuhan  DB: status=tech_approved
        ↓
👔 director → ACC pengadaan               DB: status=dir_approved
        ↓
🧑‍💼 finance_head → validasi anggaran     DB: status=fin_approved
        ↓
🧑‍💼 finance_staff → eksekusi dana        DB: status=purchased
        ↓
📦 material tiba → 🧑‍💼 warehouse_head review qty & spek
   DB: material_stocks + (Gudang Utama), material_transactions (stock_in)
       purchase_orders.status=received
   📒 DEBIT Persediaan Material | KREDIT Kas/Bank atau Utang Usaha
        ↓
🧑‍🔧 technical_head → konfirmasi final    DB: status=completed
```
> Setiap penolakan di level manapun → status=rejected + rejection_reason, kembali ke warehouse_head.

### 2.5.B Distribusi (Gudang Utama → Buffer Wilayah)
```
🧑‍💼 warehouse_head 💻 → dashboard stok semua gudang (lihat buffer kritis)
   buat Transfer Order (transfer_type=main_to_buffer)
   DB: stock_transfers (status=draft→approved→in_transit),
       stock_transfer_items (quantity_requested)
        ↓
🧑‍🔧 warehouse_staff (wilayah) → konfirmasi diterima
   DB: quantity_received (bisa < requested jika rusak → selisih dicatat)
       stock_transfers.status=received→completed
   ⚙️ Gudang Utama −, Buffer Wilayah +   (pindah lokasi aset, TIDAK ada jurnal L/R)
```

### 2.5.C Lateral Transfer (antar Buffer) — situasi khusus
```
Syarat: urgent + donor surplus + jarak dekat + material ringan
🧑‍💼 warehouse_head → Transfer Order (transfer_type=buffer_to_buffer + reason)
   staf donor konfirmasi kirim → staf penerima konfirmasi terima
   ⚙️ stok donor −, penerima +
```

### 2.5.D Pemakaian & Reorder
```
🧑‍🔧 technical_head → order pakai (pemasangan/perbaikan)
🧑‍💼 warehouse_head/staff → keluarkan   DB: material_stocks −, material_transactions
   📒 pemasangan: DEBIT Aset Jaringan     | KREDIT Persediaan
   📒 perbaikan : DEBIT Beban Pemeliharaan| KREDIT Persediaan
⚙️ cron stock_check (07:00): stok<minimum → 🔔 warehouse_head → kembali ke 2.5.A
```

---

## 2.6 [CHEM] Flow Bahan Kimia / Produksi Air

**Role terlibat:** `production_head`, `lab_analyst` (+`procurement_staff` untuk PO).

```
🧑‍💼 production_head 💻
 1. Purchase Request kimia → PO (via alur PROC 2.5.A)
    DB: chemical_purchase_requests → purchase_orders
 2. Kimia tiba → input penerimaan
    DB: chemical_receipts, chemical_receipt_items
        (batch_number, expired_date, coa_number/url)
    📒 DEBIT Persediaan Bahan Kimia | KREDIT Kas/Bank atau Utang
 3. 🧑‍🔬 lab_analyst → QC test tiap batch
    DB: chemical_qc_tests (result)
    ├── passed     → masuk chemical_stocks (qc_status=passed)
    ├── quarantine → tahan
    └── rejected   → retur ke supplier
 4. ⚙️ Gudang kimia FEFO — sistem sarankan batch paling dekat expired dulu
 5. Pemakaian harian di IPA:
    input qty kimia + volume air diolah (m³)
    ⚙️ dose_per_m3 + biaya per m³ (HPP air)
    DB: chemical_usages, chemical_transactions (stock_out)
    📒 DEBIT Beban Produksi Air | KREDIT Persediaan Bahan Kimia
 6. Forecast kebutuhan + dashboard (stok/expired/konsumsi vs produksi)
    ⚙️ batch expired/rusak → 📒 DEBIT Kerugian Persediaan | KREDIT Persediaan Kimia
```

---

## 2.7 [CORE+FIN+] Flow Keuangan & Akuntansi

**Role terlibat:** `finance_head`, `finance_staff`, `accountant`, `tax_officer`, `asset_manager`, `director` (view).

### 2.7.A Jurnal otomatis (ringkas)
```
Kejadian                    DEBIT                KREDIT
Tagihan digenerate          Piutang Pelanggan    Pendapatan Air (+komponen)
Bayar tagihan               Kas/Bank             Piutang Pelanggan
Bayar biaya pemasangan      Kas/Bank             Pendapatan Pemasangan
Beli material               Persediaan Material  Kas/Bank / Utang
Material dipakai (pasang)   Aset Jaringan        Persediaan Material
Material dipakai (perbaikan)Beban Pemeliharaan   Persediaan Material
Beli kimia                  Persediaan Kimia     Kas/Bank / Utang
Kimia dipakai               Beban Produksi Air   Persediaan Kimia
Penyusutan bulanan (cron)   Beban Penyusutan     Akumulasi Penyusutan
⚠️ SUM(DEBIT)=SUM(KREDIT) atau transaksi DITOLAK.
```

### 2.7.B Jurnal → 5 laporan
```
journal_entry_lines (sumber tunggal)
  ├→ GROUP BY akun+tgl → Buku Besar
  ├→ SUM D & K         → Neraca Saldo (harus balance)
  ├→ Pendapatan/Biaya  → Laba Rugi
  ├→ Aset/Kewajiban    → Neraca
  └→ Mutasi Kas        → Arus Kas
```

### 2.7.C FIN+ (bila dibeli) — per role
```
🧑‍💼 accountant  → AR/AP, faktur, aging, jurnal penyesuaian, rekonsiliasi,
                   tutup & lock periode (accounting_periods)
🧑‍💼 tax_officer → PPN/PPh, ekspor e-Faktur (CSV/XML DJP), SPT Masa
🧑‍💼 asset_manager → register fixed_assets, ⚙️ cron depreciation_run bulanan
🧑‍💼 finance_staff → input recurring_transactions, jurnal manual
```

---

## 2.8 [CORE] Flow Cicilan Tunggakan (Approval)

**Role terlibat:** `customer`, `finance_head`/`hublang_head` (buat & approve), `director` (ACC besar), `cashier`.

```
👤 customer menunggak (mis. 4 bln) → datang ke kantor minta cicil
        ↓
🧑‍💼 finance_head / hublang_head 💻 → buat DRAFT:
   pilih bills digabung + jumlah termin + jatuh tempo + DP + catatan
   DB: installment_plans (status=pending_approval),
       installment_plan_bills, installment_schedules
        ↓
APPROVAL (ambang di billing_settings):
   ├── kecil/termin sedikit → cukup finance_head/hublang_head
   └── besar/termin panjang → WAJIB director (core.installment.approve_director)
   ├── Approve → DB: status=active; bills terkait → in_installment
   └── Reject  → DB: status=rejected (tetap harus lunas penuh)
        ↓
👤 customer bayar per termin (gateway / 🧑‍💼 cashier loket)
   DB: installment_schedules.status=paid, payment_id
   📒 tiap termin: DEBIT Kas/Bank | KREDIT Piutang
   (TIDAK ada jurnal saat plan dibuat — hanya reschedule piutang lama)
        ↓
   ├── semua termin lunas → DB: plan.status=completed, bills→paid ✅
   └── termin mangkir (cron installment_reminder, lewat grace)
         DB: plan.status=defaulted → tunggakan aktif lagi → bisa lanjut isolir
```

---

## 2.9 [CORE+SRV/FSM] Flow Lifecycle Pelanggan

**Role terlibat:** `hublang_head`, `field_dispatcher`/`technical_head`, `installer_technician`/`field_technician`, `cashier`.

```
customer AKTIF
 ├── ISOLIR (nunggak ≥ N bln, ⚙️ cron auto_isolir_flag)
 │     🧑‍💼 hublang_head → perintahkan isolir → 🧑‍🔧 teknisi eksekusi
 │     DB: disconnections; customers.status=isolated
 │     → bayar tunggakan + biaya buka → status=reconnected (aktif lagi)
 │        DB: reconnections; 📒 Kas/Bank ↔ Piutang & Pendapatan Buka Isolir
 ├── TUTUP SEMENTARA (rumah kosong, atas permintaan)
 │     DB: status=suspended_temporary → bisa diaktifkan kembali
 ├── BALIK NAMA (ganti pemilik)
 │     DB: ownership_transfers (data lama disimpan) → approval → nama baru
 └── BERHENTI PERMANEN → DB: status=terminated
DB umum: setiap perubahan → customer_status_history (audit)
```

---

## 2.10 [CRM/CC] Flow Pengaduan & Call Center

**Role terlibat:** `customer`, `customer_service`, `call_agent`, `call_supervisor`, `hublang_head`, teknisi.

```
Kanal masuk:
 👤 customer 📱 form pengaduan + foto   ATAU  📞 call_agent 💻 catat dari telepon
        ↓
   DB: complaints (status=open, sla_due_at dihitung),
       (jika telepon) call_logs → complaint_id
   🔔 ke customer_service / hublang_head
        ↓
🧑‍💼 customer_service 💻 → terima → assign penanganan
   DB: complaints.assigned_to; complaint_tracks (log tiap perubahan)
        ↓
   Jenis?
   ├── Administratif (tagihan salah, dll) → selesaikan di kantor
   └── Teknis (bocor/mati air) → buat repair_order / work_order (FSM)
         🧑‍🔧 teknisi ke lapangan → pakai material gudang → selesai
        ↓
   ⚙️ SLA dipantau; lewat → 🔔 eskalasi ke call_supervisor/hublang_head
   selesai → DB: complaints.status=resolved
   🔔 ke pelanggan → 👤 beri feedback/rating (customer_feedbacks) ✅
```

---

## 2.11 [FSM] Flow Field Service & Work Order

**Role terlibat:** `field_dispatcher`, `field_supervisor`, `field_technician`.
**Tujuan:** kelola pekerjaan lapangan (bocor, ganti meter, isolir, sambung, cek kualitas air) dengan SLA & tracking.

```
🧑‍💼 field_dispatcher 💻 → buat Work Order (dari pengaduan/jadwal/permintaan)
   DB: work_orders (type, priority, status=open, sla_due_at)
        ↓
   assign ke field_technician (lihat lokasi & beban kerja)
   DB: assigned_technician_id; 🔔 ke teknisi
        ↓
🧑‍🔧 field_technician 📱 → terima WO → menuju lokasi
   ⚙️ GPS tercatat (technician_locations)
   kerjakan → foto progress + material dipakai + e-sign pelanggan
   DB: work_order_logs (status per tahap, foto, signature)
        ↓
   ├── selesai → DB: work_orders.status=completed
   └── butuh eskalasi → 🧑‍💼 field_supervisor 💻 monitor peta + approve/eskalasi
        ↓
🧑‍💼 field_supervisor → evaluasi SLA & kualitas
 ✅ SELESAI (material terpakai → 📒 Beban/Aset ↔ Persediaan via WH)
```

---

## 2.12 [AST+MNT] Flow Aset & Pemeliharaan

**Role terlibat:** `asset_manager`, `maintenance_technician`.

```
🧑‍💼 asset_manager 💻
 1. Register aset (IPA, pompa, reservoir, kendaraan, jaringan)
    DB: assets / fixed_assets
 2. Buat jadwal pemeliharaan preventive
    DB: maintenance_schedules (interval, next_due_date, checklist)
 ⚙️ jatuh tempo → 🔔 ke maintenance_technician
        ↓
🧑‍🔧 maintenance_technician 📱 → kerjakan → catat sparepart & hasil
    DB: maintenance_records (parts_used, cost, result)
    📒 sparepart dari gudang: DEBIT Beban Pemeliharaan | KREDIT Persediaan
        ↓
 ⚙️ cron depreciation_run (bln): 📒 DEBIT Beban Penyusutan | KREDIT Akumulasi Penyusutan
 disposal aset → 📒 catat pelepasan (hapus buku)
```

---

## 2.13 [PLATFORM] Flow Marketplace & Aktivasi Modul

**Role terlibat:** `admin_tenant`, `super_admin`.

### 2.13.A Beli via Payment Gateway (self-service)
```
🛡️ admin_tenant 💻 → "Marketplace Modul" → pilih modul (mis. METX)
   route web: /marketplace; tenant selalu diturunkan dari session
   ⚙️ cek dependency: METX butuh MTR
   ├── MTR belum aktif → tombol beli nonaktif + saran "beli MTR / paket"
   └── MTR aktif → buat order SaaS pending yang idempotent + URL pembayaran Snap
       DB: expanded module lines immutable + harga/promo hasil hitung backend
       tidak ada aktivasi sebelum settlement
   ⚙️ webhook signed + amount-matched sukses → settlement atomik/idempotent
       → DB: subscription_modules (status=active,
      activation_method=gateway) → menu & API modul terbuka ✅
   ⚙️ promo aktif? → harga dihitung ulang backend, catat promo_redemptions
```

> **Status implementasi 15 Juli 2026:** flow tenant `/marketplace`, API katalog/purchase/settlement,
> isolasi tenant, dependency, no-preactivation, dan settlement sudah diuji. Jalur platform/manual tetap ada.

### 2.13.B Aktivasi Manual oleh Super-Admin (cash)
```
👤 PDAM transfer/bayar tunai ke super_admin (di luar sistem)
🛡️→👑 super_admin 💻 → "Kelola Langganan Tenant" → pilih PDAM
   toggle UNLOCK modul + isi metode (cash/manual_transfer), nominal, bukti, expiry
   DB: subscription_modules (status=active, activation_method=cash) ✅
   (tenant nunggak/kontrak habis → super_admin LOCK → API 403, data aman)
⚙️ cron subscription_check (01:00): expired → auto-lock (opsional grace)
```

### 2.13.C Promo Musiman
```
👑 super_admin → buat promo + rentang tanggal (starts_at/ends_at)
⚙️ cron promo_activation (00:05): dalam periode→active, lewat→expired
🛡️ admin_tenant buka Marketplace → harga coret + harga promo + hitung mundur
```

### 2.13.D Laporan BI Terjadwal
```
🛡️ data_analyst → buat schedule (dataset allowlist, format CSV/HTML, timezone)
   ⚙️ permission + entitlement modul sumber diverifikasi saat create dan run
   ⚙️ next run dihitung UTC → dispatcher tiap menit → job queue default
   ⚙️ slot run unik mencegah duplikasi → ledger history menyimpan status
   🔒 artifact disimpan privat → download hanya lewat endpoint berizin
```

> CRUD, run manual, history, download privat, job, dan dispatcher tersedia. Worker queue `default` dan
> `php artisan schedule:run` setiap menit wajib aktif di deployment.

---

## 2.14 [CORE/RBAC] Flow Manajemen User & Permission

**Role terlibat:** `admin_tenant`.

```
🛡️ admin_tenant 💻 → "Kelola User" → "Tambah User"
 1. Isi data pegawai (nama, email, jabatan, zona)
 2. Pilih role bawaan (preset) atau custom
 3. ⚙️ sistem tampilkan HANYA modul aktif/dibeli
    (modul belum dibeli → permission-nya tidak muncul sama sekali)
 4. Centang permission per resource+aksi:
      Gudang: Material ☑View ☑Create ☑Update ☐Delete ; Stok ☑View ☐Adjust
      → user A full CRUD material; user B read-only
    DB: user_roles / user_permissions
 5. Simpan → user login → menu & tombol sesuai izin
⚙️ tiap request 2 gerbang: CheckModuleAccess (tenant punya modul?)
   + CheckPermission (user punya izin?) → dua-duanya lolos = boleh, else 403
```

---

# BAGIAN 3 — FLOW PER ROLE (RINCI)

Format tiap role: **Platform · Modul · Input (dari role/kejadian apa) · Aktivitas harian · Output (ke role/kejadian apa)**.

## 3.1 👑 super_admin (PLATFORM · Web)
- **Input:** PDAM baru berlangganan / bayar modul cash.
- **Aktivitas:** provisioning tenant (2.0); kelola harga & tier modul; lock/unlock modul per tenant (2.13.B); buat promo (2.13.C); monitor metrik lintas tenant; suspend/aktifkan tenant.
- **Output:** tenant + akun `admin_tenant` aktif; modul ter-unlock.
- **Batasan:** tidak mengurus operasional harian; akses data tenant selalu ber-audit.

## 3.2 🛡️ admin_tenant (PLATFORM/CORE · Web)
- **Input:** tenant baru dari super_admin.
- **Aktivitas:** setup master data (2.1); kelola user & permission (2.14); beli modul (2.13.A); lihat semua laporan PDAM-nya.
- **Output target:** master data dan user untuk role yang dikonfigurasi tersedia; kesiapan setiap role tetap bergantung pada API/UI, entitlement, integrasi, dan gate status di `temuan2.md`.

## 3.3 👔 director (CORE · Web)
- **Input:** PO menunggu ACC (2.5.A); cicilan besar (2.8); laporan periodik.
- **Aktivitas:** dashboard eksekutif (view KPI, keuangan, operasional); ACC pengadaan (dir_approved); ACC cicilan nominal besar.
- **Output:** PO lanjut ke finance_head; cicilan besar disetujui/ditolak.

## 3.4 🧑‍💼 finance_head / Kabag Keuangan (CORE+FIN+ · Web)
- **Input:** periode baca closed (dari meter_office); PR anggaran; pengajuan cicilan.
- **Aktivitas:** generate tagihan (2.4); konfigurasi tarif; validasi anggaran PO (fin_approved); buat/approve cicilan (2.8); hasilkan 5 laporan; (FIN+) supervisi AR/AP, pajak, aset.
- **Output:** tagihan terbit → notif pelanggan; PO lanjut ke finance_staff; laporan ke director.

## 3.5 🧑‍💼 finance_staff / Staf Keuangan (CORE+FIN+ · Web)
- **Input:** PO fin_approved; kebutuhan jurnal manual.
- **Aktivitas:** eksekusi pengeluaran dana (status=purchased); kelola buku besar; input jurnal manual; rekonsiliasi; recurring.
- **Output:** dana keluar → material dibeli; buku besar akurat.

## 3.6 🧑‍💼 cashier / Kasir Loket (CORE · Web)
- **Input:** pelanggan bayar tunai di kantor (tagihan/pemasangan/termin cicilan).
- **Aktivitas:** terima pembayaran tunai; cetak kuitansi; setor kas harian.
- **Output:** 📒 jurnal kas masuk; status bills/payment → paid.

## 3.7 🧑‍💼 customer_service / CS (CORE/CRM · Web)
- **Input:** pelanggan datang/telepon.
- **Aktivitas:** bantu daftar sambungan baru (2.2 jalur B); jawab pertanyaan tagihan; buat & assign tiket pengaduan (2.10).
- **Output:** prospek baru; tiket pengaduan; eskalasi bila perlu.

## 3.8 👤 customer / Pelanggan (CORE/APP · Mobile)
- **Input:** notifikasi tagihan; kebutuhan sambungan baru/pengaduan.
- **Aktivitas:** lihat & bayar tagihan; pantau grafik pemakaian; ajukan sambungan; buat pengaduan + tracking; ajukan cicilan (datang ke kantor).
- **Output:** pembayaran → 📒 kas; prospek/tiket baru.

## 3.9 🧑‍💼 hublang_head / Kepala Hublang (SRV · Web)
- **Input:** pendaftaran baru (pending_review); eskalasi pembayaran; pengaduan.
- **Aktivitas:** verifikasi & assign survey (2.2 T2); monitor eskalasi + "Hubungi Manual" (2.2 T5); konfirmasi pemasangan (2.2 T7); notif pelanggan ditolak; buat/approve cicilan.
- **Output:** survey ter-assign; calon jadi customer aktif; keputusan cicilan.

## 3.10 🧑‍🔧 survey_officer / Petugas Survey (SRV · Mobile/Web)
- **Input:** penugasan survey dari hublang_head; permintaan re-survey.
- **Aktivitas:** survey lapangan + isi form + GPS + rekomendasi (2.2 T3).
- **Output:** survey_report → ke survey_head.

## 3.11 🧑‍💼 survey_head / Kepala Survey (SRV · Web)
- **Input:** survey_report (survey_submitted).
- **Aktivitas:** review → Approve / Reject / Re-Survey + catatan (2.2 T4).
- **Output:** Approve→biaya pasang; Reject→hublang notif pelanggan; Re-Survey→balik ke petugas.

## 3.12 🧑‍🔧 technical_head / Kepala Teknik (SRV+WH · Web)
- **Input:** pembayaran pemasangan lunas; kebutuhan perbaikan; PO menunggu validasi teknik.
- **Aktivitas:** buat order material; validasi PO (tech_approved) + tembusan director; jadwalkan pemasangan/perbaikan + assign teknisi; konfirmasi terima material.
- **Output:** material keluar gudang; jadwal pemasangan; PO lanjut.

## 3.13 🧑‍🔧 installer_technician / Teknisi Pemasangan (SRV · Mobile)
- **Input:** jadwal pemasangan dari technical_head.
- **Aktivitas:** pasang di lapangan; catat material; foto hasil; update installed.
- **Output:** status installed → hublang_head konfirmasi → customer aktif.

## 3.14 🏢 meter_office / Koordinator Baca Meter (MTR · Web)
- **Input:** jadwal periode bulanan.
- **Aktivitas:** buka/tutup periode; CRUD rute + assign jalan & petugas; verifikasi pembacaan (anomali/flag/koreksi); monitor progress per rute; deteksi jalan tanpa rute.
- **Output:** periode closed → ke finance_head untuk tagihan.

## 3.15 🧑‍🔧 meter_officer / Petugas Baca Meter (MTR · Mobile)
- **Input:** periode open + rute yang di-assign.
- **Aktivitas:** baca meter per rute; foto rumah+meter; OCR/koreksi/estimasi; submit (offline→sync) (2.3).
- **Output:** meter_readings → verifikasi kantor.

## 3.16 🧑‍💼 warehouse_head / Kepala Gudang (WH · Web)
- **Input:** order material (technical_head); alert stok minimum (cron).
- **Aktivitas:** dashboard stok multi-gudang; CRUD material; ajukan PO; putuskan sumber 3-level; buat transfer; review material masuk; opname.
- **Output:** material tersedia di buffer; PO diajukan; stok akurat.

## 3.17 🧑‍🔧 warehouse_staff / Staf Gudang-Wilayah (WH · Web)
- **Input:** transfer order; order pemakaian.
- **Aktivitas:** konfirmasi terima transfer (catat selisih); keluarkan material; bantu opname.
- **Output:** stok buffer terupdate; material untuk pemasangan/perbaikan.

## 3.18 🧑‍💼 procurement_staff / Pengadaan (PROC · Web)
- **Input:** kebutuhan pengadaan (PR).
- **Aktivitas:** kelola vendor & kontrak; buat tender; kelola bid; konversi PR→PO; evaluasi vendor.
- **Output:** PO siap masuk alur approval (2.5.A).

## 3.19 🧑‍💼 production_head / Kepala Produksi IPA (CHEM · Web)
- **Input:** kebutuhan bahan kimia; target produksi air.
- **Aktivitas:** master kimia & dosis; PR kimia; catat pemakaian harian + volume air; kelola FEFO; forecast; pantau HPP air (2.6).
- **Output:** air olahan; 📒 beban produksi; laporan konsumsi kimia.

## 3.20 🧑‍🔬 lab_analyst / Analis Lab-QC (CHEM · Web)
- **Input:** batch kimia baru diterima.
- **Aktivitas:** uji QC tiap batch; tetapkan passed/quarantine/rejected; catat COA.
- **Output:** batch layak pakai / retur.

## 3.21 🧑‍💼 accountant / Akuntan (FIN+ · Web)
- **Input:** transaksi harian; akhir periode.
- **Aktivitas:** AR/AP, aging, jurnal penyesuaian, rekonsiliasi bank, tutup & lock periode, laporan lanjutan/konsolidasi.
- **Output:** laporan keuangan lengkap; periode terkunci.

## 3.22 🧑‍💼 tax_officer / Staf Pajak (FIN+ · Web)
- **Input:** transaksi kena pajak.
- **Aktivitas:** kelola PPN/PPh; ekspor e-Faktur; SPT Masa.
- **Output:** file pajak untuk DJP.

## 3.23 🧑‍💼 asset_manager / Manajer Aset (AST · Web)
- **Input:** aset baru; jadwal pemeliharaan.
- **Aktivitas:** register aset; jadwal preventive; kelola penyusutan; disposal (2.12).
- **Output:** register aset akurat; 📒 penyusutan.

## 3.24 🧑‍🔧 maintenance_technician / Teknisi Pemeliharaan (MNT · Mobile)
- **Input:** jadwal maintenance jatuh tempo.
- **Aktivitas:** kerjakan maintenance; catat sparepart & hasil (2.12).
- **Output:** maintenance_record; kondisi aset terupdate.

## 3.25 🧑‍💼 field_dispatcher (FSM · Web)
- **Input:** pengaduan teknis / permintaan pekerjaan.
- **Aktivitas:** buat & assign Work Order; atur prioritas & SLA (2.11).
- **Output:** WO ter-assign ke field_technician.

## 3.26 🧑‍💼 field_supervisor (FSM · Web)
- **Input:** WO berjalan.
- **Aktivitas:** monitor real-time (peta + GPS teknisi); approve/eskalasi; evaluasi SLA.
- **Output:** WO terkontrol; laporan performa lapangan.

## 3.27 🧑‍🔧 field_technician (FSM · Mobile)
- **Input:** WO dari dispatcher.
- **Aktivitas:** kerjakan WO; update progress + foto + e-sign; pakai material (2.11).
- **Output:** WO completed; material terpakai.

## 3.28 🧑‍💼 call_agent / call_supervisor (CC · Web)
- **Input:** panggilan masuk/keluar.
- **Aktivitas:** agent catat call log + buat tiket; supervisor monitor SLA & rekaman.
- **Output:** tiket pengaduan; laporan call center.

## 3.29 🧑‍💼 hr_head / hr_staff (HR · Web)
- **Aktivitas:** kelola pegawai, absensi & shift, cuti, payroll, appraisal, pelatihan.
- **Output:** data SDM & penggajian.

## 3.30 🧑‍💼 gis_operator / dms_officer / data_analyst (GIS/DMS/BI · Web)
- **gis_operator:** pemetaan jaringan (pipa/valve/hydrant), insiden spasial.
- **dms_officer:** kelola dokumen (surat/kontrak/SOP), versi & approval, e-sign.
- **data_analyst:** bangun dashboard & report builder, sajikan KPI ke manajemen.

---

# BAGIAN 4 — RINGKASAN AUTOMASI (CRON)

| Job | Jadwal | Modul | Fungsi | Memicu role/aksi |
|-----|--------|-------|--------|------------------|
| billing_notification | 08:00 tgl 23–25 | CORE | Notif tagihan belum bayar | → customer |
| hublang_escalation | tiap jam | SRV | Calon >batas belum bayar pasang | → hublang_head |
| stock_check | 07:00 harian | WH | Stok < minimum | → warehouse_head |
| overdue_bills | 06:00 tgl 26 | CORE | Tagihan lewat tempo → overdue+denda | → finance |
| auto_isolir_flag | 06:00 tgl 1 | CORE | Tunggakan ≥ N bulan → flag isolir | → hublang_head |
| depreciation_run | 02:00 tgl 1 | FIN+/AST | Jurnal penyusutan | → asset_manager |
| recurring_journal | 03:00 harian | FIN+ | Transaksi berulang | otomatis |
| payment_reconciliation | 01:00 harian | CORE | Cocokkan Midtrans vs DB | → finance |
| subscription_check | 01:00 harian | PLATFORM | Langganan expired → lock modul | → super_admin |
| promo_activation | 00:05 harian | PLATFORM | Aktif/kadaluarsa promo | otomatis |
| installment_reminder | 08:00 harian | CORE | Ingatkan termin; mangkir→defaulted | → customer/hublang |

---

# BAGIAN 5 — MATRIKS ROLE → FLOW (Peta Cepat)

| Role | Flow yang dijalankan |
|------|----------------------|
| super_admin | 2.0, 2.13.B, 2.13.C |
| admin_tenant | 2.1, 2.13.A, 2.14 |
| director | 2.5.A (ACC), 2.8 (ACC besar) |
| finance_head | 2.4, 2.5.A, 2.7, 2.8 |
| finance_staff | 2.5.A, 2.7 |
| cashier | 2.4, 2.8 (loket) |
| customer_service | 2.2 (jalur B), 2.10 |
| customer | 2.2 (T1), 2.4, 2.8, 2.10 |
| hublang_head | 2.2 (T2/T5/T7), 2.8, 2.9, 2.10 |
| survey_officer | 2.2 (T3) |
| survey_head | 2.2 (T4) |
| technical_head | 2.2 (T6/T7), 2.5 |
| installer_technician | 2.2 (T7) |
| meter_office | 2.3 |
| meter_officer | 2.3 |
| warehouse_head | 2.2 (T6), 2.5 |
| warehouse_staff | 2.5.B/D |
| procurement_staff | 2.5.A, (PROC) |
| production_head | 2.6 |
| lab_analyst | 2.6 (QC) |
| accountant / tax_officer / asset_manager | 2.7.C, 2.12 |
| field_dispatcher/supervisor/technician | 2.11 |
| maintenance_technician | 2.12 |
| call_agent/supervisor | 2.10 |

---

*Dokumen target/desain ini mendampingi PRD.md v3.7. Semua role mengacu ke PRD Bagian 6.B; status implementasi dan audit selalu mengacu ke temuan2.md.*

**Yusril Eka Mahendra — 2026**
