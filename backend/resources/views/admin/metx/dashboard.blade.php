@extends('admin.layouts.app')

@section('title', 'Dashboard Meter Analytics')

@section('content')
<h1>Meter Analytics (METX)</h1>
<p style="color:#6b7280;">Ringkasan kondisi meter, anomali konsumsi/manipulasi, dan rekomendasi ganti meter.</p>

<div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1.5rem;">
    <div style="flex:1;min-width:150px;padding:1rem;background:#eff6ff;border-radius:8px;">
        <div style="color:#6b7280;">Total Meter</div>
        <div style="font-size:1.5rem;font-weight:700;">{{ number_format($totalMeters, 0, ',', '.') }}</div>
    </div>
    <div style="flex:1;min-width:150px;padding:1rem;background:#fef2f2;border-radius:8px;">
        <div style="color:#6b7280;">Indikasi Manipulasi</div>
        <div style="font-size:1.5rem;font-weight:700;">{{ number_format($tampered, 0, ',', '.') }}</div>
    </div>
    <div style="flex:1;min-width:150px;padding:1rem;background:#fffbeb;border-radius:8px;">
        <div style="color:#6b7280;">Anomali High (open)</div>
        <div style="font-size:1.5rem;font-weight:700;">{{ number_format($openHigh, 0, ',', '.') }}</div>
    </div>
    <div style="flex:1;min-width:150px;padding:1rem;background:#f0fdf4;border-radius:8px;">
        <div style="color:#6b7280;">Rekomendasi Ganti</div>
        <div style="font-size:1.5rem;font-weight:700;">{{ number_format($recommendations->count(), 0, ',', '.') }}</div>
    </div>
</div>

<div style="display:flex;gap:1.5rem;flex-wrap:wrap;">
    <div style="flex:1;min-width:260px;">
        <h3>Kondisi Meter</h3>
        <table>
            <thead><tr><th>Kondisi</th><th>Jumlah</th></tr></thead>
            <tbody>
                @forelse($byCondition as $cond => $total)
                <tr><td>{{ ucfirst($cond) }}</td><td>{{ $total }}</td></tr>
                @empty
                <tr><td colspan="2" style="color:#9ca3af;">Belum ada data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="flex:1;min-width:260px;">
        <h3>Status Meter</h3>
        <table>
            <thead><tr><th>Status</th><th>Jumlah</th></tr></thead>
            <tbody>
                @forelse($byStatus as $st => $total)
                <tr><td>{{ ucfirst($st) }}</td><td>{{ $total }}</td></tr>
                @empty
                <tr><td colspan="2" style="color:#9ca3af;">Belum ada data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="flex:1;min-width:260px;">
        <h3>Anomali per Rule</h3>
        <table>
            <thead><tr><th>Rule</th><th>Jumlah</th></tr></thead>
            <tbody>
                @forelse($anomaliesByRule as $rule => $total)
                <tr><td>{{ $rule }}</td><td>{{ $total }}</td></tr>
                @empty
                <tr><td colspan="2" style="color:#9ca3af;">Belum ada anomali.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<h3 style="margin-top:1.5rem;">Rekomendasi Ganti Meter (umur &gt; {{ $ageThreshold }} th / kondisi buruk / manipulasi)</h3>
<table>
    <thead>
        <tr><th>Serial</th><th>Pelanggan</th><th>Umur (th)</th><th>Kondisi</th><th>Tamper</th></tr>
    </thead>
    <tbody>
        @forelse($recommendations as $m)
        <tr>
            <td><strong>{{ $m->serial_number }}</strong></td>
            <td>{{ $m->customer?->customer_number ?? '-' }}</td>
            <td>{{ $m->ageInYears() ?? '-' }}</td>
            <td>{{ ucfirst($m->condition) }}</td>
            <td>
                @if($m->tamper_status !== 'normal')
                    <span class="badge badge-gray">{{ $m->tamper_status }}</span>
                @else - @endif
            </td>
        </tr>
        @empty
        <tr><td colspan="5" style="text-align:center;color:#9ca3af;">Tidak ada meter yang perlu diganti.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
