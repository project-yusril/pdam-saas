<?php

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
