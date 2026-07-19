<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PdamOrganization;
use App\Models\PlatformAdmin;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * AuthController — login user tenant (PRD 16.2).
 * Login butuh identitas tenant (kode PDAM) karena email hanya unik per tenant.
 */
class AuthController extends Controller
{
    #[OA\Post(
        path: '/login',
        tags: ['Auth'],
        summary: 'Login tenant user',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['pdam_code', 'email', 'password'],
                properties: [
                    new OA\Property(property: 'pdam_code', type: 'string', example: 'pdam-canada'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                    new OA\Property(
                        property: 'device_name',
                        description: 'Opsional. Jika dikirim oleh mobile/API device, response menyertakan bearer token Sanctum.',
                        type: 'string',
                        example: 'android-emulator',
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Login sukses'),
            new OA\Response(response: 422, description: 'Validasi gagal'),
        ]
    )]
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pdam_code' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:100'],
        ]);

        $org = PdamOrganization::where('code', $data['pdam_code'])->first();
        if (! $org) {
            throw ValidationException::withMessages(['pdam_code' => 'PDAM tidak ditemukan.']);
        }

        // PDAM di-nonaktifkan Super-Admin → seluruh role tenant ini tidak boleh login.
        if ($org->subscription_status !== 'active') {
            $this->logLogin($org->id, null, 'login_org_suspended', $data['email']);
            throw ValidationException::withMessages([
                'pdam_code' => 'PDAM sedang dinonaktifkan. Hubungi administrator platform.',
            ]);
        }

        $user = User::withoutGlobalScopes()

            ->where('pdam_org_id', $org->id)
            ->where('email', $data['email'])
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            $this->logLogin($org->id, $user?->id, 'login_failed', $data['email']);
            throw ValidationException::withMessages(['email' => 'Kredensial tidak valid.']);
        }

        if (! $user->is_active) {
            $this->logLogin($org->id, $user->id, 'login_inactive', $data['email']);
            throw ValidationException::withMessages(['email' => 'Akun tidak aktif.']);
        }

        $token = isset($data['device_name'])
            ? $user->createToken($data['device_name'])->plainTextToken
            : null;

        if (! $token) {
            Auth::guard('web')->login($user);
            $request->session()->regenerate();
        }

        $this->logLogin($org->id, $user->id, 'login_success', $data['email']);

        $response = [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_tenant_admin' => $user->is_tenant_admin,
                'roles' => $user->roles()->pluck('code'),
            ],
            'organization' => [
                'id' => $org->id,
                'code' => $org->code,
                'name' => $org->name,
            ],
        ];

        if ($token) {
            $response['token'] = $token;
        }

        return response()->json($response);
    }

    public function session(Request $request): JsonResponse
    {
        $account = $request->user();

        if ($account instanceof PlatformAdmin) {
            return response()->json([
                'type' => 'platform',
                'admin' => [
                    'id' => $account->id,
                    'full_name' => $account->full_name,
                    'email' => $account->email,
                ],
            ]);
        }

        return response()->json([
            'type' => 'tenant',
            'user' => [
                'id' => $account->id,
                'name' => $account->name,
                'email' => $account->email,
                'is_tenant_admin' => $account->is_tenant_admin,
                'roles' => $account->roles()->pluck('code'),
            ],
            'organization' => $account->organization()->first(['id', 'code', 'name']),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_tenant_admin' => $user->is_tenant_admin,
            'roles' => $user->roles()->pluck('code'),
            'organization' => $user->organization()->first(['id', 'code', 'name']),
            'active_modules' => $user->organization
                ? $user->organization->subscriptionModules()
                    ->where('status', 'active')
                    ->pluck('module_code')
                : [],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        if ($request->bearerToken()) {
            $request->user()->currentAccessToken()->delete();
        } else {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'Berhasil logout.']);
    }

    public function refreshToken(Request $request): JsonResponse
    {
        abort_unless($request->bearerToken(), 403, 'Token refresh hanya tersedia untuk klien bearer.');

        $user = $request->user();
        $user->currentAccessToken()->delete();

        $token = $user->createToken('tenant-app')->plainTextToken;

        ActivityLog::create([
            'pdam_org_id' => $user->pdam_org_id,
            'user_id' => $user->id,
            'actor_type' => 'user',
            'action' => 'token_refreshed',
            'entity_type' => User::class,
            'entity_id' => $user->id,
            'ip_address' => request()->ip(),
        ]);

        return response()->json(['token' => $token]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
        ]);
        $request->user()->update($data);

        return response()->json(['success' => true, 'data' => $request->user()->only(['id', 'name', 'email', 'phone'])]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'new_password' => ['required', Password::min(8), 'different:current_password'],
        ]);
        $request->user()->update(['password' => $data['new_password']]);
        $currentTokenId = $request->bearerToken()
            ? $request->user()->currentAccessToken()?->getKey()
            : null;
        $request->user()->tokens()
            ->when($currentTokenId, fn ($query) => $query->whereKeyNot($currentTokenId))
            ->delete();

        return response()->json(['message' => 'Password berhasil diubah.']);
    }

    public function registerFcmToken(Request $request): JsonResponse
    {
        $data = $request->validate(['fcm_token' => ['required', 'string', 'max:4096']]);
        $request->user()->update(['fcm_token' => $data['fcm_token']]);

        return response()->json(['message' => 'Token perangkat tersimpan.']);
    }

    /** Catat percobaan login (sukses/gagal) untuk audit keamanan. */
    private function logLogin(int $orgId, ?int $userId, string $action, string $email): void
    {
        ActivityLog::create([
            'pdam_org_id' => $orgId,
            'user_id' => $userId,
            'actor_type' => 'user',
            'action' => $action,
            'entity_type' => User::class,
            'entity_id' => $userId,
            'new_value' => ['email' => $email],
            'ip_address' => request()->ip(),
        ]);
    }
}
