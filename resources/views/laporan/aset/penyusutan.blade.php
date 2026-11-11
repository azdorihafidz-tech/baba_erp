@extends('layouts.app')

@section('title', 'Laporan Penyusutan Aset')

@section('content')

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0" style="color:#1e293b">Laporan Penyusutan Aset</h4>
        <p class="text-muted mb-0" style="font-size:0.875rem">Jadwal depresiasi per periode</p>
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
        <a href="{{ route('laporan.aset') }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-building-gear me-1"></i>
            <span class="d-none d-sm-inline">Daftar Aset</span>
        </a>
    </div>
</div>

<!-- Filter -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('laporan.aset.penyusutan') }}">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-sm-6 col-md-3">
                    <label class="form-label form-label-sm">Periode (Bulan)</label>
                    <input type="month" name="periode" class="form-control form-control-sm" value="{{ $periode }}">
                </div>
                @if(auth()->user()->canAccessAllBranches())
                <div class="col-12 col-sm-6 col-md-3">
                    <label class="form-label form-label-sm">Lokasi</label>
                    <select name="lokasi_id" class="form-select form-select-sm">
                        <option value="">Semua Lokasi</option>
                        @foreach($cabangs as $cab)
                        <option value="{{ $cab->id }}" {{ request('lokasi_id') == $cab->id ? 'selected' : '' }}>{{ $cab->nama_cabang }}</option>
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
    <div class="col-6">
        <div class="stat-card">
            <div class="stat-icon bg-warning bg-opacity-10 mb-2"><i class="bi bi-graph-down text-warning"></i></div>
            <div class="fw-bold text-warning" style="font-size:1rem">Rp {{ number_format($totalPenyusutanPeriode, 0, ',', '.') }}</div>
            <div class="text-muted" style="font-size:0.8rem">Total Penyusutan Periode Ini</div>
        </div>
    </div>
    <div class="col-6">
        <div class="stat-card">
            <div class="stat-icon bg-primary bg-opacity-10 mb-2"><i class="bi bi-building-gear text-primary"></i></div>
            <div class="fw-bold" style="font-size:1.5rem;color:#1e293b">{{ $totalAsetTerdepresiasi }}</div>
            <div class="text-muted" style="font-size:0.8rem">Aset Terdepresiasi</div>
        </div>
    </div>
</div>

<!-- Tabel -->
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between py-3 px-4">
        <span>
            <i class="bi bi-calendar-minus me-2 text-warning"></i>
            Penyusutan Periode {{ \Carbon\Carbon::parse($periode . '-01')->translatedFormat('F Y') }}
        </span>
        <small class="text-muted">{{ $depreciations->total() }} record</small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="px-4">Aset</th>
                        <th class="d-none d-md-table-cell">Kategori</th>
                        <th class="d-none d-lg-table-cell">Lokasi</th>
                        <th class="text-end px-3">Nilai Buku Awal</th>
                        <th class="text-end px-3">Penyusutan</th>
                        <th class="text-end px-3 d-none d-md-table-cell">Akumulasi</th>
                        <th class="text-end px-3">Nilai Buku Akhir</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($depreciations as $dep)
                    <tr>
                        <td class="px-4">
                            <div class="fw-medium" style="font-size:0.875rem">{{ $dep->asset?->nama_aset ?? '-' }}</div>
                            <div class="text-muted" style="font-size:0.75rem">{{ $dep->asset?->kode_aset }}</div>
                        </td>
                        <td class="d-none d-md-table-cell text-muted" style="font-size:0.875rem">{{ $dep->asset?->kategori?->nama_kategori ?? '-' }}</td>
                        <td class="d-none d-lg-table-cell" style="font-size:0.875rem">{{ $dep->asset?->lokasi?->nama_cabang ?? '-' }}</td>
                        <td class="text-end px-3" style="font-size:0.875rem">Rp {{ number_format($dep->nilai_buku_awal, 0, ',', '.') }}</td>
                        <td class="text-end px-3 text-danger fw-medium" style="font-size:0.875rem">-Rp {{ number_format($dep->jumlah_penyusutan, 0, ',', '.') }}</td>
                        <td class="text-end px-3 d-none d-md-table-cell" style="font-size:0.875rem">Rp {{ number_format($dep->akumulasi_penyusutan, 0, ',', '.') }}</td>
                        <td class="text-end px-3 fw-bold" style="font-size:0.875rem">Rp {{ number_format($dep->nilai_buku_akhir, 0, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            <i class="bi bi-inbox me-2"></i>Belum ada data penyusutan untuk periode ini
                        </td>
                    </tr>
                    @endforelse
                </tbody>
                @if($depreciations->isNotEmpty())
                <tfoot class="table-light">
                    <tr>
                        <td colspan="4" class="px-4 text-end fw-bold d-none d-md-table-cell">Total:</td>
                        <td colspan="2" class="px-4 text-end fw-bold d-md-none">Total Penyusutan:</td>
                        <td class="text-end px-3 fw-bold text-danger">-Rp {{ number_format($totalPenyusutanPeriode, 0, ',', '.') }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
    @if($depreciations->hasPages())
    <div class="card-footer d-flex justify-content-center py-3">
        {{ $depreciations->links() }}
    </div>
    @endif
</div>

@endsection
