@extends('admin.layouts.app')

@section('title', 'Dashboard Progress Baca Meter')

@section('content')
<a href="{{ route('admin.meter-routes.index') }}" style="color:#2563eb;">&larr; Kembali ke daftar rute</a>
<h1>Progress Baca Meter</h1>

<form method="GET" style="margin-bottom:1rem;">
    <label>Periode:
        <input type="month" name="period" value="{{ $period }}" onchange="this.form.submit()">
    </label>
</form>

@php
    $totalCustomers = $rows->sum('total');
    $totalRead = $rows->sum('read');
    $totalFlagged = $rows->sum('flagged');
    $overallPercent = $totalCustomers > 0 ? round($totalRead / $totalCustomers * 100, 1) : 0;
@endphp

<div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1.5rem;">
    <div style="flex:1;min-width:160px;padding:1rem;background:#eff6ff;border-radius:8px;">
        <div style="color:#6b7280;">Total Pelanggan</div>
        <div style="font-size:1.5rem;font-weight:700;">{{ number_format($totalCustomers, 0, ',', '.') }}</div>
    </div>
    <div style="flex:1;min-width:160px;padding:1rem;background:#ecfdf5;border-radius:8px;">
        <div style="color:#6b7280;">Sudah Terbaca</div>
        <div style="font-size:1.5rem;font-weight:700;">{{ number_format($totalRead, 0, ',', '.') }} ({{ $overallPercent }}%)</div>
    </div>
    <div style="flex:1;min-width:160px;padding:1rem;background:#fef2f2;border-radius:8px;">
        <div style="color:#6b7280;">Perlu Verifikasi (flag)</div>
        <div style="font-size:1.5rem;font-weight:700;">{{ number_format($totalFlagged, 0, ',', '.') }}</div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>Kode</th>
            <th>Rute</th>
            <th>Progress</th>
            <th>Terbaca</th>
            <th>Sisa</th>
            <th>Flag</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $r)
        <tr>
            <td><strong>{{ $r['code'] }}</strong></td>
            <td>{{ $r['name'] }}</td>
            <td style="min-width:200px;">
                <div style="background:#e5e7eb;border-radius:6px;height:18px;position:relative;">
                    <div style="background:#2563eb;height:18px;border-radius:6px;width:{{ $r['percent'] }}%;"></div>
                </div>
                <small>{{ $r['percent'] }}%</small>
            </td>
            <td>{{ $r['read'] }} / {{ $r['total'] }}</td>
            <td>{{ $r['remaining'] }}</td>
            <td>
                @if($r['flagged'] > 0)
                    <span class="badge badge-gray">{{ $r['flagged'] }}</span>
                @else
                    -
                @endif
            </td>
        </tr>
        @empty
        <tr><td colspan="6" style="text-align:center;color:#9ca3af;">Belum ada rute aktif.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
