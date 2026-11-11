@extends('layouts.app')

@section('title', 'Absensi Hari Ini')

@push('styles')
<style>
.karyawan-thumb {
    width: 40px; height: 40px; border-radius: 50%;
    object-fit: cover; background: #f1f5f9; flex-shrink: 0;
}
.jam-badge {
    font-size: .78rem; font-variant-numeric: tabular-nums;
    background: #f1f5f9; padding: 2px 8px; border-radius: 6px; color: #475569;
}
.jam-badge.late        { background: #fef3c7; color: #92400e; }
.jam-badge.clock-out   { background: #dbeafe; color: #1e40af; }
.jam-badge.lembur-on   { background: #fce7f3; color: #9d174d; }
.jam-badge.lembur-done { background: #f3e8ff; color: #6b21a8; }
</style>
@endpush

@section('content')
<div class="container-fluid px-3 px-md-4">

    {{-- Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-2">
        <div>
            <h4 class="mb-1 fw-semibold">Absensi Hari Ini</h4>
            <p class="text-muted mb-0 small">
                {{ $tanggal->translatedFormat('l, d F Y') }}
                @if($cabang) — {{ $cabang->nama_cabang }} @endif
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if($cabang)
            @php $device = $cabang->absenDevices()->where('is_active', true)->first(); @endphp
            @if($device)
            <a href="{{ route('face-attendance.scan', ['token' => $device->device_token]) }}"
               target="_blank" class="btn btn-primary btn-sm">
                <i class="bi bi-camera-fill me-1"></i>Buka Scan Absen
            </a>
            @else
            @can('absensi.device')
            <a href="{{ route('absen-device.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-tablet me-1"></i>Setup Device
            </a>
            @endcan
            @endif
            @endif
            <a href="{{ route('face-attendance.log') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-list-ul me-1"></i>Lihat Log
            </a>
        </div>
    </div>

    {{-- Statistik Cards --}}
    @php $totalKaryawan = $statistics['hadir'] + $statistics['belum']; @endphp
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="h2 fw-bold text-dark mb-0">{{ $totalKaryawan }}</div>
                <div class="small text-muted">Total Karyawan</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="h2 fw-bold text-success mb-0">{{ $statistics['hadir'] }}</div>
                <div class="small text-muted">Sudah Hadir</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="h2 fw-bold text-warning mb-0">{{ $statistics['terlambat'] }}</div>
                <div class="small text-muted">Terlambat</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="h2 fw-bold text-secondary mb-0">{{ $statistics['belum'] }}</div>
                <div class="small text-muted">Belum Hadir</div>
            </div>
        </div>
    </div>

    {{-- Progress Bar --}}
    @if($totalKaryawan > 0)
    @php $persenHadir = round($statistics['hadir'] / $totalKaryawan * 100); @endphp
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-semibold small">Tingkat Kehadiran Hari Ini</span>
                <span class="fw-bold text-primary">{{ $persenHadir }}%</span>
            </div>
            <div class="progress" style="height:10px">
                <div class="progress-bar bg-success"
                     style="width:{{ max(0, $persenHadir - round($statistics['terlambat']/$totalKaryawan*100)) }}%"></div>
                @if($statistics['terlambat'] > 0)
                <div class="progress-bar bg-warning"
                     style="width:{{ round($statistics['terlambat']/$totalKaryawan*100) }}%"></div>
                @endif
            </div>
            <div class="d-flex gap-3 mt-2 small text-muted">
                <span><span class="text-success fw-semibold">●</span> Tepat waktu</span>
                <span><span class="text-warning fw-semibold">●</span> Terlambat</span>
                <span><span class="text-secondary fw-semibold">●</span> Belum hadir</span>
            </div>
        </div>
    </div>
    @endif

    {{-- Tabel Kehadiran --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h6 class="mb-0 fw-semibold">Daftar Kehadiran</h6>
            <div class="d-flex gap-2 flex-wrap align-items-center">
                {{-- Filter status --}}
                <div class="btn-group btn-group-sm" role="group" aria-label="Filter status">
                    @foreach(['all'=>'Semua','hadir'=>'Hadir','belum'=>'Belum','telat'=>'Telat'] as $val => $lbl)
                    <a href="{{ route('face-attendance.today', array_merge(request()->only(['cabang_id']), ['status'=>$val])) }}"
                       class="btn btn-outline-secondary {{ $statusFil === $val ? 'active' : '' }}">{{ $lbl }}</a>
                    @endforeach
                </div>
                <input type="text" class="form-control form-control-sm" id="searchKaryawan"
                       placeholder="Cari nama..." style="width:160px">
                <span class="badge bg-success-subtle text-success border border-success-subtle small">
                    <i class="bi bi-circle-fill" style="font-size:6px"></i> Live
                </span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle small" id="attendanceTable">
                    <thead class="table-light text-muted" style="font-size:.8rem">
                        <tr>
                            <th style="width:36px">#</th>
                            <th>Karyawan</th>
                            <th class="d-none d-md-table-cell">Jabatan</th>
                            <th>Masuk</th>
                            <th class="d-none d-sm-table-cell">Keluar</th>
                            <th class="d-none d-lg-table-cell">Lembur Masuk</th>
                            <th class="d-none d-lg-table-cell">Lembur Keluar</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="attendanceBody">
                        @forelse($karyawans as $karyawan)
                        @php
                            $abs        = $absensiToday->get($karyawan->id);
                            $masukRaw   = $abs ? $abs->getRawOriginal('jam_masuk')          : null;
                            $keluarRaw  = $abs ? $abs->getRawOriginal('jam_keluar')         : null;
                            $lemMasukR  = $abs ? $abs->getRawOriginal('jam_lembur_masuk')   : null;
                            $lemKeluarR = $abs ? $abs->getRawOriginal('jam_lembur_keluar')  : null;
                            $sudahHadir = $masukRaw !== null;
                            $terlambat  = $sudahHadir && substr($masukRaw, 0, 5) > $jamMasuk;
                            $adaLembur  = $lemMasukR !== null;
                        @endphp
                        <tr class="karyawan-row" data-nama="{{ strtolower($karyawan->nama_lengkap) }}">
                            <td class="text-muted">{{ $loop->iteration }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle flex-shrink-0"
                                         style="width:10px;height:10px;background:{{ $sudahHadir ? ($terlambat ? '#f59e0b' : '#22c55e') : '#e2e8f0' }}"></div>
                                    <img src="{{ $karyawan->foto ? url('/img/'.$karyawan->foto) : 'https://ui-avatars.com/api/?name='.urlencode($karyawan->nama_lengkap).'&background=f1f5f9&color=64748b&size=80' }}"
                                         alt="" class="karyawan-thumb d-none d-sm-block"
                                         onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name={{ urlencode(substr($karyawan->nama_lengkap,0,2)) }}&background=f1f5f9&color=64748b&size=80'">
                                    <div>
                                        <div class="fw-semibold" style="font-size:.85rem">{{ $karyawan->nama_lengkap }}</div>
                                        <div class="text-muted" style="font-size:.72rem">{{ $karyawan->nik }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="d-none d-md-table-cell text-muted">{{ $karyawan->jabatan }}</td>
                            <td>
                                @if($masukRaw)
                                <span class="jam-badge {{ $terlambat ? 'late' : '' }}">
                                    {{ substr($masukRaw, 0, 5) }}
                                    @if($terlambat)<i class="bi bi-clock ms-1" title="Terlambat"></i>@endif
                                </span>
                                @else
                                <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td class="d-none d-sm-table-cell">
                                @if($keluarRaw)
                                <span class="jam-badge clock-out">{{ substr($keluarRaw, 0, 5) }}</span>
                                @else
                                <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td class="d-none d-lg-table-cell">
                                @if($lemMasukR)
                                <span class="jam-badge lembur-on">{{ substr($lemMasukR, 0, 5) }}</span>
                                @else
                                <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td class="d-none d-lg-table-cell">
                                @if($lemKeluarR)
                                <span class="jam-badge lembur-done">{{ substr($lemKeluarR, 0, 5) }}</span>
                                @else
                                <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td>
                                @if(!$sudahHadir)
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle small">Belum</span>
                                @elseif($terlambat && $adaLembur)
                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle small">Telat+Lembur</span>
                                @elseif($terlambat)
                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle small">Terlambat</span>
                                @elseif($adaLembur)
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle small">Hadir+Lembur</span>
                                @else
                                <span class="badge bg-success-subtle text-success border border-success-subtle small">Hadir</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-people display-5 d-block mb-2 opacity-25"></i>
                                Tidak ada karyawan untuk filter yang dipilih.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3 small text-muted text-center">
        <i class="bi bi-arrow-repeat me-1"></i>Data diperbarui otomatis setiap 30 detik
        <span class="ms-2">Jam masuk: <strong>{{ $jamMasuk }}</strong></span>
    </div>
</div>
@endsection

@push('scripts')
<script>
setTimeout(() => location.reload(), 30000);
document.getElementById('searchKaryawan').addEventListener('input', function () {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.karyawan-row').forEach(row => {
        row.style.display = row.dataset.nama.includes(q) ? '' : 'none';
    });
});
</script>
@endpush
