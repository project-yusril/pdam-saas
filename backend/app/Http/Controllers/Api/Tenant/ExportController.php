<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Exceptions\MarketplaceException;
use App\Http\Controllers\Controller;
use App\Services\ReportExportService;
use App\Support\ApiResponse;
use App\Support\ReportDatasetGate;
use App\Support\ReportDatasetRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    public function export(Request $request, ReportExportService $exports): JsonResponse
    {
        $data = $request->validate([
            'format' => ['required', 'in:'.implode(',', ReportExportService::supportedFormats())],
            'table' => ['required', 'string'],
            'filters' => ['nullable', 'array', 'max:20'],
            'sort' => ['nullable', 'string'],
            'columns' => ['nullable', 'array', 'min:1', 'max:30'],
            'columns.*' => ['string', 'distinct'],
            'title' => ['nullable', 'string', 'max:150', 'not_regex:/[\r\n]/'],
        ]);

        $datasetName = $data['table'];
        $dataset = ReportDatasetRegistry::get($datasetName);
        if ($dataset === null) {
            return ApiResponse::error('INVALID_TABLE', 'Tabel tidak didukung untuk export.', null, 422);
        }
        if ($denial = ReportDatasetGate::authorize($request, $dataset, true)) {
            return $denial;
        }

        $columns = $data['columns'] ?? $dataset['export_default'];
        try {
            $result = $exports->generate(
                $request->user()->pdam_org_id,
                $datasetName,
                $data['format'],
                $columns,
                $data['filters'] ?? [],
                $data['sort'] ?? null,
                $data['title'] ?? null,
            );
        } catch (MarketplaceException $exception) {
            return ApiResponse::error($exception->errorCode, $exception->getMessage(), $exception->details, 422);
        }

        $filename = $datasetName.'_'.now()->format('Ymd_His');

        $payload = [
            'filename' => $filename.'.'.$data['format'],
            'format' => $data['format'],
            'content_type' => $result['content_type'],
            'total_rows' => $result['row_count'],
        ];
        if ($result['binary']) {
            $payload['content_base64'] = base64_encode($result['content']);
        } else {
            $payload['data'] = $result['content'];
        }

        return response()->json($payload);
    }
}
