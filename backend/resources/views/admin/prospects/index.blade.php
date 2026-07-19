@extends('admin.layouts.app')

@section('title', 'Pendaftaran & Survey')

@section('content')
<h1>Pendaftaran & Survey Calon Pelanggan</h1>

{{-- ANTRIAN 1: Menunggu Review Hublang (pending_review) --}}
<h2>Menunggu Review Hublang <span class="badge badge-blue">{{ $pendingReview->count() }}</span></h2>
<p style="color:#6b7280;">Verifikasi berkas & tugaskan surveyor.</p>
<table>
    <thead>
        <tr>
            <th>No. Registrasi</th>
            <th>Nama</th>
            <th>Alamat Pemasangan</th>
            <th>Telepon</th>
            <th>Tanggal Daftar</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @forelse($pendingReview as $p)
        <tr>
            <td><strong>{{ $p->registration_number }}</strong></td>
            <td>{{ $p->full_name }}</td>
            <td>{{ $p->installation_address }}</td>
            <td>{{ $p->phone ?? '-' }}</td>
            <td>{{ $p->created_at->format('d/m/Y H:i') }}</td>
            <td><a href="{{ route('admin.prospects.show', $p) }}" class="btn btn-primary">Detail</a></td>
        </tr>
        @empty
        <tr><td colspan="6" style="text-align:center;color:#9ca3af;">Tidak ada prospek menunggu review.</td></tr>
        @endforelse
    </tbody>
</table>

{{-- ANTRIAN 2: Menunggu Keputusan Kepala Survey (survey_submitted) --}}
<h2 style="margin-top:2rem;">Menunggu Keputusan Kepala Survey <span class="badge badge-blue">{{ $awaitingDecision->count() }}</span></h2>
<p style="color:#6b7280;">Tinjau foto rumah, GPS, & penilaian sebelum approve/reject/re-survey.</p>
<table>
    <thead>
        <tr>
            <th>No. Registrasi</th>
            <th>Nama</th>
            <th>Rekomendasi Surveyor</th>
            <th>Jarak Pipa (m)</th>
            <th>Foto</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @forelse($awaitingDecision as $p)
        <tr>
            <td><strong>{{ $p->registration_number }}</strong></td>
            <td>{{ $p->full_name }}</td>
            <td>
                @if($p->survey?->recommendation === 'feasible')
                    <span class="badge badge-green">Layak</span>
                @elseif($p->survey?->recommendation === 'not_feasible')
                    <span class="badge badge-gray">Tidak Layak</span>
                @else - @endif
            </td>
            <td>{{ $p->survey?->distance_to_main_pipe ?? '-' }}</td>
            <td>{{ is_array($p->survey?->photo_house_urls) ? count($p->survey->photo_house_urls) : 0 }} foto</td>
            <td><a href="{{ route('admin.prospects.show', $p) }}" class="btn btn-primary">Tinjau</a></td>
        </tr>
        @empty
        <tr><td colspan="6" style="text-align:center;color:#9ca3af;">Tidak ada laporan menunggu keputusan.</td></tr>
        @endforelse
    </tbody>
</table>

{{-- ANTRIAN 3: Sedang Disurvey / Perlu Survey Ulang --}}
<h2 style="margin-top:2rem;">Sedang Berjalan <span class="badge badge-gray">{{ $inProgress->count() }}</span></h2>
<table>
    <thead>
        <tr>
            <th>No. Registrasi</th>
            <th>Nama</th>
            <th>Status</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @forelse($inProgress as $p)
        <tr>
            <td><strong>{{ $p->registration_number }}</strong></td>
            <td>{{ $p->full_name }}</td>
            <td>
                @if($p->status === 'surveying')
                    <span class="badge badge-blue">Sedang Disurvey</span>
                @else
                    <span class="badge badge-gray">Perlu Survey Ulang</span>
                @endif
            </td>
            <td><a href="{{ route('admin.prospects.show', $p) }}" class="btn btn-primary">Detail</a></td>
        </tr>
        @empty
        <tr><td colspan="4" style="text-align:center;color:#9ca3af;">Tidak ada prospek berjalan.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
