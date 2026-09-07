# PDAM SaaS — Backend (Laravel)

Backend REST API untuk platform **PDAM SaaS** — sistem manajemen PDAM multi-tenant.
Satu instalasi melayani banyak PDAM (tenant), dengan katalog modul yang bisa
di-lock/unlock per tenant dan RBAC granular per aksi.

Dokumen ini adalah sumber utama setup backend dan mekanisme autentikasi. Detail fixture dan seluruh
kredensial demo dimiliki [`SEED_DATA.md`](SEED_DATA.md); deployment production dimiliki
[`DEPLOY.md`](DEPLOY.md); status verifikasi dimiliki [`../temuan2.md`](../temuan2.md).

> **Sumber status saat ini:** [`../temuan2.md`](../temuan2.md), termasuk enam gate persetujuan production canonical.
> Verifikasi 7 September 2026: backend SQLite **109/109 (768 assertions)**; route registry **387 rows —
> 368 method endpoint `/api/v1` (292 URI unik) + 19 web** (kanonis di [`../docs/COUNTS.json`](../docs/COUNTS.json), check
> `php artisan pdam:counts`); Vitest 7 file/**13 test** + build lulus; Flutter **44/44** + analyze; ML
> **29 test** (pytest; `test_calibration.py` gate §13 #6 — jalan di CI). Seeder kini router: production
> hanya `ProductionKernelSeeder` dan `DemoGuard` memblokir **mutlak** fixture demo/akun `12345678`
> (tanpa env escape; bukti `ProductionSeederIsolationTest`). Export menerima `csv|html|xlsx|pdf` native.
> CI lintas komponen + drill MySQL (privilege, backup/restore, audit command) di
> [`.github/workflows/ci.yml`](../.github/workflows/ci.yml); peta dokumen/fact sheet [`../docs/DOC_MAP.md`](../docs/DOC_MAP.md);
> tooling & runbook gate [`../ops/README.md`](../ops/README.md). Snapshot MySQL lama 89/89–15 Juli tetap tercatat di
> [`../temuan2.md`](../temuan2.md) §9.
>
> **Batasan klaim:** dokumen ini memverifikasi kontrol source dan test internal, bukan bukti pentest
> eksternal atau deployment production. Enam gate canonical production tetap didefinisikan di
> [`../temuan2.md`](../temuan2.md).
>
> Dokumen terkait: [`../README.md`](../README.md) · [`../PRD.md`](../PRD.md) dan [`../02_flow.md`](../02_flow.md) sebagai
> target/desain · [`../task.md`](../task.md) dan [`../temuan.md`](../temuan.md) sebagai arsip ·
> [`../SECURITY_CHECKLIST.md`](../SECURITY_CHECKLIST.md) sebagai baseline internal · [`../tests/security/OWASP_ASVS_AUDIT.md`](../tests/security/OWASP_ASVS_AUDIT.md)
> assessment internal · [`DEPLOY.md`](DEPLOY.md) sebagai runbook deployment · [`SEED_DATA.md`](SEED_DATA.md) ·
> [`../ml/README.md`](../ml/README.md) · keputusan bisnis [`../docs/BUSINESS_DECISIONS.md`](../docs/BUSINESS_DECISIONS.md).

---

## 1. Tech Stack

| Komponen        | Teknologi                          |
| --------------- | ---------------------------------- |
| Framework       | Laravel (PHP 8.3)                  |
| Auth            | Sanctum session web + token mobile |
| Database        | MySQL (`pdam_saas`)                |
| Multi-tenancy   | Application-level (global scope)   |

---

## 2. Setup Awal

```bash
# 1. Install dependency
composer install

# 2. Salin env (jika belum) & generate key
copy .env.example .env        # Windows
php artisan key:generate

# 3. Pastikan konfigurasi DB di .env sudah benar
#    DB_CONNECTION=mysql
#    DB_DATABASE=pdam_saas
#    DB_USERNAME=root
#    DB_PASSWORD=

# 4. Buat database `pdam_saas` di MySQL (jika belum ada)

# 5. Jalankan migration dan data demo awal tanpa menghapus tabel yang ada
php artisan migrate --seed

# 6. Jalankan server dev
php artisan serve
# → http://localhost:8000
```

> `php artisan migrate:fresh --seed` bersifat destruktif dan hanya untuk reset database demo. Workflow
> non-destruktif, bootstrap demo, dan reset penuh dijelaskan di [`SEED_DATA.md`](SEED_DATA.md).

---

## 3. Perintah Penting

| Perintah                                   | Fungsi                                                     |
| ------------------------------------------ | ---------------------------------------------------------- |
| `php artisan migrate`                      | Jalankan migrasi yang belum jalan                          |
| `php artisan migrate:fresh`                | Drop semua tabel + migrasi ulang (DATA HILANG)             |
| `php artisan migrate:fresh --seed`         | Migrasi ulang + semua seeder (development/staging; di production otomatis hanya kernel) |
| `php artisan pdam:counts [--write]`          | Drift-check / regenerasi inventaris → `../docs/COUNTS.json` |
| `php artisan pdam:audit-db-privileges`       | Bukti least-privilege akun DB + trigger append-only |
| `php artisan pdam:queue-health [--max-...]`  | Alarm backlog/failed_jobs worker |
| `php artisan pdam:integrations-health [--ping]` | Status Midtrans/FCM/OCR/ML/email |
| `php artisan pdam:ml-export-training-data`   | Ekspor CSV dataset latih ML (gate §13 #6) |
| `php artisan db:seed`                      | Jalankan semua seeder (`DatabaseSeeder`)                   |
| `php artisan db:seed --class=ModuleSeeder` | Jalankan satu seeder spesifik                              |
| `php artisan route:list --path=api`        | Lihat daftar route API                                     |
| `php artisan test`                         | Jalankan backend test suite                                |
| `npm test -- --run`                        | Jalankan Vitest frontend sekali                            |
| `npm run build`                            | Build production frontend                                  |
| `php artisan serve`                        | Jalankan server pengembangan                               |
| `php artisan queue:work --queue=default`   | Proses scheduled report dan job default                    |
| `php artisan schedule:run`                 | Dispatch task terjadwal; production menjalankan per menit  |

---

## 4. Daftar Seeder

Dijalankan berurutan oleh `DatabaseSeeder` (`database/seeders/DatabaseSeeder.php`). Pada environment
non-production router memanggil `ProductionKernelSeeder` (Permission → Module → RoleTemplate →
admin platform via env) lalu `DemoSeeder` (seluruh fixture demo di bawah, `PlatformAdminSeeder` paling
akhir); pada production hanya kernel yang berjalan dan semua seeder demo diblokir:

**Platform & fondasi:**

| Urutan | Seeder                  | Isi                                                                                   |
| ------ | ----------------------- | ------------------------------------------------------------------------------------- |
| 1      | `PlatformAdminSeeder`   | 1 akun Super-Admin platform (`superadmin@gmail.com`)                                  |
| 2      | `ModuleSeeder`          | 27 modul katalog (CORE default + 26 modul berbayar), 3 tier                           |
| 3      | `RoleTemplateSeeder`    | 35 role template global, termasuk `compliance_officer`                                |
| 4      | `PermissionSeeder`      | Permission granular pola `modul.resource.aksi` (snapshot seed terbaru: 202)            |
| 5      | `DemoTenantSeeder`      | 2 tenant demo (Canada & Brazil) + user per role template                                 |
| 6      | `MasterFinanceSeeder`   | COA standar + 17 golongan tarif Pontianak + billing settings (per tenant)             |
| 7      | `OperationalDataSeeder` | Data operasional terintegrasi: pelanggan, meter, tagihan, pembayaran, jurnal, neraca  |

**Pelengkap modul (17 seeder — mengisi tabel modul aktif & enterprise):**

| Urutan | Seeder                     | Modul / Isi                                                          |
| ------ | -------------------------- | ------------------------------------------------------------------- |
| 8      | `AddressSeeder`            | Master alamat berjenjang (provinsi→jalan) + detail rute meter       |
| 9      | `CustomerLifecycleSeeder`  | SRV: prospek, survey, isolir, balik nama                            |
| 10     | `CrmDetailSeeder`          | CRM: riwayat & feedback pengaduan                                   |
| 11     | `MeterExtendedSeeder`      | METX: lifecycle, stok, anomali meter (Canada)                       |
| 12     | `NotificationSeeder`       | APP: notifikasi in-app + chat                                       |
| 13     | `BillingExtraSeeder`       | BILL+: cicilan + penyesuaian tagihan                                |
| 14     | `WarehouseAdvancedSeeder`  | WH: PO, transfer stok, opname, repair order                         |
| 15     | `FinanceEnterpriseSeeder`  | FIN+: invoice AR/AP, bank, budget, pajak                            |
| 16     | `AssetSeeder`              | AST: aset tetap + penyusutan                                        |
| 17     | `ChemicalSeeder`           | CHEM: rantai bahan kimia IPA                                        |
| 18     | `ProcurementSeeder`        | PROC: vendor, tender, kontrak                                       |
| 19     | `FieldServiceSeeder`       | FSM: work order lapangan                                            |
| 20     | `MaintenanceSeeder`        | MNT: pemeliharaan preventif (butuh AST)                             |
| 21     | `HrSeeder`                 | HR: kepegawaian & payroll                                           |
| 22     | `DmsGisIntegrationSeeder`  | DMS/GIS/INT/CC: dokumen, GIS, integrasi, call center                |
| 23     | `SmartUtilitySeeder`       | Tier-3: IoT/SCADA, DMA/NRW, ML                                      |
| 24     | `PlatformCommerceSeeder`   | Komersial platform: price tier, bundle, promo, saas invoice         |
| 25     | `SambasTenantSeeder`       | Tenant demo ke-3 `pdam-sambas`: 27 modul, 3.000 pelanggan, alamat nyata Kab. Sambas, baca meter (foto+petugas), tunggakan/isolir, gudang & tawas, procurement, modul enterprise |

> Semua seeder **idempotent** & **tenant-aware** (data enterprise hanya untuk tenant yang modulnya
> aktif — Canada & SAMBAS full 27 modul, Brazil hanya modul aktif). Snapshot MySQL 8.4.9 dan SQLite terbaru
> menghasilkan **167 tabel dari 60 migration**; snapshot terkini **65 migration / 28 class seeder**
> (lihat `../docs/COUNTS.json`): `DatabaseSeeder`+`DemoSeeder`+`ProductionKernelSeeder`+`DemoGuard`;
> material order & trigger privacy-audit adalah dua migration September.
>
> **Tenant:** `DemoTenantSeeder` membuat Canada & Brazil; `SambasTenantSeeder` membuat tenant ke-3
> (`pdam-sambas`, 3.000 pelanggan) — kredensial & akun tambahan (7 petugas baca per rute) ada di
> [`SEED_DATA.md`](SEED_DATA.md) Section 2.

> Database kini memiliki 97 tenant FK `RESTRICT` dan 8 composite same-tenant meter actor FK. Jangan
> menghapus tenant secara fisik; gunakan workflow decommission. Tidak semua tabel memerlukan model:
> pivot, tabel framework, dan seeder-only/query-builder tables yang terdokumentasi sengaja tanpa model.

> Detail per seeder ada di `SEED_DATA.md` Section 8.

> **Produksi:** Jalur seed production telah dipisahkan (temuan2.md P1): `DatabaseSeeder` pada
> `APP_ENV=production` hanya menjalankan `ProductionKernelSeeder` (permission, katalog modul, role
> template, admin platform opsional via env `PLATFORM_ADMIN_*`) — seluruh fixture demo dan password
> `12345678` diblokir `DemoGuard` dan diuji oleh `ProductionSeederIsolationTest`. Provisioning tenant
> produksi lewat workflow Super-Admin, bukan seeder. Ketentuan lengkap ada di [`DEPLOY.md`](DEPLOY.md).


---

## 5. Autentikasi dan Login

Kredensial berikut hanya contoh demo. Daftar role lengkap dan kepemilikan kredensial berada di
[`SEED_DATA.md`](SEED_DATA.md).

### 5.1 Super-Admin (Platform)

Login lewat `POST /api/v1/platform/login` (tanpa kode PDAM).

| Email                  | Password   |
| ---------------------- | ---------- |
| `superadmin@gmail.com` | `12345678` |

### 5.2 User Tenant (PDAM)

Login lewat `POST /api/v1/login` dengan menyertakan **kode PDAM**. Email user demo
mengikuti pola `{role_code}@gmail.com` dan **sama di ketiga tenant** — yang
membedakan adalah kode PDAM saat login. (Tenant Sambas juga menambah 7 petugas baca meter
`meter_officer1..7@gmail.com` — lihat [`SEED_DATA.md`](SEED_DATA.md) Section 2.)

| Field       | PDAM Canada     | PDAM Brazil     | PDAM Sambas     |
| ----------- | --------------- | --------------- | --------------- |
| `pdam_code` | `pdam-canada`   | `pdam-brazil`   | `pdam-sambas`   |

Contoh body login sebagai Direktur di PDAM Canada:

```json
{
  "pdam_code": "pdam-canada",
  "email": "director@gmail.com",
  "password": "12345678"
}
```

### 5.3 Web: session cookie + CSRF

Browser memakai Sanctum stateful session. Urutannya:

1. Gunakan satu host secara konsisten, misalnya `http://127.0.0.1:8000`.
2. `GET /sanctum/csrf-cookie` dengan credentials/cookie aktif.
3. `POST /api/v1/login` untuk tenant atau `POST /api/v1/platform/login` untuk platform.
4. Simpan dan kirim cookie otomatis; response login web tidak berisi bearer token.
5. Verifikasi sesi melalui `GET /api/v1/session`.
6. Logout tenant melalui `POST /api/v1/logout`, atau platform melalui `POST /api/v1/platform/logout`.

Axios frontend telah memakai `withCredentials` dan `withXSRFToken`. Jangan menyimpan autentikasi web
di `localStorage`. `localhost` dan `127.0.0.1` adalah host cookie berbeda; jangan mencampurnya dalam satu sesi.

### 5.4 Mobile/API device: bearer token

Client perangkat mengirim payload tenant yang sama ditambah `device_name`. Kehadiran field ini membuat
login mengembalikan token Sanctum yang disimpan di secure storage dan dikirim sebagai
`Authorization: Bearer {token}`.

```json
{
  "pdam_code": "pdam-canada",
  "email": "meter_officer@gmail.com",
  "password": "12345678",
  "device_name": "android-emulator"
}
```

`POST /api/v1/refresh-token` hanya untuk bearer client. Web session tidak memakai endpoint tersebut.

### 5.5 Format error standar

Error API menggunakan envelope berikut. UI login menampilkan detail validasi field pertama bila tersedia.

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Data yang dikirim tidak valid.",
    "details": {
      "pdam_code": ["PDAM tidak ditemukan."]
    }
  }
}
```

Kode utama: `VALIDATION_ERROR` (422), `UNAUTHENTICATED` (401), `FORBIDDEN` (403), `NOT_FOUND`
(404), `HTTP_ERROR`, dan `SERVER_ERROR` (500). Download/stream dapat menjadi pengecualian envelope JSON.

### 5.6 Diagnosis login lokal

| Gejala | Pemeriksaan/solusi |
|--------|---------------------|
| `PDAM tidak ditemukan` | Pastikan fixture demo sudah dibuat sesuai `SEED_DATA.md` dan kode memakai huruf kecil, mis. `pdam-canada`. |
| Kredensial ditolak | Pastikan email/password sesuai `SEED_DATA.md`, tenant aktif, dan akun `is_active`. |
| Login 200 tetapi kembali ke `/login` | Gunakan hostname yang sama untuk CSRF, login, dan aplikasi; hapus cookie host lama lalu ulangi. |
| 419/CSRF | Panggil `/sanctum/csrf-cookie` lebih dahulu dan aktifkan credentials/XSRF. |
| 401 setelah login | Periksa cookie sesi terkirim dan tabel/session store dapat diakses. |
| 429 | Batas login `5/menit`; tunggu window throttle sebelum mencoba lagi. |

---

## 6. Referensi Role Demo

Setiap role di bawah punya 1 user demo di **masing-masing** tenant (Canada &
Brazil). Email sama, password `12345678`, dibedakan oleh `pdam_code`.

> Tabel ini dipertahankan sebagai referensi role backend. Sumber operasional kredensial demo tetap
> [`SEED_DATA.md`](SEED_DATA.md); ringkasan lengkap per PDAM juga tersedia di
> [`../README.md`](../README.md) (Section **Demo Credentials**). Perubahan pola akun harus diperbarui di
> `SEED_DATA.md` terlebih dahulu.

| #  | Role Code                 | Nama Role                              | Email Demo                       | Password   |
| -- | ------------------------- | -------------------------------------- | -------------------------------- | ---------- |
| 1  | `super_admin`             | Super-Admin (Platform Owner)*          | `super_admin@gmail.com`          | `12345678` |
| 2  | `admin_tenant`            | Admin PDAM                             | `admin_tenant@gmail.com`         | `12345678` |
| 2a | `compliance_officer`      | Petugas Kepatuhan / Perlindungan Data | `compliance_officer@gmail.com`   | `12345678` |
| 3  | `director`                | Direktur                               | `director@gmail.com`             | `12345678` |
| 4  | `finance_head`            | Kabag Keuangan                         | `finance_head@gmail.com`         | `12345678` |
| 5  | `finance_staff`           | Staf Keuangan                          | `finance_staff@gmail.com`        | `12345678` |
| 6  | `cashier`                 | Kasir / Loket Pembayaran               | `cashier@gmail.com`              | `12345678` |
| 7  | `customer_service`        | Customer Service                       | `customer_service@gmail.com`     | `12345678` |
| 8  | `customer`                | Pelanggan                              | `customer@gmail.com`             | `12345678` |
| 9  | `hublang_head`            | Kepala Hublang                         | `hublang_head@gmail.com`         | `12345678` |
| 10 | `survey_officer`          | Petugas Survey                         | `survey_officer@gmail.com`       | `12345678` |
| 11 | `survey_head`             | Kepala Survey                          | `survey_head@gmail.com`          | `12345678` |
| 12 | `technical_head`          | Kepala Teknik                          | `technical_head@gmail.com`       | `12345678` |
| 13 | `installer_technician`    | Teknisi Pemasangan                     | `installer_technician@gmail.com` | `12345678` |
| 14 | `meter_office`            | Koordinator Baca Meter (Kantor)        | `meter_office@gmail.com`         | `12345678` |
| 15 | `meter_officer`           | Petugas Baca Meter                     | `meter_officer@gmail.com`        | `12345678` |
| 16 | `warehouse_head`          | Kepala Gudang                          | `warehouse_head@gmail.com`       | `12345678` |
| 17 | `warehouse_staff`         | Staf Gudang / Staf Wilayah             | `warehouse_staff@gmail.com`      | `12345678` |
| 18 | `procurement_staff`       | Staf/Panitia Pengadaan                 | `procurement_staff@gmail.com`    | `12345678` |
| 19 | `production_head`         | Kepala Produksi / Operator IPA         | `production_head@gmail.com`      | `12345678` |
| 20 | `lab_analyst`             | Analis Lab / QC                        | `lab_analyst@gmail.com`          | `12345678` |
| 21 | `accountant`              | Akuntan / Staf Akuntansi               | `accountant@gmail.com`           | `12345678` |
| 22 | `tax_officer`             | Staf Pajak                             | `tax_officer@gmail.com`          | `12345678` |
| 23 | `asset_manager`           | Manajer Aset                           | `asset_manager@gmail.com`        | `12345678` |
| 24 | `maintenance_technician`  | Teknisi Pemeliharaan                   | `maintenance_technician@gmail.com` | `12345678` |
| 25 | `field_dispatcher`        | Dispatcher Lapangan                    | `field_dispatcher@gmail.com`     | `12345678` |
| 26 | `field_supervisor`        | Supervisor Lapangan                    | `field_supervisor@gmail.com`     | `12345678` |
| 27 | `field_technician`        | Teknisi Lapangan (WO)                  | `field_technician@gmail.com`     | `12345678` |
| 28 | `hr_staff`                | Staf HR                                | `hr_staff@gmail.com`             | `12345678` |
| 29 | `hr_head`                 | Kepala HR                              | `hr_head@gmail.com`              | `12345678` |
| 30 | `gis_operator`            | Operator GIS                           | `gis_operator@gmail.com`         | `12345678` |
| 31 | `dms_officer`             | Petugas Arsip/Dokumen                  | `dms_officer@gmail.com`          | `12345678` |
| 32 | `call_agent`              | Agent Call Center                      | `call_agent@gmail.com`           | `12345678` |
| 33 | `call_supervisor`         | Supervisor Call Center                 | `call_supervisor@gmail.com`      | `12345678` |
| 34 | `data_analyst`            | Analis Data / BI                       | `data_analyst@gmail.com`         | `12345678` |

> \* Role `super_admin` ada sebagai template di tiap tenant untuk kelengkapan,
> namun Super-Admin platform yang sesungguhnya adalah akun terpisah di tabel
> `platform_admins` (lihat 5.1), bukan user tenant.

**Catatan penting:**
- Hanya `admin_tenant` yang otomatis mendapat SELURUH permission (akses penuh dalam tenant-nya).
- Role lain sudah dibuat, namun pemetaan permission detail per role diisi bertahap pada fase modul berikutnya.

---

## 7. Katalog Modul (27)

CORE aktif otomatis (gratis) saat tenant di-provisioning. Sisanya berstatus
`locked` sampai diaktifkan Super-Admin (via pembayaran gateway atau manual).

| Tier | Kode Modul                                                                 |
| ---- | -------------------------------------------------------------------------- |
| 1    | CORE*, WH, MTR, SRV, FIN+, CRM, AST, ZONE, APP, C360, BILL+, METX          |
| 2    | FSM, PROC, MNT, HR, DMS, GIS, CC, BI, INT, CHEM                            |
| 3    | IOT, PROD, DIST, NRW, AI                                                    |

> \* CORE = `is_default` (gratis, tanpa kedaluwarsa).

---

## 8. Arsitektur Multi-Tenant & RBAC

### 8.1 Isolasi Tenant
- Tabel bisnis tenant-owned yang relevan punya `pdam_org_id`; tabel global/platform, pivot, framework, dan audit tertentu dikecualikan secara sengaja.
- Trait `App\Models\Concerns\BelongsToTenant` memasang **global scope** yang
  otomatis memfilter query ke tenant aktif, plus mengisi `pdam_org_id` saat create.
- Tenant aktif disimpan di `App\Support\TenantContext`, di-set oleh middleware `tenant`.
- **Master alamat** memakai pola "global reference + override per tenant": `provinces/cities/districts/villages`
  memiliki `pdam_org_id` nullable (`null` = reference global, di-scope `scopeForTenant()` = global OR own),
  dan `streets` menyimpa `pdam_org_id` (seed: 12 jalan org 1, 6 org 2). Semua level alamat menggunakan
  `SoftDeletes` sehingga penghapusan lembut & bisa dipulihkan (`/address/*/restore`); endpoint list mendukung
  `?with_trashed=1`, dan `DELETE ...?cascade=1` menghapus induk beserta anak milik tenant.

### 8.2 Middleware (alias di `bootstrap/app.php`)
| Alias        | Kelas                | Fungsi                                                      |
| ------------ | -------------------- | ---------------------------------------------------------- |
| `tenant`     | `SetTenant`          | Set `TenantContext` dari user login                        |
| `module`     | `CheckModuleAccess`  | Gerbang entitlement modul (`module:WH`) → 403 jika locked  |
| `permission` | `CheckPermission`    | Gerbang RBAC (`permission:wh.material.create`)             |

Contoh proteksi berlapis pada route:
```php
Route::get('materials', [MaterialController::class, 'index'])
    ->middleware(['auth:sanctum', 'tenant', 'module:WH', 'permission:wh.material.view']);
```

### 8.3 Provisioning Tenant
`App\Services\TenantProvisioningService::provision()` (dipanggil endpoint
`POST /api/platform/tenants`) melakukan dalam 1 transaksi:
1. Buat record `pdam_organizations`.
2. Clone 35 role template → role milik tenant.
3. Aktifkan modul default (CORE); modul lain `locked`.
4. Buat user admin tenant + assign role `admin_tenant`.

---

## 9. Endpoint API (Fase 0)

Tabel berikut adalah ringkasan, bukan inventaris lengkap. Gunakan `php artisan route:list --path=api`
sebagai sumber endpoint aktual. Swagger dan Postman masih parsial dan harus divalidasi terhadap registry.

| Method | Path                    | Auth                | Fungsi                          |
| ------ | ----------------------- | ------------------- | ------------------------------- |
| POST   | `/api/v1/platform/login`   | —                   | Login Super-Admin               |
| POST   | `/api/v1/platform/logout`  | Sanctum (platform)  | Logout Super-Admin              |
| GET    | `/api/v1/platform/tenants` | Sanctum (platform)  | Daftar semua tenant             |
| POST   | `/api/v1/platform/tenants` | Sanctum (platform)  | Provisioning PDAM baru          |
| POST   | `/api/v1/login`            | —                   | Login user tenant (+ pdam_code) |
| GET    | `/api/v1/me`               | Sanctum + tenant    | Profil + role + modul aktif     |
| POST   | `/api/v1/logout`           | Sanctum + tenant    | Logout user tenant              |
| GET    | `/api/v1/address/{level}`  | Sanctum + tenant    | List alamat berjenjang per level (`provinces/cities/districts/villages/streets`), mendukung filter parent + `?with_trashed=1` |
| POST   | `/api/v1/address/{level}`  | Sanctum + tenant    | Create alamat (milik tenant, `pdam_org_id` otomatis) |
| PUT    | `/api/v1/address/{level}/{id}` | Sanctum + tenant | Update alamat                    |
| DELETE | `/api/v1/address/{level}/{id}` | Sanctum + tenant | Soft-delete; `?cascade=1` ikut menghapus anak milik tenant |
| POST   | `/api/v1/address/{level}/{id}/restore` | Sanctum + tenant | Pulihkan record + keturunan |

> **Laporan baca meter (MTR):** `GET /meter-readings/report?period=YYYY-MM&route_id=`
> (`MeterReadingController@report`, izin `mtr.reading.view`) mengembalikan rincian baca per pelanggan
> dalam rute — baca lalu vs kini, pemakaian (m³), golongan tarif, biaya, foto meter & rumah, petugas
> (`read_by`) & verifikator. UI-nya di halaman **Laporan Baca Meter** (`#/meter-reading-report`).
> Detail integrasi ada di [`SEED_DATA.md`](SEED_DATA.md) Section 9.

---

## 10. Skema Database (Fase 0)

**Platform:** `platform_admins`, `pdam_organizations`, `modules`,
`module_price_tiers`, `tenant_module_overrides`, `price_change_logs`,
`subscriptions`, `subscription_modules`, `saas_invoices`, `promos`,
`promo_targets`, `promo_redemptions`.

**Tenant & RBAC:** `users` (+`pdam_org_id`, email unik per tenant), `roles`,
`permissions`, `role_permissions`, `user_roles`, `user_permissions`,
`employees`, `activity_logs`.
