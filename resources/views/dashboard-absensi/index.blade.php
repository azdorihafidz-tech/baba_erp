@extends('layouts.app')

@section('title', 'Dashboard Absensi')

@push('styles')
<style>
.stat-card { border-radius: 12px; border: none; }
.stat-card .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0; }
.status-badge { font-size: .72rem; padding: 3px 8px; border-radius: 20px; font-weight: 600; }
.status-hadir     { background:#dcfce7; color:#16a34a; }
.status-telat      { background:#fef9c3; color:#b45309; }
.status-belum      { background:#fee2e2; color:#b91c1c; }
.status-kerja      { background:#dbeafe; color:#1d4ed8; }
.status-lembur     { background:#ede9fe; color:#7c3aed; }
.status-tdk_hadir  { background:#f1f5f9; color:#64748b; }
.karyawan-row:hover { background:#f8fafc; }
.overview-card { border-radius: 10px; }
.progress-sm { height: 6px; border-radius: 3px; }
</style>
@endpush

@section('content')
@php
$statusBadge = function(string $status, $absensi): array {
    return match($status) {
        'hadir_tepat'   => ['status-hadir',    '✅ Hadir Tepat'],
        'telat'         => ['status-telat',    '⚠️ Telat ' . ($absensi?->menit_telat ?? 0) . ' mnt'],
        'sedang_kerja'  => ['status-kerja',    '🟦 Sedang Bekerja'],
        'sedang_lembur' => ['status-lembur',   '🟪 Sedang Lembur'],
        'belum_masuk'   => ['status-belum',    '🔴 Belum Masuk'],
        'tidak_hadir'   => ['status-tdk_hadir','⚫ Tidak Hadir'],
        default         => ['status-tdk_hadir', ucfirst($status)],
    };
};
@endphp
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0 fw-bold"><i class="bi bi-graph-up me-2 text-primary"></i>Dashboard Absensi</h4>
        <small class="text-muted">
            {{ $tanggal->translatedFormat('l, d F Y') }}
            @if($cabang) &bull; {{ $cabang->nama_cabang }} @endif
        </small>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <button class="btn btn-sm btn-outline-secondary" onclick="location.reload()">
            <i class="bi bi-arrow-clockwise me-1"></i>Refresh
        </button>
        <x-panduan-button slug="dashboard-absensi" />
    </div>
</div>

{{-- Filter --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
            @if(auth()->user()->canAccessAllBranches())
            <div>
                <label class="form-label form-label-sm mb-1">Cabang</label>
                <select name="cabang_id" class="form-select form-select-sm" style="width:auto">
                    @foreach($cabangs as $c)
                    <option value="{{ $c->id }}" {{ $cabangId == $c->id ? 'selected' : '' }}>
                        {{ $c->nama_cabang }}
                    </option>
                    @endforeach
                </select>
            </div>
            @endif
            <div>
                <label class="form-label form-label-sm mb-1">Tanggal</label>
                <input type="date" name="tanggal" class="form-control form-control-sm"
                       style="width:auto" value="{{ $tanggal->format('Y-m-d') }}">
            </div>
            <div>
                <label class="form-label form-label-sm mb-1">Status</label>
                <select name="status_filter" class="form-select form-select-sm" style="width:auto">
                    <option value="">Semua</option>
                    <option value="hadir_tepat" {{ $filterStatus === 'hadir_tepat' ? 'selected' : '' }}>Hadir Tepat</option>
                    <option value="telat" {{ $filterStatus === 'telat' ? 'selected' : '' }}>Telat</option>
                    <option value="sedang_kerja" {{ $filterStatus === 'sedang_kerja' ? 'selected' : '' }}>Sedang Bekerja</option>
                    <option value="sedang_lembur" {{ $filterStatus === 'sedang_lembur' ? 'selected' : '' }}>Sedang Lembur</option>
                    <option value="belum_masuk" {{ $filterStatus === 'belum_masuk' ? 'selected' : '' }}>Belum Masuk</option>
                    <option value="tidak_hadir" {{ $filterStatus === 'tidak_hadir' ? 'selected' : '' }}>Tidak Hadir</option>
                </select>
            </div>
            <button type="submit" class="btn btn-secondary btn-sm">
                <i class="bi bi-search me-1"></i>Tampilkan
            </button>
            @if($tanggal->isToday())
            <span class="ms-auto badge bg-success-subtle text-success border border-success-subtle align-self-center">
                <i class="bi bi-circle-fill me-1" style="font-size:.5rem;animation:pulse 2s infinite"></i>Live
            </span>
            @endif
        </form>
    </div>
</div>

{{-- Statistik 4 Card --}}
<div class="row g-3 mb-3">
    @php
        $statCards = [
            ['label'=>'Total Hadir',    'val'=>$stats['hadir'],       'color'=>'success', 'icon'=>'bi-person-check-fill'],
            ['label'=>'Telat',          'val'=>$stats['telat'],        'color'=>'warning',  'icon'=>'bi-clock-fill'],
            ['label'=>'Belum Masuk',    'val'=>$stats['belum_masuk'], 'color'=>'danger',  'icon'=>'bi-person-x-fill'],
            ['label'=>'Sedang Lembur',  'val'=>$stats['sedang_lembur'],'color'=>'purple',  'icon'=>'bi-moon-stars-fill'],
        ];
    @endphp
    @foreach($statCards as $sc)
    <div class="col-6 col-lg-3">
        <div class="card stat-card shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="stat-icon bg-{{ $sc['color'] === 'purple' ? 'secondary' : $sc['color'] }}-subtle text-{{ $sc['color'] === 'purple' ? 'secondary' : $sc['color'] }}">
                    <i class="bi {{ $sc['icon'] }}"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold lh-1">{{ $sc['val'] }}</div>
                    <div class="text-muted small">{{ $sc['label'] }}</div>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- Tabel Karyawan --}}
<div class="card mb-4">
    <div class="card-header fw-semibold d-flex justify-content-between small">
        <span><i class="bi bi-people me-1"></i>Status Kehadiran Karyawan</span>
        <span class="text-muted">{{ $rows->count() }} karyawan</span>
    </div>

    {{-- Desktop --}}
    <div class="d-none d-lg-block table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light text-muted" style="font-size:.78rem">
                <tr>
                    <th style="width:44px"></th>
                    <th>Nama</th>
                    <th>Jabatan</th>
                    <th>Shift</th>
                    <th>Masuk</th>
                    <th>Keluar</th>
                    <th>Lembur</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                @php [$badgeCls, $badgeLbl] = $statusBadge($row['status'], $row['absensi']); @endphp
                <tr class="karyawan-row">
                    <td class="text-center">
                        @if($row['karyawan']->foto)
                        <img src="{{ url('/img/'.$row['karyawan']->foto) }}"
                             class="rounded-circle" style="width:34px;height:34px;object-fit:cover"
                             alt="{{ $row['karyawan']->nama_lengkap }}">
                        @else
                        <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center mx-auto"
                             style="width:34px;height:34px">
                            <i class="bi bi-person text-white"></i>
                        </div>
                        @endif
                    </td>
                    <td class="fw-semibold">{{ $row['karyawan']->nama_lengkap }}</td>
                    <td class="text-muted">{{ $row['karyawan']->jabatan }}</td>
                    <td class="text-muted">
                        @if($row['karyawan']->shift)
                        {{ $row['karyawan']->shift->nama_shift }}
                        <div style="font-size:.7rem">{{ substr($row['karyawan']->shift->jam_masuk,0,5) }}–{{ substr($row['karyawan']->shift->jam_keluar,0,5) }}</div>
                        @else
                        <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="fw-medium" style="font-variant-numeric:tabular-nums">
                        {{ $row['absensi']?->jam_masuk ? $row['absensi']->jam_masuk->format('H:i') : '—' }}
                    </td>
                    <td style="font-variant-numeric:tabular-nums">
                        {{ $row['absensi']?->jam_keluar ? $row['absensi']->jam_keluar->format('H:i') : '—' }}
                    </td>
                    <td style="font-variant-numeric:tabular-nums">
                        @if($row['absensi']?->jam_lembur_masuk)
                        {{ $row['absensi']->jam_lembur_masuk->format('H:i') }}
                        @if($row['absensi']->jam_lembur_keluar) – {{ $row['absensi']->jam_lembur_keluar->format('H:i') }} @endif
                        @else
                        <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        <span class="status-badge {{ $badgeCls }}">{{ $badgeLbl }}</span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-4 text-muted">
                        <i class="bi bi-people display-6 d-block mb-2 opacity-25"></i>
                        Tidak ada karyawan aktif di cabang ini.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Mobile card view --}}
    <div class="d-lg-none">
        @forelse($rows as $row)
        @php [$badgeCls, $badgeLbl] = $statusBadge($row['status'], $row['absensi']); @endphp
        <div class="d-flex align-items-center gap-3 px-3 py-2 border-bottom karyawan-row">
            <div style="flex-shrink:0">
                @if($row['karyawan']->foto)
                <img src="{{ url('/img/'.$row['karyawan']->foto) }}"
                     class="rounded-circle" style="width:40px;height:40px;object-fit:cover">
                @else
                <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center"
                     style="width:40px;height:40px"><i class="bi bi-person text-white"></i></div>
                @endif
            </div>
            <div class="flex-grow-1 min-w-0">
                <div class="fw-semibold small">{{ $row['karyawan']->nama_lengkap }}</div>
                <div class="text-muted" style="font-size:.72rem">{{ $row['karyawan']->jabatan }}</div>
                @if($row['absensi']?->jam_masuk)
                <div style="font-size:.72rem" class="text-muted">
                    Masuk: <span class="fw-medium text-dark">{{ $row['absensi']->jam_masuk->format('H:i') }}</span>
                    @if($row['absensi']->jam_keluar)
                    &bull; Keluar: {{ $row['absensi']->jam_keluar->format('H:i') }}
                    @endif
                </div>
                @endif
            </div>
            <div><span class="status-badge {{ $badgeCls }}">{{ $badgeLbl }}</span></div>
        </div>
        @empty
        <div class="text-center py-4 text-muted small p-3">Tidak ada karyawan aktif.</div>
        @endforelse
    </div>
</div>

{{-- Overview Per Cabang (Owner only) --}}
@if(auth()->user()->canAccessAllBranches() && count($overviewCabang) > 0)
<div class="mb-3">
    <h6 class="fw-bold mb-2"><i class="bi bi-bar-chart me-1"></i>Overview Semua Cabang — {{ $tanggal->format('d/m/Y') }}</h6>
    <div class="row g-3">
        @foreach($overviewCabang as $ov)
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card overview-card h-100">
                <div class="card-body py-2 px-3">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <div class="fw-semibold small">{{ $ov['cabang']->nama_cabang }}</div>
                        <span class="badge {{ $ov['persen'] >= 80 ? 'text-bg-success' : ($ov['persen'] >= 50 ? 'text-bg-warning' : 'text-bg-danger') }}" style="font-size:.72rem">
                            {{ $ov['persen'] }}%
                        </span>
                    </div>
                    <div class="progress progress-sm mb-1">
                        <div class="progress-bar {{ $ov['persen'] >= 80 ? 'bg-success' : ($ov['persen'] >= 50 ? 'bg-warning' : 'bg-danger') }}"
                             style="width:{{ $ov['persen'] }}%"></div>
                    </div>
                    <div class="d-flex gap-3" style="font-size:.72rem">
                        <span class="text-success"><i class="bi bi-check-circle me-1"></i>{{ $ov['hadir'] }} hadir</span>
                        @if($ov['telat'] > 0)
                        <span class="text-warning"><i class="bi bi-clock me-1"></i>{{ $ov['telat'] }} telat</span>
                        @endif
                        <span class="text-danger"><i class="bi bi-x-circle me-1"></i>{{ $ov['belum'] }} belum</span>
                        <span class="text-muted ms-auto">{{ $ov['hadir'] }}/{{ $ov['total'] }}</span>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

@endsection

@push('scripts')
<script>
@if($tanggal->isToday())
// Auto-refresh setiap 60 detik
setTimeout(function() { location.reload(); }, 60000);
@endif
</script>
@endpush
