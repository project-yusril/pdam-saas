@extends('admin.layouts.app')

@section('title', 'Detail Calon Pelanggan')

@section('content')
@php $s = $prospect->survey; @endphp

<a href="{{ route('admin.prospects.index') }}" style="color:#2563eb;">&larr; Kembali ke antrian</a>
<h1>{{ $prospect->full_name }} <small style="color:#6b7280;">({{ $prospect->registration_number }})</small></h1>

<div style="display:flex;gap:1.5rem;flex-wrap:wrap;">
    {{-- KOLOM KIRI: Data Calon Pelanggan --}}
    <div style="flex:1;min-width:320px;">
        <h2>Data Calon Pelanggan</h2>
        <table>
            <tbody>
                <tr><th style="text-align:left;">Status</th><td><span class="badge badge-blue">{{ $prospect->status }}</span></td></tr>
                <tr><th style="text-align:left;">NIK</th><td>{{ $prospect->nik ? '••••••••' . substr($prospect->nik, -4) : '-' }}</td></tr>
                <tr><th style="text-align:left;">Tempat/Tgl Lahir</th><td>{{ $prospect->birth_place ?? '-' }}, {{ $prospect->birth_date?->format('d/m/Y') ?? '-' }}</td></tr>
                <tr><th style="text-align:left;">Jenis Kelamin</th><td>{{ $prospect->gender === 'L' ? 'Laki-laki' : ($prospect->gender === 'P' ? 'Perempuan' : '-') }}</td></tr>
                <tr><th style="text-align:left;">Agama</th><td>{{ $prospect->religion ?? '-' }}</td></tr>
                <tr><th style="text-align:left;">Status Perkawinan</th><td>{{ $prospect->marital_status ?? '-' }}</td></tr>
                <tr><th style="text-align:left;">Pekerjaan</th><td>{{ $prospect->occupation ?? '-' }}</td></tr>
                <tr><th style="text-align:left;">Kewarganegaraan</th><td>{{ $prospect->nationality ?? '-' }}</td></tr>
                <tr><th style="text-align:left;">Alamat Pemasangan</th><td>{{ $prospect->installation_address }}</td></tr>
                <tr><th style="text-align:left;">RT/RW</th><td>{{ $prospect->rt ?? '-' }}/{{ $prospect->rw ?? '-' }}</td></tr>
                <tr><th style="text-align:left;">Telepon</th><td>{{ $prospect->phone ?? '-' }}</td></tr>
                <tr><th style="text-align:left;">Email</th><td>{{ $prospect->email ?? '-' }}</td></tr>
            </tbody>
        </table>

        @if($prospect->ktp_photo_url)
            <p><strong>Berkas KTP:</strong> {{ strtoupper($prospect->ktp_file_type ?? 'file') }} terunggah</p>
        @endif

        @if($prospect->installation_fee)
            <h3>Biaya Pemasangan</h3>
            <p style="font-size:1.25rem;font-weight:700;">Rp {{ number_format((float) $prospect->installation_fee, 0, ',', '.') }}</p>
            @if(is_array($prospect->installation_fee_breakdown))
                <table>
                    <thead><tr><th>Item</th><th style="text-align:right;">Jumlah</th></tr></thead>
                    <tbody>
                        @foreach($prospect->installation_fee_breakdown as $row)
                        <tr><td>{{ $row['item'] ?? '-' }}</td><td style="text-align:right;">Rp {{ number_format((float) ($row['amount'] ?? 0), 0, ',', '.') }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @endif
    </div>

    {{-- KOLOM KANAN: Laporan Survey --}}
    <div style="flex:1;min-width:320px;">
        <h2>Laporan Survey</h2>
        @if($s)
            <table>
                <tbody>
                    <tr><th style="text-align:left;">Rekomendasi</th>
                        <td>
                            @if($s->recommendation === 'feasible')
                                <span class="badge badge-green">Layak</span>
                            @else
                                <span class="badge badge-gray">Tidak Layak</span>
                            @endif
                        </td></tr>
                    <tr><th style="text-align:left;">Kondisi Bangunan</th><td>{{ $s->building_condition ?? '-' }}</td></tr>
                    <tr><th style="text-align:left;">Aksesibilitas</th><td>{{ $s->accessibility ?? '-' }}</td></tr>
                    <tr><th style="text-align:left;">Status Tanah</th><td>{{ $s->land_status ?? '-' }}</td></tr>
                    <tr><th style="text-align:left;">Jarak ke Pipa Utama</th><td>{{ $s->distance_to_main_pipe ?? '-' }} m</td></tr>
                    <tr><th style="text-align:left;">Estimasi Biaya</th><td>Rp {{ number_format((float) $s->estimated_cost, 0, ',', '.') }}</td></tr>
                    <tr><th style="text-align:left;">Catatan Surveyor</th><td>{{ $s->surveyor_notes ?? '-' }}</td></tr>
                    <tr><th style="text-align:left;">Koordinat (final)</th><td>{{ $s->latitude ?? '-' }}, {{ $s->longitude ?? '-' }} <small>({{ $s->location_source ?? 'n/a' }})</small></td></tr>
                </tbody>
            </table>

            {{-- Peta GPS: titik final surveyor --}}
            @if($s->latitude && $s->longitude)
                <h3>Lokasi (Peta)</h3>
                <div id="map" style="height:280px;border-radius:8px;"></div>
                <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
                <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        var lat = {{ $s->latitude }}, lng = {{ $s->longitude }};
                        var map = L.map('map').setView([lat, lng], 17);
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: '&copy; OpenStreetMap'
                        }).addTo(map);
                        L.marker([lat, lng]).addTo(map).bindPopup('Lokasi rumah (surveyor)').openPopup();
                    });
                </script>
            @endif

            {{-- Foto rumah (bukti kehadiran + bahan keputusan) --}}
            <h3>Foto Rumah ({{ is_array($s->photo_house_urls) ? count($s->photo_house_urls) : 0 }})</h3>
            <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
                @foreach(($s->photo_house_urls ?? []) as $url)
                    <a href="{{ $url }}" target="_blank"><img src="{{ $url }}" alt="foto rumah" style="width:140px;height:100px;object-fit:cover;border-radius:6px;border:1px solid #e5e7eb;"></a>
                @endforeach
            </div>

            {{-- Keputusan Kepala Survey --}}
            @if($prospect->status === 'survey_submitted')
                <h3 style="margin-top:1.5rem;">Keputusan Kepala Survey</h3>
                <p style="color:#6b7280;">
                    <strong>APPROVED</strong> = lokasi layak, lanjut biaya pemasangan.
                    <strong>REJECTED</strong> = tidak layak teknis (jarak/bangunan).
                    <strong>RE-SURVEY</strong> = laporan buruk (foto buram/data kurang), ambil ulang.
                </p>
                <p style="background:#eff6ff;padding:.75rem;border-radius:6px;color:#1e40af;">
                    Kirim keputusan via API: <code>POST /api/v1/survey-reports/{{ $s->id }}/review</code>
                    dengan <code>decision</code> = approved|rejected|re_survey (+ installation_fee &amp; breakdown bila approved).
                </p>
            @elseif($s->review_status)
                <p><strong>Keputusan:</strong> <span class="badge badge-blue">{{ $s->review_status }}</span> — {{ $s->review_notes ?? '' }}</p>
            @endif
        @else
            <p style="color:#9ca3af;">Belum ada laporan survey.</p>
        @endif
    </div>
</div>
@endsection
