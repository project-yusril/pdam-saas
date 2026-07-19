@extends('admin.layouts.app')

@section('title', 'Manajemen Wilayah')

@section('content')
<h1>Manajemen Wilayah / Cabang</h1>

<table>
    <thead>
        <tr>
            <th>Kode</th>
            <th>Nama Wilayah</th>
            <th>Tipe</th>
            <th>Pelanggan</th>
            <th>Pegawai</th>
            <th>Gudang</th>
            <th>Status</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @forelse($zones as $zone)
        <tr>
            <td><strong>{{ $zone->code }}</strong></td>
            <td>
                {{ $zone->name }}
                @if($zone->is_main)
                    <span class="badge badge-blue">Utama</span>
                @endif
            </td>
            <td>{{ $zone->type ?? '-' }}</td>
            <td>{{ $zone->customers_count }}</td>
            <td>{{ $zone->employees_count }}</td>
            <td>
                @foreach($zone->warehouses as $wh)
                    <span class="badge badge-gray">{{ $wh->code }}</span>
                @endforeach
            </td>
            <td>
                @if($zone->is_active)
                    <span class="badge badge-green">Aktif</span>
                @else
                    <span class="badge badge-gray">Nonaktif</span>
                @endif
            </td>
            <td>
                <a href="{{ route('admin.zones.show', $zone) }}" class="btn btn-primary">Detail</a>
            </td>
        </tr>
        @empty
        <tr><td colspan="8" style="text-align:center;color:#9ca3af;">Belum ada wilayah.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
