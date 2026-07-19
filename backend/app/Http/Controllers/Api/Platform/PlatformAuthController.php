<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PlatformAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * PlatformAuthController — login Super-Admin (PRD 5.4 & 16.1).
 */
class PlatformAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $admin = PlatformAdmin::where('email', $data['email'])->first();

        if (! $admin || ! Hash::check($data['password'], $admin->password)) {
            $this->logLogin($admin?->id, 'login_failed', $data['email']);
            throw ValidationException::withMessages(['email' => 'Kredensial tidak valid.']);
        }

        if (! $admin->is_active) {
            $this->logLogin($admin->id, 'login_inactive', $data['email']);
            throw ValidationException::withMessages(['email' => 'Akun tidak aktif.']);
        }

        Auth::guard('platform')->login($admin);
        $request->session()->regenerate();
        $this->logLogin($admin->id, 'login_success', $data['email']);

        return response()->json([
            'admin' => [
                'id' => $admin->id,
                'full_name' => $admin->full_name,
                'email' => $admin->email,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('platform')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Berhasil logout.']);
    }

    /** Catat percobaan login Super-Admin untuk audit keamanan (pdam_org_id null). */
    private function logLogin(?int $adminId, string $action, string $email): void
    {
        ActivityLog::create([
            'pdam_org_id' => null,
            'user_id' => $adminId,
            'actor_type' => 'platform_admin',
            'action' => $action,
            'entity_type' => PlatformAdmin::class,
            'entity_id' => $adminId,
            'new_value' => ['email' => $email],
            'ip_address' => request()->ip(),
        ]);
    }
}
