@extends('layouts.app')

@section('title', 'Laporan Penggajian')

@section('content')

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0" style="color:#1e293b">Laporan Penggajian</h4>
        <p class="text-muted mb-0" style="font-size:0.875rem">Rekap penggajian karyawan</p>
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
        @can('laporan.hr_absensi.view')
        <a href="{{ route('laporan.hr.absensi') }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-calendar-check me-1"></i>
            <span class="d-none d-sm-inline">Absensi</span>
        </a>
        @endcan
    </div>
</div>

<!-- Filter -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('laporan.hr.penggajian') }}">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-sm-6 col-md-3">
                    <label class="form-label form-label-sm">Periode (Bulan)</label>
                    <input type="month" name="periode" class="form-control form-control-sm" value="{{ $periode }}">
                </div>
                @if(auth()->user()->canAccessAllBranches())
                <div class="col-12 col-sm-6 col-md-3">
                    <label class="form-label form-label-sm">Cabang</label>
                    <select name="cabang_id" class="form-select form-select-sm">
                        <option value="">Semua Cabang</option>
                        @foreach($cabangs as $cab)
                        <option value="{{ $cab->id }}" {{ $cabangId == $cab->id ? 'selected' : '' }}>{{ $cab->nama_cabang }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-search me-1"></i>Tampilkan
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-primary bg-opacity-10 mb-2"><i class="bi bi-people text-primary"></i></div>
            <div class="fw-bold" style="font-size:1.5rem;color:#1e293b">{{ $totalKaryawan }}</div>
            <div class="text-muted" style="font-size:0.8rem">Karyawan Digaji</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-success bg-opacity-10 mb-2"><i class="bi bi-cash-stack text-success"></i></div>
            <div class="fw-bold text-success" style="font-size:1rem">Rp {{ number_format($totalGaji, 0, ',', '.') }}</div>
            <div class="text-muted" style="font-size:0.8rem">Total Gaji</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-info bg-opacity-10 mb-2"><i class="bi bi-check-circle text-info"></i></div>
            <div class="fw-bold text-info" style="font-size:1.5rem">{{ $sudahBayar }}</div>
            <div class="text-muted" style="font-size:0.8rem">Sudah Dibayar</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-warning bg-opacity-10 mb-2"><i class="bi bi-clock text-warning"></i></div>
            <div class="fw-bold text-warning" style="font-size:1.5rem">{{ $belumBayar }}</div>
            <div class="text-muted" style="font-size:0.8rem">Belum Dibayar</div>
        </div>
    </div>
</div>

<!-- Tabel -->
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between py-3 px-4">
        <span><i class="bi bi-table me-2"></i>Data Penggajian</span>
        <small class="text-muted">{{ $penggajians->total() }} karyawan</small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="px-4">Karyawan</th>
                        <th class="d-none d-lg-table-cell">Cabang</th>
                        <th class="text-end px-3">Gaji Pokok</th>
                        <th class="text-end px-3 d-none d-md-table-cell">Tunjangan</th>
                        <th class="text-end px-3 d-none d-lg-table-cell">Bonus</th>
                        <th class="text-end px-3 d-none d-md-table-cell">Potongan</th>
                        <th class="text-end px-3">Total</th>
                        <th>Status</th>
                        <th class="d-none d-sm-table-cell">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($penggajians as $pg)
                    <tr>
                        <td class="px-4" style="font-size:0.875rem">
                            <div class="fw-medium">{{ $pg->karyawan?->nama_lengkap ?? '-' }}</div>
                            <div class="text-muted d-lg-none" style="font-size:0.75rem">{{ $pg->cabang?->nama_cabang }}</div>
                        </td>
                        <td class="d-none d-lg-table-cell text-muted" style="font-size:0.875rem">{{ $pg->cabang?->nama_cabang }}</td>
                        <td class="text-end px-3" style="font-size:0.875rem">Rp {{ number_format($pg->gaji_pokok, 0, ',', '.') }}</td>
                        <td class="text-end px-3 d-none d-md-table-cell" style="font-size:0.875rem">Rp {{ number_format($pg->tunjangan, 0, ',', '.') }}</td>
                        <td class="text-end px-3 d-none d-lg-table-cell" style="font-size:0.875rem">Rp {{ number_format($pg->bonus, 0, ',', '.') }}</td>
                        <td class="text-end px-3 d-none d-md-table-cell text-danger" style="font-size:0.875rem">
                            -Rp {{ number_format($pg->potongan_absensi + $pg->potongan_lain + $pg->potongan_ganti_rugi, 0, ',', '.') }}
                        </td>
                        <td class="text-end px-3 fw-bold" style="font-size:0.875rem">Rp {{ number_format($pg->total_gaji, 0, ',', '.') }}</td>
                        <td>
                            @php
                                $statusBadge = match($pg->status) {
                                    'dibayar' => 'bg-success',
                                    'disetujui' => 'bg-info',
                                    'pending' => 'bg-warning text-dark',
                                    default => 'bg-secondary',
                                };
                            @endphp
                            <span class="badge {{ $statusBadge }}" style="font-size:0.7rem">{{ ucfirst($pg->status) }}</span>
                        </td>
                        <td class="d-none d-sm-table-cell">
                            <a href="{{ route('penggajian.show', $pg) }}" class="btn btn-xs btn-outline-primary" style="font-size:0.7rem;padding:2px 8px">Detail</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            <i class="bi bi-inbox me-2"></i>Belum ada data penggajian untuk periode ini
                        </td>
                    </tr>
                    @endforelse
                </tbody>
                @if($penggajians->isNotEmpty())
                <tfoot class="table-light">
                    <tr>
                        <td colspan="6" class="px-4 fw-bold text-end d-none d-md-table-cell">Total:</td>
                        <td colspan="3" class="px-4 fw-bold d-md-none">Total:</td>
                        <td class="text-end px-3 fw-bold">Rp {{ number_format($totalGaji, 0, ',', '.') }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
    @if($penggajians->hasPages())
    <div class="card-footer d-flex justify-content-center py-3">
        {{ $penggajians->links() }}
    </div>
    @endif
</div>

@endsection
