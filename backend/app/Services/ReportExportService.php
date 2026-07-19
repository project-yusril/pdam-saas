<?php

namespace App\Services;

use App\Exceptions\MarketplaceException;
use App\Support\ReportDatasetRegistry;
use Illuminate\Support\Facades\DB;

class ReportExportService
{
    public function generate(int $organizationId, string $datasetName, string $format, array $columns, array $filters = [], ?string $sort = null, ?string $title = null): array
    {
        $dataset = ReportDatasetRegistry::get($datasetName);
        if (! $dataset) {
            throw new MarketplaceException('INVALID_TABLE', 'Tabel tidak didukung untuk export.');
        }
        $invalidColumns = array_diff($columns, $dataset['export_columns']);
        $invalidFilters = array_diff(array_keys($filters), $dataset['export_filters']);
        if ($invalidColumns !== []) {
            throw new MarketplaceException('INVALID_COLUMN', 'Kolom export tidak diizinkan.', array_values($invalidColumns));
        }
        if ($invalidFilters !== []) {
            throw new MarketplaceException('INVALID_FILTER', 'Filter export tidak diizinkan.', array_values($invalidFilters));
        }

        $query = DB::table($dataset['table'])->where('pdam_org_id', $organizationId);
        foreach ($filters as $column => $value) {
            if (! $this->validFilterValue($value)) {
                throw new MarketplaceException('INVALID_FILTER_VALUE', 'Nilai filter export tidak valid.', [$column]);
            }
            is_array($value) ? $query->whereIn($column, $value) : $query->where($column, $value);
        }
        if ($sort) {
            $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
            $column = ltrim($sort, '-');
            if (! in_array($column, $dataset['export_columns'], true)) {
                throw new MarketplaceException('INVALID_SORT', 'Kolom sort tidak diizinkan.', [$column]);
            }
            $query->orderBy($column, $direction);
        }

        $rows = $query->select($columns)->limit(10000)->get();
        $title ??= 'Laporan '.ucfirst($datasetName);
        $content = $format === 'html' ? $this->html($rows, $columns, $title) : $this->csv($rows, $columns, $title);

        return ['content' => $content, 'row_count' => $rows->count(), 'content_type' => $format === 'html' ? 'text/html' : 'text/csv'];
    }

    private function csv($rows, array $columns, string $title): string
    {
        $output = $this->csvCell('Organisasi').','.$this->csvCell($title)."\nPeriode: ".now()->format('d/m/Y H:i')."\n\n".implode(',', $columns)."\n";
        foreach ($rows as $row) {
            $output .= implode(',', array_map(fn ($column) => $this->csvCell((string) ($row->$column ?? '')), $columns))."\n";
        }

        return $output;
    }

    private function csvCell(string $value): string
    {
        if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value)) {
            $value = "'".$value;
        }

        return '"'.str_replace('"', '""', $value).'"';
    }

    private function html($rows, array $columns, string $title): string
    {
        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>'.e($title).'</title><style>body{font-family:sans-serif}table{border-collapse:collapse;width:100%}th,td{border:1px solid #ccc;padding:6px;font-size:12px}th{background:#f0f0f0}</style></head><body><h3>'.e($title).'</h3><p>Periode: '.now()->format('d/m/Y H:i').'</p><table><thead><tr>';
        foreach ($columns as $column) {
            $html .= '<th>'.e(ucwords(str_replace('_', ' ', $column))).'</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($columns as $column) {
                $html .= '<td>'.e((string) ($row->$column ?? '')).'</td>';
            }
            $html .= '</tr>';
        }

        return $html.'</tbody></table></body></html>';
    }

    private function validFilterValue(mixed $value): bool
    {
        if (! is_array($value)) {
            return is_scalar($value) || $value === null;
        }

        return $value !== [] && count($value) <= 100 && collect($value)->every(fn ($item) => is_scalar($item) || $item === null);
    }
}
