<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * MfaService — TOTP-based Multi-Factor Authentication (RFC 6238).
 * Role sensitif (finance, director, super_admin) WAJIB MFA per PRD 0.3.
 */
class MfaService
{
    private const DIGITS = 6;

    private const TIMESTEP = 30;

    private const ALGORITHM = 'sha1';

    private const SECRET_BYTES = 20;

    public function generateSecret(): string
    {
        $bytes = random_bytes(self::SECRET_BYTES);

        return $this->base32Encode($bytes);
    }

    public function generateQrDataUrl(User $user, string $issuer = 'PDAM SaaS'): string
    {
        $label = rawurlencode("{$issuer}:{$user->email}");
        $secret = $user->mfa_secret;
        $uri = "otpauth://totp/{$label}?secret={$secret}&issuer=".rawurlencode($issuer).'&algorithm=SHA1&digits=6&period=30';

        return 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data='.rawurlencode($uri);
    }

    public function verify(string $secret, string $code): bool
    {
        if (strlen($code) !== self::DIGITS || ! ctype_digit($code)) {
            return false;
        }

        $binarySecret = $this->base32Decode($secret);
        $timeSlice = floor(time() / self::TIMESTEP);

        for ($i = -1; $i <= 1; $i++) {
            if (hash_equals($this->computeTotp($binarySecret, $timeSlice + $i), $code)) {
                return true;
            }
        }

        return false;
    }

    public function generateRecoveryCodes(): array
    {
        $plainCodes = [];
        $hashedEntries = [];

        for ($i = 0; $i < 8; $i++) {
            $raw = bin2hex(random_bytes(5));
            $code = substr($raw, 0, 4).'-'.substr($raw, 4, 4);
            $plainCodes[] = $code;
            $hashedEntries[] = [
                'hash' => Hash::make($code),
                'used' => false,
            ];
        }

        return ['plain' => $plainCodes, 'hashed' => $hashedEntries];
    }

    public function verifyRecoveryCode(User $user, string $code): ?int
    {
        $codes = $user->mfa_recovery_codes ?? [];

        foreach ($codes as $index => $entry) {
            if (! $entry['used'] && Hash::check($code, $entry['hash'])) {
                $codes[$index]['used'] = true;
                $user->mfa_recovery_codes = $codes;
                $user->save();

                return $index;
            }
        }

        return null;
    }

    private function computeTotp(string $secret, int $counter): string
    {
        $counter = pack('J', $counter);
        $hash = hash_hmac(self::ALGORITHM, $counter, $secret, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;

        $binary = (
            ((ord($hash[$offset + 0]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        );

        return str_pad((string) ($binary % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary = '';
        foreach (str_split($data) as $char) {
            $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $result = '';
        foreach (str_split($binary, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0');
            $result .= $alphabet[bindec($chunk)];
        }

        $padding = strlen($result) % 8;
        if ($padding > 0) {
            $result .= str_repeat('=', 8 - $padding);
        }

        return $result;
    }

    private function base32Decode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $data = rtrim($data, '=');
        $binary = '';

        foreach (str_split($data) as $char) {
            $pos = strpos($alphabet, strtoupper($char));
            if ($pos === false) {
                throw new \InvalidArgumentException('Invalid base32 character');
            }
            $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $result = '';
        foreach (str_split($binary, 8) as $chunk) {
            if (strlen($chunk) < 8) {
                break;
            }
            $result .= chr(bindec($chunk));
        }

        return $result;
    }
}
