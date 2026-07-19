@extends('admin.layouts.app')

@section('title', 'Rute Baca Meter')

@section('content')
<h1>Rute Baca Meter</h1>
<p style="color:#6b7280;">Kelola rute, assign jalan &amp; petugas tetap. Pelanggan otomatis dipetakan ke rute via jalan (street).</p>

<p><a href="{{ route('admin.meter-routes.dashboard') }}" class="btn btn-primary">Lihat Dashboard Progress</a></p>

<table>
    <thead>
        <tr>
            <th>Kode</th>
            <th>Nama Rute</th>
            <th>Wilayah</th>
            <th>Jalan</th>
            <th>Petugas</th>
            <th>Pelanggan</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        @forelse($routes as $route)
        <tr>
            <td><strong>{{ $route->code }}</strong></td>
            <td>{{ $route->name }}</td>
            <td>{{ $route->zone?->name ?? '-' }}</td>
            <td>{{ $route->streets_count }}</td>
            <td>{{ $route->assignments_count }}</td>
            <td>{{ $customersByRoute[$route->id] ?? 0 }}</td>
            <td>
                @if($route->is_active)
                    <span class="badge badge-green">Aktif</span>
                @else
                    <span class="badge badge-gray">Nonaktif</span>
                @endif
            </td>
        </tr>
        @empty
        <tr><td colspan="7" style="text-align:center;color:#9ca3af;">Belum ada rute baca meter.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
