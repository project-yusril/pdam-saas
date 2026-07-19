<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * KtpOcrService — parsing hasil OCR mentah KTP (Google Cloud Vision) menjadi
 * field terstruktur (NIK, nama, tempat/tgl lahir, alamat, dan field e-KTP
 * lainnya: jenis kelamin, agama, status perkawinan, pekerjaan, gol. darah,
 * kewarganegaraan). Fase 3.1.
 *
 * OCR eksternal (Vision) hanya menghasilkan teks mentah; service ini yang
 * memetakannya ke field. Hasil WAJIB direview/koreksi user sebelum submit
 * (akurasi OCR tidak 100% — PRD risiko OCR KTP; field gender/agama/status/
 * pekerjaan sering tak akurat, jadi confidence dihitung dari field inti saja).
 */
class KtpOcrService
{
    public function recognize(UploadedFile $file): array
    {
        $apiKey = (string) config('services.google_cloud.vision_api_key');
        if ($apiKey === '') {
            throw new RuntimeException('Google Cloud Vision belum dikonfigurasi.');
        }

        $response = Http::timeout(20)
            ->retry(2, 250)
            ->post("https://vision.googleapis.com/v1/images:annotate?key={$apiKey}", [
                'requests' => [[
                    'image' => ['content' => base64_encode($file->getContent())],
                    'features' => [['type' => 'TEXT_DETECTION', 'maxResults' => 1]],
                ]],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Layanan OCR KTP gagal merespons.');
        }

        $rawText = $response->json('responses.0.fullTextAnnotation.text')
            ?? $response->json('responses.0.textAnnotations.0.description');
        if (! is_string($rawText) || trim($rawText) === '') {
            throw new RuntimeException('Teks KTP tidak terdeteksi. Ambil ulang foto yang lebih jelas.');
        }

        return ['raw_text' => $rawText, 'parsed' => $this->parse($rawText)];
    }

    /**
     * Ekstrak field dari teks mentah OCR (baris per baris KTP).
     *
     * @param  string  $rawText  teks gabungan hasil OCR
     * @return array{
     *     nik:?string, full_name:?string, birth_place:?string, birth_date:?string,
     *     gender:?string, religion:?string, marital_status:?string, occupation:?string,
     *     blood_type:?string, nationality:?string, address:?string,
     *     rt:?string, rw:?string, confidence:float
     * }
     */
    public function parse(string $rawText): array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $rawText))));

        $result = [
            'nik' => $this->extractNik($rawText),
            'full_name' => $this->extractLabeled($lines, ['nama']),
            'birth_place' => null,
            'birth_date' => null,
            'gender' => $this->extractGender($lines),
            'religion' => $this->extractReligion($lines),
            'marital_status' => $this->extractMaritalStatus($lines),
            'occupation' => $this->extractLabeled($lines, ['pekerjaan']),
            'blood_type' => $this->extractBloodType($lines),
            'nationality' => $this->extractLabeled($lines, ['kewarganegaraan']) ?? 'WNI',
            'address' => $this->extractLabeled($lines, ['alamat']),
            'rt' => null,
            'rw' => null,
            'confidence' => 0.0,
        ];

        // Tempat/Tgl Lahir → "PONTIANAK, 17-08-1990"
        $ttl = $this->extractLabeled($lines, ['tempat/tgl lahir', 'tempat tgl lahir', 'ttl']);
        if ($ttl !== null) {
            [$place, $date] = $this->splitBirth($ttl);
            $result['birth_place'] = $place;
            $result['birth_date'] = $date;
        }

        // RT/RW → "001/002"
        [$rt, $rw] = $this->extractRtRw($lines);
        $result['rt'] = $rt;
        $result['rw'] = $rw;

        $result['confidence'] = $this->estimateConfidence($result);

        return $result;
    }

    /** NIK = tepat 16 digit angka (bukan bagian dari rangkaian digit lebih panjang). */
    private function extractNik(string $text): ?string
    {
        // Hilangkan spasi antar-digit dulu (OCR kadang memecah NIK), lalu cari 16 digit utuh.
        $compact = preg_replace('/\s+/', '', $text);
        if (preg_match('/(?<!\d)(\d{16})(?!\d)/', $compact, $m)) {
            return $m[1];
        }

        return null;
    }

    /** Ambil nilai setelah label (mis. "Nama : BUDI" → "BUDI"). */
    private function extractLabeled(array $lines, array $labels): ?string
    {
        foreach ($lines as $line) {
            foreach ($labels as $label) {
                if (stripos($line, $label) === 0) {
                    $value = preg_replace('/^[^:]*:?\s*/', '', $line);
                    $value = trim($value);

                    return $value !== '' ? $value : null;
                }
            }
        }

        return null;
    }

    /** Jenis Kelamin → L|P (label "Jenis Kelamin: LAKI-LAKI/PEREMPUAN"). */
    private function extractGender(array $lines): ?string
    {
        $value = $this->extractLabeled($lines, ['jenis kelamin']);
        if ($value === null) {
            return null;
        }

        $upper = strtoupper($value);
        if (str_contains($upper, 'LAKI')) {
            return 'L';
        }
        if (str_contains($upper, 'PEREMPUAN')) {
            return 'P';
        }

        return null;
    }

    /** Agama → normalisasi ke daftar standar. */
    private function extractReligion(array $lines): ?string
    {
        $value = $this->extractLabeled($lines, ['agama']);
        if ($value === null) {
            return null;
        }

        $known = ['ISLAM', 'KRISTEN', 'KATOLIK', 'HINDU', 'BUDHA', 'BUDDHA', 'KONGHUCU'];
        $upper = strtoupper($value);
        foreach ($known as $religion) {
            if (str_contains($upper, $religion)) {
                return ucfirst(strtolower($religion === 'BUDDHA' ? 'BUDHA' : $religion));
            }
        }

        return ucwords(strtolower($value));
    }

    /** Status Perkawinan → belum_kawin|kawin|cerai_hidup|cerai_mati. */
    private function extractMaritalStatus(array $lines): ?string
    {
        $value = $this->extractLabeled($lines, ['status perkawinan']);
        if ($value === null) {
            return null;
        }

        $upper = strtoupper($value);
        if (str_contains($upper, 'BELUM')) {
            return 'belum_kawin';
        }
        if (str_contains($upper, 'CERAI HIDUP')) {
            return 'cerai_hidup';
        }
        if (str_contains($upper, 'CERAI MATI')) {
            return 'cerai_mati';
        }
        if (str_contains($upper, 'KAWIN')) {
            return 'kawin';
        }

        return null;
    }

    /** Golongan Darah → A|B|AB|O (dari label "Gol. Darah: O"). */
    private function extractBloodType(array $lines): ?string
    {
        $value = $this->extractLabeled($lines, ['gol. darah', 'gol darah', 'golongan darah']);
        if ($value === null) {
            return null;
        }

        if (preg_match('/\b(AB|A|B|O)\b/i', strtoupper($value), $m)) {
            return strtoupper($m[1]);
        }

        return null;
    }

    /** RT/RW → ["001", "002"] dari baris "RT/RW : 001/002". */
    private function extractRtRw(array $lines): array
    {
        $value = $this->extractLabeled($lines, ['rt/rw', 'rt / rw']);
        if ($value !== null && preg_match('/(\d{1,3})\s*[\/-]\s*(\d{1,3})/', $value, $m)) {
            return [$m[1], $m[2]];
        }

        return [null, null];
    }

    /** Pisah "KOTA, dd-mm-yyyy" → [tempat, Y-m-d]. */
    private function splitBirth(string $value): array
    {
        $place = null;
        $date = null;

        if (preg_match('/(\d{2})[-\/](\d{2})[-\/](\d{4})/', $value, $m)) {
            $date = "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        $parts = explode(',', $value, 2);
        if (count($parts) === 2) {
            $place = trim($parts[0]) ?: null;
        }

        return [$place, $date];
    }

    /**
     * Estimasi kepercayaan sederhana: proporsi field INTI yang berhasil terisi.
     * Field inti = nik, nama, tgl lahir, alamat (paling penting & paling akurat
     * dibaca OCR). Field gender/agama/status/pekerjaan sengaja TIDAK dihitung
     * karena OCR e-KTP sering keliru pada field tsb (user wajib koreksi manual).
     */
    private function estimateConfidence(array $r): float
    {
        $core = ['nik', 'full_name', 'birth_date', 'address'];
        $filled = 0;
        foreach ($core as $key) {
            if (! empty($r[$key])) {
                $filled++;
            }
        }

        return round($filled / count($core), 2);
    }
}
