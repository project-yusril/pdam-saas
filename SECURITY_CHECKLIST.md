# OWASP Top 10 + ASVS — Checklist Keamanan PDAM SaaS

**Diperbarui:** 7 September 2026 (sebelumnya 18 Juli; peta dokumen & fact sheet: [`docs/DOC_MAP.md`](docs/DOC_MAP.md))  
**Status:** baseline implementasi + bukti internal; **bukan** sertifikasi OWASP/ASVS, bukan hasil penetration test eksternal.  
**Gap aktif & gate production:** [`temuan2.md`](temuan2.md).

> **Sumber status saat ini:** [`temuan2.md`](temuan2.md), termasuk enam gate persetujuan production canonical. Peta dokumen/fact sheet: [`docs/DOC_MAP.md`](docs/DOC_MAP.md) · tooling pembuktian: [`ops/README.md`](ops/README.md) · CI: [`.github/workflows/ci.yml`](.github/workflows/ci.yml). Dokumen terkait: [`README.md`](README.md) · [`PRD.md`](PRD.md) dan [`02_flow.md`](02_flow.md) sebagai target/desain · [`task.md`](task.md) dan [`temuan.md`](temuan.md) sebagai arsip · [`tests/security/OWASP_ASVS_AUDIT.md`](tests/security/OWASP_ASVS_AUDIT.md) sebagai assessment internal · [`HANDOVER.md`](HANDOVER.md) · [`backend/DEPLOY.md`](backend/DEPLOY.md) sebagai runbook deployment.

> **Tautan wajib:** [`README.md`](README.md) · [`temuan2.md`](temuan2.md) · [`02_flow.md`](02_flow.md) · [`HANDOVER.md`](HANDOVER.md) · [`SECURITY_CHECKLIST.md`](SECURITY_CHECKLIST.md) · [`docs/DOC_MAP.md`](docs/DOC_MAP.md) · [`docs/COUNTS.json`](docs/COUNTS.json) · [`ops/README.md`](ops/README.md) · [`backend/README.md`](backend/README.md) · [`backend/DEPLOY.md`](backend/DEPLOY.md) · [`backend/SEED_DATA.md`](backend/SEED_DATA.md) · [`ml/README.md`](ml/README.md) · [`docs/ML_CALIBRATION.md`](docs/ML_CALIBRATION.md) · [`docs/BUSINESS_DECISIONS.md`](docs/BUSINESS_DECISIONS.md)
> <!-- doc-sync:links -->
<!-- doc-sync:start verifikasi 7 Sept 2026 -->
> **Verifikasi terintegrasi:** 117/117 (117 test backend, 812 assertions) · Vitest 13 · Flutter analyze 0 issue + 46/46 · ML 32 (CI) · 369 method /api/v1 (293 path registry) · 65 migration · 28 seeder · 21 command · 167 tabel statis · 136 model · 2026-09-07. Kanoni angka: [`docs/COUNTS.json`](docs/COUNTS.json) · status resmi: [`temuan2.md`](temuan2.md) §13 · peta dokumen: [`docs/DOC_MAP.md`](docs/DOC_MAP.md) · tooling: [`ops/README.md`](ops/README.md) · CI: [`.github/workflows/ci.yml`](.github/workflows/ci.yml) + `nightly-ops.yml`.
<!-- doc-sync:end -->

## A1: Broken Access Control
- [x] Web memakai Sanctum stateful/session cookie `HttpOnly` + CSRF; mobile/API device memakai bearer token
- [x] `CheckPermission` memeriksa entitlement modul sebelum bypass RBAC admin tenant
- [x] Module gate eksplisit dipasang pada endpoint berbayar yang tidak memiliki permission granular
- [x] Middleware `SetTenant` (isolasi data antar tenant via Global Scope)
- [x] `TenantContext` diturunkan dari user terautentikasi oleh middleware tenant
- [x] Model bisnis utama memakai `BelongsToTenant`; tabel platform/audit tertentu sengaja dikecualikan
- [x] 97 tenant FK `RESTRICT` dan 8 composite same-tenant meter actor FK mencegah orphan/cross-tenant actor
- [x] `TenantContext` dibersihkan antar-request; regresi long-lived request diuji
- [x] Signed URL me-resolve resource tenant dan memvalidasi prefix tenant/purpose

## A2: Cryptographic Failures
- [x] Enkripsi at-rest field sensitif (NIK via `Encrypted` cast)
- [x] Storage privat untuk foto (KTP, meter, rumah) + signed URL temporer
- [x] `APP_KEY` `.env` — tidak boleh kosong
- [x] Sanctum token hash SHA-256 (default)
- [x] Midtrans webhook signature SHA512 verified
- [x] Flutter release memverifikasi primary+backup SPKI SHA-256 setelah trust platform dan hostname check

## A3: Injection
- [x] Query biasa memakai binding Eloquent/query builder
- [x] BI/export dinamis memakai registry allowlist untuk dataset, kolom, filter, sort, dan metric
- [x] Identifier aggregate BI di-quote dengan grammar koneksi; nilai filter tetap memakai binding query builder
- [x] Export generik membatasi PII/internal ID dan menetralkan formula CSV; menerima `csv|html|xlsx|pdf|docx`
      dengan ekstensi+MIME sesuai: XLSX riil via PhpSpreadsheet (sel angka bertipe number, formula
      di-netralkan hanya pada sel teks, header/title tidak lagi jadi formula vector), PDF riil via dompdf
      dengan kop surat PDAM; biner dikirm via `content_base64`. DOC surat-menyurat tetap roadmap.
- [x] WAF middleware: blokir SQL injection pattern + XSS + path traversal
- [x] Jalur sensitif/dinamis yang diaudit memakai validasi server-side; coverage seluruh endpoint tidak diklaim exhaustive

## A4: Insecure Design
- [x] Rate limit login: `throttle:5,1` + lock akun setelah 5x gagal
- [x] Middleware `LockAccount` — cache-based 15 menit lock
- [x] `TrackFailedLogin` — hitung kegagalan
- [x] Middleware `throttleApi()` global Laravel

## A5: Security Misconfiguration
- [x] `SecureHeaders` middleware: X-Frame-Options `DENY`, X-Content-Type-Options `nosniff`, XSS Protection, HSTS, Referrer-Policy, Permissions-Policy
- [x] `ContentSecurityPolicy` middleware: CSP header proper
- [x] `CorsWhitelist` — whitelist origin spesifik (env-based)
- [x] Vite: `sourcemap: false` di production, `drop_console: true`, `drop_debugger: true`
- [x] `.gitignore` — coverage `node_modules`, `storage`, `.env`, `vendor`
- [x] `APP_DEBUG=false` di production (default `.env.ci` sudah false)

## A6: Vulnerable Components
- [x] `composer audit` + `npm audit --audit-level=high` + `pip-audit` dijalankan CI (job `security`)
- [x] Dependensi dibekukan di `composer.lock` + `package-lock.json` + `ml/requirements.txt`
- [x] Secret-scan (gitleaks CLI + allowlist path-scoped, bukan action berlisensi) + counts/openapi-drift + drill privilege/backup AES-CBC — lulus CI run 2909807
- [ ] Coverage audit runtime dependency di host produksi (versi terpasang) tetap prosedur Berkala — ops

## A7: Authentication Failures
- [x] MFA/2FA (TOTP) untuk role sensitif (finance, director, super_admin)
- [x] Password hashed (bcrypt default Laravel)
- [x] Session web direstore server-side dan logout merevoke session; token mobile dapat direvoke (`auth:sanctum`)
- [x] `X-Data-Consent` header untuk consent tracking (UU PDP)
- [x] Session/Sanctum token scoped per tenant
- [x] Browser memakai urutan CSRF bootstrap + cookie session; mobile bearer hanya diterbitkan jika `device_name` dikirim
- [x] Dokumentasi lokal melarang pencampuran hostname `localhost`/`127.0.0.1` dalam satu sesi cookie

## A8: Software and Data Integrity Failures
- [x] Signature key Midtrans webhook diverifikasi (SHA512)
- [x] File upload: validasi mimes + max size + disk privat
- [x] Path attachment KTP/meter/survey divalidasi terhadap tenant dan purpose
- [x] Deserialisasi: tidak ada `unserialize()` yang menerima input user
- [x] ML memvalidasi manifest/schema/checksum/provenance sebelum pickle artifact dideserialisasi

## A9: Security Logging and Monitoring Failures
- [x] `ActivityLog` dan `LogsActivity` mencatat banyak aksi bisnis utama
- [x] Log IP address di setiap activity log
- [x] Privacy purge memakai audit append-only model terpisah dengan hash chain
- [x] Privilege database INSERT-only untuk `privacy_audit_events` harus diterapkan saat deployment
      ➜ `backend/database/provisioning/mysql-privileges.sql` + trigger append-only (migration
      `2026_09_06_000002`) + opsi connection `audit` (`DB_AUDIT_USERNAME`) utk akun INSERT/SELECT-only;
      bukti otomatis: job CI `mysql-production-gates` (run 2909807: privileges drill OK) menjalankan `ops/mysql/verify_privileges.sh`
      (probe UPDATE/DELETE/TRUNCATE/DROP **harus ditolak**) + `php artisan pdam:audit-db-privileges`.
- [x] Exception handler terpusat (`ApiResponse` + handler di `bootstrap/app.php`)

## A10: SSRF (Server-Side Request Forgery)
- [x] Tidak ada fitur user-input URL yang di-fetch server
- [x] Webhook verify signature before processing (Midtrans)
- [x] Signed URL untuk file (tidak ada open redirect)

---

## ASVS Level 1 Checklist

### V2 Authentication — [x] Rate limit login, [x] MFA TOTP
### V3 Session — [x] Cookie web HttpOnly+CSRF, [x] Token tidak ada di URL/localStorage, [x] Revoke logout
### V4 Access Control — [x] RBAC, [x] Tenant isolation, [x] Module enforcement
### V5 Validation — [x] Input validation, [x] File type/size, [x] WAF filter
### V6 Cryptography — [x] Encrypted cast, [x] Signed URL
### V7 Error Handling — [x] Exception handler no leak stack trace
### V8 Data Protection — [x] Right to access/anonymization, [x] Retention preview, [x] Purge dual-control

---

## WAF Rules Summary

| Rule | Implementation |
|------|---------------|
| SQL Injection | Pattern blocked + Eloquent ORM |
| XSS | Pattern blocked + CSP header |
| Path Traversal | Pattern blocked |
| Rate Limit | `throttle:5,1` + global `throttleApi()` |
| IP Block | Manual via `LockAccount` (15 menit) |
| Bot Detection | Added `WafMiddleware` for signature check |
| CSP | `ContentSecurityPolicy` middleware |
| CORS | `CorsWhitelist` middleware |
| Input Sanitization | `InputSanitizer` middleware |

---

## Secret Handling

- `.env` di `.gitignore` — ✅
- Kredensial demo hanya untuk development; `DatabaseSeeder` pada production otomatis menjalankan
  HANYA `ProductionKernelSeeder` — `DemoSeeder`/`DemoTenantSeeder`/`SambasTenantSeeder` dan
  password `12345678` diblokir `Database\Seeders\Support\DemoGuard` — ✅
- Semua key via `config/services.php` + `env()` — ✅
- Audit dependency manual terbaru PASS; verifikasi gate CI masih perlu dilakukan — ⚠️

## Gap Terbuka

- Seluruh 39/39 temuan audit aplikasi selesai; status ini bukan sertifikasi atau persetujuan production.
- Certificate pinning mobile sudah diimplementasikan dengan primary+backup SPKI wajib dan fail-closed; endpoint TLS/rotation drill masih gate deployment.
- Export kini `csv|html|xlsx|pdf|docx` (lihat A3); DOCX surat-menyurat via PhpWord (file WordprocessingML riil).
- Frontend tidak menyimpan bearer auth di `localStorage`; login web memakai session cookie HttpOnly+CSRF.
- Logging mobile sudah dibatasi ke route ternormalisasi/status/type dan tercakup suite Flutter terbaru.
- Enam gate persetujuan production canonical belum ditutup: TLS endpoint, pentest eksternal, tested backup/restore, least-privilege DB, drill rotasi pin, dan kalibrasi/acceptance ML representative-data. Definisi authoritative ada di `temuan2.md`.
- Fixture-validation ML tersedia dan terverifikasi, tetapi production-calibrated models memerlukan data historis representatif 1-2 tahun, acceptance threshold, dan publikasi artifact dari pipeline tepercaya.

---

**Updated:** 7 September 2026 — PDAM SaaS Security Baseline (peta dokumen: [`docs/DOC_MAP.md`](docs/DOC_MAP.md))
