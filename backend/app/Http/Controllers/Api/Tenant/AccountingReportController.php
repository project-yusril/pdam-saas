<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Services\AccountingReportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AccountingReportController — 5 laporan keuangan standar (PRD 3.4, Fase 1.6).
 * Semua diturunkan dari journal_entry_lines (satu sumber kebenaran).
 */
class AccountingReportController extends Controller
{
    public function __construct(private AccountingReportService $reports) {}

    public function generalLedger(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);

        return ApiResponse::success($this->reports->generalLedger($from, $to), $this->meta($from, $to));
    }

    public function trialBalance(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);

        return ApiResponse::success($this->reports->trialBalance($from, $to), $this->meta($from, $to));
    }

    public function incomeStatement(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);

        return ApiResponse::success($this->reports->incomeStatement($from, $to), $this->meta($from, $to));
    }

    public function balanceSheet(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);

        return ApiResponse::success($this->reports->balanceSheet($from, $to), $this->meta($from, $to));
    }

    public function cashFlow(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);

        return ApiResponse::success($this->reports->cashFlow($from, $to), $this->meta($from, $to));
    }

    /** Ambil rentang periode dari query, default bulan berjalan. */
    protected function range(Request $request): array
    {
        $current = now()->format('Y-m');
        $from = $request->query('from', $current);
        $to = $request->query('to', $from);

        return [$from, $to];
    }

    protected function meta(string $from, string $to): array
    {
        return ['from_period' => $from, 'to_period' => $to];
    }
}
