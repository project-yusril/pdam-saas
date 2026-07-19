<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

/**
 * ModuleSeeder — katalog 27 modul (PRD 4.C).
 * CORE = is_default (gratis saat provisioning). Sisanya berbayar.
 * Format baris: [code, name, tier, price, dependencies[], isDefault, description]
 */
class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            // Tier 1 — CORE OPERATIONS
            ['CORE', 'Paket Dasar', 1, 25000000, [], true,
                'Fondasi wajib seluruh sistem: manajemen pelanggan, master data tarif & wilayah, billing dasar, pencatatan pembayaran, dan buku besar akuntansi. Semua modul lain berdiri di atas paket ini.'],
            ['WH', 'Gudang & Inventory', 1, 12000000, ['CORE'], false,
                'Kelola stok material & meter air: penerimaan barang, pengeluaran, stok opname, dan kartu stok per gudang. Terhubung ke jurnal persediaan otomatis.'],
            ['MTR', 'Baca Meter Digital + Route', 1, 15000000, ['CORE'], false,
                'Pencatatan angka meter berbasis rute untuk petugas lapangan, lengkap dengan foto bukti, validasi GPS, dan perhitungan pemakaian otomatis ke billing.'],
            ['SRV', 'Survey & Pemasangan', 1, 12000000, ['CORE'], false,
                'Alur calon pelanggan baru: survey lokasi, verifikasi, penjadwalan pemasangan sambungan, hingga aktivasi menjadi pelanggan resmi.'],
            ['FIN+', 'Keuangan Advance (Accurate-like)', 1, 30000000, ['CORE'], false,
                'Akuntansi lengkap setara Accurate: multi jurnal, buku besar, neraca, laba rugi, arus kas, dan rekonsiliasi bank untuk kebutuhan keuangan PDAM skala penuh.'],
            ['CRM', 'Pengaduan & CRM', 1, 8000000, ['CORE'], false,
                'Manajemen pengaduan pelanggan end-to-end: tiket, kategori keluhan, SLA penyelesaian, dan riwayat interaksi pelanggan.'],
            ['AST', 'Aset Tetap & Penyusutan', 1, 9000000, ['CORE'], false,
                'Registrasi aset tetap PDAM, perhitungan penyusutan otomatis, dan integrasi nilai aset ke laporan keuangan.'],
            ['ZONE', 'Multi-Wilayah / Cabang', 1, 9000000, ['CORE'], false,
                'Dukungan operasi multi cabang / wilayah layanan dengan pemisahan data, laporan, dan hak akses per zona.'],
            ['APP', 'Portal & Mobile Pelanggan', 1, 12000000, ['CORE'], false,
                'Portal web & aplikasi mobile pelanggan: cek tagihan, riwayat pemakaian, pembayaran, dan notifikasi. Butuh Paket Dasar untuk data billing.'],
            ['C360', 'Customer 360 View', 1, 6000000, ['CORE'], false,
                'Tampilan menyeluruh satu pelanggan: profil, tagihan, pembayaran, pengaduan, dan pemakaian dalam satu layar terpadu.'],
            ['BILL+', 'Advanced Billing', 1, 7000000, ['CORE'], false,
                'Penagihan lanjutan: skema tarif progresif, denda keterlambatan, angsuran, dan penyesuaian tagihan yang fleksibel.'],
            ['METX', 'Meter Analytics', 1, 7000000, ['MTR'], false,
                'Analitik pola pemakaian meter: deteksi anomali konsumsi, tren pemakaian, dan indikasi kebocoran. Membutuhkan modul Baca Meter Digital.'],
            // Tier 2 — ENTERPRISE
            ['FSM', 'Field Service + Work Order', 2, 18000000, ['CORE'], false,
                'Manajemen pekerjaan lapangan: pembuatan work order, penugasan teknisi, pelacakan status, dan bukti penyelesaian di lapangan.'],
            ['PROC', 'Procurement, Tender & Vendor', 2, 13000000, ['WH', 'FIN+'], false,
                'Pengadaan barang & jasa: permintaan pembelian, tender, manajemen vendor, hingga purchase order. Terhubung ke Gudang dan Keuangan Advance.'],
            ['MNT', 'Maintenance (preventive)', 2, 11000000, ['AST'], false,
                'Pemeliharaan preventif aset: jadwal perawatan berkala, riwayat perbaikan, dan pengingat servis. Membutuhkan modul Aset Tetap.'],
            ['HR', 'HR Management', 2, 15000000, ['CORE'], false,
                'Manajemen SDM: data pegawai, absensi, penggajian dasar, dan struktur organisasi PDAM.'],
            ['DMS', 'Document Management System', 2, 9000000, ['CORE'], false,
                'Arsip dokumen digital terpusat: penyimpanan, kategorisasi, versi, dan kontrol akses dokumen.'],
            ['GIS', 'GIS Water Network', 2, 22000000, ['CORE'], false,
                'Pemetaan jaringan pipa & aset air berbasis peta: visualisasi spasial pelanggan, pipa, katup, dan titik layanan.'],
            ['CC', 'Call Center', 2, 15000000, ['CRM'], false,
                'Operasional call center: antrean panggilan, script layanan, dan pencatatan interaksi. Membutuhkan modul Pengaduan & CRM.'],
            ['BI', 'Business Intelligence + Report Builder', 2, 22000000, ['CORE'], false,
                'Dasbor analitik & pembuat laporan mandiri: KPI operasional, keuangan, dan pelanggan dengan visualisasi interaktif.'],
            ['INT', 'Integration Platform', 2, 17000000, ['CORE'], false,
                'Platform integrasi ke sistem eksternal: payment gateway, PPOB, API pihak ketiga, dan webhook.'],
            ['CHEM', 'Chemical Management (IPA)', 2, 14000000, ['WH'], false,
                'Pengelolaan bahan kimia instalasi pengolahan air: stok, dosis, dan konsumsi kimia. Membutuhkan modul Gudang & Inventory.'],
            // Tier 3 — SMART UTILITY
            ['IOT', 'Smart Meter / IoT (AMR/AMI)', 3, 90000000, ['MTR'], false,
                'Integrasi smart meter (AMR/AMI): pembacaan meter otomatis jarak jauh secara real-time. Membutuhkan modul Baca Meter Digital.'],
            ['PROD', 'Water Production (SCADA IPA)', 3, 70000000, [], false,
                'Monitoring produksi air terintegrasi SCADA: debit, kualitas, dan performa instalasi pengolahan air secara real-time.'],
            ['DIST', 'Distribution (DMA/pressure)', 3, 70000000, [], false,
                'Manajemen distribusi: pemantauan tekanan, District Metered Area (DMA), dan keseimbangan aliran jaringan.'],
            ['NRW', 'Non-Revenue Water Management', 3, 40000000, ['DIST'], false,
                'Pengendalian air tak berbayar (kehilangan air): analisis kebocoran, water balance, dan target penurunan NRW. Membutuhkan modul Distribution.'],
            ['AI', 'AI/ML (prediksi, chatbot, fraud)', 3, 45000000, ['BI'], false,
                'Kecerdasan buatan: prediksi pemakaian, chatbot pelanggan, dan deteksi kecurangan meter. Membutuhkan modul Business Intelligence.'],
        ];

        foreach ($modules as [$code, $name, $tier, $price, $deps, $isDefault, $description]) {
            Module::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'description' => $description,
                    'tier' => $tier,
                    'base_price_year' => $price,
                    'dependencies' => $deps,
                    'is_default' => $isDefault,
                    'is_active' => true,
                ]
            );
        }
    }
}
