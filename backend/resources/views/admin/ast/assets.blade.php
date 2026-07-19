@extends('admin.layouts.app')

@section('title', 'Daftar Aset Tetap')

@section('content')
<h1>Register Aset Tetap</h1>
<p style="color:#6b7280;">Daftar aset tetap PDAM (KIB). Klik kode untuk melihat kartu aset.</p>

<form method="GET" style="display:flex;gap:0.75rem;flex-wrap:wrap;margin-bottom:1rem;">
    <select name="asset_category_id">
        <option value="">— Semua Kategori —</option>
        @foreach($categories as $c)
        <option value="{{ $c->id }}" @selected(request('asset_category_id') == $c->id)>{{ $c->name }}</option>
        @endforeach
    </select>
    <select name="status">
        <option value="">— Semua Status —</option>
        @foreach(['aktif', 'dijual', 'dihapus', 'rusak'] as $s)
        <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
        @endforeach
    </select>
    <button type="submit">Filter</button>
</form>

<table>
    <thead>
        <tr><th>Kode</th><th>Nama</th><th>Kategori</th><th>Wilayah</th><th>Perolehan</th><th>Nilai Buku</th><th>Status</th></tr>
    </thead>
    <tbody>
        @forelse($assets as $a)
        <tr>
            <td><a href="{{ route('admin.ast.card', $a->id) }}"><strong>{{ $a->code }}</strong></a></td>
            <td>{{ $a->name }}</td>
            <td>{{ $a->category?->name ?? '-' }}</td>
            <td>{{ $a->zone?->name ?? '-' }}</td>
            <td>Rp {{ number_format($a->acquisition_cost, 0, ',', '.') }}</td>
            <td>Rp {{ number_format($a->book_value, 0, ',', '.') }}</td>
            <td><span class="badge badge-gray">{{ ucfirst($a->status) }}</span></td>
        </tr>
        @empty
        <tr><td colspan="7" style="text-align:center;color:#9ca3af;">Belum ada aset terdaftar.</td></tr>
        @endforelse
    </tbody>
</table>

<div style="margin-top:1rem;">{{ $assets->links() }}</div>
@endsection
