<?php

namespace App\Services;

/**
 * MeterOcrService — parsing hasil OCR angka meter air (Google ML Kit/Vision).
 * Fase 4.2.
 *
 * OCR eksternal menghasilkan teks/angka mentah dari foto register meter;
 * service ini membersihkan & memvalidasinya jadi angka pemakaian (m³) yang
 * masuk akal. Hasil WAJIB dikonfirmasi/dikoreksi petugas sebelum disimpan
 * (reading_type = ocr_confirmed vs manual_corrected) — akurasi OCR meter
 * tidak 100% (angka buram, embun, digit setengah putar).
 *
 * Register meter PDAM umumnya 5 digit angka hitam (m³) + digit merah (liter,
 * diabaikan untuk penagihan). Service ini mengambil bagian hitam saja.
 */
class MeterOcrService
{
    /**
     * Ekstrak angka meter dari teks mentah OCR.
     *
     * @param  string  $rawText  teks hasil OCR (mis. "00123", "0 0 1 2 3", "00123.4")
     * @param  int  $blackDigits  jumlah digit hitam register (default 5)
     * @return array{reading:?int, confidence:float, raw:string, warnings:array<int,string>}
     */
    public function parse(string $rawText, int $blackDigits = 5): array
    {
        $warnings = [];

        // Hilangkan spasi antar-digit (OCR sering memecah angka).
        $compact = preg_replace('/\s+/', '', trim($rawText));

        // Buang bagian desimal/merah setelah titik/koma (liter — tak ditagih).
        $blackPart = preg_split('/[.,]/', $compact)[0] ?? $compact;

        // Ambil hanya digit.
        $digits = preg_replace('/\D+/', '', $blackPart);

        if ($digits === '') {
            return ['reading' => null, 'confidence' => 0.0, 'raw' => $rawText, 'warnings' => ['no_digits_detected']];
        }

        // Jika digit terbaca lebih banyak dari register hitam, kemungkinan
        // digit merah/liter ikut terbaca → ambil digit paling kiri (bagian m³).
        if (strlen($digits) > $blackDigits) {
            $warnings[] = 'extra_digits_truncated';
            $digits = substr($digits, 0, $blackDigits);
        }

        $reading = (int) $digits;

        return [
            'reading' => $reading,
            'confidence' => $this->estimateConfidence($rawText, $digits, $blackDigits),
            'raw' => $rawText,
            'warnings' => $warnings,
        ];
    }

    /**
     * Confidence sederhana berbasis kualitas teks OCR:
     * - jumlah digit terbaca mendekati jumlah digit register → makin yakin
     * - banyak karakter non-digit (noise) → turunkan keyakinan
     */
    private function estimateConfidence(string $raw, string $digits, int $blackDigits): float
    {
        $len = strlen($digits);
        if ($len === 0) {
            return 0.0;
        }

        // Rasio kelengkapan digit (idealnya = blackDigits).
        $completeness = min($len, $blackDigits) / $blackDigits;

        // Rasio noise: proporsi karakter non-digit di teks mentah.
        $rawLen = max(strlen(preg_replace('/\s+/', '', $raw)), 1);
        $nonDigit = $rawLen - $len;
        $noisePenalty = min($nonDigit / $rawLen, 0.5);

        return round(max(0.0, $completeness - $noisePenalty), 2);
    }
}
