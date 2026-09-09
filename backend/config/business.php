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
        // Estimasi biaya feasibility SR jaringan: tarif material per meter.
        // 0 = sengaja belum ditetapkan manajemen => biaya otomatis disembunyikan.
        'pipe_cost_per_m' => (float) env('PDAM_SR_PIPE_COST_PER_M', 0),
        // Timer eskalasi hublang (PRD §23) BELUM punya konsumen — field SLA
        // per tenant di DB menang sampai konsumen nyata dibuat.
    ],

    'accounting' => [
        // COA kapitalisasi material saat stock-out pemasangan (InstallationService).
        'installation_capitalization_account' => env('PDAM_INSTALL_CAPITALIZATION_ACCOUNT', '1-004'),
        'inventory_account' => env('PDAM_INVENTORY_ACCOUNT', '1-003'),
    ],

    // GIS Jaringan — petugas lapangan live di peta (NetworkWebController::technicians,
    // FieldLocationService). Titik dianggap ONLINE bila lapor GPS dalam N menit.
    'gis' => [
        'officer_stale_minutes' => (int) env('PDAM_GIS_OFFICER_STALE_MINUTES', 30),
        'officer_roles' => ['field_technician', 'maintenance_technician', 'installer_technician', 'field_dispatcher'],

        // MNF / debit malam (deteksi bocor halus per DMA). Jendela tetap 02:00–04:00.
        // DMA 'merah' bila debit malam > 2× ambang; 'waspada' > ambang.
        'mnf_alert_pct' => (float) env('PDAM_MNF_ALERT_PCT', 15),
        'mnf_lookback_days' => (int) env('PDAM_MNF_LOOKBACK_DAYS', 7),
        // Fallback baseline bila base_demand_m3day DMA belum diisi: liter/koneksi/hari.
        'mnf_default_lpcd' => (float) env('PDAM_MNF_DEFAULT_LPCD', 0.8),

        // Audit kesehatan jaringan (health.json): ruas di atas N meter yang kedua
        // ujungnya bukan valve dianggap tak bisa diisolasi; DMA idealnya ≥ M hydrant.
        'audit_uncontrolled_segment_m' => (float) env('PDAM_GIS_AUDIT_SEGMENT_M', 400),
        'audit_min_hydrants_per_dma' => (int) env('PDAM_GIS_AUDIT_HYDRANTS', 2),

        // Peta risiko pipa: bobot umur maksimal (tahun) + saturasi jumlah WO repair
        // (half-life) + ambang level skor. Default konservatif; manajemen tinggal
        // override .env tanpa ganti kode.
        'risk_max_age_years' => (int) env('PDAM_GIS_RISK_MAX_AGE_YEARS', 60),
        'risk_repair_half_life' => (int) env('PDAM_GIS_RISK_REPAIR_HALF_LIFE', 4),
        'risk_thresholds' => [
            'tinggi' => (float) env('PDAM_GIS_RISK_THRESHOLD_HIGH', 45),
            'kritis' => (float) env('PDAM_GIS_RISK_THRESHOLD_CRIT', 70),
        ],

        // Feasibility SR: jarak maksimum cari pipa (m), faktor rute bukan-garis-lurus,
        // ambang 'sangat eligible' vs perlu persetujuan.
        'feasibility_max_reach_m' => (float) env('PDAM_SR_MAX_REACH_M', 2000),
        'feasibility_route_factor' => (float) env('PDAM_SR_ROUTE_FACTOR', 1.3),
        'feasibility_eligible_m' => (float) env('PDAM_SR_ELIGIBLE_M', 600),
        'feasibility_needs_approval_m' => (float) env('PDAM_SR_APPROVAL_M', 1500),
    ],

    // ── BELUM ADA KUNSUMEN (decision PRD §23; JANGAN dibaca sebagai switch) ──
    // harga tier final | siklus tagihan SaaS | denda flat/persen (BillingSetting
    // sudah menyimpannya — lihat App\Models\BillingSetting) | batas isolir
    // (BillingSetting isolir_after_months) | digit meter tampilan (ui/web) |
    // COA Pemda custom saat provisioning | refund flow | provider payment kedua
    // (adapter PaymentGatewayInterface menunggu implementasi Xendit/DOKU) |
    // target PDAM tahun 1 (SaaS metrics).
];
