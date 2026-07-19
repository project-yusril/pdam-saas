<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Services\NotificationChannelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private NotificationChannelService $notif) {}

    public function preferences(Request $request): JsonResponse
    {
        return response()->json(['preferences' => $this->notif->getUserPreferences($request->user()->id)]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'in_app' => ['boolean'],
            'push' => ['boolean'],
            'email' => ['boolean'],
        ]);

        $this->notif->updatePreferences($request->user()->id, $data);

        return response()->json(['message' => 'Preferensi diperbarui.']);
    }

    public function testPush(Request $request): JsonResponse
    {
        $this->notif->send($request->user()->id, 'Test Push', 'Ini notifikasi uji coba PDAM SaaS.', ['push']);

        return response()->json(['message' => 'Push terkirim.']);
    }
}
