@extends('layouts.app')

@section('title', 'Absensi Saya')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0 fw-bold"><i class="bi bi-person-check me-2 text-primary"></i>Absensi Saya</h4>
        <small class="text-muted">Riwayat kehadiran pribadi — hanya bisa dilihat, tidak bisa diubah</small>
    </div>
    <x-panduan-button slug="absensi-saya" />
</div>

@if(!$karyawan)
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle me-2"></i>
    Akun Anda tidak terhubung ke data karyawan. Fitur ini hanya untuk karyawan yang memiliki akun.
    Hubungi administrator untuk menghubungkan akun Anda.
</div>
@else

{{-- Filter Tanggal --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="d-flex flex-wrap align-items-end gap-2">
            <div>
                <label class="form-label form-label-sm mb-1">Dari</label>
                <input type="date" name="dari" class="form-control form-control-sm"
                       value="{{ $dari->format('Y-m-d') }}" style="width:auto">
            </div>
            <div>
                <label class="form-label form-label-sm mb-1">Sampai</label>
                <input type="date" name="sampai" class="form-control form-control-sm"
                       value="{{ $sampai->format('Y-m-d') }}" style="width:auto">
            </div>
            <button type="submit" class="btn btn-secondary btn-sm">
                <i class="bi bi-search me-1"></i>Filter
            </button>
            {{-- Quick filters --}}
            @php
                $qs = fn($d,$s) => '?dari='.$d.'&sampai='.$s;
                $bln = now(); $prev = now()->subMonth();
            @endphp
            <div class="d-flex gap-1 flex-wrap ms-auto">
                <a href="{{ $qs(now()->format('Y-m-d'), now()->format('Y-m-d')) }}"
                   class="btn btn-outline-secondary btn-sm" style="font-size:.75rem">Hari Ini</a>
                <a href="{{ $qs(now()->startOfWeek()->format('Y-m-d'), now()->endOfWeek()->format('Y-m-d')) }}"
                   class="btn btn-outline-secondary btn-sm" style="font-size:.75rem">Minggu Ini</a>
                <a href="{{ $qs(now()->startOfMonth()->format('Y-m-d'), now()->endOfMonth()->format('Y-m-d')) }}"
                   class="btn btn-outline-secondary btn-sm" style="font-size:.75rem">Bulan Ini</a>
                <a href="{{ $qs(now()->subMonth()->startOfMonth()->format('Y-m-d'), now()->subMonth()->endOfMonth()->format('Y-m-d')) }}"
                   class="btn btn-outline-secondary btn-sm" style="font-size:.75rem">Bulan Lalu</a>
            </div>
        </form>
    </div>
</div>

{{-- Profil Karyawan --}}
<div class="card mb-3 border-0 bg-primary bg-opacity-10">
    <div class="card-body py-3">
        <div class="d-flex align-items-center gap-3">
            @if($karyawan->foto)
            <img src="{{ url('/img/'.$karyawan->foto) }}" class="rounded-circle"
                 style="width:52px;height:52px;object-fit:cover" alt="{{ $karyawan->nama_lengkap }}">
            @else
            <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center"
                 style="width:52px;height:52px">
                <i class="bi bi-person text-white fs-4"></i>
            </div>
            @endif
            <div>
                <div class="fw-bold">{{ $karyawan->nama_lengkap }}</div>
                <small class="text-muted">{{ $karyawan->jabatan }} &bull; {{ $karyawan->cabang?->nama_cabang ?? '-' }}</small>
            </div>
        </div>
    </div>
</div>

{{-- Statistik Mini --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card text-center h-100">
            <div class="card-body py-2">
                <div class="fs-4 fw-bold text-success">{{ $stats['hadir'] }}</div>
                <small class="text-muted">Hari Hadir</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center h-100">
            <div class="card-body py-2">
                <div class="fs-4 fw-bold text-warning">{{ $stats['izin'] + $stats['sakit'] }}</div>
                <small class="text-muted">Izin / Sakit</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center h-100">
            <div class="card-body py-2">
                <div class="fs-4 fw-bold text-danger">{{ $stats['alpha'] }}</div>
                <small class="text-muted">Alpha</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center h-100">
            <div class="card-body py-2">
                <div class="fs-4 fw-bold text-primary">{{ $stats['jam_lembur'] }}</div>
                <small class="text-muted">Jam Lembur</small>
            </div>
        </div>
    </div>
</div>

{{-- Tabel Absensi --}}
<div class="card">
    <div class="card-header fw-semibold small d-flex justify-content-between">
        <span><i class="bi bi-calendar3 me-1"></i>Riwayat Absensi</span>
        <span class="text-muted">{{ $dari->format('d/m/Y') }} – {{ $sampai->format('d/m/Y') }}</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <th>Tanggal</th>
                    <th>Status</th>
                    <th class="d-none d-md-table-cell">Masuk</th>
                    <th class="d-none d-md-table-cell">Keluar</th>
                    <th class="d-none d-md-table-cell">Lembur</th>
                    <th class="d-none d-sm-table-cell">Total Jam</th>
                    <th class="d-none d-lg-table-cell">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($absensis as $abs)
                @php
                    $statusColor = match($abs->status) {
                        'hadir'  => 'text-bg-success',
                        'izin'   => 'text-bg-info',
                        'sakit'  => 'text-bg-warning',
                        'alpha'  => 'text-bg-danger',
                        'libur'  => 'text-bg-secondary',
                        'cuti'   => 'text-bg-primary',
                        default  => 'text-bg-secondary',
                    };
                    $durasi = '';
                    if ($abs->jam_masuk && $abs->jam_keluar) {
                        $masuk  = \Carbon\Carbon::parse($abs->jam_masuk);
                        $keluar = \Carbon\Carbon::parse($abs->jam_keluar);
                        if ($keluar->gt($masuk)) {
                            $menit  = $masuk->diffInMinutes($keluar);
                            $durasi = floor($menit/60) . 'j ' . ($menit%60) . 'm';
                        }
                    }
                @endphp
                <tr>
                    <td>
                        <div class="fw-medium small">{{ $abs->tanggal->format('d/m/Y') }}</div>
                        <div class="text-muted" style="font-size:.7rem">{{ $abs->tanggal->translatedFormat('D') }}</div>
                    </td>
                    <td>
                        <span class="badge {{ $statusColor }}" style="font-size:.7rem">
                            {{ ucfirst($abs->status) }}
                        </span>
                        @if($abs->dicatat_oleh)
                        <span class="badge bg-secondary-subtle text-secondary border" style="font-size:.6rem">Manual</span>
                        @endif
                    </td>
                    <td class="d-none d-md-table-cell small">
                        {{ $abs->jam_masuk ? $abs->jam_masuk->format('H:i') : '—' }}
                    </td>
                    <td class="d-none d-md-table-cell small">
                        {{ $abs->jam_keluar ? $abs->jam_keluar->format('H:i') : '—' }}
                    </td>
                    <td class="d-none d-md-table-cell small">
                        @if($abs->jam_lembur_masuk || $abs->jam_lembur_keluar)
                        <span class="text-warning">
                            {{ $abs->jam_lembur_masuk?->format('H:i') ?? '?' }}
                            –
                            {{ $abs->jam_lembur_keluar?->format('H:i') ?? '?' }}
                        </span>
                        @else
                        <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="d-none d-sm-table-cell small">
                        {{ $durasi ?: ($abs->jam_lembur ? number_format((float)$abs->jam_lembur,1).' jam lembur' : '—') }}
                    </td>
                    <td class="d-none d-lg-table-cell small text-muted">{{ $abs->keterangan ?? '—' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">
                        <i class="bi bi-calendar-x display-6 d-block mb-2 opacity-25"></i>
                        Tidak ada data absensi pada periode ini.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endif
@endsection
