# Business decisions register — PRD §23

**[DOC_MAP](DOC_MAP.md)** · nilai knob dicocokkan dengan `backend/config/business.php`; anti-drift dijelaskan di DOC_MAP §4

Register semua keputusan bisnis yang **masih menunggu konfirmasi manajemen**.
Implementasi kode sudah menyediakan knob (via `config/business.php` + env)
sehingga saat keputusan diambil HANYA perlu ganti env/DB per tenant — tidak
ada perubahan kode.

| # | Keputusan | State | Knob / tempat ubah | Owner | Due |
|---|-----------|-------|--------------------|-------|-----|
| 1 | Harga final tiap modul/tier | 🟡 menunggu | `module_price_tiers` (marketplace admin UI) + tier seed untuk harga awal | Direktur/Keuangan | rilis |
| 2 | Siklus tagihan SaaS | 🟡 menunggu | `PDAM_SAAS_BILLING_CYCLE` (bln/thn) via `billing_saas_cycle` | Manajemen | prarisilis |
| 3 | Durasi trial | 🟢 fitur siap (knob default 0) | `PDAM_TRIAL_DAYS` → `business.billing.trial_days`; `TenantModuleController::activate` buat trial + `Subscription` billing_cycle=trial (test `TenantTrialActivationTest`) | Manajemen | = nilai final |
| 4 | Timer eskalasi hublang | 🟢 siap (per tenant) | field SLA di DB per tenant menang; config `PDAM_INSTALL_EXPIRE_HOURS` = tenggang bayar pemasangan | Ops hublang | prarisilis |
| 5 | Denda overdue (flat/persen) | 🟢 siap (per tenant) | `BillingSetting` simpan flat & percent; mark-overdue memakai keduanya | Keuangan | rilis |
| 6 | Batas/tata cara isolir | 🟢 siap (per tenant) | `BillingSetting.isolir_after_months` + `pdam:auto-isolir` | Ops | prarisilis |
| 7 | Digit meter | 🟢 fitur siap (knob default 0 → fallback 5) | `PDAM_METER_DIGITS` → `business.meter.digits`; validasi `MeterReadingController::store` (max register) + default OCR (test `MeterDigitValidationTest`) | Teknik | = nilai final |
| 8 | COA baku Pemda vs custom saat onboarding | 🟢 parsial | `MasterFinanceSeeder` COA default + admin UI ChartOfAccount utk custom per tenant | Keuangan | saat onboarding tenant |
| 9 | Refund | 🟢 fitur siap (knob default OFF) | `PDAM_REFUND_ENABLED` -> `business.refund.enabled`; `RefundService` + endpoint `POST /payments/{id}/refund` (permission `core.payment.refund`, gated; jurnal balik otomatis). Limit/kebijakan final = manajemen | Manajemen | aktifkan saat kebijakan disetujui |
| 10 | Provider payment selain Midtrans | 🔴 roadmap | adapter `PaymentGatewayInterface` siap; TIDAK ada knob env provider (config tidak mengakui provider yang belum diimplement) — implement dulu, baru daftarkan | Dev | roadmap |
| 11 | Target jumlah PDAM tahun 1 | 🔴 belum ada konsumen | — (metrics; tidak dibaca kode) | Direktur | roadmap |

> 🟢 = knob siap dipergunakan tanpa kode baru · 🟡 knob ada tapi nilai final
> belum ditetapkan · 🔴 butuh keputusan + perubahan kode tersendiri.
> Setelah keputusan final: update `PDAM_*` di `/etc/pdam/env.production` lalu
> `php artisan config:cache` — TIDAK perlu deploy ulang kode.
