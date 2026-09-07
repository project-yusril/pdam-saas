<?php

namespace App\Services;

use App\Exceptions\MarketplaceException;
use App\Support\ReportDatasetRegistry;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Writer\Word2007;

class ReportExportService
{
    /** @var array<string, array{mime:string, binary:bool}> */
    public const FORMATS = [
        'csv' => ['mime' => 'text/csv', 'binary' => false],
        'html' => ['mime' => 'text/html', 'binary' => false],
        'doc' => ['mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'binary' => true],
        'xlsx' => ['mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'binary' => true],
        'pdf' => ['mime' => 'application/pdf', 'binary' => true],
    ];

    public function __construct(private PdfExportService $pdfTemplate) {}

    public static function supportedFormats(): array
    {
        return array_keys(self::FORMATS);
    }

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

        if (! isset(self::FORMATS[$format])) {
            throw new MarketplaceException('INVALID_FORMAT', 'Format export tidak didukung.', [$format]);
        }

        [$content, $contentType] = $this->render($rows, $columns, $title, $format, $organizationId);

        return [
            'content' => $content,
            'row_count' => $rows->count(),
            'content_type' => $contentType,
            'format' => $format,
            'binary' => self::FORMATS[$format]['binary'],
        ];
    }

    /** @return array{0:string,1:string} [content, content_type] */
    private function render($rows, array $columns, string $title, string $format, int $organizationId): array
    {
        return match ($format) {
            'html' => [$this->html($rows, $columns, $title), self::FORMATS['html']['mime']],
            'xlsx' => [$this->xlsx($rows, $columns, $title), self::FORMATS['xlsx']['mime']],
            'doc' => [$this->doc($rows, $columns, $title), self::FORMATS['doc']['mime']],
            'pdf' => [$this->pdf($rows, $columns, $title), self::FORMATS['pdf']['mime']],
            default => [$this->csv($rows, $columns, $title), self::FORMATS['csv']['mime']],
        };
    }

    /**
     * DOCX (.docx) native via PhpWord — tabel + header bold + tautan privat,
     * formula injection netral (setText rada sel). Ekstensi yang dikenal Office
     * = .docx (kontainer WordprocessingML); nama file memakai .docx saat dibuat.
     */
    private function doc($rows, array $columns, string $title): string
    {
        $ph = new PhpWord;
        $section = $ph->addSection(['orientation' => 'landscape']);
        $ph->addTitleStyle(1, ['size' => 14, 'bold' => true]);

        $section->addTitle($title, 1);
        $section->addText('Periode: '.now()->format('d/m/Y H:i'), ['italic' => true, 'size' => 10]);

        $cellStyle = ['borderSize' => 6, 'cellMargin' => 60];
        $headerStyle = ['borderSize' => 6, 'cellMargin' => 60, 'bgColor' => 'E0F2FE'];

        $table = $section->addTable();
        $table->addRow();
        foreach ($columns as $column) {
            $cell = $table->addCell(null, $headerStyle);
            $cell->addText(ucwords(str_replace('_', ' ', $column)), ['bold' => true, 'size' => 9]);
        }

        foreach ($rows as $row) {
            $table->addRow();
            foreach ($columns as $column) {
                $value = $this->neutralize((string) ($row->$column ?? ''));
                $cell = $table->addCell(null, $cellStyle);
                $cell->addText($value, ['size' => 9]);
            }
        }

        $tmp = tempnam(sys_get_temp_dir(), 'pdam_docx_');
        try {
            (new Word2007($ph))->save($tmp);

            return (string) file_get_contents($tmp);
        } finally {
            @unlink($tmp);
        }
    }

    private function xlsx($rows, array $columns, string $title): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr(preg_replace('/[^A-Za-z0-9 _-]/', '', $title) ?: 'Report', 0, 31));

        $colLetters = array_map(
            fn ($i) => Coordinate::stringFromColumnIndex($i + 1),
            array_keys($columns),
        );
        $lastCol = $colLetters[count($colLetters) - 1];

        $mergeFromTo = "A1:{$lastCol}1";
        $sheet->mergeCells($mergeFromTo);
        $sheet->setCellValueExplicit('A1', $title."\u{200E}", DataType::TYPE_STRING);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->setCellValueExplicit('A2', 'Periode: '.now()->format('d/m/Y H:i')."\u{200E}", DataType::TYPE_STRING);

        foreach ($columns as $i => $column) {
            $sheet->setCellValueExplicit(
                $colLetters[$i].'3',
                $this->neutralize(ucwords(str_replace('_', ' ', $column))),
                DataType::TYPE_STRING,
            );
        }

        $rowNo = 4;
        foreach ($rows as $row) {
            foreach ($columns as $i => $column) {
                $cell = $colLetters[$i].$rowNo;
                $raw = (string) ($row->$column ?? '');

                // Angka riil ditulis sebagai number — bukan string ber-apostrof
                // (review: kolom rupiah SUM-nya mati & "-1500000" korup jadi apostrope)
                if ($this->isPureNumeric($raw)) {
                    $sheet->setCellValue($cell, $raw + 0);
                    if (is_string($raw) && str_contains($raw, '.')) {
                        $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0.00');
                    } else {
                        $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0');
                    }

                    continue;
                }

                if ($raw === '') {
                    $sheet->setCellValueExplicit($cell, '', DataType::TYPE_STRING);
                } else {
                    $sheet->setCellValueExplicit($cell, $this->neutralize($raw), DataType::TYPE_STRING);
                }
            }
            $rowNo++;
        }

        $sheet->getStyle('A3:'.$lastCol.'3')->getFont()->setBold(true);
        $sheet->getStyle('A3:'.$lastCol.'3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E0F2FE');

        $tmp = tempnam(sys_get_temp_dir(), 'pdam_xlsx_');
        try {
            (new Xlsx($spreadsheet))->save($tmp);

            return (string) file_get_contents($tmp);
        } finally {
            $spreadsheet->disconnectWorksheets();
            @unlink($tmp);
        }
    }

    private function pdf($rows, array $columns, string $title): string
    {
        $headers = array_map(fn ($column) => ucwords(str_replace('_', ' ', $column)), $columns);
        $data = [];
        foreach ($rows as $row) {
            $data[] = array_map(fn ($column) => $this->neutralize((string) ($row->$column ?? '')), $columns);
        }

        $html = $this->pdfTemplate->generate($title, $headers, $data, 'Periode: '.now()->format('d/m/Y H:i'));

        return Pdf::loadHTML($html)->setPaper('a4', 'landscape')->output();
    }

    /**
     * TRUE untuk angka murni yang boleh ditulis Excel sebagai number:
     * - optional negative, integer/decimal pendek, TANPA plus/eksponen
     * - hindari serial panjang (>15 digit) → presisi float hilang
     * - hindari leading-zero "kode" (007, 0012) → jangan dikonversi ke angka
     */
    private function isPureNumeric(string $raw): bool
    {
        if ($raw === '' || strlen($raw) >= 15 || preg_match('/[eE@+\s]/', $raw)) {
            return false;
        }

        return (bool) preg_match('/^-?(?:0|[1-9]\d*)(?:\.\d+)?$/', $raw);
    }

    /** Netralkan formula injection pada semua sel non-CVS. */
    private function neutralize(string $value): string
    {
        return $value !== '' && preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
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
