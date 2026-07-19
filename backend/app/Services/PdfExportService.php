<?php

namespace App\Services;

use App\Support\TenantContext;

/**
 * PdfExportService — generate laporan PDF + kop surat PDAM (PRD 28.2).
 * Dipakai oleh ExportController dan laporan per modul.
 */
class PdfExportService
{
    public function generate(string $title, array $headers, array $rows, ?string $subTitle = null): string
    {
        $org = TenantContext::org();
        $orgName = $org->name ?? 'PDAM';
        $date = now()->format('d/m/Y H:i');

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>'.e($title).'</title>';
        $html .= '<style>
            @page { margin: 2cm; size: A4 landscape; }
            body { font-family: Arial, sans-serif; font-size: 10px; }
            .header { text-align: center; border-bottom: 2px solid #1d4ed8; padding-bottom: 10px; margin-bottom: 15px; }
            .header h2 { margin: 0; color: #1d4ed8; font-size: 16px; }
            .header .sub { font-size: 11px; color: #666; margin-top: 4px; }
            .header .kop { font-size: 14px; font-weight: bold; margin-bottom: 4px; }
            .meta { font-size: 9px; color: #999; margin-bottom: 10px; text-align: right; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th { background: #1d4ed8; color: white; padding: 6px 8px; text-align: left; font-size: 9px; text-transform: uppercase; }
            td { padding: 5px 8px; border-bottom: 1px solid #e5e7eb; font-size: 9px; }
            tr:nth-child(even) { background: #f8fafc; }
            .footer { margin-top: 20px; font-size: 9px; color: #999; text-align: center; border-top: 1px solid #e5e7eb; padding-top: 10px; }
            .ttd { margin-top: 30px; text-align: right; }
        </style></head><body>';

        $html .= '<div class="header">';
        $html .= '<div class="kop">PEMERINTAH KOTA/KABUPATEN</div>';
        $html .= '<div class="kop">PERUSAHAAN DAERAH AIR MINUM (PDAM)</div>';
        $html .= '<div class="kop">'.e(strtoupper($orgName)).'</div>';
        $html .= '<h2>'.e($title).'</h2>';
        if ($subTitle) {
            $html .= '<div class="sub">'.e($subTitle).'</div>';
        }
        $html .= '<div class="meta">Dicetak: '.$date.' | Hal: <span class="page"></span></div>';
        $html .= '</div>';

        $html .= '<table><thead><tr>';
        foreach ($headers as $h) {
            $html .= '<th>'.e($h).'</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>'.e((string) $cell).'</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        $html .= '<div class="footer">Dokumen ini dicetak dari Sistem Informasi Manajemen PDAM — '.e($orgName).'</div>';
        $html .= '</body></html>';

        return $html;
    }

    public function generateReceipt(array $data): string
    {
        $org = TenantContext::org();
        $orgName = $org->name ?? 'PDAM';

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
            body { font-family: monospace; font-size: 10px; width: 80mm; margin: 0; padding: 5mm; }
            .center { text-align: center; }
            .bold { font-weight: bold; }
            .line { border-top: 1px dashed #000; margin: 3mm 0; }
            .total { font-size: 14px; }
        </style></head><body>';

        $html .= '<div class="center bold">'.e($orgName).'</div>';
        $html .= '<div class="center">KWITANSI PEMBAYARAN</div>';
        $html .= '<div class="line"></div>';
        $html .= '<div>No: '.e($data['receipt_number']).'</div>';
        $html .= '<div>Tgl: '.e($data['paid_at'] ?? now()->format('d/m/Y H:i')).'</div>';
        $html .= '<div class="line"></div>';
        $html .= '<div>Metode: '.e($data['method'] ?? 'Tunai').'</div>';
        $html .= '<div class="line"></div>';
        $html .= '<div class="center bold total">Rp '.number_format($data['amount'] ?? 0, 0, ',', '.').'</div>';
        $html .= '<div class="line"></div>';
        $html .= '<div>Status: LUNAS</div>';
        $html .= '<div class="center" style="margin-top:5mm;">Terima kasih</div>';

        $html .= '</body></html>';

        return $html;
    }
}
