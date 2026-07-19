@extends('admin.layouts.app')

@section('title', 'Kartu Aset ' . $fixedAsset->code)

@section('content')
<p style="margin-bottom:0.5rem;"><a href="{{ route('admin.ast.assets') }}">← Kembali ke daftar aset</a></p>
<h1>{{ $fixedAsset->name }}</h1>
<p style="color:#6b7280;">Kartu Inventaris Barang (KIB) — <strong>{{ $fixedAsset->code }}</strong></p>

<div style="display:flex;gap:1.5rem;flex-wrap:wrap;margin-bottom:1.5rem;">
    <table style="flex:1;min-width:320px;">
        <tbody>
            <tr><th style="width:45%;">Kategori</th><td>{{ $fixedAsset->category?->name ?? '-' }}</td></tr>
            <tr><th>Wilayah</th><td>{{ $fixedAsset->zone?->name ?? '-' }}</td></tr>
            <tr><th>Lokasi</th><td>{{ $fixedAsset->location_note ?? '-' }}</td></tr>
            <tr><th>Tgl Perolehan</th><td>{{ $fixedAsset->acquisition_date?->format('d/m/Y') }}</td></tr>
            <tr><th>Sumber</th><td>{{ ucfirst($fixedAsset->source) }}</td></tr>
            <tr><th>Status</th><td><span class="badge badge-gray">{{ ucfirst($fixedAsset->status) }}</span></td></tr>
        </tbody>
    </table>
    <table style="flex:1;min-width:320px;">
        <tbody>
            <tr><th style="width:45%;">Nilai Perolehan</th><td>Rp {{ number_format($fixedAsset->acquisition_cost, 0, ',', '.') }}</td></tr>
            <tr><th>Nilai Residu</th><td>Rp {{ number_format($fixedAsset->residual_value, 0, ',', '.') }}</td></tr>
            <tr><th>Metode</th><td>{{ $fixedAsset->depreciation_method === 'declining_balance' ? 'Saldo Menurun (' . rtrim(rtrim($fixedAsset->declining_rate, '0'), '.') . '%/th)' : 'Garis Lurus' }}</td></tr>
            <tr><th>Masa Manfaat</th><td>{{ $fixedAsset->useful_life_months }} bln</td></tr>
            <tr><th>Akumulasi Penyusutan</th><td>Rp {{ number_format($fixedAsset->accumulated_depreciation, 0, ',', '.') }}</td></tr>
            <tr><th>Nilai Buku</th><td><strong>Rp {{ number_format($fixedAsset->book_value, 0, ',', '.') }}</strong></td></tr>
        </tbody>
    </table>
</div>

<h3>Histori Penyusutan</h3>
<table>
    <thead><tr><th>Periode</th><th>Beban Penyusutan</th><th>Akumulasi</th><th>Nilai Buku</th></tr></thead>
    <tbody>
        @forelse($history as $h)
        <tr>
            <td>{{ $h->period }}</td>
            <td>Rp {{ number_format($h->depreciation_amount, 0, ',', '.') }}</td>
            <td>Rp {{ number_format($h->accumulated_after, 0, ',', '.') }}</td>
            <td>Rp {{ number_format($h->book_value_after, 0, ',', '.') }}</td>
        </tr>
        @empty
        <tr><td colspan="4" style="text-align:center;color:#9ca3af;">Belum ada penyusutan tercatat.</td></tr>
        @endforelse
    </tbody>
</table>

<h3 style="margin-top:1.5rem;">Histori Mutasi</h3>
<table>
    <thead><tr><th>Tanggal</th><th>Dari</th><th>Ke</th><th>Alasan</th></tr></thead>
    <tbody>
        @forelse($movements as $m)
        <tr>
            <td>{{ $m->moved_at?->format('d/m/Y') }}</td>
            <td>{{ $m->from_location ?? '-' }}</td>
            <td>{{ $m->to_location ?? '-' }}</td>
            <td>{{ $m->reason ?? '-' }}</td>
        </tr>
        @empty
        <tr><td colspan="4" style="text-align:center;color:#9ca3af;">Belum ada mutasi.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
