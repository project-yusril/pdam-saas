<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\MfaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * MfaController — Multi-Factor Authentication (TOTP) untuk role sensitif.
 * PRD 0.3: finance, director, super_admin wajib MFA.
 */
class MfaController extends Controller
{
    public function __construct(private MfaService $mfa) {}

    public function setup(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->mfa_enabled_at) {
            return response()->json(['message' => 'MFA sudah aktif.'], 409);
        }

        $secret = $this->mfa->generateSecret();
        $qrUrl = $this->mfa->generateQrDataUrl($user);
        $user->mfa_secret = $secret;
        $user->save();

        ActivityLog::create([
            'pdam_org_id' => $user->pdam_org_id,
            'user_id' => $user->id,
            'actor_type' => 'user',
            'action' => 'mfa_setup_initiated',
            'entity_type' => User::class,
            'entity_id' => $user->id,
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'secret' => $secret,
            'qr_code_url' => $qrUrl,
        ]);
    }

    public function enable(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = $request->user();

        if (! $user->mfa_secret) {
            throw ValidationException::withMessages(['code' => 'MFA belum di-setup. Jalankan setup MFA terlebih dahulu.']);
        }

        if ($user->mfa_enabled_at) {
            return response()->json(['message' => 'MFA sudah aktif.'], 409);
        }

        if (! $this->mfa->verify($user->mfa_secret, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'Kode TOTP tidak valid.']);
        }

        $codes = $this->mfa->generateRecoveryCodes();
        $user->mfa_enabled_at = now();
        $user->mfa_recovery_codes = $codes['hashed'];
        $user->save();

        ActivityLog::create([
            'pdam_org_id' => $user->pdam_org_id,
            'user_id' => $user->id,
            'actor_type' => 'user',
            'action' => 'mfa_enabled',
            'entity_type' => User::class,
            'entity_id' => $user->id,
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'MFA berhasil diaktifkan.',
            'recovery_codes' => $codes['plain'],
        ]);
    }

    public function disable(Request $request): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => 'Password salah.']);
        }

        $user->mfa_secret = null;
        $user->mfa_enabled_at = null;
        $user->mfa_recovery_codes = null;
        $user->save();

        ActivityLog::create([
            'pdam_org_id' => $user->pdam_org_id,
            'user_id' => $user->id,
            'actor_type' => 'user',
            'action' => 'mfa_disabled',
            'entity_type' => User::class,
            'entity_id' => $user->id,
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'MFA berhasil dinonaktifkan.']);
    }

    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
            'recovery' => ['boolean'],
        ]);

        $user = $request->user();

        $isRecovery = $data['recovery'] ?? false;

        if ($isRecovery) {
            $index = $this->mfa->verifyRecoveryCode($user, $data['code']);
            if ($index === null) {
                throw ValidationException::withMessages(['code' => 'Recovery code tidak valid atau sudah dipakai.']);
            }
        } else {
            if (! $user->mfa_secret || ! $this->mfa->verify($user->mfa_secret, $data['code'])) {
                throw ValidationException::withMessages(['code' => 'Kode TOTP tidak valid.']);
            }
        }

        $token = $user->createToken('tenant-mfa')->plainTextToken;

        ActivityLog::create([
            'pdam_org_id' => $user->pdam_org_id,
            'user_id' => $user->id,
            'actor_type' => 'user',
            'action' => 'mfa_verified',
            'entity_type' => User::class,
            'entity_id' => $user->id,
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'token' => $token,
            'message' => 'MFA berhasil diverifikasi.',
        ]);
    }

    public function getRecoveryCodes(Request $request): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => 'Password salah.']);
        }

        if (! $user->mfa_recovery_codes) {
            throw ValidationException::withMessages(['mfa' => 'MFA tidak aktif.']);
        }

        $codes = array_map(
            fn ($entry, $index) => [
                'index' => $index,
                'used' => $entry['used'],
                'code' => $entry['used'] ? '****-****' : null,
            ],
            $user->mfa_recovery_codes,
            array_keys($user->mfa_recovery_codes)
        );

        return response()->json(['recovery_codes' => $codes]);
    }

    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = $request->user();

        if (! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => 'Password salah.']);
        }

        if (! $user->mfa_secret || ! $this->mfa->verify($user->mfa_secret, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'Kode TOTP tidak valid.']);
        }

        $codes = $this->mfa->generateRecoveryCodes();
        $user->mfa_recovery_codes = $codes['hashed'];
        $user->save();

        ActivityLog::create([
            'pdam_org_id' => $user->pdam_org_id,
            'user_id' => $user->id,
            'actor_type' => 'user',
            'action' => 'mfa_recovery_regenerated',
            'entity_type' => User::class,
            'entity_id' => $user->id,
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Recovery codes berhasil dibuat ulang.',
            'recovery_codes' => $codes['plain'],
        ]);
    }
}
