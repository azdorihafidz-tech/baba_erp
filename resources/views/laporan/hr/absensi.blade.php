@extends('layouts.app')

@section('title', 'Laporan Absensi')

@section('content')

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0" style="color:#1e293b">Laporan Absensi Karyawan</h4>
        <p class="text-muted mb-0" style="font-size:0.875rem">Rekap kehadiran karyawan</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}" class="btn btn-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i>
            <span class="d-none d-sm-inline">Export Excel</span>
        </a>
        <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}" class="btn btn-danger btn-sm">
            <i class="bi bi-file-earmark-pdf me-1"></i>
            <span class="d-none d-sm-inline">Export PDF</span>
        </a>
        @can('laporan.hr_penggajian.view')
        <a href="{{ route('laporan.hr.penggajian') }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-cash me-1"></i>
            <span class="d-none d-sm-inline">Penggajian</span>
        </a>
        @endcan
        <x-panduan-button slug="laporan-hr" />
    </div>
</div>

<x-date-range-filter
    action="{{ route('laporan.hr.absensi') }}"
    :dari="$dari->toDateString()"
    :sampai="$sampai->toDateString()"
    session-key="laporanabsensi">
    <div class="row g-2 mb-2">
        @if(auth()->user()->canAccessAllBranches())
        <div class="col-12 col-sm-6 col-md-3">
            <label class="form-label form-label-sm mb-1">Cabang</label>
            <select name="cabang_id" class="form-select form-select-sm">
                <option value="">Semua Cabang</option>
                @foreach($cabangs as $cab)
                <option value="{{ $cab->id }}" {{ $cabangId == $cab->id ? 'selected' : '' }}>{{ $cab->nama_cabang }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <div class="col-12 col-sm-6 col-md-3">
            <label class="form-label form-label-sm mb-1">Status Kehadiran</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">Semua Status</option>
                <option value="hadir" {{ request('status') === 'hadir' ? 'selected' : '' }}>Hadir</option>
                <option value="alpha" {{ request('status') === 'alpha' ? 'selected' : '' }}>Alpha</option>
                <option value="izin" {{ request('status') === 'izin' ? 'selected' : '' }}>Izin</option>
                <option value="sakit" {{ request('status') === 'sakit' ? 'selected' : '' }}>Sakit</option>
            </select>
        </div>
    </div>
</x-date-range-filter>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-success bg-opacity-10 mb-2"><i class="bi bi-check-circle text-success"></i></div>
            <div class="fw-bold text-success" style="font-size:1.5rem">{{ $totalHadir }}</div>
            <div class="text-muted" style="font-size:0.8rem">Hadir</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-danger bg-opacity-10 mb-2"><i class="bi bi-x-circle text-danger"></i></div>
            <div class="fw-bold text-danger" style="font-size:1.5rem">{{ $totalAlpha }}</div>
            <div class="text-muted" style="font-size:0.8rem">Alpha</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-info bg-opacity-10 mb-2"><i class="bi bi-calendar-check text-info"></i></div>
            <div class="fw-bold text-info" style="font-size:1.5rem">{{ $totalIzin }}</div>
            <div class="text-muted" style="font-size:0.8rem">Izin</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-warning bg-opacity-10 mb-2"><i class="bi bi-hospital text-warning"></i></div>
            <div class="fw-bold text-warning" style="font-size:1.5rem">{{ $totalSakit }}</div>
            <div class="text-muted" style="font-size:0.8rem">Sakit</div>
        </div>
    </div>
</div>

<!-- Rekap per Karyawan -->
@if($rekapKaryawan->isNotEmpty())
<div class="card mb-4">
    <div class="card-header py-3 px-4">
        <i class="bi bi-person-lines-fill me-2 text-primary"></i>Rekap per Karyawan
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead>
                    <tr>
                        <th class="px-4">Karyawan</th>
                        <th class="text-center">Hadir</th>
                        <th class="text-center">Alpha</th>
                        <th class="text-center d-none d-sm-table-cell">Izin</th>
                        <th class="text-center d-none d-sm-table-cell">Sakit</th>
                        <th class="text-end px-3 d-none d-md-table-cell">Lembur (jam)</th>
                        <th class="text-center">% Hadir</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rekapKaryawan as $rekap)
                    @php
                        $total = $rekap->hadir + $rekap->alpha + $rekap->izin + $rekap->sakit;
                        $pctHadir = $total > 0 ? round(($rekap->hadir / $total) * 100) : 0;
                    @endphp
                    <tr>
                        <td class="px-4" style="font-size:0.875rem">{{ $rekap->karyawan?->nama_lengkap ?? '-' }}</td>
                        <td class="text-center"><span class="badge bg-success" style="font-size:0.7rem">{{ $rekap->hadir }}</span></td>
                        <td class="text-center"><span class="badge bg-danger" style="font-size:0.7rem">{{ $rekap->alpha }}</span></td>
                        <td class="text-center d-none d-sm-table-cell"><span class="badge bg-info" style="font-size:0.7rem">{{ $rekap->izin }}</span></td>
                        <td class="text-center d-none d-sm-table-cell"><span class="badge bg-warning text-dark" style="font-size:0.7rem">{{ $rekap->sakit }}</span></td>
                        <td class="text-end px-3 d-none d-md-table-cell" style="font-size:0.875rem">{{ number_format($rekap->total_lembur ?? 0, 1) }}</td>
                        <td class="text-center">
                            <div class="progress" style="height:8px;min-width:50px">
                                <div class="progress-bar {{ $pctHadir >= 90 ? 'bg-success' : ($pctHadir >= 75 ? 'bg-warning' : 'bg-danger') }}" style="width:{{ $pctHadir }}%"></div>
                            </div>
                            <small class="text-muted" style="font-size:0.7rem">{{ $pctHadir }}%</small>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

<!-- Detail Absensi -->
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between py-3 px-4">
        <span><i class="bi bi-calendar3 me-2"></i>Detail Absensi</span>
        <small class="text-muted">{{ $absensis->total() }} record</small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="px-4">Tanggal</th>
                        <th>Karyawan</th>
                        <th class="d-none d-md-table-cell">Cabang</th>
                        <th>Status</th>
                        <th class="d-none d-sm-table-cell">Jam Masuk</th>
                        <th class="d-none d-sm-table-cell">Jam Keluar</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($absensis as $abs)
                    <tr>
                        <td class="px-4" style="font-size:0.875rem">{{ $abs->tanggal?->format('d/m/Y') }}</td>
                        <td style="font-size:0.875rem">{{ $abs->karyawan?->nama_lengkap ?? '-' }}</td>
                        <td class="d-none d-md-table-cell text-muted" style="font-size:0.875rem">{{ $abs->cabang?->nama_cabang }}</td>
                        <td>
                            @php
                                $statusBadge = match($abs->status) {
                                    'hadir' => 'bg-success',
                                    'alpha' => 'bg-danger',
                                    'izin' => 'bg-info',
                                    'sakit' => 'bg-warning text-dark',
                                    default => 'bg-secondary',
                                };
                            @endphp
                            <span class="badge {{ $statusBadge }}" style="font-size:0.7rem">{{ ucfirst($abs->status) }}</span>
                        </td>
                        <td class="d-none d-sm-table-cell text-muted" style="font-size:0.875rem">{{ $abs->jam_masuk ?? '-' }}</td>
                        <td class="d-none d-sm-table-cell text-muted" style="font-size:0.875rem">{{ $abs->jam_keluar ?? '-' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <i class="bi bi-inbox me-2"></i>Tidak ada data
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($absensis->hasPages())
    <div class="card-footer d-flex justify-content-center py-3">
        {{ $absensis->links() }}
    </div>
    @endif
</div>

@endsection
