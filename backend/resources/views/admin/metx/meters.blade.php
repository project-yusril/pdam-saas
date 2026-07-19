@extends('admin.layouts.app')

@section('title', 'Master Meter Fisik')

@section('content')
<a href="{{ route('admin.metx.dashboard') }}" style="color:#2563eb;">&larr; Dashboard METX</a>
<h1>Master Meter Fisik</h1>

<form method="GET" style="margin-bottom:1rem;display:flex;gap:.75rem;flex-wrap:wrap;">
    <select name="status" onchange="this.form.submit()">
        <option value="">— Semua status —</option>
        @foreach(['gudang','terpasang','dicabut','afkir'] as $s)
            <option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst($s) }}</option>
        @endforeach
    </select>
    <select name="condition" onchange="this.form.submit()">
        <option value="">— Semua kondisi —</option>
        @foreach(['baru','baik','buram','macet','rusak'] as $c)
            <option value="{{ $c }}" @selected(request('condition')===$c)>{{ ucfirst($c) }}</option>
        @endforeach
    </select>
</form>

<table>
    <thead>
        <tr><th>Serial</th><th>Merk</th><th>Diameter</th><th>Kondisi</th><th>Status</th><th>Tamper</th><th>Pelanggan</th></tr>
    </thead>
    <tbody>
        @forelse($meters as $m)
        <tr>
            <td><strong>{{ $m->serial_number }}</strong></td>
            <td>{{ $m->brand ?? '-' }}</td>
            <td>{{ $m->diameter ?? '-' }}</td>
            <td>{{ ucfirst($m->condition) }}</td>
            <td>{{ ucfirst($m->status) }}</td>
            <td>
                @if($m->tamper_status !== 'normal')
                    <span class="badge badge-gray">{{ $m->tamper_status }}</span>
                @else - @endif
            </td>
            <td>{{ $m->customer?->customer_number ?? '-' }}</td>
        </tr>
        @empty
        <tr><td colspan="7" style="text-align:center;color:#9ca3af;">Belum ada meter.</td></tr>
        @endforelse
    </tbody>
</table>

<div style="margin-top:1rem;">{{ $meters->links() }}</div>
@endsection
