<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * CrmDetailSeeder — melengkapi modul CRM yang sudah aktif (kedua tenant).
 *
 * Mengisi:
 *  - complaint_tracks : histori penanganan tiap pengaduan (open → in_progress → resolved)
 *  - customer_feedbacks : rating & komentar pelanggan atas pengaduan yang selesai
 *
 * Idempotent: dilewati bila complaint_tracks sudah ada.
 */
class CrmDetailSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('complaint_tracks')->exists()) {
            return;
        }

        $complaints = DB::table('complaints')->get();
        foreach ($complaints as $complaint) {
            $orgId = $complaint->pdam_org_id;

            // Track 1: tiket dibuka
            DB::table('complaint_tracks')->insert([
                'pdam_org_id' => $orgId,
                'complaint_id' => $complaint->id,
                'from_status' => null,
                'to_status' => 'open',
                'action' => 'created',
                'note' => 'Tiket pengaduan dibuat oleh pelanggan.',
                'user_id' => null,
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(3),
            ]);

            if (in_array($complaint->status, ['in_progress', 'resolved'], true)) {
                DB::table('complaint_tracks')->insert([
                    'pdam_org_id' => $orgId,
                    'complaint_id' => $complaint->id,
                    'from_status' => 'open',
                    'to_status' => 'in_progress',
                    'action' => 'assigned',
                    'note' => 'Tiket ditugaskan ke petugas lapangan.',
                    'user_id' => null,
                    'created_at' => now()->subDays(2),
                    'updated_at' => now()->subDays(2),
                ]);
            }

            if ($complaint->status === 'resolved') {
                DB::table('complaint_tracks')->insert([
                    'pdam_org_id' => $orgId,
                    'complaint_id' => $complaint->id,
                    'from_status' => 'in_progress',
                    'to_status' => 'resolved',
                    'action' => 'resolved',
                    'note' => 'Masalah selesai ditangani teknisi.',
                    'user_id' => null,
                    'created_at' => now()->subDay(),
                    'updated_at' => now()->subDay(),
                ]);

                // Feedback untuk pengaduan yang selesai
                DB::table('customer_feedbacks')->insert([
                    'pdam_org_id' => $orgId,
                    'customer_id' => $complaint->customer_id,
                    'complaint_id' => $complaint->id,
                    'rating' => 5,
                    'comment' => 'Penanganan cepat, terima kasih petugas PDAM.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
