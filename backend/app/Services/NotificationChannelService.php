<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * NotificationChannelService — notifikasi lintas kanal (in-app, email, push).
 * PRD 12.1. Memperluas NotificationService yang sudah ada.
 */
class NotificationChannelService
{
    public function send(int $userId, string $title, string $body, array $channels = ['in_app'], ?array $data = null): void
    {
        $user = User::find($userId);
        if (! $user) {
            return;
        }

        if (in_array('in_app', $channels)) {
            AppNotification::create([
                'pdam_org_id' => $user->pdam_org_id,
                'user_id' => $userId,
                'type' => $data['type'] ?? 'general',
                'title' => $title,
                'body' => $body,
                'data' => $data,
            ]);
        }

        if (in_array('push', $channels) && $user->fcm_token) {
            $this->sendFcm($user->fcm_token, $title, $body, $data);
        }

        if (in_array('email', $channels) && $user->email) {
            try {
                Mail::raw($body, function ($message) use ($user, $title) {
                    $message->to($user->email)->subject($title);
                });
            } catch (\Exception $e) {
                Log::warning("Email notification failed: {$e->getMessage()}");
            }
        }
    }

    public function broadcastToRole(string $roleCode, string $title, string $body, array $channels = ['in_app'], ?array $data = null): void
    {
        $users = User::whereHas('roles', fn ($q) => $q->where('code', $roleCode))->get();

        foreach ($users as $user) {
            $this->send($user->id, $title, $body, $channels, $data);
        }
    }

    public function sendBulk(array $userIds, string $title, string $body, array $channels = ['in_app'], ?array $data = null): void
    {
        foreach ($userIds as $userId) {
            $this->send($userId, $title, $body, $channels, $data);
        }
    }

    public function getUserPreferences(int $userId): array
    {
        $user = User::find($userId);
        if (! $user?->notification_preferences) {
            return ['in_app' => true, 'push' => true, 'email' => true];
        }

        return json_decode($user->notification_preferences, true)
            ?? ['in_app' => true, 'push' => true, 'email' => true];
    }

    public function updatePreferences(int $userId, array $preferences): void
    {
        User::where('id', $userId)->update(['notification_preferences' => json_encode($preferences)]);
    }

    private function sendFcm(string $token, string $title, string $body, ?array $data = null): void
    {
        $serverKey = config('services.fcm.server_key');
        if (! $serverKey) {
            return;
        }

        try {
            Http::withHeaders([
                'Authorization' => 'key='.$serverKey,
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', [
                'to' => $token,
                'notification' => ['title' => $title, 'body' => $body],
                'data' => $data ?? [],
            ]);
        } catch (\Exception $e) {
            Log::warning("FCM push failed: {$e->getMessage()}");
        }
    }
}
