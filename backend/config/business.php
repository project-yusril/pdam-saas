<?php

use App\Services\Gateways\MidtransSnap;
use App\Services\Gateways\XenditCharge;

/**
 * Business decision knobs (PRD §23) — SATU tempat untuk nilai yang sudah punya
 * konsumen kode. Keputusan yang BELUM ada konsumennya secara sadar TIDAK dibuat
 * key di sini (review: false-config) — dicatat sebagai komentar +
 * ../docs/BUSINESS_DECISIONS.md supaya tidak kelihatan seperti switch nyata.
 *
 * Nilai final harga/denda/digit meter menunggu manajemen; begitu konsumen
 * fitur dibuat, pindahkan ke config secara sadar.
 */
return [
    'billing' => [
        // Grace days sebelum langganan tenant dianggap expired (SubscriptionCheck).
        'subscription_grace_days' => (int) env('PDAM_SUBSCRIPTION_GRACE_DAYS', 0),
        // Durasi trial (hari) utk tenant pertama kali aktivasi manual modul tier berbayar
        // oleh Super-Admin. PRD §23: manajemen menetapkan; default 0 = tidak ada trial.
        'trial_days' => (int) env('PDAM_TRIAL_DAYS', 0),
    ],

    // Register meter: jumlah digit hitam (m³) yang dipakai utk validasi input
    // & parsing OCR. PRD §23 row "digit meter" — 0 = belum ditetapkan (fallback 5).
    'meter' => [
        'digits' => (int) env('PDAM_METER_DIGITS', 0),
    ],

    // Pengembalian dana (PRD §23) — OFF secara default sampai manajemen memutuskan
    // kebijakan/limitnya. RefundService & PaymentController::refund wajib
    // `business.refund.enabled = true`; endpoint tetap ada utk audit UI.
    'refund' => [
        'enabled' => filter_var(env('PDAM_REFUND_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'auto_gateway' => filter_var(env('PDAM_REFUND_AUTO_GATEWAY', false), FILTER_VALIDATE_BOOLEAN),
    ],

    // PaymentGateway: provider pertama = Midtrans; provider kedua (Xendit) kini
    // punya adapter nyata. PRD §23: provider lain di luar daftar ini = roadmap.
    'payment' => [
        'default_provider' => env('PDAM_PAYMENT_PROVIDER', 'midtrans'),
        'providers' => [
            'midtrans' => MidtransSnap::class,
            'xendit' => XenditCharge::class,
        ],
    ],

    'installation' => [
        // Jam tenggang pembayaran pemasangan (pdam:escalate-installation).
        'payment_expire_hours' => (int) env('PDAM_INSTALL_EXPIRE_HOURS', 24),
        // Timer eskalasi hublang (PRD §23) BELUM punya konsumen — field SLA
        // per tenant di DB menang sampai konsumen nyata dibuat.
    ],

    'accounting' => [
        // COA kapitalisasi material saat stock-out pemasangan (InstallationService).
        'installation_capitalization_account' => env('PDAM_INSTALL_CAPITALIZATION_ACCOUNT', '1-004'),
        'inventory_account' => env('PDAM_INVENTORY_ACCOUNT', '1-003'),
    ],

    // ── BELUM ADA KUNSUMEN (decision PRD §23; JANGAN dibaca sebagai switch) ──
    // harga tier final | siklus tagihan SaaS | denda flat/persen (BillingSetting
    // sudah menyimpannya — lihat App\Models\BillingSetting) | batas isolir
    // (BillingSetting isolir_after_months) | digit meter tampilan (ui/web) |
    // COA Pemda custom saat provisioning | refund flow | provider payment kedua
    // (adapter PaymentGatewayInterface menunggu implementasi Xendit/DOKU) |
    // target PDAM tahun 1 (SaaS metrics).
];
