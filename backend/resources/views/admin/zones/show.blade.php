@extends('admin.layouts.app')

@section('title', $zone->name . ' — Detail Wilayah')

@section('content')
<div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;">
    <a href="{{ route('admin.zones.index') }}" style="color:#6b7280;font-size:.9rem;">← Kembali</a>
    <h1 style="margin:0;">{{ $zone->name }}
        @if($zone->is_main)<span class="badge badge-blue">Kantor Utama</span>@endif
        @if($zone->is_active)
            <span class="badge badge-green">Aktif</span>
        @else
            <span class="badge badge-gray">Nonaktif</span>
        @endif
    </h1>
</div>

{{-- Ringkasan statistik --}}
<div class="stat-grid">
    <div class="stat">
        <div class="label">Pelanggan</div>
        <div class="value">{{ $zone->customers_count }}</div>
    </div>
    <div class="stat">
        <div class="label">Pegawai</div>
        <div class="value">{{ $zone->employees_count }}</div>
    </div>
    <div class="stat">
        <div class="label">Gudang Buffer</div>
        <div class="value">{{ $zone->warehouses->count() }}</div>
    </div>
    <div class="stat">
        <div class="label">Kode Wilayah</div>
        <div class="value" style="font-size:1rem;">{{ $zone->code }}</div>
    </div>
</div>

{{-- Info dasar --}}
<div class="card">
    <h2>Informasi Wilayah</h2>
    <table>
        <tbody>
            <tr><th>Kode</th><td>{{ $zone->code }}</td></tr>
            <tr><th>Nama</th><td>{{ $zone->name }}</td></tr>
            <tr><th>Alamat</th><td>{{ $zone->office_address ?? '-' }}</td></tr>
            <tr><th>Telepon</th><td>{{ $zone->office_phone ?? '-' }}</td></tr>
            <tr><th>Kantor Utama</th><td>{{ $zone->is_main ? 'Ya' : 'Tidak' }}</td></tr>
        </tbody>
    </table>
</div>

{{-- Gudang buffer --}}
<div class="card">
    <h2>Gudang Buffer</h2>
    @if($zone->warehouses->isEmpty())
        <p style="color:#9ca3af;">Belum ada gudang terdaftar.</p>
    @else
    <table>
        <thead>
            <tr><th>Kode</th><th>Nama</th><th>Tipe</th><th>Status</th></tr>
        </thead>
        <tbody>
            @foreach($zone->warehouses as $wh)
            <tr>
                <td>{{ $wh->code }}</td>
                <td>{{ $wh->name }}</td>
                <td>{{ $wh->warehouse_type ?? '-' }}</td>
                <td>
                    @if($wh->is_active)
                        <span class="badge badge-green">Aktif</span>
                    @else
                        <span class="badge badge-gray">Nonaktif</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
</div>

{{-- Pegawai --}}
<div class="card">
    <h2>Pegawai di Wilayah Ini</h2>
    @if($zone->employees->isEmpty())
        <p style="color:#9ca3af;">Belum ada pegawai ditugaskan.</p>
    @else
    <table>
        <thead>
            <tr><th>Nama</th><th>Email</th><th>Telepon</th><th>Status</th></tr>
        </thead>
        <tbody>
            @foreach($zone->employees as $emp)
            <tr>
                <td>{{ $emp->name }}</td>
                <td>{{ $emp->email }}</td>
                <td>{{ $emp->phone ?? '-' }}</td>
                <td>
                    @if($emp->is_active)
                        <span class="badge badge-green">Aktif</span>
                    @else
                        <span class="badge badge-gray">Nonaktif</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
</div>
@endsection
