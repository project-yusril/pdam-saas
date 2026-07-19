<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentApproval;
use App\Services\FileUploadService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Document::when($request->input('category'), fn ($q, $v) => $q->where('category', $v))
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('created_at');

        return ApiResponse::paginated($query->paginate($request->input('per_page', 25)));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png'],
            'title' => ['required', 'string', 'max:200'],
            'category' => ['required', 'string', 'max:30'],
            'tags' => ['nullable', 'array'],
        ]);

        $uploadService = app(FileUploadService::class);
        $upload = $uploadService->upload($request->user()->pdam_org_id.'/document', $request->file('file'));

        $doc = Document::create([
            'doc_number' => 'DOC-'.now()->format('ym').'-'.strtoupper(uniqid()),
            'title' => $request->input('title'),
            'category' => $request->input('category'),
            'version' => 1,
            'file_path' => $upload['path'],
            'file_type' => $request->file('file')->guessExtension(),
            'file_size' => $upload['size'],
            'tags' => $request->input('tags'),
            'status' => 'draft',
            'uploaded_by' => $request->user()->id,
        ]);

        return ApiResponse::success($doc, status: 201);
    }

    public function show(Request $request, Document $document): JsonResponse
    {
        $uploadService = app(FileUploadService::class);
        try {
            $path = $uploadService->assertTenantPath(
                $document->file_path,
                $request->user()->pdam_org_id,
                'document',
                true,
            );
        } catch (\InvalidArgumentException) {
            abort(404, 'File tidak ditemukan.');
        }
        $url = $uploadService->signedUrl($path, 60);

        return ApiResponse::success([
            'document' => $document,
            'download_url' => $url,
            'url_expires_at' => now()->addMinutes(60)->toIso8601String(),
        ]);
    }

    public function approve(Request $request, Document $document): JsonResponse
    {
        $data = $request->validate([
            'comments' => ['nullable', 'string'],
            'signature_data' => ['nullable', 'string'],
        ]);

        DocumentApproval::create([
            'document_id' => $document->id,
            'approver_id' => $request->user()->id,
            'approval_order' => 1,
            'status' => 'approved',
            'approved_at' => now(),
            'comments' => $data['comments'] ?? null,
            'signature_data' => $data['signature_data'] ?? null,
        ]);

        $document->update(['status' => 'approved']);

        return ApiResponse::success($document);
    }
}
