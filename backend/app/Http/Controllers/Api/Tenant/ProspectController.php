<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\CustomerProspect;
use App\Models\SurveyReport;
use App\Services\FileUploadService;
use App\Services\KtpOcrService;
use App\Services\PaymentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * ProspectController — pendaftaran & alur survey calon pelanggan (PRD 3.1, Fase 3).
 * Menangani: registrasi (OCR KTP + upload foto/PDF + GPS pin), review Hublang,
 * assign surveyor, submit laporan survey, dan keputusan Kepala Survey
 * (approve/reject/re-survey) beserta rincian biaya pemasangan.
 */
class ProspectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 20), 100);
        $query = CustomerProspect::orderByDesc('created_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return ApiResponse::paginated($query->paginate($perPage));
    }

    /**
     * Parse teks mentah OCR KTP → field terstruktur untuk prefill form (Fase 3.1).
     * Hasil (termasuk gender/agama/status kawin/pekerjaan) WAJIB direview/koreksi
     * user di mobile sebelum submit (store) — OCR e-KTP tidak selalu akurat.
     */
    public function parseKtp(Request $request, KtpOcrService $ocr): JsonResponse
    {
        $data = $request->validate([
            'raw_text' => ['required', 'string'],
        ]);

        return ApiResponse::message('Hasil OCR diekstrak.', $ocr->parse($data['raw_text']));
    }

    /**
     * Upload foto/scan KTP (JPG/PNG/PDF) → simpan privat, kembalikan url + tipe file.
     * Dipanggil sebelum store untuk mendapatkan ktp_photo_url + ktp_file_type.
     */
    public function uploadKtp(Request $request, KtpOcrService $ocr, FileUploadService $files): JsonResponse
    {
        $request->validate([
            // JPG/PNG/PDF, maks 5 MB
            'ktp_file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $file = $request->file('ktp_file');
        $ext = strtolower($file->getClientOriginalExtension());
        $type = $ext === 'jpeg' ? 'jpg' : $ext;

        try {
            $result = $ocr->recognize($file);
        } catch (RuntimeException $exception) {
            return ApiResponse::error('OCR_UNAVAILABLE', $exception->getMessage(), null, 503);
        }

        $upload = $files->upload($request->user()->pdam_org_id.'/ktp', $file);

        return ApiResponse::message('KTP terunggah.', [
            'ktp_photo_url' => $upload['path'],
            'ktp_file_type' => $type,
            'raw_text' => $result['raw_text'],
            'parsed' => $result['parsed'],
        ]);
    }

    /** Registrasi calon pelanggan (dari mobile, hasil OCR KTP + GPS pin). */
    public function store(Request $request): JsonResponse
    {
        $tenantFile = $this->tenantFileRule($request, 'ktp');
        $data = $request->validate([
            'nik' => ['required', 'string', 'size:16'],
            'full_name' => ['required', 'string', 'max:150'],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date'],
            // Field e-KTP tambahan (dapat dikoreksi manual sebelum submit)
            'gender' => ['nullable', 'in:L,P'],
            'religion' => ['nullable', 'string', 'max:30'],
            'marital_status' => ['nullable', 'in:belum_kawin,kawin,cerai_hidup,cerai_mati'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'blood_type' => ['nullable', 'in:A,B,AB,O'],
            'nationality' => ['nullable', 'string', 'max:30'],
            // Alamat
            'address' => ['nullable', 'string', 'max:255'],
            'installation_address' => ['required', 'string', 'max:255'],
            'street_id' => ['nullable', 'integer', 'exists:streets,id'],
            'house_number' => ['nullable', 'string', 'max:20'],
            'rt' => ['nullable', 'string', 'max:5'],
            'rw' => ['nullable', 'string', 'max:5'],
            'zone_id' => ['nullable', 'integer'],
            // Two-Stage GPS Tahap 1 (customer_pin)
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'location_accuracy' => ['nullable', 'numeric', 'min:0'],
            // Kontak & KTP
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'ktp_photo_url' => ['nullable', 'string', $tenantFile],
            'ktp_file_type' => ['nullable', 'in:jpg,png,pdf'],
            'ktp_ocr_raw' => ['nullable', 'array'],
            'tariff_category_id' => ['nullable', 'integer'],
        ]);

        $prospect = new CustomerProspect($data);
        $prospect->registration_number = 'REG-'.strtoupper(Str::random(8));
        $prospect->status = 'pending_review';
        $prospect->nationality = $data['nationality'] ?? 'WNI';
        $prospect->user_id = $request->user()?->id;

        // Jika pelanggan menandai lokasi di peta → tandai sumber koordinat
        if (isset($data['latitude'], $data['longitude'])) {
            $prospect->location_source = 'customer_pin';
        }

        $prospect->save();

        return ApiResponse::message('Pendaftaran diterima.', $prospect, 201);
    }

    /** Update data prospek (kontak/alamat) — NIK tidak dapat berubah setelah submit. */
    public function show(Request $request, CustomerProspect $prospect): JsonResponse
    {
        return ApiResponse::success($prospect->load(['survey', 'street']));
    }

    public function update(Request $request, CustomerProspect $prospect): JsonResponse
    {
        $data = $request->validate([
            'full_name' => ['sometimes', 'string', 'max:150'],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['nullable', 'in:L,P'],
            'religion' => ['nullable', 'string', 'max:30'],
            'marital_status' => ['nullable', 'in:belum_kawin,kawin,cerai_hidup,cerai_mati'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'blood_type' => ['nullable', 'in:A,B,AB,O'],
            'address' => ['nullable', 'string', 'max:255'],
            'installation_address' => ['sometimes', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:20'],
            'rt' => ['nullable', 'string', 'max:5'],
            'rw' => ['nullable', 'string', 'max:5'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $prospect->update($data);

        return ApiResponse::success($prospect->fresh());
    }

    /** Hublang: assign surveyor → status surveying. */
    public function assignSurveyor(Request $request, CustomerProspect $prospect): JsonResponse
    {
        $data = $request->validate([
            'surveyor_id' => ['required', 'integer'],
        ]);

        if ($prospect->status !== 'pending_review') {
            return ApiResponse::error('INVALID_STATE', 'Prospek tidak dalam status menunggu review.', null, 422);
        }

        $prospect->update([
            'assigned_surveyor_id' => $data['surveyor_id'],
            'status' => 'surveying',
        ]);

        return ApiResponse::message('Surveyor ditugaskan.', $prospect);
    }

    /**
     * Surveyor: submit laporan survey → status survey_submitted.
     * Foto rumah WAJIB min. 2 (bukti kehadiran + bahan keputusan survey_head).
     * Two-Stage GPS Tahap 2: koordinat surveyor = titik FINAL (surveyor_verified).
     */
    public function submitSurvey(Request $request, CustomerProspect $prospect): JsonResponse
    {
        $tenantFile = $this->tenantFileRule($request, 'survey');
        $data = $request->validate([
            'photo_house_urls' => ['required', 'array', 'min:2'],
            'photo_house_urls.*' => ['required', 'string', $tenantFile],
            'distance_to_main_pipe' => ['nullable', 'numeric', 'min:0'],
            'building_condition' => ['nullable', 'string', 'max:100'],
            'accessibility' => ['nullable', 'string', 'max:100'],
            'land_status' => ['nullable', 'in:milik_sendiri,sewa,lainnya'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'location_accuracy' => ['nullable', 'numeric', 'min:0'],
            'estimated_materials' => ['nullable', 'array'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'recommendation' => ['required', 'in:feasible,not_feasible'],
            'surveyor_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! in_array($prospect->status, ['surveying', 're_survey_needed'], true)) {
            return ApiResponse::error('INVALID_STATE', 'Prospek belum siap disurvey.', null, 422);
        }

        $report = new SurveyReport($data);
        $report->prospect_id = $prospect->id;
        $report->surveyor_id = $request->user()->id;
        $report->location_source = 'surveyor_verified';
        $report->save();

        // Koordinat FINAL (surveyor_verified) ditulis balik ke prospek — dipakai
        // GIS (node berwarna), rute baca meter, dsb (Two-Stage GPS Tahap 2).
        $prospect->update([
            'status' => 'survey_submitted',
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'location_source' => 'surveyor_verified',
            'location_accuracy' => $data['location_accuracy'] ?? null,
        ]);

        return ApiResponse::message('Laporan survey terkirim.', $report, 201);
    }

    /**
     * Kepala Survey: keputusan atas laporan (approve|reject|re_survey).
     * - approved  → lokasi layak, set biaya pemasangan (+rincian) → payment_pending
     * - rejected  → tidak layak teknis (jarak/bangunan) → rejected
     * - re_survey → laporan buruk (foto buram/data kurang) → re_survey_needed
     */
    public function reviewSurvey(Request $request, SurveyReport $report): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected,re_survey'],
            'notes' => ['nullable', 'string', 'max:500'],
            'installation_fee' => ['nullable', 'numeric', 'min:0'],
            // Rincian biaya: [{item, qty, unit_price, subtotal}, ...] + jasa
            'installation_fee_breakdown' => ['nullable', 'array'],
            'installation_fee_breakdown.*.item' => ['required_with:installation_fee_breakdown', 'string'],
            'installation_fee_breakdown.*.amount' => ['required_with:installation_fee_breakdown', 'numeric', 'min:0'],
        ]);

        $prospect = CustomerProspect::findOrFail($report->prospect_id);

        $report->update([
            'reviewed_by' => $request->user()->id,
            'review_status' => $data['decision'],
            'review_notes' => $data['notes'] ?? null,
            'reviewed_at' => now(),
        ]);

        // Transisi status prospek sesuai keputusan (PRD tahap 4)
        switch ($data['decision']) {
            case 'approved':
                $breakdown = $data['installation_fee_breakdown'] ?? null;
                // Fee final: eksplisit > jumlah rincian > estimasi surveyor
                $fee = $data['installation_fee']
                    ?? ($breakdown ? array_sum(array_column($breakdown, 'amount')) : null)
                    ?? $report->estimated_cost;

                $prospect->update([
                    'status' => 'payment_pending',
                    'installation_fee' => $fee,
                    'installation_fee_breakdown' => $breakdown,
                    'payment_due_at' => now()->addHours(12), // timer eskalasi (PRD 13.1)
                ]);
                break;
            case 'rejected':
                $prospect->update(['status' => 'rejected', 'rejection_reason' => $data['notes'] ?? 'Lokasi tidak layak']);
                break;
            case 're_survey':
                $prospect->update(['status' => 're_survey_needed']);
                break;
        }

        return ApiResponse::message('Keputusan survey tersimpan.', $report->fresh());
    }

    /**
     * Buat pembayaran biaya pemasangan untuk prospek (payment_pending).
     * Mengembalikan record Payment pending untuk diproses gateway (Midtrans).
     */
    public function payInstallation(Request $request, CustomerProspect $prospect, PaymentService $payments): JsonResponse
    {
        $data = $request->validate([
            'payment_method' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $payment = $payments->createForInstallation($prospect, 'gateway', $data['payment_method'] ?? null);
        } catch (RuntimeException $e) {
            return ApiResponse::error('INVALID_STATE', $e->getMessage(), null, 422);
        }

        return ApiResponse::message('Pembayaran pemasangan dibuat.', $payment, 201);
    }

    public function destroy(Request $request, CustomerProspect $prospect): JsonResponse
    {
        $prospect->delete();

        return ApiResponse::message('Calon pelanggan dihapus (soft-delete).');
    }

    private function tenantFileRule(Request $request, string $purpose): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($request, $purpose): void {
            try {
                app(FileUploadService::class)->assertTenantPath((string) $value, $request->user()->pdam_org_id, $purpose);
            } catch (\InvalidArgumentException $exception) {
                $fail($exception->getMessage());
            }
        };
    }
}
