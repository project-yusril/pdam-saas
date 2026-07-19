<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\ReportDatasetGate;
use App\Support\ReportDatasetRegistry;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BiController extends Controller
{
    public function kpiEkskutif(Request $request): JsonResponse
    {
        $orgId = $request->user()->pdam_org_id;
        $month = $request->input('month', now()->format('Y-m'));

        $revenue = DB::table('bills')
            ->where('pdam_org_id', $orgId)->where('status', 'paid')
            ->where('period', $month)->sum('amount_due');

        $outstanding = DB::table('bills')
            ->where('pdam_org_id', $orgId)->whereIn('status', ['unpaid', 'overdue'])->sum('amount_due');

        $customers = DB::table('customers')->where('pdam_org_id', $orgId)->where('status', 'active')->count();

        $waterSold = DB::table('bills')
            ->where('pdam_org_id', $orgId)->where('status', 'paid')
            ->where('period', $month)->sum('consumption');

        $collectionRate = $revenue > 0 ? round(($revenue / ($revenue + $outstanding)) * 100, 1) : 0;

        $complaints = ReportDatasetGate::hasActiveModule($orgId, 'CRM')
            ? DB::table('complaints')->where('pdam_org_id', $orgId)->whereIn('status', ['open', 'assigned'])->count()
            : null;

        return ApiResponse::success([
            'period' => $month,
            'revenue' => (float) $revenue,
            'outstanding' => (float) $outstanding,
            'active_customers' => $customers,
            'water_sold_m3' => (float) $waterSold,
            'collection_rate_percent' => $collectionRate,
            'open_complaints' => $complaints,
        ]);
    }

    public function reportBuilder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'table' => ['required', 'in:'.implode(',', ReportDatasetRegistry::names())],
            'dimensions' => ['required', 'array', 'min:1', 'max:10'],
            'dimensions.*' => ['string', 'distinct'],
            'metrics' => ['required', 'array', 'min:1', 'max:10'],
            'metrics.*' => ['string', 'distinct'],
            'filters' => ['nullable', 'array', 'max:20'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        $orgId = $request->user()->pdam_org_id;
        $dataset = ReportDatasetRegistry::get($data['table']);
        $table = $dataset['table'];

        if ($denial = ReportDatasetGate::authorize($request, $dataset, false)) {
            return $denial;
        }

        $invalidDimensions = array_diff($data['dimensions'], $dataset['dimensions']);
        if ($invalidDimensions !== []) {
            return ApiResponse::error('INVALID_DIMENSION', 'Dimensi tidak diizinkan.', array_values($invalidDimensions), 422);
        }

        $metrics = [];
        foreach ($data['metrics'] as $metric) {
            [$function, $column] = array_pad(explode(':', strtolower($metric), 2), 2, null);
            if (! in_array($function, $dataset['metrics'][$column] ?? [], true)) {
                return ApiResponse::error('INVALID_METRIC', 'Metric tidak diizinkan.', [$metric], 422);
            }
            $metrics[] = [$function, $column];
        }

        $invalidFilters = array_diff(array_keys($data['filters'] ?? []), $dataset['bi_filters']);
        if ($invalidFilters !== []) {
            return ApiResponse::error('INVALID_FILTER', 'Filter tidak diizinkan.', array_values($invalidFilters), 422);
        }

        $query = DB::table($table)->where('pdam_org_id', $orgId);

        if (! empty($data['date_from'])) {
            $query->where('created_at', '>=', Carbon::createFromFormat('Y-m-d', $data['date_from'])->startOfDay());
        }
        if (! empty($data['date_to'])) {
            $query->where('created_at', '<=', Carbon::createFromFormat('Y-m-d', $data['date_to'])->endOfDay());
        }

        foreach ($data['filters'] ?? [] as $col => $val) {
            if (! $this->validFilterValue($val)) {
                return ApiResponse::error('INVALID_FILTER_VALUE', 'Nilai filter tidak valid.', [$col], 422);
            }
            if (is_array($val)) {
                $query->whereIn($col, $val);
            } else {
                $query->where($col, $val);
            }
        }

        $selects = $data['dimensions'];
        $grammar = DB::connection()->getQueryGrammar();
        foreach ($metrics as [$function, $column]) {
            $alias = "{$function}_{$column}";
            $selects[] = DB::raw(strtoupper($function).'('.$grammar->wrap($column).') as '.$grammar->wrap($alias));
        }

        $results = $query->select(...$selects)
            ->groupBy($data['dimensions'])
            ->limit(1000)
            ->get()
            ->map(function (object $row) use ($metrics): object {
                foreach ($metrics as [$function, $column]) {
                    $alias = "{$function}_{$column}";
                    $row->{$alias} = $function === 'count'
                        ? (int) $row->{$alias}
                        : (float) $row->{$alias};
                }

                return $row;
            });

        return ApiResponse::success([
            'table' => $table,
            'dimensions' => $data['dimensions'],
            'metrics' => $data['metrics'],
            'rows' => $results,
            'total_rows' => $results->count(),
        ]);
    }

    public function scheduledReports(Request $request): JsonResponse
    {
        return ApiResponse::success([]);
    }

    private function validFilterValue(mixed $value): bool
    {
        if (! is_array($value)) {
            return is_scalar($value) || $value === null;
        }

        if ($value === [] || count($value) > 100) {
            return false;
        }

        foreach ($value as $item) {
            if (! is_scalar($item) && $item !== null) {
                return false;
            }
        }

        return true;
    }
}
