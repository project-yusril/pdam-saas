<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Services\BillReconciliationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillRecController extends Controller
{
    public function __construct(private BillReconciliationService $recon) {}

    public function reconcile(Request $request): JsonResponse
    {
        $data = $request->validate(['period' => ['required', 'regex:/^\d{4}-\d{2}$/']]);

        return ApiResponse::success($this->recon->reconcilePeriod($data['period']));
    }

    public function audit(Request $request): JsonResponse
    {
        $data = $request->validate(['bill_id' => ['required', 'integer', 'exists:bills,id']]);

        return ApiResponse::success($this->recon->auditLog($data['bill_id']));
    }
}
