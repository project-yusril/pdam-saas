<?php

namespace App\Services;

use App\Models\AppNotification;

/**
 * NotificationService — buat notifikasi in-app (dan tandai channel email/push).
 * Push/email sesungguhnya di-hook di adapter terpisah (FCM/mailer) — di sini
 * fokus mencatat notifikasi agar bisa ditampilkan & diaudit.
 */
class NotificationService
{
    public function notify(
        int $orgId,
        ?int $userId,
        string $type,
        string $title,
        ?string $body = null,
        array $data = [],
        string $channel = 'in_app'
    ): AppNotification {
        return AppNotification::create([
            'pdam_org_id' => $orgId,
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'channel' => $channel,
        ]);
    }

    public function markRead(AppNotification $notification): void
    {
        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }
    }
}
