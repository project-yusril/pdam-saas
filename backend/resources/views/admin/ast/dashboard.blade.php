@extends('admin.layouts.app')

@section('title', 'Dashboard Aset Tetap')

@section('content')
<h1>Aset Tetap & Penyusutan (AST)</h1>
<p style="color:#6b7280;">Ringkasan nilai perolehan vs nilai buku, komposisi per kategori & status.</p>

<div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1.5rem;">
    <div style="flex:1;min-width:180px;padding:1rem;background:#eff6ff;border-radius:8px;">
        <div style="color:#6b7280;">Jumlah Aset</div>
        <div style="font-size:1.5rem;font-weight:700;">{{ number_format($assetCount, 0, ',', '.') }}</div>
    </div>
    <div style="flex:1;min-width:180px;padding:1rem;background:#f0fdf4;border-radius:8px;">
        <div style="color:#6b7280;">Nilai Perolehan</div>
        <div style="font-size:1.3rem;font-weight:700;">Rp {{ number_format($totalCost, 0, ',', '.') }}</div>
    </div>
    <div style="flex:1;min-width:180px;padding:1rem;background:#fffbeb;border-radius:8px;">
        <div style="color:#6b7280;">Akumulasi Penyusutan</div>
        <div style="font-size:1.3rem;font-weight:700;">Rp {{ number_format($totalAccum, 0, ',', '.') }}</div>
    </div>
    <div style="flex:1;min-width:180px;padding:1rem;background:#eef2ff;border-radius:8px;">
        <div style="color:#6b7280;">Nilai Buku</div>
        <div style="font-size:1.3rem;font-weight:700;">Rp {{ number_format($totalBook, 0, ',', '.') }}</div>
    </div>
</div>

<div style="display:flex;gap:1.5rem;flex-wrap:wrap;">
    <div style="flex:2;min-width:340px;">
        <h3>Nilai per Kategori</h3>
        <table>
            <thead><tr><th>Kategori</th><th>Jml</th><th>Perolehan</th><th>Nilai Buku</th></tr></thead>
            <tbody>
                @forelse($byCategory as $row)
                <tr>
                    <td>{{ $row->category?->name ?? '-' }}</td>
                    <td>{{ $row->total }}</td>
                    <td>Rp {{ number_format($row->cost, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($row->book, 0, ',', '.') }}</td>
                </tr>
                @empty
                <tr><td colspan="4" style="color:#9ca3af;">Belum ada aset.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="flex:1;min-width:220px;">
        <h3>Status Aset</h3>
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
        <p style="margin-top:1rem;"><a href="{{ route('admin.ast.assets') }}">→ Lihat daftar aset</a></p>
    </div>
</div>
@endsection
