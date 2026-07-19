@extends('admin.layouts.app')

@section('title', 'Anomali Meter')

@section('content')
<a href="{{ route('admin.metx.dashboard') }}" style="color:#2563eb;">&larr; Dashboard METX</a>
<h1>Anomali Meter</h1>
<p style="color:#6b7280;">Hasil deteksi rule-based konsumsi &amp; indikasi manipulasi. Tinjau &amp; tindak lanjut.</p>

<form method="GET" style="margin-bottom:1rem;display:flex;gap:.75rem;flex-wrap:wrap;">
    <select name="status" onchange="this.form.submit()">
        <option value="">— Semua status —</option>
        @foreach(['open','reviewing','confirmed','dismissed'] as $s)
            <option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst($s) }}</option>
        @endforeach
    </select>
    <select name="rule_code" onchange="this.form.submit()">
        <option value="">— Semua rule —</option>
        @foreach(['spike','drop','zero_streak','repeated_estimate','permanent_drop','category_mismatch'] as $r)
            <option value="{{ $r }}" @selected(request('rule_code')===$r)>{{ $r }}</option>
        @endforeach
    </select>
</form>

<table>
    <thead>
        <tr><th>Periode</th><th>Pelanggan</th><th>Rule</th><th>Severity</th><th>Ekspektasi</th><th>Aktual</th><th>Status</th></tr>
    </thead>
    <tbody>
        @forelse($anomalies as $a)
        <tr>
            <td>{{ $a->period }}</td>
            <td>{{ $a->customer?->customer_number ?? '-' }}<br><small style="color:#9ca3af;">{{ $a->customer?->full_name }}</small></td>
            <td>{{ $a->rule_code }}</td>
            <td>
                @php $color = ['high'=>'#dc2626','medium'=>'#d97706','low'=>'#6b7280'][$a->severity] ?? '#6b7280'; @endphp
                <span style="color:{{ $color }};font-weight:600;">{{ ucfirst($a->severity) }}</span>
            </td>
            <td>{{ $a->expected_value !== null ? number_format($a->expected_value, 0) : '-' }}</td>
            <td>{{ $a->actual_value !== null ? number_format($a->actual_value, 0) : '-' }}</td>
            <td><span class="badge badge-gray">{{ ucfirst($a->status) }}</span></td>
        </tr>
        @empty
        <tr><td colspan="7" style="text-align:center;color:#9ca3af;">Tidak ada anomali.</td></tr>
        @endforelse
    </tbody>
</table>

<div style="margin-top:1rem;">{{ $anomalies->links() }}</div>
@endsection
