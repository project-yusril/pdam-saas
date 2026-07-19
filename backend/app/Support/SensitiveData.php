<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * SensitiveData helper — enkripsi at-rest field sensitif.
 * Dipakai via Eloquent cast atau manual untuk NIK, NPWP, no rekening.
 * PRD 0.7 — PRD 15.B (kepatuhan UU PDP).
 */
class SensitiveData
{
    public static function encrypt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Crypt::encryptString($value);
    }

    public static function decrypt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return null;
        }
    }

    public static function mask(string $value, int $showFirst = 4, int $showLast = 4): string
    {
        $len = mb_strlen($value);
        if ($len <= $showFirst + $showLast) {
            return $value;
        }

        $first = mb_substr($value, 0, $showFirst);
        $last = mb_substr($value, -$showLast);
        $maskLen = $len - $showFirst - $showLast;

        return $first.str_repeat('*', $maskLen).$last;
    }
}
