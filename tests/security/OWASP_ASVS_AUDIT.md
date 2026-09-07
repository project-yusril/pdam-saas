# OWASP ASVS Level 2 — Working Assessment
# PDAM SaaS Platform
# Updated: 7 September 2026 (sebelumnya 15 Juli 2026)

> Dokumen ini adalah working assessment internal, bukan sertifikasi ASVS atau laporan penetration test.
> Status `✅` lama harus dibaca sebagai kontrol yang ditemukan di source, bukan bukti seluruh requirement
> telah diuji pada production. Seluruh 39/39 temuan aplikasi sudah ditutup dan diverifikasi, termasuk suite
> MySQL 8.4.9 disposable. Sejak snapshot 15 Juli, item yang toolingnya TERBUKTI OTOMATIS DI CI sudah bisa
> dianggap "terbukti sebagai kontrol" (seeder-production rut, privilege DB + trigger append-only, backup/restore
> drill, export injection-guard, audit `pdam:counts`) — tetapi **TLS endpoint/riil, external pentest, data
> riil ML, dan keputusan PRD §23 tetap terbuka**. Status authoritative: `../../temuan2.md`.
>
> **Dokumen terkait:** peta dokumen & fact sheet [`../../docs/DOC_MAP.md`](../../docs/DOC_MAP.md) ·
> [`../../README.md`](../../README.md) · [`../../PRD.md`](../../PRD.md) dan [`../../02_flow.md`](../../02_flow.md)
> sebagai target/desain · [`../../task.md`](../../task.md) dan [`../../temuan.md`](../../temuan.md) sebagai arsip ·
> [`../../SECURITY_CHECKLIST.md`](../../SECURITY_CHECKLIST.md) sebagai baseline internal ·
> [`../../HANDOVER.md`](../../HANDOVER.md) · [`../../backend/DEPLOY.md`](../../backend/DEPLOY.md).
> Enam gate persetujuan production canonical hanya didefinisikan di [`../../temuan2.md`](../../temuan2.md);
> tooling pembuktian: [`../../ops/README.md`](../../ops/README.md).

<!-- doc-sync:start verifikasi 7 Sept 2026 -->
> **Verifikasi terintegrasi:** 117/117 (117 test backend, 812 assertions) · Vitest 13 · Flutter analyze 0 issue + 46/46 · ML 32 (CI) · 369 method /api/v1 (293 path registry) · 65 migration · 28 seeder · 21 command · 167 tabel statis · 136 model · 2026-09-07. Kanoni angka: [`docs/COUNTS.json`](../../docs/COUNTS.json) · status resmi: [`temuan2.md`](../../temuan2.md) §13 · peta dokumen: [`docs/DOC_MAP.md`](../../docs/DOC_MAP.md) · tooling: [`ops/README.md`](../../ops/README.md) · CI: [`.github/workflows/ci.yml`](../../.github/workflows/ci.yml) + `nightly-ops.yml`.
<!-- doc-sync:end -->

## Executive Summary

| Metric | Value |
|--------|-------|
| Target | PDAM SaaS documentation/source snapshot, 7 September 2026 (sebelumnya 15 Juli) |
| Framework | OWASP ASVS 4.0.3 Level 2 working baseline |
| Testing Method | Source review + selected automated/manual tests; no external pentest (gate §13) |
| Total Controls Tested | Legacy scope estimate 125; not recounted against this snapshot |
| Passed | Belum dihitung ulang |
| Failed | Belum dihitung ulang |
| Not Applicable | Belum dihitung ulang |

---

## V1: Architecture, Design and Threat Modeling

| # | Requirement | Status | Evidence |
|---|-------------|--------|----------|
| V1.1.1 | Secure SDLC documented | ✅ | PRD.md Section 15 Security, SECURITY_CHECKLIST.md |
| V1.1.2 | Threat model for all components | ⚠️ Partial | Security requirements and architecture notes exist; no complete component/data-flow threat model was evidenced |
| V1.1.3 | User stories include security constraints | ✅ | Roles defined with permission boundaries (PRD 6.B) |
| V1.1.4 | Security requirements defined | ✅ | SECURITY_CHECKLIST.md all OWASP A1-A10 |
| V1.2.1 | Input validation architecture | ✅ | WafMiddleware, InputSanitizer, Form Requests |
| V1.2.2 | Output encoding architecture | ✅ | Vue auto-escape, CSP headers |
| V1.2.3 | Authentication architecture | ✅ | Sanctum + MFA TOTP (PRD 15.D) |
| V1.2.4 | Access control architecture | ✅ | RBAC 120+ permissions + tenant isolation |
| V1.4.1 | Trusted service layer documented | ✅ | API Gateway pattern via Sanctum + rate limiting |
| V1.4.2 | All components listed | ✅ | Docker compose services (app, mysql, redis, queue, scheduler) |
| V1.4.3 | Third-party components documented | ✅ | composer.json + package.json + pip requirements |
| V1.4.4 | Architecture for sensitive data | ✅ | Encrypted cast, private storage, signed URLs |
| V1.4.5 | High-level architecture diagram | ✅ | README.md |

---

## V2: Authentication Verification Requirements

| # | Requirement | Status | Implementation |
|---|-------------|--------|----------------|
| V2.1.1 | Password strength enforced | ⚠️ Evidence-limited | Hashing is evidenced; complete strength policy was not independently verified |
| V2.1.2 | Password minimum 8 chars | ⚠️ Evidence-limited | Demo password length is eight; all create/reset/change paths were not exhaustively traced |
| V2.1.3 | Password not commonly used | ❌ Not evidenced | Rate limiting/lockout does not provide breached/common-password screening |
| V2.1.4 | Password change requires current password | ⚠️ Evidence-limited | Change-password contract exists; requirement needs endpoint-specific verification |
| V2.1.5 | Multi-factor authentication for sensitive operations | ✅ | MfaController (TOTP setup/enable/verify) |
| V2.1.6 | MFA code valid for limited time | ✅ | TOTP standard (30-second window) |
| V2.1.7 | Recovery codes for MFA | ✅ | MfaController recovery codes |
| V2.1.8 | Weak authenticators prevented | ✅ | Rate limit 5/min |
| V2.2.1 | Anti-automation (rate limiting) | ✅ | throttle:5,1 + LockAccount |
| V2.2.2 | Lockout mechanism | ✅ | 15-minute lock after 5 failed attempts |
| V2.2.3 | Failed login notification (optional) | ✅ | TrackFailedLogin middleware |
| V2.3.1 | Password reset secure | ⚠️ Evidence-limited | Framework tables/config exist; complete reset flow was not verified in this audit |
| V2.4.1 | Credentials over encrypted channel | ⚠️ | Konfigurasi HTTPS dan pinning Flutter tersedia; endpoint TLS production belum diverifikasi |
| V2.5.1 | Token-based authentication | ✅ | Sanctum bearer untuk mobile/API device; session Sanctum stateful untuk web |
| V2.5.2 | Token hashing | ✅ | SHA-256 (Laravel default) |
| V2.5.3 | Session/token selection | ✅ | Web cookie HttpOnly+CSRF; mobile bearer token per `device_name` |
| V2.5.4 | Token/session revocation | ✅ | Logout merevoke token mobile atau session web |
| V2.5.5 | Token not in URL | ✅ | Only Authorization header |
| V2.5.6 | Token refresh mechanism | ✅ | /api/v1/refresh-token |
| V2.7.1 | No default credentials | ✅ | Seeder demo memakai `12345678` **hanya untuk development**; `DemoGuard` memblokir mutlak seed demo/`12345678` saat `APP_ENV=production` (+ admin production via env `PLATFORM_ADMIN_*` yang menolak password demo). Bukti: `ProductionSeederIsolationTest` 9/9 di CI |
| V2.7.2 | Account enumeration prevented | ✅ | Consistent error messages |

---

## V3: Session Management

| # | Requirement | Status | Implementation |
|---|-------------|--------|----------------|
| V3.1.1 | No session tokens in URL/client storage | ✅ | Cookie web HttpOnly; bearer mobile hanya di Authorization header |
| V3.2.1 | Session/token generated server-side | ✅ | Laravel session dan Sanctum token generation |
| V3.3.1 | Logout invalidates session | ✅ | Session web/token mobile direvoke saat logout |
| V3.3.2 | Session/token timeout | ✅ | Laravel session dan Sanctum TTL configurable |
| V3.4.1 | Session/token bound to user | ✅ | Session/token scoped ke user + tenant; mobile memakai `device_name` |

---

## V4: Access Control

| # | Requirement | Status | Implementation |
|---|-------------|--------|----------------|
| V4.1.1 | Enforce access control on trusted service layer | ✅ | CheckPermission + CheckModuleAccess middleware |
| V4.1.2 | Deny-by-default access control | ⚠️ | Route berbayar utama sudah digate; audit route per endpoint tetap diperlukan |
| V4.1.3 | Principle of least privilege | ⚠️ | 35 role template tersedia; admin tenant sengaja memiliki akses luas |
| V4.1.4 | Sensitive operations protected | ⚠️ | Private file, privacy, dan BI/export diperkuat; audit endpoint lain dan deployment tetap terbuka |
| V4.1.5 | Directory browsing disabled | ✅ | Nginx config blocks hidden files |
| V4.2.1 | Validation of inputs | ⚠️ Partial | Reviewed sensitive/dynamic paths use validation; no exhaustive proof for every endpoint |
| V4.2.2 | Cross-tenant isolation | ✅ | BelongsToTenant global scope + 97 tenant FK RESTRICT + TenantContext reset antar-request |
| V4.3.1 | Anti-IDOR (Insecure Direct Object Reference) | ✅ | Query scope + 8 composite same-tenant meter actor FK mencegah actor lintas tenant |
| V4.3.2 | Module-based entitlement | ✅ | CheckModuleAccess blocks locked modules with 403 |

---

## V5: Validation, Sanitization and Encoding

| # | Requirement | Status | Implementation |
|---|-------------|--------|----------------|
| V5.1.1 | Input validation on HTTP parameters | ⚠️ Partial | Validation is evidenced on reviewed endpoints; not exhaustively enumerated |
| V5.1.2 | Framework validation used | ✅ Reviewed paths | Laravel `validate()`/Form Requests are used on audited paths |
| V5.1.3 | Server-side validation mandatory | ⚠️ Partial | Security-sensitive reviewed paths validate server-side; global absolute not established |
| V5.1.4 | Whitelist validation preferred | ✅ | MIME, enum, dan registry dataset/identifier BI-export |
| V5.1.5 | URL redirect validated | ✅ | No user-input redirects |
| V5.2.1 | SQL injection prevention | ✅ | Eloquent/query binding + static BI/export identifier registry dan grammar quoting |
| V5.2.2 | LDAP injection (N/A) | N/A | No LDAP used |
| V5.2.3 | OS command injection prevented | ✅ | No shell execution from user input |
| V5.2.4 | Output encoding for XSS | ✅ | Vue auto-escape + CSP + WAF |
| V5.2.5 | DOM XSS prevented | ✅ | CSP headers |
| V5.2.6 | Deserialization safe | ✅ | No unserialize() on user data; ML verifies artifact manifest/schema/checksum before pickle load |
| V5.2.7 | Expression language injection (N/A) | N/A | No template injection engine used |
| V5.3.1 | HTTP parameter pollution prevented | ✅ | Laravel array handling |
| V5.3.2 | Mass assignment protection | ⚠️ Partial | Explicit model controls are present on reviewed runtime models; “all models” was not exhaustively proven |
| V5.3.3 | File upload validation | ✅ | MIME type + size + private disk |
| V5.3.4 | File metadata stripped | ❌ Not evidenced | Renaming a file does not strip EXIF/document metadata |
| V5.3.5 | Uploaded files stored outside web root | ✅ | Private disk + signed URLs |
| V5.4.1 | Anti-automation on sensitive operations | ✅ | throttleApi global |

---

## V6: Stored Cryptography

| # | Requirement | Status | Implementation |
|---|-------------|--------|----------------|
| V6.1.1 | Modern cryptography only | ✅ | AES-256 via Laravel Encrypted cast |
| V6.1.2 | Key management | ⚠️ Partial | `APP_KEY` is environment-provided; production storage, access, backup, and custody were not verified |
| V6.1.3 | Encryption key rotation capable | ⚠️ Partial | Configuration is replaceable, but a data-safe rotation procedure/drill was not evidenced |
| V6.2.1 | Sensitive data encrypted at rest | ✅ | NIK (KTP) via Encrypted cast |
| V6.2.2 | Hashed passwords with salt | ✅ | bcrypt (Laravel default) |
| V6.2.3 | Transport layer encryption | ⚠️ | Konfigurasi TLS 1.2+ tersedia; server/certificate target belum diverifikasi |
| V6.3.1 | Secret management | ⚠️ Partial | `.env` is ignored; production vaulting, access, and rotation are deployment work |
| V6.3.2 | No secrets in code | ⚠️ Unverified | No complete secret-scan result was evidenced; CI coverage is partial |
| V6.4.1 | Random values cryptographically strong | ✅ | Laravel Str::random() |

---

## V7: Error Handling and Logging

| # | Requirement | Status | Implementation |
|---|-------------|--------|----------------|
| V7.1.1 | No stack traces exposed | ✅ | Centralized exception handler |
| V7.1.2 | Generic error messages to users | ✅ | ApiResponse wrapper |
| V7.2.1 | Authentication decisions logged | ⚠️ Partial | Activity/failure logging exists; exhaustive logging of every decision was not verified |
| V7.2.2 | Access control failures logged | ⚠️ Partial | Central handling/middleware exists; exhaustive 403 audit coverage was not demonstrated |
| V7.3.1 | Logs don't include sensitive data | ✅ | SensitiveData masking |
| V7.3.2 | Log timestamps with timezone | ✅ | Laravel timestamps |
| V7.3.3 | Log integrity | ✅ | Privacy audit append-only + hash chain (trigger DB `privacy_audit_events_no_{update,delete}` + `pdam:audit-db-privileges` fail-closed). CI `mysql-production-gates` membuktikan probe penolakan + INSERT/SELECT-only akun audit |
| V7.4.1 | Error handling doesn't leak info | ✅ | No server/PHP version in responses |
| V7.4.2 | Exception handler unified | ✅ | bootstrap/app.php exceptions handler |

---

## V8: Data Protection

| # | Requirement | Status | Implementation |
|---|-------------|--------|----------------|
| V8.1.1 | Sensitive data in HTTP body only | ✅ | POST/PUT for sensitive operations |
| V8.1.2 | Cache-Control for sensitive data | ✅ | SecureHeaders middleware |
| V8.1.3 | Auto-complete disabled | ✅ | N/A for API |
| V8.2.1 | Data classification documented | ✅ | PRD 15.E (PII vs non-PII) |
| V8.2.2 | Data at rest encrypted for PII | ✅ | NIK encrypted |
| V8.2.3 | Data retention policy | ✅ | DataPrivacyController (export, delete, retention) |
| V8.3.1 | Right to access data | ✅ | /privacy/export-my-data |
| V8.3.2 | Right to erasure | ✅ | /privacy/delete-my-data (anonymization) |
| V8.3.3 | Data retention limits | ✅ | /privacy/retention-status |
| V8.3.4 | Consent management | ✅ | X-Data-Consent header tracking |
| V8.3.5 | Purpose limitation | ✅ | DataPrivacyController |

---

## V9: Communications

| # | Requirement | Status | Implementation |
|---|-------------|--------|----------------|
| V9.1.1 | TLS for all communication | ⚠️ | Konfigurasi Nginx tersedia; environment target belum diverifikasi |
| V9.1.2 | Strong TLS ciphers only | ⚠️ | Harus diuji terhadap server production |
| V9.1.3 | TLS certificate valid | ⚠️ | Domain/certificate production belum tersedia pada audit |
| V9.2.1 | Certificate pinning for mobile | ✅ | Release mewajibkan SPKI SHA-256 primary+backup, mempertahankan trust/hostname platform, dan fail-closed pada pin invalid; drill endpoint/rotasi masih deployment gate |
| V9.2.2 | API communication encrypted | ⚠️ | API dirancang untuk HTTPS Bearer token; deployment target belum diverifikasi |
| V9.2.3 | Webhooks verify signatures | ✅ | SHA512 signature verification (Midtrans) |

---

## V10: Malicious Code

| # | Requirement | Status | Implementation |
|---|-------------|--------|----------------|
| V10.1.1 | Code review for malicious code | ⚠️ Partial | Dependency audits are clean; they are not a malicious-code review |
| V10.2.1 | Application integrity verified | ✅ | composer.lock + package-lock.json |
| V10.2.2 | Third-party library verification | ✅ | composer audit + npm audit in CI |
| V10.3.1 | Build pipeline security | ✅ | Root CI `.github/workflows/ci.yml` menjalankan secret scan (gitleaks + allowlist path-scoped `.gitleaks.toml`), composer/npm/pip audit, pint/route-cache, PHPUnit, Vitest+build, Flutter analyze/test, pytest ml, `pdam:counts`, plus drill MySQL (privileges + backup/restore) — lihat pula `../../SECURITY_CHECKLIST.md` A6/A9 |
| V10.3.2 | Code signing | ⚠️ | Obfuscation Flutter bukan code signing; signing release perlu diverifikasi terpisah |

---

## V11: Business Logic

| # | Requirement | Status | Implementation |
|---|-------------|--------|----------------|
| V11.1.1 | Business logic flows sequential | ✅ | Lifecycle customer strictly enforced |
| V11.1.2 | Workflow constraints enforced | ✅ | CustomerLifecycleController |
| V11.1.3 | Approval workflows | ✅ | Multi-level PO approval, installment approval |
| V11.1.4 | Anti-fraud for billing | ✅ | MeterAnomalyController anomaly detection |
| V11.1.5 | Payment integrity | ✅ | Webhook signature + idempotency + auto-journal |

---

## V12: Files and Resources

| # | Requirement | Status | Implementation |
|---|-------------|--------|----------------|
| V12.1.1 | File upload size limits | ✅ | 5MB KTP, 10MB general |
| V12.1.2 | File type validation | ✅ | MIME type check |
| V12.2.1 | Malicious file scanning | ❌ Not implemented/evidenced | Extension and MIME validation are not malware scanning |
| V12.3.1 | No direct file access | ✅ | Signed URLs with expiry |
| V12.3.2 | File paths not user-controlled | ✅ | Random filenames generated server-side |
| V12.4.1 | File metadata cleaned | ❌ Not evidenced | Filename randomization does not remove embedded metadata |

---

## V13: API and Web Service

| # | Requirement | Status | Implementation |
|---|-------------|--------|----------------|
| V13.1.1 | API authentication where required | ⚠️ Scoped | Tenant/private endpoints use Sanctum; public health/login and signature-authenticated webhook endpoints are intentional exceptions |
| V13.1.2 | Rate limiting on API | ✅ | throttle:5,1 + global throttleApi |
| V13.1.3 | API versioning | ✅ | /api/v1/ |
| V13.2.1 | RESTful input validation | ✅ | Laravel validation |
| V13.2.2 | JSON serialization safe | ✅ | Eloquent toArray() |
| V13.3.1 | CORS configured | ✅ | CorsWhitelist (env-based) |
| V13.4.1 | Webhook verification | ✅ | Midtrans SHA512 signature |
| V13.4.2 | Idempotency for payments | ✅ | midtrans_order_id UNIQUE |

---

## V14: Configuration

| # | Requirement | Status | Implementation |
|---|-------------|--------|----------------|
| V14.1.1 | Security headers | ⚠️ Application configuration | Middleware is present; production response headers remain deployment verification |
| V14.2.1 | Build process secure | ✅ | Vite sourcemap off, drop console/debugger |
| V14.2.2 | Debug mode off in production | ✅ | APP_DEBUG=false |
| V14.3.1 | HTTP security headers | ⚠️ Application configuration | Reviewed headers are configured; target proxy/CDN behavior is pending |
| V14.3.2 | CORS limited to allowed origins | ✅ | Whitelist in .env |
| V14.4.1 | HTTP Strict Transport Security | ⚠️ Deployment pending | Header configuration exists; effectiveness requires verified HTTPS production responses |
| V14.5.1 | No unnecessary HTTP methods | ✅ | Only GET/POST/PUT/DELETE on routes |

---

## Compliance Summary

| Standard | Coverage | Status |
|----------|----------|--------|
| OWASP Top 10 (2021) | Baseline | ⚠️ Kontrol parsial; gap aktif di [`../../temuan2.md`](../../temuan2.md) |
| ASVS Level 2 | ~125 controls | ⚠️ Belum dihitung dan diverifikasi ulang |
| UU PDP No. 27/2022 | Access, anonymization, retention purge | ⚠️ Implementasi teknis tersedia; review legal/operasional tetap diperlukan |
| PCI DSS (payment data) | Midtrans-hosted/tokenized flow | ⚠️ Scope/attestation not assessed; no PCI certification claim |

## Risk Register

| Risk | Severity | Likelihood | Mitigation | Status |
|------|----------|-----------|------------|--------|
| SQL Injection | High | Not quantified | Binding + static BI/export registries + WAF | Reduced; residual dynamic-query/review risk |
| Cross-Tenant Data Leak | Critical | Not quantified | Global scope, tests, 97 tenant FKs, actor FKs | Reduced; residual route/query coverage risk |
| Token Theft via MiTM | High | Not quantified | TLS design + release SPKI primary/backup pinning | Open until TLS and rotation gates close |
| Payment Fraud | High | Not quantified | Webhook signature, amount match, idempotency | Reduced; external settlement verification recommended |
| Brute Force Attack | Medium | Not quantified | Rate limit + account lock | Reduced; operational monitoring residual |
| PII Data Exposure | Critical | Not quantified | NIK encryption + private owned files | Reduced; key management/access review residual |
| Zero-Day in Dependency | Medium | Not quantified | Frozen locks + release dependency audits | Accepted residual; continuous monitoring needed |
| Insider Threat | High | Not quantified | RBAC + append-only audit + least-privilege DB (tooling+CI self-drill siap; provisioning produksi menunggu gate §13 #4) | Residual; monitoring produksi pending |

---

*Framework snapshot used: OWASP ASVS 4.0.3 Level 2 and OWASP Top 10:2021. This working assessment is not a complete conformance mapping.*
*Item bertanda ✅ menunjukkan evidence source/test internal yang ditemukan pada snapshot 15 July 2026, bukan sertifikasi eksternal atau jaminan tanpa residual risk.*
*Last updated: 7 September 2026 (verified: backend 117/117, API 369/293, ml 32, tier3 simulator, h-10 openapi/coverage drift di CI).*
