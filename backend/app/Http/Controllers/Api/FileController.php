<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerProspect;
use App\Models\Document;
use App\Models\MeterReading;
use App\Services\FileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * FileController — akses file privat via signed URL.
 * Semua foto (KTP, meter, rumah) disimpan di disk privat.
 */
class FileController extends Controller
{
    public function upload(Request $request): JsonResponse
    {
        $data = $request->validate([
            'purpose' => ['required', 'in:meter,survey,document,asset,profile'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $upload = app(FileUploadService::class)->upload(
            $request->user()->pdam_org_id.'/'.$data['purpose'],
            $request->file('file'),
        );

        return response()->json([
            'success' => true,
            'data' => collect($upload)->except('url')->all(),
        ], 201);
    }

    public function signedUrl(Request $request): JsonResponse
    {
        $data = $request->validate([
            'resource_type' => ['required', 'in:document,prospect_ktp,meter_reading'],
            'resource_id' => ['required', 'integer', 'min:1'],
            'field' => ['nullable', 'string'],
        ]);

        $service = app(FileUploadService::class);
        $path = $this->resolveAuthorizedPath(
            $request,
            $data['resource_type'],
            (int) $data['resource_id'],
            $data['field'] ?? null,
        );

        if (! $service->exists($path)) {
            throw ValidationException::withMessages(['path' => 'File tidak ditemukan.']);
        }

        $url = $service->signedUrl($path);

        return response()->json([
            'url' => $url,
            'expires_in' => 30 * 60,
        ]);
    }

    private function resolveAuthorizedPath(Request $request, string $type, int $id, ?string $field): string
    {
        $user = $request->user();

        return match ($type) {
            'document' => $this->documentPath($user, $id),
            'prospect_ktp' => $this->prospectPath($user, $id),
            'meter_reading' => $this->meterReadingPath($user, $id, $field),
        };
    }

    private function documentPath($user, int $id): string
    {
        $document = Document::where('pdam_org_id', $user->pdam_org_id)->findOrFail($id);
        $this->authorizeResource($user, $document->uploaded_by === $user->id || $user->hasPermission('dms.document.view'));

        return $this->guardPath($document->file_path, $user->pdam_org_id, 'document', true);
    }

    private function prospectPath($user, int $id): string
    {
        $prospect = CustomerProspect::where('pdam_org_id', $user->pdam_org_id)->findOrFail($id);
        $this->authorizeResource(
            $user,
            $prospect->user_id === $user->id || $user->hasPermission('srv.prospect.view'),
        );

        return $this->guardPath((string) $prospect->ktp_photo_url, $user->pdam_org_id, 'ktp');
    }

    private function meterReadingPath($user, int $id, ?string $field): string
    {
        if (! in_array($field, ['photo_meter_url', 'photo_house_url'], true)) {
            throw ValidationException::withMessages([
                'field' => 'Field meter reading harus photo_meter_url atau photo_house_url.',
            ]);
        }

        $reading = MeterReading::with('customer:id,user_id')
            ->where('pdam_org_id', $user->pdam_org_id)
            ->findOrFail($id);
        $this->authorizeResource(
            $user,
            $reading->read_by === $user->id
                || $reading->customer?->user_id === $user->id
                || $user->hasPermission('mtr.reading.view'),
        );

        return $this->guardPath((string) $reading->{$field}, $user->pdam_org_id, 'meter');
    }

    private function authorizeResource($user, bool $allowed): void
    {
        if (! $user->is_tenant_admin && ! $allowed) {
            abort(403, 'Anda tidak memiliki akses ke file ini.');
        }
    }

    private function guardPath(string $path, int $tenantId, string $purpose, bool $allowLegacyDocument = false): string
    {
        try {
            return app(FileUploadService::class)->assertTenantPath($path, $tenantId, $purpose, $allowLegacyDocument);
        } catch (\InvalidArgumentException) {
            abort(404, 'File tidak ditemukan.');
        }
    }
}
