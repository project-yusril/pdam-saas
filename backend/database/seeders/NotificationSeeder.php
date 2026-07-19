<?php

namespace Database\Seeders;

use App\Models\PdamOrganization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * NotificationSeeder — modul APP (notifikasi & chat).
 *
 * Mengisi: app_notifications, notification_logs, chats, chat_messages.
 * Idempotent: dilewati bila app_notifications sudah ada.
 */
class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('app_notifications')->exists()) {
            return;
        }

        foreach (['pdam-canada', 'pdam-brazil'] as $code) {
            $org = PdamOrganization::where('code', $code)->first();
            if (! $org) {
                continue;
            }
            $this->seedForTenant($org->id);
        }
    }

    private function seedForTenant(int $orgId): void
    {
        $user = DB::table('users')->where('pdam_org_id', $orgId)->first();
        $customer = DB::table('customers')->where('pdam_org_id', $orgId)->first();

        // In-app notifications
        $notifs = [
            ['bill_reminder', 'Tagihan Bulan Ini', 'Tagihan air periode 2026-06 sudah terbit. Segera lakukan pembayaran.'],
            ['overdue', 'Tagihan Menunggak', 'Terdapat tagihan yang melewati jatuh tempo. Mohon segera dilunasi.'],
            ['info', 'Pemeliharaan Jaringan', 'Akan ada pemeliharaan jaringan di zona Anda pada akhir pekan ini.'],
        ];
        foreach ($notifs as $i => [$type, $title, $body]) {
            $notifId = DB::table('app_notifications')->insertGetId([
                'pdam_org_id' => $orgId,
                'user_id' => $user?->id,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'data' => json_encode(['source' => 'seeder']),
                'channel' => 'in_app',
                'read_at' => $i === 0 ? now() : null,
                'created_at' => now()->subDays(3 - $i),
                'updated_at' => now()->subDays(3 - $i),
            ]);

            // Log pengiriman
            DB::table('notification_logs')->insert([
                'pdam_org_id' => $orgId,
                'notification_id' => $notifId,
                'channel' => 'in_app',
                'status' => 'sent',
                'error_message' => null,
                'created_at' => now()->subDays(3 - $i),
                'updated_at' => now()->subDays(3 - $i),
            ]);
        }

        // Chat pelanggan ↔ CS
        if ($user && $customer) {
            $chatId = DB::table('chats')->insertGetId([
                'pdam_org_id' => $orgId,
                'customer_id' => $customer->id,
                'user_id' => $user->id,
                'assigned_to' => $user->id,
                'subject' => 'Pertanyaan tagihan',
                'status' => 'open',
                'created_at' => now()->subDay(),
                'updated_at' => now(),
            ]);

            DB::table('chat_messages')->insert([
                [
                    'chat_id' => $chatId,
                    'sender_id' => $customer->id,
                    'sender_type' => 'customer',
                    'message' => 'Selamat siang, saya mau tanya soal tagihan bulan ini.',
                    'is_read' => true,
                    'created_at' => now()->subDay(),
                    'updated_at' => now()->subDay(),
                ],
                [
                    'chat_id' => $chatId,
                    'sender_id' => $user->id,
                    'sender_type' => 'user',
                    'message' => 'Selamat siang, silakan sebutkan nomor pelanggan Anda.',
                    'is_read' => false,
                    'created_at' => now()->subHours(20),
                    'updated_at' => now()->subHours(20),
                ],
            ]);
        }
    }
}
