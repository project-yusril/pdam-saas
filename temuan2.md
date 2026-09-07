# TEMUAN 2 - Audit Menyeluruh Proyek PDAM

**Tanggal audit:** 15 Juli 2026  
**Cakupan:** backend Laravel, migration/model/relation/seeder, web Vue, mobile Flutter, layanan ML Python, konfigurasi, deployment, test, dan seluruh dokumen utama.  
**Metode:** inspeksi source non-vendor/non-generated, pencocokan route-controller-model-table, pencocokan kontrak web/mobile/ML, migration/seeder/rollback dan suite backend pada MySQL 8.4.9 disposable, compatibility suite SQLite, test/build/static analysis, serta dependency audit.  
**Catatan:** database target proyek adalah **MySQL 8**. Validasi langsung memakai instance portable disposable non-user/non-production di `127.0.0.1:3307`, schema `pdam_audit`. SQLite tetap dipakai sebagai compatibility test kedua. Validasi ini menutup temuan aplikasi, tetapi bukan pengganti gate deployment pada target production.

> **Dokumen terkait:** [`README.md`](README.md) · [`PRD.md`](PRD.md) dan [`02_flow.md`](02_flow.md) sebagai target/desain · [`task.md`](task.md) dan [`temuan.md`](temuan.md) sebagai arsip historis · [`SECURITY_CHECKLIST.md`](SECURITY_CHECKLIST.md) dan [`tests/security/OWASP_ASVS_AUDIT.md`](tests/security/OWASP_ASVS_AUDIT.md) sebagai baseline internal · [`HANDOVER.md`](HANDOVER.md) · [`backend/DEPLOY.md`](backend/DEPLOY.md) sebagai runbook draft · [`backend/SEED_DATA.md`](backend/SEED_DATA.md) · [`ml/README.md`](ml/README.md) · tooling gate [`ops/README.md`](ops/README.md) · peta & fact sheet [`docs/DOC_MAP.md`](docs/DOC_MAP.md) · CI [`.github/workflows/ci.yml`](.github/workflows/ci.yml) · angka live [`docs/COUNTS.json`](docs/COUNTS.json).
>
> Dokumen ini adalah **sumber authoritative status implementasi/audit saat ini**. Bila angka atau status dokumen lain berbeda, gunakan dokumen ini.

> **Catatan (setelah audit 15 Juli 2026):** dataset demo ditambah **1 seeder tenant** (`SambasTenantSeeder`,
> tenant `pdam-sambas`, 3.000 pelanggan, 27 modul) → **25 seeder demo (kini 28 class seeder termasuk router/guard; see docs/COUNTS.json) / 3 tenant demo**, dan fitur
> **Laporan Baca Meter** ditambahkan (`GET /api/v1/meter-readings/report` + halaman web
> `#/meter-reading-report`). Perubahan ini **demo-level saja**; angka audit di bawah (24 seeder, 167 tabel,
> 358 route) merujuk snapshot 15 Juli dan tidak berubah. Detail: [`backend/SEED_DATA.md`](backend/SEED_DATA.md) Section 1 & 9.

## Status Task

- `[ ]` = belum diperbaiki atau belum diverifikasi pada target sebenarnya.
- `[X]` = sudah dikerjakan dan hasilnya telah diverifikasi.
- Sebuah temuan hanya boleh diubah menjadi `[X]` setelah implementasi, test regresi, dan verifikasi target selesai.
- Semua 39 temuan sudah diperbaiki dan lulus verifikasi yang ditetapkan; gate deployment/external/data-quality tetap dilacak terpisah dari temuan aplikasi.

### Checklist Validasi Lingkungan

- [X] Audit statis backend, database layer, web, mobile, ML, konfigurasi, test, dan dokumentasi.
- [X] Compatibility test 60 migration pada SQLite temporary.
- [X] Compatibility smoke test 24 seeder pada SQLite temporary.
- [X] Siapkan instance MySQL 8.4.9 khusus audit yang disposable dan bukan database pengguna/production.
- [X] Jalankan `php artisan migrate:fresh --seed --force`: 60 migration, 24 seeder, dan 167 tabel.
- [X] Rollback migration `000004`-`000010`, perbaiki supporting index rollback `L-08`, lalu migrate ulang sampai PASS.
- [X] Verifikasi metadata `information_schema`: 234 total FK, 97 FK tenant batch, 8 FK actor, bundle unique aktif, dan bundle FK `CASCADE`; preflight orphan/cross-tenant nol.
- [X] Jalankan seluruh suite backend pada MySQL: 89 ditemukan, 88 lulus, 1 SQLite-only di-skip secara sengaja, 756 assertions, nol failure.
- [X] Query khusus MySQL tercakup oleh suite penuh termasuk jalur report/BI; migration metadata dan constraint juga diverifikasi langsung. Ini bukan klaim exhaustive untuk seluruh variasi collation/locking production.

## 1. Ringkasan Eksekutif

Seluruh **39 dari 39 temuan audit sudah application-complete dan diverifikasi**. Status ini tidak berarti produk production-ready. Persetujuan production hanya dapat diberikan setelah enam gate canonical pada Bagian 13 ditutup; pekerjaan operasional lain dilacak terpisah sebagai rekomendasi.

### Ringkasan Severity

| Severity | Jumlah | Makna |
|---|---:|---|
| Critical | 5 | Risiko akses berbayar/tenant, kebocoran data, atau fitur komersial utama gagal total |
| High | 12 | Fitur utama rusak, kontrak API salah, data berisiko tidak konsisten, atau klaim keamanan tidak benar |
| Medium | 14 | Logic/reporting/test/UI tidak lengkap atau mudah gagal pada kondisi nyata |
| Low | 8 | Drift dokumentasi, duplikasi source, hygiene, dan gap kualitas |

**Progres perbaikan:** 39 dari 39 temuan selesai (`39/39`). Seluruh 5 Critical, 12 High, 14 Medium, dan 8 Low berstatus `[X]`. Penutupan temuan tidak menghapus gate produksi yang dirangkum di bagian prioritas dan kesimpulan.

### Kesimpulan Utama

| Area | Status Audit |
|---|---|
| 60 migration | **PASS** pada MySQL 8.4.9 dan SQLite; tetap menghasilkan 167 tabel karena lima migration terbaru hanya menambah constraint |
| 24 seeder terdaftar | **PASS** pada MySQL 8.4.9 dan SQLite |
| Model-table coverage | **FIXED** untuk runtime aktif: `SaasInvoice`, tenant scope/relations, maintenance PSR-4, commerce, dan seluruh relasi actor meter; pivots/framework/seeder-only tanpa model didokumentasikan sengaja |
| Entitlement modul | **FIXED**, 25 modul Laravel berbayar diuji locked untuk admin tenant |
| Marketplace/promo/subscription | **FIXED**, tenant self-service menghasilkan order pending + URL Snap dan aktivasi hanya setelah settlement Midtrans terverifikasi |
| Scheduled reports | **FIXED**, CRUD/run/history/download privat, queue, dispatcher per menit, dan ledger slot unik tersedia |
| Backend test | **PASS**: MySQL 88 passed/1 intentionally skipped, 756 assertions; SQLite 89/89, 664 assertions |
| Route registry | **PASS**, 358 route terbaca dan route cache berhasil |
| Web build | **PASS**, 322 modules termasuk seluruh view yang diroute |
| Web test | **PASS**, 5/5 Vitest route/error/contract/component tests |
| Flutter analyze | **PASS**, no issues; konfigurasi cleartext main/debug dan pinning tercakup |
| Flutter test | **PASS**, 44/44 termasuk sync failure/retry, auth, endpoint builder, KTP review, HTTPS, cleartext, pins, dan logging |
| Python 3.11 compile/test | **PASS**, compile sukses dan 25/25 tests lulus tanpa skip |
| Composer audit | **PASS**, tidak ada advisory |
| NPM audit | **PASS**, tidak ada vulnerability terdeteksi |

## 2. Temuan Critical

### [X] C-01 - Mayoritas Modul Berbayar Dapat Melewati Gerbang Entitlement

**Bukti kondisi awal audit:**

- `backend/routes/api.php:343` dan `backend/routes/api.php:381` hanya memberi `module:METX` dan `module:AST`.
- Route WH mulai `backend/routes/api.php:414`, CRM `:435`, FSM `:480`, PROC `:496`, HR `:508`, FIN+ `:520`, CHEM `:548`, DMS `:565`, MNT `:577`, IOT/PROD/DIST/NRW `:618`, CC `:637`, INT `:654`, BI `:671`, dan GIS `:688` tidak dikelompokkan dengan `module:<CODE>`.
- `backend/app/Http/Middleware/CheckPermission.php:24-27` memberi admin tenant bypass seluruh permission.
- `backend/app/Services/TenantProvisioningService.php:117-120` memberi role `admin_tenant` semua permission, termasuk permission modul terkunci.

**Dampak:** tenant yang belum membeli modul dapat memanggil endpoint modul berbayar. Admin tenant pasti melewati permission dan tidak ada pemeriksaan entitlement pada route-route tersebut. Ini merusak model bisnis SaaS dan klaim double gate pada PRD.

**Perbaikan:** kelompokkan setiap domain berbayar dengan `module:<CODE>` dan tetap pasang permission granular. Tambahkan test untuk setiap modul: tenant locked mendapat 403, tenant active mendapat akses, admin tenant tidak boleh bypass module gate.

**Status perbaikan (15 Juli 2026):** selesai. `CheckPermission` sekarang memetakan permission non-CORE ke entitlement modul sebelum bypass RBAC admin tenant; alias `FIN -> FIN+` dan `BILL -> BILL+` ditangani eksplisit. Endpoint APP/GIS/METX/sync tanpa permission mendapat `module:*` eksplisit. Test matriks memastikan admin tenant ditolak pada 25 modul Laravel berbayar ketika locked, serta dapat mengakses CRM setelah entitlement aktif.

### [X] C-02 - Marketplace Menggunakan Model yang Tidak Ada

**Bukti kondisi awal audit:**

- `backend/app/Http/Controllers/Api/Platform/MarketplaceController.php:9-16` mengimpor `ModulePriceTier`, `PriceChangeLog`, `Promo`, `PromoRedemption`, `PromoTarget`, `Subscription`, dan `TenantModuleOverride`.
- Verifikasi autoload menghasilkan `NO` untuk seluruh class tersebut.
- `backend/app/Console/Commands/SubscriptionCheck.php:5-6` juga mengimpor `Subscription` dan `TenantModuleOverride` yang tidak ada.

**Dampak:** endpoint marketplace, promo, harga, dashboard SaaS, purchase, dan cron subscription akan gagal dengan `Class not found` ketika dieksekusi.

**Perbaikan:** buat model yang benar untuk tabel yang sudah ada atau tulis ulang controller memakai `SubscriptionModule` sebagai satu-satunya sumber entitlement. Tambahkan test endpoint dan test command scheduler.

**Status perbaikan (15 Juli 2026):** selesai. Model `ModulePriceTier`, `PriceChangeLog`, `Promo`, `PromoRedemption`, `PromoTarget`, `Subscription`, dan `TenantModuleOverride` ditambahkan sesuai migration. Verifikasi Composer autoload menghasilkan `YES` untuk seluruh model commerce, dan optimized autoload berhasil dibuat.

### [X] C-03 - Marketplace Tidak Cocok dengan Skema Database

**Bukti kondisi awal audit:**

- `MarketplaceController.php:30-36,46,68,71,81,87-89` memakai `module_code`, `dependency_code`, `module_id`, `tier_name`, `status`, `activated_at`, dan `price_year` pada struktur yang tidak menyediakan kolom tersebut.
- Tabel `modules` menggunakan `code` dan `dependencies`: `backend/database/migrations/2026_07_01_150003_create_modules_table.php:16-23`.
- `module_price_tiers` menggunakan `module_code`, `min_customers`, `max_customers`, `price_year`: `2026_07_01_150004_create_module_price_tiers_table.php:16-22`; tidak ada `module_id` atau `tier_name`.
- `tenant_module_overrides` adalah tabel harga khusus dengan `custom_price`, bukan entitlement: `2026_07_01_150005_create_tenant_module_overrides_table.php:16-22`.
- `subscriptions` tidak memiliki `amount`, `original_amount`, `discount_amount`, atau `promo_code`: `2026_07_01_150007_create_subscriptions_table.php:16-22`.
- Promo menggunakan `discount_type`, `discount_value`, `starts_at`, `ends_at`, `usage_quota`, dan `status`: `2026_07_01_150010_create_promos_table.php:19-30`, bukan field controller `discount_percent`, `start_date`, `end_date`, `max_redemptions`, dan `is_active`.

**Dampak:** walaupun model ditambahkan, query dan insert tetap gagal SQL. Purchase tidak dapat diandalkan dan harga/promo tidak bekerja sesuai PRD.

**Perbaikan:** tetapkan satu kontrak schema. Rekomendasi minimal: katalog dari `modules`, harga dari `base_price_year/module_price_tiers/tenant_module_overrides`, entitlement hanya dari `subscription_modules`, pembayaran/kontrak dari `subscriptions/saas_invoices`, dan promo mengikuti migration saat ini.

**Status perbaikan (15 Juli 2026):** selesai. `MarketplaceController` ditulis ulang memakai `modules.code/dependencies`, tier berdasarkan jumlah pelanggan, override harga aktif, schema promo aktual, `subscriptions`, dan `saas_invoices`. Harga bundle memakai `module_bundles.price_year`, scope promo dihormati, dependency diperiksa sebelum write, dan duplicate module lintas item ditolak.

### [X] C-04 - Purchase Mengaktifkan Tabel yang Tidak Dibaca Middleware

**Bukti:**

- `MarketplaceController.php:85-99` mengaktifkan `TenantModuleOverride`.
- `CheckModuleAccess.php:28-34` hanya membaca `subscription_modules`.
- `TenantModuleController.php:40-58` juga mengaktifkan `subscription_modules`.

**Dampak:** bila purchase berhasil, akses modul tetap locked karena middleware membaca sumber lain. Dua sumber status juga berpotensi berbeda dan menghasilkan billing/akses yang tidak konsisten.

**Perbaikan:** hapus status aktivasi dari `tenant_module_overrides`; tabel itu hanya untuk harga khusus. Semua jalur manual, gateway, promo, bundle, expiration, dan middleware wajib menulis/membaca `subscription_modules`.

**Status perbaikan (15 Juli 2026):** selesai. Purchase, manual activation, middleware, dan `pdam:subscription-check` sekarang memakai `subscription_modules` sebagai satu-satunya status akses. `tenant_module_overrides` hanya dipakai untuk custom price. Test memastikan purchase mengaktifkan entitlement, membuat kontrak/invoice, tidak menulis override status, dependency failure tidak partial-write, bundle memakai harga bundle, dan expiration mengubah entitlement menjadi `expired`.

### [X] C-05 - API ML Tanpa Auth dan Dapat Mengakses Tenant Arbitrer

**Bukti:**

- `ml/src/api.py:66-94` menyediakan `/train` dan `/predict` tanpa autentikasi.
- `ml/src/api.py:138-194` menyediakan pembacaan prediction berdasarkan `pdam_org_id` dari URL tanpa autentikasi/otorisasi.
- Request dapat memilih tenant pada `TrainRequest.pdam_org_id` dan `PredictRequest.pdam_org_id`: `ml/src/api.py:27-41`.

**Dampak:** siapa pun yang mencapai service ML dapat membaca prediction tenant lain, memicu training berat berulang sebagai DoS, menulis prediction ke tenant arbitrer, dan mempelajari data operasional lintas PDAM.

**Perbaikan:** jadikan ML service internal-only, gunakan service-to-service auth/mTLS atau signed token, validasi entitlement AI dan tenant dari token, rate limit/job queue, serta jangan menerima tenant ID bebas tanpa authorization mapping.

**Status perbaikan (15 Juli 2026):** selesai untuk application-layer service auth. Semua endpoint model/train/predict/predictions mewajibkan `X-Service-Token`; konfigurasi kosong fail-closed 503, token salah 401, dan tenant tanpa entitlement AI aktif 403. Entitlement dibaca dari `subscription_modules`; training dipindah ke worker thread dan dikunci satu job pada satu waktu. Secret diwajibkan pada Docker Compose ML dan dicontohkan di env production. Security logic lulus 5/5 unit test dan seluruh source ML lulus compile. mTLS/network isolation tetap direkomendasikan sebagai hardening infrastruktur deployment.

## 3. Temuan High

### [X] H-01 - Route Registry Rusak Karena Controller Tidak Di-import

**Bukti:** `php artisan route:list --path=api` gagal `Class "CustomerViewController" does not exist`. Class sebenarnya ada pada `backend/app/Http/Controllers/Api/Tenant/CustomerViewController.php:11`, tetapi tidak di-import di `routes/api.php:1-72`. `CallCenterController` juga dipakai pada `routes/api.php:638-640` tanpa import.

**Dampak:** tooling route, optimasi/route cache, dokumentasi endpoint, dan request ke route tersebut dapat gagal resolve.

**Perbaikan:** tambahkan import kedua controller dan jadikan `php artisan route:list` serta `php artisan route:cache` sebagai CI gate.

**Status perbaikan (15 Juli 2026):** selesai. Import `CustomerViewController` dan `CallCenterController` ditambahkan. Parse error tersembunyi pada `HrTrainingController` juga diperbaiki. Registry terbaru setelah seluruh batch perbaikan membaca 358 route dan `php artisan route:cache` berhasil.

### [X] H-02 - Signed URL Membuka File Privat Tenant Lain

**Bukti:** `backend/app/Http/Controllers/Api/FileController.php:17-34` menerima path bebas dari user, hanya mengecek file ada, lalu membuat URL. Tidak ada verifikasi bahwa path/file dimiliki tenant atau user tersebut.

**Dampak:** user terautentikasi yang mengetahui/menebak path dapat meminta signed URL untuk KTP, foto meter, dokumen, atau bukti pembayaran tenant lain.

**Perbaikan:** jangan terima path mentah. Terima resource type + resource ID, resolve model dengan tenant scope dan authorization policy, lalu ambil path server-side. Pisahkan direktori per tenant dan validasi prefix sebagai defense-in-depth.

**Status perbaikan (15 Juli 2026):** selesai. Endpoint signed URL hanya menerima referensi resource yang di-resolve dengan scope tenant dan otorisasi per resource. Path privat wajib relatif, bebas traversal/URL, dan berada tepat pada prefix tenant+purpose; attachment KTP, foto meter, dan survey divalidasi saat ditulis agar path tenant lain tidak dapat “dicuci” melalui resource milik sendiri. Upload DMS baru dipisahkan per tenant, dengan compatibility rule sempit untuk record DMS lama. Test mencakup penolakan raw path, resource lintas tenant, path laundering, dan prefix upload tenant.

### [X] H-03 - Query BI Dinamis Memungkinkan SQL Injection/Identifier Injection

**Bukti:**

- `backend/app/Http/Controllers/Api/Tenant/BiController.php:48-57` hanya memvalidasi dimensions/metrics sebagai string.
- `BiController.php:72-81` memasukkan dimension ke `select/groupBy` dan metric ke `DB::raw("{$parts[0]}({$parts[1]})...")`.

**Dampak:** nama fungsi/kolom dari request masuk raw SQL. WAF bukan pengganti allowlist identifier. Endpoint juga dapat mengekspos kolom sensitif yang tidak dimaksudkan.

**Perbaikan:** allowlist dimensions dan metrics per tabel, allowlist fungsi agregasi (`COUNT`, `SUM`, `AVG`, `MIN`, `MAX`), quote identifier melalui grammar/query builder, dan test payload injeksi.

**Status perbaikan (15 Juli 2026):** selesai pada application layer. Seluruh dataset, dimension, kombinasi fungsi+kolom metric, filter, dan identifier memakai registry statis. Identifier aggregate di-quote melalui grammar koneksi aktif; input array dibatasi, duplicate/nested filter ditolak, rentang tanggal divalidasi, dan dataset modul non-CORE memerlukan entitlement sumber aktif. Permission route diselaraskan ke permission seeder `bi.view.view`, termasuk preset data analyst. Test membuktikan payload identifier/function tidak valid ditolak, non-admin dengan permission aktual dapat memakai BI, admin tidak dapat membaca dataset modul sumber terkunci, dan aggregate allowlisted tetap berjalan. Eksekusi final pada MySQL 8 tetap dilacak pada Checklist Validasi Lingkungan.

### [X] H-04 - Export Memakai Filter, Sort, dan Columns Bebas

**Bukti kondisi awal audit:** `ExportController.php:15-22` hanya memvalidasi string/array; `:33-55` memakai key filter, sort field, dan requested columns tanpa allowlist.

**Dampak:** request dapat menyebabkan SQL error, mengakses kolom sensitif dalam tabel yang diizinkan, atau menyalahgunakan identifier. Endpoint export juga tidak memiliki permission pada `routes/api.php:477-478`.

**Perbaikan:** definisikan allowlist kolom/filter/sort per dataset dan pasang permission export. Jangan ekspor `pdam_org_id`, token, PII, atau internal IDs kecuali perlu.

**Status perbaikan (15 Juli 2026):** selesai untuk security identifier dan authorization. Route memakai `core.report.export`; setiap dataset memakai allowlist terpisah untuk column/filter/sort, permission domain, dan entitlement modul sumber. PII/free text/internal foreign key berisiko dikeluarkan dari export generik, array kosong/nested dibatasi, sort opsional tidak lagi memicu error, judul menolak newline, dan nilai CSV berawalan formula dinetralisasi. Test membuktikan identifier sensitif/asing ditolak, admin tidak dapat mengekspor dataset modul terkunci, default export tanpa sort berjalan, serta formula pada title/data tidak dieksekusi spreadsheet. Kontrak format palsu ditutup terpisah pada `M-08` dengan format aktual CSV/HTML.

### [X] H-05 - Endpoint Privacy Purge Bisa Dipanggil Semua User Tenant

**Bukti:** route `privacy/purge-old-data` pada `routes/api.php:665-669` hanya memakai auth+tenant, tanpa permission/admin check. `DataPrivacyController.php:112-133` menganonimkan seluruh bill lama tenant dan menghapus activity log tenant cukup dengan `confirm=true`.

**Dampak:** user biasa/pelanggan dapat menghapus audit log dan mengubah data historis seluruh tenant. Ini merusak auditability dan berpotensi melanggar retensi keuangan.

**Perbaikan:** batasi ke admin/compliance role, gunakan approval dua tahap, immutable audit trail, legal retention berbeda per jenis data, transaction, dry-run, dan backup.

**Status perbaikan (15 Juli 2026):** selesai pada application layer. Purge dibatasi ke admin tenant atau role compliance yang memiliki permission, default ke dry-run, dan hanya menarget activity log melewati retensi; record keuangan tidak pernah dihapus endpoint ini. Eksekusi menggunakan request ber-UUID yang kedaluwarsa setelah 60 menit, memerlukan admin/compliance kedua yang berbeda dan frasa spesifik request, membatalkan eksekusi bila scope berubah, serta berjalan atomik dengan audit append-only bertaut hash pada tabel terpisah. Test membuktikan user biasa ditolak, request tidak langsung menghapus, self-approval ditolak, eksekusi dual-control berhasil, dan audit tidak dapat dihapus melalui model. Database-level privilege append-only dan prosedur backup tetap hardening deployment.

### [X] H-06 - Offline Sync Meter Tidak Idempotent dan Tidak Memvalidasi Assignment/Periode

**Bukti:**

- Route sync tidak memiliki permission/module gate: `routes/api.php:697-700`.
- `SyncController.php:69-111` menerima `customer_id`, menggunakan bulan server, dan langsung membuat `MeterReading`.
- Tidak ada idempotency key, validasi officer-route assignment, reading period open, reading monotonic/rollover, atau duplikasi.
- Validasi `if (!$readingValue)` pada `SyncController.php:75` menolak reading sah bernilai 0.
- Client menghapus record berdasarkan jumlah ID, bukan hasil per item: `mobile/lib/core/cache/sync_service.dart:134-146`.

**Dampak:** retry dapat menghasilkan duplicate/unique conflict, reading pelanggan di luar rute dapat dimasukkan, reading 0 tidak dapat disinkronkan, dan mapping sukses/gagal salah dapat menghapus draft yang belum tersimpan.

**Perbaikan:** tambahkan `client_uuid` unique per tenant, response per item dengan key client, gunakan service meter yang sama dengan online flow, cek MTR entitlement, permission, assignment, period, dan aturan rollover.

**Status perbaikan (15 Juli 2026):** selesai. Migration menambahkan `client_uuid` unique tenant untuk meter reading dan survey report. Sync meter memvalidasi entitlement MTR, periode dari payload, nilai 0, assignment rute, tipe reading, rollover, dan duplicate retry dalam transaction/locking. Response per item membawa `client_uuid`, status, server ID, dan error code sehingga client menghapus draft berdasarkan hasil item, bukan jumlah response. Test membuktikan nilai 0 diterima, retry idempotent, rute tidak ditugaskan ditolak, dan periode tertutup ditolak.

### [X] H-07 - Endpoint Sync Mengklaim Survey tetapi Tidak Mendukungnya

**Bukti:** validasi menerima `survey_reports` di `SyncController.php:22-24`, tetapi `:30-35` hanya memproses meter dan mengembalikan unsupported untuk survey. Download/status selalu hardcoded kosong/nol pada `:37-55`.

**Dampak:** klaim offline sync tidak benar; survey offline tidak tersinkron melalui endpoint tersebut dan status server menyesatkan.

**Perbaikan:** implementasikan survey sync idempotent atau hapus tipe/claim sampai tersedia. Simpan sync jobs/status nyata.

**Status perbaikan (15 Juli 2026):** selesai untuk kontrak sync saat ini. `survey_reports` diproses idempotent dengan `client_uuid`, validasi assignment/status prospek, transaction pembuatan report + update prospek, dan hasil per item. Download mengembalikan tugas survey aktual berdasarkan assignment, sedangkan status mengembalikan periode baca terbuka dan jumlah tugas survey aktual. Test membuktikan create, duplicate retry, update prospek, download, dan status server.

### [X] H-08 - Integrasi OCR KTP Mobile Belum Memakai Endpoint Upload yang Benar

**Bukti kondisi awal audit:** data identitas hardcoded sudah dihapus dan widget mengirim multipart file. Namun saat audit, `Endpoints.ocrKtpUpload` masih menunjuk `/prospects/parse-ktp`, sedangkan endpoint tersebut menerima `raw_text`; endpoint multipart yang benar adalah `/prospects/upload-ktp` dengan field `ktp_file`.

**Dampak kondisi awal:** risiko data fiktif sudah ditutup, tetapi OCR mobile gagal validasi karena kontrak endpoint salah dan pengguna tidak mendapat hasil OCR nyata.

**Perbaikan:** arahkan mobile ke `/prospects/upload-ktp`, pertahankan review wajib, tampilkan confidence bila provider menyediakannya, dan tambah Flutter/API contract test.

**Status perbaikan (15 Juli 2026):** selesai. `Endpoints.ocrKtpUpload` menunjuk `/prospects/upload-ktp`, widget tetap mengirim multipart field `ktp_file`, menampilkan confidence parser, dan mewajibkan pesan review/koreksi sebelum submit. Backend contract test membuktikan raw text ditolak pada endpoint multipart, provider yang tidak dikonfigurasi fail-closed tanpa menyimpan file, serta mock provider sukses mengembalikan parsed data+confidence dan menyimpan KTP di direktori privat tenant. Flutter contract test memastikan endpoint mobile tidak drift kembali.

### [X] H-09 - Mobile Upload Mengirim File ke Endpoint yang Hanya Menerima Path

**Bukti:**

- Mobile upload meter/survey mengirim multipart `file`: `meter_remote_source.dart:47-55`, `survey_remote_source.dart:51-61`.
- `Endpoints.uploadFile` menunjuk `/files/signed-url`: `endpoints.dart:59-60`.
- Backend endpoint hanya menerima string `path`: `FileController.php:17-29`.

**Dampak:** upload foto meter/survey selalu gagal validasi 422; bukti lapangan tidak masuk database/storage.

**Perbaikan:** buat endpoint upload privat yang memanggil `FileUploadService::upload`, lalu endpoint signed URL terpisah untuk download berizin.

**Status perbaikan (15 Juli 2026):** selesai. Backend menyediakan `POST /files/upload` multipart terpisah dari `POST /files/signed-url`. Upload disimpan pada `<tenant>/<purpose>/...`, sedangkan download signed URL memakai resource ownership dan path guard. Mobile meter/survey menunjuk `/files/upload`; backend contract test memastikan file berada di direktori tenant.

### [X] H-10 - Banyak Endpoint Mobile Tidak Ada di Backend

**Bukti:** `mobile/lib/core/network/endpoints.dart:12-14,19,21,27-29` mendefinisikan `/profile/update`, `/change-password`, `/fcm-token`, `/portal/bills/{id}`, `/portal/usage-history`, `/portal/complaints`, dan `/portal/profile`; tidak ada route tersebut di `backend/routes/api.php`. Backend memiliki `/portal/consumption-chart`, tetapi mobile tidak memakainya.

**Dampak:** detail bill, usage, complaint, profile update, password change, dan FCM registration menghasilkan 404.

**Perbaikan:** buat contract test terpusat/OpenAPI-generated client; selaraskan nama endpoint dan hapus endpoint phantom.

**Status perbaikan (15 Juli 2026):** selesai untuk endpoint yang tercantum pada temuan. Backend kini menyediakan update profile, change password, FCM token, detail bill portal, usage history, complaints, dan profile portal. `MobileContractTest` memverifikasi profile/password/FCM serta ownership detail bill. **Follow-up 7 Sept:** `pdam:openapi` (spek OpenAPI 3.0 yang di-generate dari route registry — 293 path, bukan anotasi parsial) dan `pdam:mobile-coverage` (kontrak path endpoints.dart vs registry — menangkap gap nyata `portal/complaints/{id}` yang kini diimplementasi `CustomerPortalController::complaint`) keduanya aktif di CI; codegen *client* Dart dari spek tetap enhancement berikutnya (H-10 lanjut). Duplikat konstan `ocrMeterReading`/`notificationsList*` dibuang; flutter analyze 0 issue.

### [X] H-11 - Debug Mobile Tidak Bisa Mengakses Backend dari Android

**Bukti:** `mobile/lib/core/constants.dart:6` menggunakan `http://localhost:8000/api/v1` ketika debug.

**Dampak:** pada Android emulator, `localhost` adalah emulator sendiri; pada device fisik adalah device sendiri. Login/API gagal kecuali backend berjalan di device.

**Perbaikan:** gunakan `10.0.2.2` untuk Android emulator atau `--dart-define=API_BASE_URL=...`; jangan hardcode environment URL.

**Status perbaikan (15 Juli 2026):** selesai. Base URL production dan development dapat dioverride melalui `--dart-define`; default development memakai `http://10.0.2.2:8000/api/v1` untuk Android emulator.

### [X] H-12 - Certificate Pinning Tidak Diimplementasikan

**Bukti:** `mobile/lib/core/network/api_client.dart:32-55` memanggil `_configureCertificatePinning`, tetapi fungsi hanya memasang callback bypass sertifikat di cabang `kDebugMode` yang justru tidak mungkin dipanggil karena fungsi hanya dipanggil saat `!kDebugMode`. Tidak ada fingerprint/public-key verification.

**Dampak:** klaim certificate pinning pada `task.md:322` salah; mobile production bergantung pada trust store biasa.

**Perbaikan:** implementasikan adapter pinning yang kompatibel dengan versi Dio, pin SPKI dengan rotasi/backup pin, dan tambah integration test TLS.

**Status perbaikan (15 Juli 2026):** selesai pada aplikasi. Build release mewajibkan dua pin SPKI SHA-256 melalui dart-define `CERT_SPKI_SHA256_PRIMARY` dan `CERT_SPKI_SHA256_BACKUP`; pin hilang atau malformed membuat koneksi fail-closed. Adapter tetap mempertahankan validasi trust platform dan hostname sebelum mencocokkan pin. Suite Flutter penuh 44/44 dan `flutter analyze` lulus. Uji endpoint TLS production nyata dan drill rotasi primary/backup tetap gate deployment, bukan alasan membiarkan temuan aplikasi terbuka.

## 4. Temuan Medium

### [X] M-01 - Test Backend Tidak Hijau

`php artisan test` semula menghasilkan **35 passed, 1 failed, 99 assertions**. `tests/Feature/ExampleTest.php:15-17` mengharapkan root redirect ke Swagger, sedangkan `routes/web.php:13-15` mengembalikan SPA 200. Dokumen `task.md:327` mengklaim 36 test/100 assertions lulus.

**Status perbaikan (15 Juli 2026):** selesai. Test root diselaraskan dengan SPA aktual dan test regresi keamanan ditambahkan. Suite terbaru lulus pada SQLite **89/89, 664 assertions** dan pada MySQL **89 ditemukan, 88 lulus, 1 intentionally skipped, 756 assertions, nol failure**. Skip tersebut khusus fixture no-mutation SQLite; ekuivalen constraint, preflight, dan metadata migration diverifikasi langsung pada MySQL.

### [X] M-02 - Frontend Tidak Memiliki Script Test

**Bukti kondisi awal audit:** `backend/package.json:5-8` hanya memiliki `build` dan `dev`. `npm test -- --run` gagal `Missing script: test`, walaupun Vitest dan satu spec tersedia serta README menyuruh `npm run test`.

**Status perbaikan (15 Juli 2026):** selesai. Script `test` menjalankan Vitest dengan environment jsdom. Test route/error, kontrak asset, dan auth cookie ditambahkan. `npm test -- --run` lulus **5/5 test** dan `npm run build` tetap lulus.

### [X] M-03 - Halaman Web Banyak yang Tidak Terdaftar di Router Aktif

Router aktif adalah `resources/js/router/index.js` karena `app.js:11` mengimpor direktori router dan resolver memilih JS. Router hanya mendaftarkan landing/login/dashboard/zones/prospects/meter/platform (`index.js:3-17`). View complaint, installment, asset, GIS, HR, call center, procurement, serta role dashboard tidak terdaftar, sehingga build sukses tetapi pengguna tidak punya route ke fitur tersebut.

**Status perbaikan (15 Juli 2026):** selesai untuk halaman yang memiliki kontrak backend nyata. Router dan sidebar kini mengekspos complaint, asset, GIS, employee, employee self-service, call center, tender, serta empat dashboard role. Route/link detail prospek dan installment yang endpoint backend-nya tidak ada dihapus, bukan dipublikasikan sebagai fitur palsu. Test registry memastikan sebelas route baru tersedia dan dua route phantom tidak kembali. Production build mengompilasi seluruh view terdaftar.

### [X] M-04 - Source JS dan TS Ganda Sudah Drift

Ada `api/index.js` + `index.ts`, `router/index.js` + `index.ts`, dan `stores/auth.js` + `auth.ts`. Router JS memiliki platform/landing, router TS tidak. Store JS mendukung platform auth, store TS tidak. Store TS mengharapkan `active_modules` pada organization, tetapi login backend mengirimkannya hanya dari `/me`, bukan login (`AuthController.php:83-97,100-116`). Duplikasi ini mudah membuat developer mengedit file yang tidak dipakai.

**Status perbaikan (15 Juli 2026):** selesai. Runtime JavaScript ditetapkan sebagai source of truth; duplikat `api/index.ts`, `router/index.ts`, dan `stores/auth.ts` dihapus. Entry point serta auth store memakai import `.js` eksplisit. Test router mengimpor source yang sama dengan runtime dan production build lulus.

### [X] M-05 - Web Menelan Error dan Menampilkan Data Kosong

Berbagai view menggunakan `catch {}` tanpa feedback, misalnya `ComplaintListView.vue:35`, `AssetListView.vue:31`, `ZoneListView.vue:30`, `MeterRouteListView.vue:28`, dan view lain. Endpoint 403/404/500 tampak seperti “tidak ada data”, menyembunyikan bug integrasi.

**Status perbaikan (15 Juli 2026):** selesai. Axios interceptor mempublikasikan seluruh kegagalan ke `ApiErrorBanner` global yang membedakan sesi berakhir, module locked, permission denied, 404, validation, rate limit, server, dan network failure. Redirect 401 diselaraskan dengan hash router. Tidak ada lagi `catch {}` kosong pada source web aktif; catch view eksplisit bergantung pada banner global, sedangkan logout tetap best-effort dengan alasan terdokumentasi. Vitest mengunci normalisasi error dan production build lulus.

### [X] M-06 - Asset Web Memanggil Endpoint Salah

**Bukti kondisi awal audit:** `resources/js/views/assets/AssetListView.vue:31` memanggil `/fixed-assets`, sedangkan backend menyediakan `/assets` pada `routes/api.php:391-410`.

**Status perbaikan (15 Juli 2026):** selesai. Asset web sekarang memanggil `/assets`; Vitest contract test mengunci URL tersebut dan web production build lulus.

### [X] M-07 - Marketplace Super-Admin Bukan Marketplace Tenant

Seluruh route marketplace berada dalam `platform` + ability platform (`routes/api.php:84-114`), sementara PRD menjelaskan admin tenant dapat membeli modul. Web `ModuleCatalogView` juga berada di `/platform/modules`. Tidak ada tenant marketplace route/UI yang melakukan checkout.

**Status perbaikan (15 Juli 2026):** selesai. Tenant memiliki route/UI `/marketplace` dan API katalog yang selalu menurunkan tenant dari session, lalu menghitung pricing, entitlement, dan dependency server-side. Purchase membuat order SaaS tenant-scoped berstatus `pending`, idempotent, dengan expanded module lines immutable, mendaftarkannya ke Midtrans Snap, lalu mengembalikan URL pembayaran; tidak ada modul aktif sebelum settlement bertanda tangan valid dan amount-matched. Settlement berjalan atomik/idempotent dan tetap menjadikan `subscription_modules` sumber entitlement tunggal. Jalur platform/manual tetap tersedia. Test mencakup request Snap, cross-tenant, dependency, tidak ada pre-activation, dan settlement terverifikasi.

### [X] M-08 - Export PDF/XLSX Bukan File PDF/XLSX

`ExportController.php:58-71` mengembalikan CSV string untuk format `pdf` dan `excel`, namun memberi nama `.pdf` atau `.xlsx`. File tersebut bukan PDF/XLSX valid dan content type tetap `text/csv`.

**Status perbaikan (15 Juli 2026):** selesai dengan kontrak yang jujur terhadap artifact yang didukung dependency saat ini. Endpoint hanya menerima `csv|html`, mengembalikan ekstensi `.csv|.html` dan MIME yang sesuai, serta menolak `pdf|excel|doc|xlsx` dengan 422. Postman dan seluruh test diperbarui. Test memastikan HTML di-escape, CSV formula tetap dinetralisasi, dan label format lama tidak dapat kembali. Native PDF/XLSX/DOC tetap requirement roadmap PRD, tetapi tidak lagi diklaim sebagai output implementasi saat ini.

### [X] M-09 - Scheduled Reports Adalah Stub

`BiController.php:94-97` selalu mengembalikan array kosong. Tidak terlihat tabel `scheduled_reports` atau worker pembuat laporan terjadwal, meski fitur BI dinyatakan selesai.

**Status perbaikan (15 Juli 2026):** selesai. API menyediakan CRUD schedule, run manual, history, dan download privat; `scheduled_reports` serta ledger run menyimpan jadwal dan slot eksekusi unik. `ReportDatasetRegistry` membatasi dataset/source, mengecek permission serta entitlement modul sumber saat eksekusi, dan menghasilkan CSV/HTML. Waktu run berikutnya dihitung dalam UTC dari timezone jadwal. Job berjalan pada queue dan dispatcher dipanggil scheduler setiap menit; artifact tidak diletakkan pada disk publik. Test mencakup command/dispatch, izin, entitlement, slot unik, history, dan download privat. `schedule:list` tidak dapat dijalankan pada environment lokal karena cache lock MySQL, tetapi registrasi route/cache dan perilaku command/dispatcher lulus test.

### [X] M-10 - Installation Masih Memiliki Fallback Stub

`backend/app/Services/InstallationService.php:22-51` mengembalikan `mode=stub` ketika WH tidak aktif dan hanya mencatat rencana material. Ini mungkin keputusan fallback sah, tetapi dokumen menyatakan integrasi material end-to-end lengkap; perlu status eksplisit bahwa tidak ada reservasi/pengeluaran stok pada mode tersebut.

**Status perbaikan (15 Juli 2026):** selesai untuk status fitur eksplisit. Kedua kondisi WH aktif/nonaktif sekarang mengembalikan `mode=planning_only`, `warehouse_module_active`, `stock_reserved=false`, `stock_issued=false`, dan `accounting_posted=false`. Pesan API menyatakan tidak ada reservasi, pengeluaran stok, atau jurnal. Test mencakup WH aktif dan nonaktif. Integrasi stock-out/jurnal nyata tetap belum diimplementasikan, tetapi tidak lagi tersembunyi di balik mode bernama `wh` atau klaim end-to-end.

### [X] M-11 - Model/Table Coverage Tidak Lengkap

Snapshot SQLite terbaru memiliki 167 tabel dan coverage model belum satu banding satu. Tidak semua tabel memerlukan model; model commerce aktif (`Promo`, `PromoTarget`, `PromoRedemption`, `Subscription`, `TenantModuleOverride`, `ModulePriceTier`, dan `PriceChangeLog`) sudah ditambahkan. Banyak tabel enterprise lain tetap hanya diakses query builder/seeder; coverage relation dan tenant scope masih perlu audit bertahap.

**Status perbaikan (15 Juli 2026):** selesai. `SaasInvoice` kini memiliki model, tenant scope, dan relations; `MaintenanceRecord` dipisahkan ke file PSR-4 yang benar; model commerce dan seluruh relasi actor meter aktif tersedia. Inventaris runtime mendokumentasikan bahwa tidak setiap tabel membutuhkan model Eloquent: pivot, tabel framework, dan tabel seeder-only tanpa kebutuhan runtime sengaja dikecualikan, bukan coverage yang terlewat.

### [X] M-12 - Banyak `pdam_org_id` Tidak Memiliki Foreign Key

Banyak migration memakai `unsignedBigInteger('pdam_org_id')->index()` tanpa `constrained`, contohnya customers `2026_07_02_100004_create_customers_tables.php:18`, bills `2026_07_02_100006_create_bills_tables.php:18`, meter tables `2026_07_02_100011_create_meter_tables.php:18,30,40,49,63`. Dokumen lama menyatakan ini sengaja, tetapi risikonya orphan tenant meningkat pada import/raw SQL dan deletion. Application scope tidak menjaga referential integrity.

**Status perbaikan (15 Juli 2026):** selesai. Inventaris lengkap menemukan tepat **97** kolom `pdam_org_id` tanpa FK, bukan sekadar contoh. Empat migration additive memasang seluruh 97 tenant FK `RESTRICT` dengan preflight deterministik yang tidak memutasi data; row global nullable tetap dipertahankan. MySQL migration lulus dengan orphan nol dan metadata menunjukkan 97 tenant batch FK. Physical delete tenant langsung kini dilarang oleh constraint; decommission tenant tetap wajib mengikuti workflow operasional, bukan direct delete.

### [X] M-13 - Relasi User pada Meter Tidak Memiliki FK

`meter_route_assignments.officer_id`, `reading_periods.opened_by/closed_by`, dan `meter_readings.read_by/verified_by` hanya unsigned integer tanpa FK (`create_meter_tables.php:42-55,76-78`). Record dapat menunjuk user tidak ada atau tenant lain.

**Status perbaikan (15 Juli 2026):** selesai. Unique `(pdam_org_id,id)` ditambahkan pada `users`, lalu delapan composite same-tenant actor FK `RESTRICT` melindungi `officer`, `opened`, `closed`, `read`, `verified`, `processed`, `performed`, dan `reviewed`. Constraint mencegah actor hilang maupun lintas tenant; relations dan test regresi ditambahkan. Preflight MySQL menemukan nol orphan/cross-tenant actor dan metadata menunjukkan delapan actor FK aktif.

### [X] M-14 - ML Training-Serving Skew dan Model Dummy

- Consumption training menyimpan `feature_names` tetapi tidak pernah mengisinya; inference membangun ulang one-hot columns dari data saat itu (`consumption_predictor.py:24,89-109`).
- Churn dan meter failure menyimpan `feature_cols` **setelah** memanggil `save()` (`pipeline.py:145-148,174-177`), sehingga artifact menyimpan `None`.
- Inference mengabaikan feature columns tersimpan dan membangun ulang column order (`churn_predictor.py:73-82`, `meter_failure_predictor.py:137-143`).
- Pada data sedikit, classifier/regressor dilatih pada satu baris nol dan bisa tetap disimpan sebagai model (`consumption_predictor.py:26-31`, `churn_predictor.py:24-29`, `meter_failure_predictor.py:92-97`).

**Dampak:** shape/order fitur dapat berbeda antara training dan prediction, prediction salah atau runtime error, dan model dummy tampak seperti artifact valid.

**Status perbaikan (15 Juli 2026):** selesai. Anomaly path tidak lagi memakai dummy dan seluruh model memakai kontrak 5-tuple konsisten. Feature order dipersist lalu serving me-reindex input; enabled-model controls konsisten, model unknown/disabled gagal eksplisit, CLI mengembalikan exit nonzero saat gagal, dan artifact contract divalidasi. Python 3.11 x64 yang didukung lulus compile dan suite penuh 25/25 tanpa skip.

## 5. Temuan Low

### [X] L-01 - Konfigurasi DB ML Default Berbeda dari Backend

`ml/src/data_loader.py:15` default database `pdam`, sedangkan backend/docs memakai `pdam_saas`. Tanpa env eksplisit ML tersambung ke DB salah/tidak ada.

**Status perbaikan (15 Juli 2026):** selesai. Default `DataLoader`, `config.yaml`, `.env.example`, dan Docker Compose ML diselaraskan ke `pdam_saas`. Resolver konfigurasi baru memberi environment precedence terhadap YAML, mendukung `DB_USER` maupun `DB_USERNAME`, mempertahankan password kosong yang eksplisit, dan menghapus placeholder `${...}` yang sebelumnya tidak pernah diinterpolasi PyYAML serta dapat menggagalkan import karena port bukan angka. Test membuktikan default dan environment override aktual.

### [X] L-02 - Tidak Ada Artifact Model ML

Tidak ditemukan `.pkl`, `.joblib`, atau `.onnx` pada path model yang diharapkan. `ml/output` juga kosong. Karena itu AI saat ini adalah pipeline source, bukan layanan prediction siap pakai.

**Status perbaikan (15 Juli 2026):** selesai secara jujur untuk temuan "tidak ada artifact sama sekali". Generator deterministik `python scripts/generate_validation_artifacts.py` menghasilkan empat artifact lokal ignored di `ml/models`, manifest/checksum, dan `fixture_validation_summary.json` dari fixture 40 pelanggan x 12 periode, seed 42. Artifact berjenis `fixture_validation` dan `production_calibrated=false`: `consumption_xgboost.pkl` SHA-256 `d0a356449ed7c64bfd5139dfef997a22c90a468db16722cb323d3c05ea2c2cc6`, anomaly `ff8581a7d46590b74594455d18fe0534e564ada1de7702de1c4d06c0ed070be9`, churn `fd7660ceb7f0b97ea5ab6d029f506024addd9d6e8f1fee3670445908d60e9667`, dan meter failure `92f4de90db2d18f39e87001e10f313301aa1429a492a7d79228680b8d9ed77b6`. Manifest menyimpan schema/checksum/provenance; write atomik, validasi sebelum pickle deserialize, finite smoke, dan `check_artifacts` semuanya lulus. Binary tidak di-commit dan harus digenerate/dipublikasikan pipeline tepercaya. Model production-calibrated tetap membutuhkan data historis representatif 1-2 tahun dan acceptance threshold.

### [X] L-03 - Coverage Test Mobile dan ML Belum Memadai

**Status perbaikan (15 Juli 2026):** selesai. Python 3.11 x64 lulus compile dan **25/25** test tanpa skip, termasuk artifact contract dan hardening M-14. Flutter analyze melaporkan no issues dan **44/44** test lulus, mencakup sync per `client_uuid` untuk missing/unknown/duplicate/malformed/whole failure/retry, mobile token/refresh, tenant-neutral typed endpoint builders, parser KTP dengan editable confirmation eksplisit, HTTPS base URL, Android cleartext config, certificate pins, dan logging. Android main manifest menonaktifkan cleartext; debug exception hanya untuk `10.0.2.2`.

### [X] L-04 - API ML Mengabaikan Filter `prediction_type`

`ml/src/api.py:143,187` menerima `prediction_type`, tetapi `DataLoader.get_ml_predictions` hanya menerima model_name dan period (`data_loader.py:198-212`). Filter tidak pernah dipakai.

**Status perbaikan (15 Juli 2026):** selesai. Kedua route prediction meneruskan `prediction_type` ke `DataLoader`; query memakai parameter binding dan limit diterapkan langsung di database. Panjang `model_name`/`prediction_type` dibatasi. Unit test memastikan payload injeksi hanya masuk parameter, bukan SQL, serta urutan seluruh filter dan limit benar.

### [X] L-05 - Health ML Tidak Memeriksa Dependency

`ml/src/api.py:61-64` selalu mengembalikan healthy tanpa memeriksa DB, artifact model, disk, atau kemampuan predict.

**Status perbaikan (15 Juli 2026):** selesai. `/health` dan `/health/live` menjadi liveness ringan, sedangkan `/health/ready` mengembalikan 503 sampai service token, koneksi DB, seluruh artifact model enabled, dan model storage siap. Probe blocking dijalankan melalui worker thread, error detail hanya dicatat server-side, koneksi/cursor selalu ditutup, dan DB connection timeout dibatasi. Docker healthcheck memakai liveness agar dependency eksternal yang gagal tidak menyebabkan restart loop. Readiness probes lulus unit test success dan multi-failure tanpa membutuhkan DB/artifact nyata.

### [X] L-06 - Login Web Menyimpan Bearer Token di localStorage

`resources/js/stores/auth.js:24-28,39-43` menyimpan token di localStorage. Jika XSS terjadi, token dapat dicuri. CSP membantu tetapi tidak menghilangkan risiko. Pertimbangkan secure HttpOnly cookie untuk web atau mitigasi lebih ketat.

**Status perbaikan (15 Juli 2026):** selesai. Web memakai Sanctum stateful/session dengan cookie `HttpOnly` dan proteksi CSRF, memulihkan session melalui endpoint user, serta tidak menerima token dari response login atau menyimpan auth di `localStorage`. Mobile tetap memakai Sanctum bearer token dan wajib mengirim `device_name`, sehingga kontrak web dan perangkat dipisahkan eksplisit. Backend test dan Vitest mengunci kedua perilaku.

### [X] L-07 - Logging Mobile Debug Membocorkan Credential/PII ke Log

**Bukti kondisi awal audit yang dikoreksi:** source terbaru sebenarnya tidak lagi mencetak header/body/response, tetapi masih mencetak full base URL, dynamic resource ID, dan raw `DioException.message`. Pola itu tetap membocorkan metadata internal dan tidak memiliki regression guard bila message mengandung nilai sensitif.

**Status perbaikan (15 Juli 2026):** selesai. Logger hanya menghasilkan method, route yang dinormalisasi, status, dan tipe error. Host, query, header, request/response body, raw error message, ID numerik, UUID, dan opaque ID tidak dibaca atau dicetak. Logger menerima `enabled` serta sink terinjeksi agar testable; Flutter tests membuktikan host/query/ID tidak muncul. Seluruh suite Flutter **44/44** dan analyze lulus.

### [X] L-08 - Migration Bundle Kurang Constraint Unik/Cascade

`create_module_bundles_table.php:20-27` tidak memberi unique `(module_bundle_id,module_id)` dan FK tidak memiliki cascade delete. Item duplikat dapat muncul dan bundle tidak dapat dihapus tanpa penanganan manual.

**Status perbaikan (15 Juli 2026):** selesai. MySQL 8.4.9 langsung menolak duplicate bundle item dan cascade delete bundle berhasil. Rollback awal mengungkap bug supporting index; migration diperbaiki dengan membuat single-column support index sebelum composite unique dijatuhkan. Rollback migration `000004`-`000010` dan migrate ulang kemudian lulus; metadata mengonfirmasi composite unique aktif dan FK bundle `CASCADE`, sementara FK module tetap restrictive.

## 6. Audit Tabel, Relasi, Model, dan Seeder

### 6.1 Compatibility Smoke Test yang Sudah Dijalankan

- [X] Semua **60 migration** berhasil pada MySQL 8.4.9 disposable dan SQLite temporary; keduanya menghasilkan **167 tabel** karena lima migration baru hanya menambah constraint.
- [X] Semua **24 seeder** yang didaftarkan pada `DatabaseSeeder.php` berhasil dari database kosong pada MySQL dan SQLite.
- [X] Rollback migration `000004`-`000010` dan migrate ulang lulus pada MySQL setelah supporting-index rollback `L-08` diperbaiki.
- [X] MySQL `information_schema` mengonfirmasi 234 FK total, termasuk 97 tenant batch FK dan 8 actor FK; orphan/cross-tenant actor preflight nol.
- Constraint unik penting tersedia pada customer bill per periode (`bills`: tenant+customer+period), meter reading per periode (`meter_readings`: tenant+customer+period), subscription module (`tenant+module`), dan sejumlah master code per tenant.
- Seeder tersedia untuk fondasi alamat, customer lifecycle, CRM detail, meter extended, notification/chat, billing extra, warehouse, FIN+, asset, chemical, procurement, FSM, maintenance, HR, DMS/GIS/INT/CC, smart utility, dan commerce.

### 6.2 Status Integrasi Runtime Tabel yang Diaudit

| Tabel | Migration | Seeder | Model | Integrasi Runtime |
|---|---|---|---|---|
| `subscription_modules` | Ada | Ada | Ada | Menjadi sumber tunggal entitlement purchase/manual activation/cron/middleware |
| `tenant_module_overrides` | Ada | Wajar kosong | Ada | Hanya dipakai sebagai custom price |
| `subscriptions` | Ada | Wajar kosong | Ada | Dipakai kontrak marketplace platform dan tenant sesuai schema aktual |
| `saas_purchase_orders` | Ada | Wajar kosong | Ada | Pending/idempotent per tenant dan settlement Midtrans amount-matched |
| `saas_purchase_order_lines` | Ada | Wajar kosong | Ada | Snapshot expanded module lines immutable per order |
| `module_price_tiers` | Ada | Ada | Ada | Dipakai pricing marketplace sesuai schema aktual |
| `price_change_logs` | Ada | Wajar kosong | Ada | Model tersedia sesuai schema aktual |
| `promos` | Ada | Ada | Ada | Dipakai marketplace sesuai schema aktual |
| `promo_targets` | Ada | Ada | Ada | Dipakai scope promo sesuai schema aktual |
| `promo_redemptions` | Ada | Ada | Ada | Ditulis sesuai field wajib schema aktual |
| `module_bundles` | Ada | Ada | Ada | Controller memakai `items.module`, dependency, harga bundle, dan purchase aktual |
| `ml_predictions` | Ada | Ada demo | Tanpa Laravel model, sengaja | Python adalah runtime owner dan memakai service token + entitlement AI; bridge serving Laravel belum tersedia |
| `scheduled_reports` | Ada | Wajar kosong | Ada | CRUD/run; timezone dikonversi ke UTC dan didispatch setiap menit |
| `scheduled_report_runs` | Ada | Wajar kosong | Ada | Slot unik, status/history, dan path artifact privat |
| sync job/status table | **Tidak ada** | Tidak ada | Tidak ada | Tidak ada ledger job; status ringkas dihitung langsung dari periode/tugas aktual |

### 6.3 Tabel yang Memang Wajar Tanpa Seeder

Tabel sistem/event-driven tidak wajib diisi demo: `personal_access_tokens`, `password_reset_tokens`, `sessions`, `jobs`, `job_batches`, `failed_jobs`, `cache`, `cache_locks`, `activity_logs`, `payment_gateway_logs`, `user_permissions`, dan log/queue lain. Kekosongan tabel tersebut bukan bug dengan sendirinya.

Tidak setiap tabel memerlukan model Eloquent. Pivot, tabel framework, dan tabel yang hanya dipakai seeder/query builder tanpa runtime model didokumentasikan sebagai pengecualian sengaja; model runtime aktif tetap wajib memiliki tenant scope dan relations yang sesuai.

### 6.4 Catatan Skema dan Integrasi

- Scheduled report memakai tabel jadwal dan ledger run; delivery saat ini berupa artifact CSV/HTML privat yang diunduh melalui endpoint berizin.
- Ledger sync job terpisah tidak ada; idempotency per item dijaga oleh `client_uuid` unique pada meter reading dan survey report.
- Purchase tenant membuat order pending dan baris modul immutable, lalu memperoleh URL pembayaran Snap; settlement Midtrans terverifikasi baru menulis aktivasi secara atomik/idempotent. Jalur platform/manual tetap tersedia.
- Endpoint profile/password/FCM dan portal mobile sudah tersedia; contract test mencakup profile/password/FCM dan ownership detail bill.

## 7. Audit Hardcode dan Data yang Tidak Terhubung Database

### Hardcode Bermasalah

| Lokasi | Hardcode | Status |
|---|---|---|
| `mobile/.../ktp_scanner.dart` | Multipart `ktp_file` memakai `/prospects/upload-ktp`, review+confidence ditampilkan | **Fixed** |
| `mobile/lib/core/constants.dart` | URL sudah memakai `--dart-define`; default dev `10.0.2.2` | **Fixed** |
| `SyncController.php` | Download/status memakai assignment, periode, dan count aktual | **Fixed** |
| Scheduled report dispatcher | Dataset registry, timezone ke UTC, queue, slot unik, artifact privat | **Fixed** |
| Mobile release TLS | Primary+backup SPKI pin wajib melalui dart-define | **Fixed; endpoint/rotasi production perlu drill deployment** |
| `InstallationService.php` | `planning_only` dengan flag reservasi/stock-out/jurnal false | **Fixed, integrasi stok nyata belum tersedia** |
| Login placeholders/demo credential | `pdam-canada`, email demo | **Low**, boleh untuk demo tetapi jangan muncul production |
| Seeder demo password `12345678` | Kredensial semua role | **High bila DatabaseSeeder dijalankan production** |

### Hardcode yang Sah

- Daftar status/role/module code sebagai domain enum/constant dapat diterima bila diselaraskan dengan schema.
- Warna, label UI, pagination default, timeout, dan threshold rule-based dapat berupa konfigurasi/constant.
- Data demo di seeder sah untuk development, tetapi harus dipisah dari seeder production.

## 8. Drift Dokumentasi

### Klaim Historis dan Status Sinkronisasi

| Klaim | Dokumen | Kondisi Audit |
|---|---|---|
| Backend/Web/Mobile 100% | `README.md`, `HANDOVER.md` | **Dikoreksi 15 Juli:** status sekarang hardening/belum production-ready |
| 28/28 done | `README.md`, `HANDOVER.md` | **Dikoreksi:** tabel modul kini menyatakan coverage, bukan production readiness |
| 36 tests, 100 assertions hijau | `task.md` | **Dikoreksi:** SQLite 89/89 (664 assertions); MySQL 88 pass + 1 intentional skip (756 assertions) |
| `npm run test` tersedia | `README.md` | **Sesuai:** script Vitest tersedia dan 5/5 test lulus |
| OWASP/ASVS semua PASS | `SECURITY_CHECKLIST.md`, audit ASVS | **Dikoreksi:** baseline internal dengan gap aktif, bukan sertifikasi |
| Tidak ada raw SQL | `SECURITY_CHECKLIST.md` | **Dikoreksi:** raw aggregate BI dibangun hanya dari registry statis dan identifier di-quote grammar; export memakai allowlist |
| Tidak ada hardcoded credentials | `SECURITY_CHECKLIST.md` | **Dikoreksi:** password demo dinyatakan risiko production |
| Certificate pinning | `task.md`, audit ASVS | **Dikoreksi:** release SPKI primary+backup fail-closed sudah diimplementasikan; endpoint/rotasi nyata adalah gate deployment |
| AI/ML model selesai | `task.md:201`, `:291` | Empat fixture-validation artifact tersedia dan 25/25 test lulus; model production-calibrated tetap memerlukan data representatif 1-2 tahun |
| Offline cache sync selesai | `task.md:184` | Meter/survey sync idempotent dan failure/retry per client UUID tercakup suite Flutter 44/44 |
| Export PDF/Excel/DOC | `task.md:308` | Kontrak aktual kini jujur `csv|html`; native PDF/XLSX/DOC tetap roadmap PRD |
| Marketplace tenant self-service | PRD/flow | **Sesuai:** route/UI tenant, order pending + URL Snap, dan settlement Midtrans terverifikasi tersedia |
| Scheduled report | HANDOVER/flow | **Sesuai:** CRUD/run/history/download privat dan dispatcher queue tersedia |
| Auth bearer web di `localStorage` | checklist/audit | **Dikoreksi:** web memakai cookie session HttpOnly+CSRF; bearer token khusus mobile/API device |
| Dokumentasi login/URL/seeder tersebar | README/backend/HANDOVER/Postman/Swagger | **Disinkronkan 18 Juli:** ownership map, matriks URL, CSRF/session/device flow, troubleshooting, kredensial demo, dan larangan seeder production ditautkan silang |

## 9. Hasil Perintah Verifikasi

| Perintah | Hasil |
|---|---|
| `php artisan test` (SQLite) | **PASS 7 Sept 2026**: 109/109, 768 assertions (15 Jul: 89/89, 664) |
| `php artisan test` (MySQL 8.4.9) | **PASS**: 89 discovered, 88 passed, 1 intentionally SQLite-only skipped, 756 assertions, zero failures |
| `php artisan route:list --json` | **PASS (7 Sep 2026)**: 387 rows — 368 method endpoint `/api/v1` + 19 web; 292 URI API unik. Kanonis: `docs/COUNTS.json` |
| `php artisan route:cache` | **PASS** |
| `npm run test` (Vitest) | **PASS (7 Sep 2026)**: 7 file / 13 test (helper workbench, resources config, router, api errors, auth flow) |
| `npm run build` | **PASS**: 322 modules transformed |
| `flutter analyze` | **PASS**: no issues found |
| `flutter test --no-pub -r expanded` | **PASS**: 44/44 tests |
| `python -m compileall -q src scripts` | **PASS** |
| `python -m pytest tests` (Python 3.11 CI) | **PASS**: 32 test (`test_calibration.py` gate §13 #6 + `test_telemetry_simulator.py` contract ops/simulators); lokal Win-ARM64 tidak ada wheel xgboost → `compileall` saja |
| `python scripts/generate_validation_artifacts.py` | **PASS**: 4 fixture-validation artifacts + manifests/checksums + summary; `check_artifacts` true |
| `composer audit` | **PASS**: no advisories |
| `npm audit --audit-level=high` | **PASS**: 0 vulnerabilities |
| `php artisan migrate:fresh --seed --force` dengan MySQL 8.4.9 | **PASS**: 60 migration + 24 seeder, 167 tabel |
| Rollback `000004`-`000010`, lalu migrate ulang pada MySQL | **PASS** setelah supporting-index rollback `L-08` diperbaiki |
| MySQL `information_schema`/preflight | **PASS**: 234 FK; 97 tenant batch; 8 actor; bundle unique + `CASCADE`; nol orphan/cross-tenant actor |
| `php artisan schedule:list` | **BLOCKED lokal**: cache lock memakai MySQL; command/dispatcher scheduled report tercakup test |
| `php artisan migrate:status` pada `.env` lokal | **BLOCKED**: MySQL lokal port 3306 tidak aktif |
| `docker compose ps` | **BLOCKED**: command Docker tidak tersedia pada mesin ini |

### Snapshot verifikasi 7 September 2026

| Area | Status |
|---|---|
| Seeder production-safe | ✅ `ProductionKernelSeeder` + `DemoSeeder` + `DemoGuard` (blokade mutlak — tidak ada env escape lagi), tes `ProductionSeederIsolationTest` |
| Material order | ✅ reservasi stok → stock-out + jurnal DEBIT kapitalisasi / KREDIT persediaan saat `complete()`; idempoten, race-safe (lock), tes `InstallationMaterialStockOutTest` |
| Export | ✅ kontrak jujur `csv/html` + **xlsx/pdf native** (PhpSpreadsheet + dompdf kop surat); sel angka bertipe number, formula dinetralkan; tes `NativeExportFormatTest` |
| Least-priv DB | ✅ provisioning + trigger append-only + connection `audit`; audit command kini **fail-closed untuk `ALL PRIVILEGES`**; CI job `mysql-production-gates` probe penolakan |
| Backup/restore | ✅ single-DB dump + AES-GCM/PBKDF2 + sha256; restore **terikat TARGET_DB** (tidak bisa menimpa tanpa CONFIRM), strip DEFINER, kredensial via env; bukti drill di CI (artefak evidence) |
| UI web modul | ✅ halaman workbench generik (`/modules/:code`) search+pagination+create+aksi status; KPI dashboard object; helper teruji Vitest |
| Worker/scheduler | ✅ `supervisord.production.conf` (2 worker + scheduler + `pdam:queue-health`) + eventlistener protokol benarmelalui `ops/supervisor/crash_alert.py` |
| Load test | ✅ k6 parameterized (PROFILE/BASE_URL/kredensial env, tolak hardcode), runner `ops/load/run_load_test.sh`, capacity baseline ke `docs/CAPACITY_BASELINE.md` |
| Ops scripts | ✅ `ops/tls/*`, `ops/backup/*`, `ops/mysql/*`, `ops/load/*`, `ops/runbooks/*` lolos `bash -n`; dipakai CI |
| CI | ✅ root `.github/workflows/ci.yml` (6 job: backend-sqlite, mysql-production-gates, frontend, mobile, ml, security); `.gitleaks.toml` diganti allowlist path-scoped (tanpa regex global `12345678`) |
| API/OpenAPI/mobile (H-10) | ✅ CI tooling: `pdam:openapi` (293 path dari registry → docs/openapi.json), `pdam:mobile-coverage` (gate path endpoints.dart; gap nyata `portal/complaints/{id}` kini ditangani `CustomerPortalController::complaint`); Flutter analyze 0 issue + 44/44. Codegen Dart = lanjutan manual.
| Kept-open canonical | 6 gate §13 § status tooling per gate di bawah |

## 10. Prioritas Perbaikan

### P0 - Blokir Rilis

- [X] C-01: Pasang module gate pada seluruh route modul berbayar dan test tenant locked/active.
- [X] C-02/C-03/C-04: Konsolidasikan entitlement ke `subscription_modules`; perbaiki marketplace, promo, subscription, bundle, dan cron agar memakai schema aktual.
- [X] H-01: Tambahkan/import controller route yang hilang dan pastikan `route:list` + `route:cache` lulus.
- [X] C-05: Tutup akses ML dengan service auth dan tenant authorization.
- [X] H-02: Lindungi signed URL berdasarkan ownership/resource, bukan path bebas.
- [X] H-05: Batasi privacy purge ke role compliance/admin dan tambahkan approval/audit.

### P1 - Data dan Fitur Inti

- [X] H-08: Selaraskan endpoint multipart OCR KTP mobile dan tambah contract test.
- [X] H-09/H-10/H-11: Endpoint upload/account/portal dan config Android sudah diselaraskan.
- [X] H-06/H-07: Sync meter/survey idempotent dengan client UUID, assignment, period, dan result per item.
- [X] H-03/H-04: Allowlist seluruh field BI/export dan hilangkan raw identifier dari input.
- [X] M-07: Sediakan marketplace tenant dengan order pending dan settlement Midtrans terverifikasi sebelum aktivasi.
- [X] M-12/M-13: Tambahkan 97 FK tenant dan 8 composite same-tenant actor FK setelah no-mutation preflight.
- [x] Pisahkan seeder demo dari seeder production agar password demo tidak dibuat saat deploy.
  `ProductionKernelSeeder` (permission+module+role+admin-env-only) vs `DemoSeeder`+`DemoGuard`;
  `DatabaseSeeder` me-routing otomatis; dibuktikan `ProductionSeederIsolationTest` (9/9: routing, blokade produksi, blokade admin-env, probe DemoTenant).
- [X] Jalankan dan dokumentasikan migration, rollback, seeder, FK/index/relation/orphan metadata, dan suite backend pada MySQL 8.4.9 disposable.

### P2 - Kualitas dan Kelengkapan

- [X] M-04: Hapus duplikasi JS/TS atau pilih satu source of truth.
- [X] M-03/M-05: Daftarkan halaman web yang didukung dan tampilkan error API.
- [X] M-06: Selaraskan endpoint asset web ke `/assets` dan tambahkan contract test.
- [X] M-08: Ganti kontrak artifact menjadi CSV/HTML yang sesuai dengan format aktual.
- [x] M-10: Material order upgraded — reservasi stok di gudang utama saat order
  (`pdam:wh`+stok cukup), stock-out + jurnal kapitalisasi (DEBIT 1-004/5-001,
  KREDIT 1-003, balance D=K) otomatis saat pemasangan selesai; endpoint issue/cancel
  + idempotensi dijaga `InstallationMaterialStockOutTest` (5/5). `planning_only` hanya
  tetap dipakai bila modul WH mati / material tak resolve / tidak ada gudang utama
  (dengan `reason` eksplisit).
- [X] M-09: Scheduled report CRUD/run/history/download privat, queue, dan dispatcher setiap menit tersedia.
- [X] M-14: Persist/alignment feature order, kontrak 5-tuple, no dummy, enabled controls, failure CLI, dan artifact contract; Python 3.11 suite lulus.
- [X] M-02: Tambah script Vitest dan pastikan frontend test dapat dijalankan.
- [X] L-03: Flutter 44/44 + analyze lulus; Python 3.11 compile + 25/25 lulus tanpa skip.
- [X] H-12: Release SPKI primary+backup fail-closed selesai dan diuji; lakukan endpoint/rotation drill saat deployment.
- [X] L-06: Web memakai Sanctum session HttpOnly+CSRF tanpa bearer token di `localStorage`; mobile tetap bearer per perangkat.

### P3 - Dokumentasi dan Operasional

- [X] Ubah dokumentasi utama dari klaim “100% done” menjadi status coverage dan audit faktual.
- [x] Sinkronkan jumlah endpoint, test, model, tabel, dan seeder secara otomatis di CI.
  `php artisan pdam:counts` (check) + `docs/COUNTS.json`; `--write` regenerasi. Angka faktual:
  368 endpoint API, 65 migration, 28 seeder (jalur production 4), 136 model, 167 tabel statis,
  107 test method backend; CI job `backend-sqlite` gagal bila file committed drift.
- [x] Tambahkan CI gates: migrate+seed MySQL, route cache, backend tests, frontend tests/build,
  Flutter analyze/test, pytest, secret scan, dan dependency audit.
  Workflow baru **root** `.github/workflows/ci.yml` (file lama di `backend/.github` tidak pernah
  dieksekusi GitHub Actions — location bug): jobs `backend-sqlite` (pint, config/route/view/event
  cache, PHPUnit, counts), `mysql-production-gates` (migrate:fresh --seed MySQL 8, provisioning
  least-priv + destructive-probe verify, `pdam:audit-db-privileges`, backup→decrypt→restore drill
  dengan evidence artifact), `frontend` (vitest+build), `mobile` (flutter analyze+test),
  `ml` (compileall+pytest), `security` (gitleaks + composer/npm/pip audit).

## 11. Definisi Selesai yang Direkomendasikan

Sebuah modul baru boleh berstatus **Done** hanya bila:

- Migration dan rollback lulus pada MySQL production-compatible.
- Model/relation/query cocok dengan kolom aktual.
- Seeder demo opsional dan seeder production aman.
- Route memakai auth + tenant + module + permission sesuai kebutuhan.
- API contract terdokumentasi dan diuji.
- UI/web/mobile memakai endpoint nyata tanpa mock/stub tersembunyi.
- Test happy path, validation, forbidden, cross-tenant, retry/idempotency, dan failure path lulus.
- Logging/audit dan observability tersedia.
- Dokumen tidak mengklaim hardware/integrasi eksternal aktif sebelum benar-benar diverifikasi.

## 12. Kesimpulan Akhir

Proyek memiliki 65 migration, 28 class seeder (jalur seed production terisolasi ke `ProductionKernelSeeder`), 136 model, dan 167 tabel (statis dari migration) yang terbukti dapat dibangun pada MySQL 8.4.9 dan SQLite. Semua **39/39 temuan audit application-complete**, termasuk coverage model runtime, 97 tenant FK, 8 actor FK, fixture-validation artifact ML, coverage Flutter/ML, dan constraint bundle/rollback MySQL. Verifikasi MySQL juga menemukan lalu menutup long identifier migration `000005`, supporting-index rollback `L-08`, kebocoran `TenantContext` antar-request, fixture tenant ID `1`, dan normalisasi aggregate decimal string MySQL.

Rekomendasi keputusan tetap: **jangan menyamakan 39/39 temuan aplikasi dengan production-ready**. Enam gate persetujuan production dan rekomendasi operasional tambahan ditetapkan secara canonical pada Bagian 13 berikut.

## 13. Gate Persetujuan Production Canonical

Enam item berikut adalah **blocking gate** yang terpisah dari status 39/39 temuan aplikasi. Persetujuan production memerlukan bukti penutupan untuk **tepat enam gate** ini:

1. **Production TLS endpoint:** domain, sertifikat, chain, hostname, cipher/protocol, HSTS, dan koneksi web/API/mobile diverifikasi pada endpoint production sebenarnya.
2. **External penetration test:** pengujian independen selesai, temuan ditriage, dan seluruh risiko yang menghalangi rilis ditutup atau diterima secara formal oleh pemilik risiko.
3. **Tested backup/restore:** backup terenkripsi dan prosedur restore diuji end-to-end pada environment terisolasi, dengan bukti integritas serta RPO/RTO yang disetujui.
4. **Production database least privileges:** akun aplikasi, migration, backup, dan audit dipisahkan sesuai kebutuhan minimum; termasuk perlindungan append-only yang relevan dan larangan hak destruktif pada akun runtime.
5. **Certificate primary/backup rotation drill:** kedua pin SPKI release diverifikasi terhadap endpoint nyata dan drill perpindahan primary/backup berhasil sebelum distribusi mobile production.
6. **Representative-data ML calibration/acceptance:** model dilatih dan dievaluasi dengan data historis representatif, threshold penerimaan disetujui, serta artifact `production_calibrated=true` dipublikasikan lewat pipeline tepercaya. Empat artifact saat ini tetap `fixture_validation` dan `production_calibrated=false`.

### Status tooling §13 (diperbarui 7 September 2026 — pasca code review)

Keenam gate canonical **tetap terbuka** sampai dunia nyata membuktikan, tetapi tooling-nya sudah
lengkap, sudah ditinjau ulang (bug lama diperbaiki), dan CI sudah menjalankan drill yang bisa
dijalankan otomatis. Indeks tooling: [`docs/DOC_MAP.md`](docs/DOC_MAP.md) §3 dan [`ops/README.md`](ops/README.md).

| Gate | Tooling di repo | Otomatis di CI | Bukti yang masih butuh dunia nyata |
|---|---|---|---|
| 1 TLS endpoint | `ops/tls/verify_production_tls.sh` (DNS, redirect, chain+SAN/CN, expiry, tolak TLS<1.2/cipher lemah, HSTS, cek `/up` + endpoint publik API) → `ops/tls/evidence/tls-<host>-<ts>.json` | — | deploy domain/certbot production; jalankan script → lampirkan evidence |
| 2 Pentest eksternal | `ops/PENTEST_SCOPE.md` (scope + template sign-off), pre-scan internal `tests/security/{pentest,zap_scan,smoke_test}.sh` | — | engagement vendor, laporan, triage, sign-off pemilik risiko |
| 3 Backup/restore | `ops/backup/backup_production.sh` (dump single-DB, gzip, AES-256-GCM+PBKDF2, sha256, meta durasi, umask 077, kredensial via env), `restore_production.sh` (`TARGET_DB` wajib, refusal schema sumber, strip DEFINER), `restore_drill.sh` (`evidence.json` count/checksum) | **Ya** — job `mysql-production-gates` menjalankan drill penuh | cron di host backup, passphrase di vault, arsip evidence run target, approval RPO≤60mnt/RTO≤4j |
| 4 Least-priv DB | `backend/database/provisioning/mysql-privileges.sql` (runtime tanpa DDL; backup db-scoped read-only; audit INSERT/SELECT), trigger `privacy_audit_events_no_{update,delete}`, koneksi `audit` opsional (`DB_AUDIT_*`), `ops/mysql/{create_users,verify_privileges}.sh`, `php artisan pdam:audit-db-privileges` (fail-closed; akun ALL/DDL terdeteksi) | **Ya** — provisioning + 11 probe penolakan + audit + queue/integrations health | eksekusi provisioning di cluster produksi + arsip output |
| 5 Rotasi pin | `ops/tls/verify_spki_pins.sh` + `ops/runbooks/TLS_PINNING_ROTATION.md` (drill 7 langkah) + dart-defines build | — | endpoint §1 hidup; drill primary↔backup di device uji |
| 6 ML calibration | `php artisan pdam:ml-export-training-data` (streaming; CSV = kontrak `ml/src/data_loader.py`; file 0600; `days_since_*` positif), `ml/src/{calibration,csv_dataset}.py`, `ml/scripts/calibrate_production.py` (candidate → `--promote`), ambang `ml/config.yaml:production_acceptance`, runbook [`docs/ML_CALIBRATION.md`](docs/ML_CALIBRATION.md) | **Ya** — job `ml` menjalankan pytest gerbang threshold (dataset sintetis 24 bln); artifact produksi TIDAK tersentuh tanpa `--promote` sah | ekspor data riil 1–2 th + persetujuan threshold + jalankan `--promote` + arsip summary |

Status artefak ML hari ini: keempat artifact tetap **`fixture_validation`** dengan
`production_calibrated=false` sampai langkah riil gate #6 selesai. Seeder demo: `ProductionKernelSeeder`
+ `DemoGuard` memblokir fixture demo **secara mutlak** saat `APP_ENV=production`. Kontrak export UI:
`csv|html|xlsx|pdf|docx` (DOCX via PhpWord; DOC-native legacy .doc belum, kontrak jujur .docx). Worker/scheduler production: `backend/docker/supervisord.production.conf`
(alarm `pdam:queue-health` + eventlistener protokol `ops/supervisor/crash_alert.py`).

### Rekomendasi Operasional Tambahan

Item berikut penting untuk operasi yang andal, tetapi **tidak dicampur ke daftar enam gate canonical**:

- Aktifkan dan uji worker queue `default`, Laravel scheduler per menit, retry, serta `failed_jobs` pada target.
  ➜ `backend/docker/supervisord.production.conf` (worker+scheduler+`pdam:queue-health`);
  `php artisan pdam:queue-health` = command alarm.
- Verifikasi integrasi eksternal end-to-end, termasuk settlement Midtrans, OCR, email/push, dan integrasi vendor yang benar-benar digunakan.
  ➜ `php artisan pdam:integrations-health [--ping]` — check config + panggilan live (Midtrans status/Vision/ML ready).
- Pisahkan seeder demo dari jalur provisioning/deploy production agar kredensial dan fixture demo tidak dibuat. ✅
  Sudah selesai (`ProductionKernelSeeder` vs `DemoSeeder`+`DemoGuard`, lihat §10 P1).
- Terapkan observability, alerting, runbook insiden, capacity baseline, dan load test pada topology representatif.
  ➜ `ops/runbooks/OBSERVABILITY.md` + `ops/load/run_load_test.sh` (k6 PROFILE=smoke|load|stress,
  kredensial via env, BASE_URL riil) — jalankan di staging mirror sebelum rilis.

---
