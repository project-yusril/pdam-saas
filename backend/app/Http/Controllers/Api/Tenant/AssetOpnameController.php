<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\FixedAsset;
use App\Services\JournalService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetOpnameController extends Controller
{
    public function __construct(private JournalService $journal) {}

    public function revaluation(Request $request, FixedAsset $asset): JsonResponse
    {
        $data = $request->validate([
            'new_value' => ['required', 'numeric', 'min:0'],
            'reason' => ['required', 'string'],
            'effective_date' => ['required', 'date'],
        ]);

        $oldValue = $asset->acquisition_value;
        $diff = $data['new_value'] - $oldValue;

        if (abs($diff) < 1) {
            return ApiResponse::message('Tidak ada perubahan nilai.');
        }

        $asset->update([
            'acquisition_value' => $data['new_value'],
            'book_value' => $asset->book_value + $diff,
        ]);

        if ($diff > 0) {
            $this->journal->record(
                "Revaluasi aset - {$asset->asset_code}",
                [
                    ['account_code' => "1-0{$asset->category_id}0", 'type' => 'DEBIT', 'amount' => $diff, 'memo' => "Revaluasi aset {$asset->name}"],
                    ['account_code' => '3-003', 'type' => 'KREDIT', 'amount' => $diff, 'memo' => 'Surplus revaluasi'],
                ],
                'asset_revaluation',
                $asset->id,
            );
        }

        return ApiResponse::success([
            'asset' => $asset->fresh(),
            'old_value' => $oldValue,
            'new_value' => $data['new_value'],
            'difference' => $diff,
        ]);
    }

    public function opname(Request $request): JsonResponse
    {
        $data = $request->validate([
            'opname_date' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.asset_id' => ['required', 'integer', 'exists:fixed_assets,id'],
            'items.*.status_cek' => ['required', 'in:ada,rusak,hilang,tidak_ada'],
            'items.*.keterangan' => ['nullable', 'string'],
        ]);

        $results = [];
        foreach ($data['items'] as $item) {
            $asset = FixedAsset::findOrFail($item['asset_id']);
            $oldStatus = $asset->status;

            $results[] = [
                'asset_code' => $asset->asset_code,
                'asset_name' => $asset->name,
                'cek_status' => $item['status_cek'],
                'old_status' => $oldStatus,
                'keterangan' => $item['keterangan'] ?? null,
            ];

            if ($item['status_cek'] === 'hilang' || $item['status_cek'] === 'rusak') {
                $asset->update(['status' => $item['status_cek'] === 'hilang' ? 'dihapus' : 'rusak']);

                if ($asset->book_value > 0) {
                    $this->journal->record(
                        "Opname aset - {$asset->asset_code}",
                        [
                            ['account_code' => '5-102', 'type' => 'DEBIT', 'amount' => $asset->book_value, 'memo' => 'Kerugian aset hilang/rusak'],
                            ['account_code' => "1-0{$asset->category_id}0", 'type' => 'KREDIT', 'amount' => $asset->book_value, 'memo' => 'Penghapusan nilai buku'],
                        ],
                        'asset_opname',
                        $asset->id,
                    );
                }
            }
        }

        return ApiResponse::success([
            'opname_date' => $data['opname_date'],
            'total_items' => count($results),
            'results' => $results,
        ]);
    }
}
