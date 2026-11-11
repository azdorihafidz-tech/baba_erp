@extends('layouts.app')

@section('title', 'Laporan Aset')

@section('content')

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0" style="color:#1e293b">Laporan Aset</h4>
        <p class="text-muted mb-0" style="font-size:0.875rem">Daftar aset & nilai buku terkini</p>
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
        <a href="{{ route('laporan.aset.penyusutan') }}" class="btn btn-outline-warning btn-sm">
            <i class="bi bi-calendar-minus me-1"></i>
            <span class="d-none d-sm-inline">Penyusutan</span>
        </a>
        <a href="{{ route('aset.index') }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-building-gear me-1"></i>
            <span class="d-none d-sm-inline">Kelola Aset</span>
        </a>
        <x-panduan-button slug="laporan-aset" />
    </div>
</div>

@if(isset($viewMode) && $viewMode === 'maintenance')
    {{-- Maintenance view --}}
    <div class="alert alert-info">Tampilan maintenance. <a href="{{ route('laporan.aset') }}">Kembali ke Daftar Aset</a></div>
@else

<!-- Filter -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('laporan.aset') }}">
            <div class="row g-2 align-items-end">
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
                <div class="col-12 col-sm-6 col-md-3">
                    <label class="form-label form-label-sm">Kategori</label>
                    <select name="kategori_id" class="form-select form-select-sm">
                        <option value="">Semua Kategori</option>
                        @foreach($kategories as $kat)
                        <option value="{{ $kat->id }}" {{ request('kategori_id') == $kat->id ? 'selected' : '' }}>{{ $kat->nama_kategori }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-md-2">
                    <label class="form-label form-label-sm">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        <option value="aktif" {{ request('status') === 'aktif' ? 'selected' : '' }}>Aktif</option>
                        <option value="tidak_aktif" {{ request('status') === 'tidak_aktif' ? 'selected' : '' }}>Tidak Aktif</option>
                        <option value="dijual" {{ request('status') === 'dijual' ? 'selected' : '' }}>Dijual</option>
                        <option value="dihapuskan" {{ request('status') === 'dihapuskan' ? 'selected' : '' }}>Dihapuskan</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-search me-1"></i>Filter
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
            <div class="stat-icon bg-primary bg-opacity-10 mb-2"><i class="bi bi-building-gear text-primary"></i></div>
            <div class="fw-bold" style="font-size:1.5rem;color:#1e293b">{{ $totalAset }}</div>
            <div class="text-muted" style="font-size:0.8rem">Total Aset</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-success bg-opacity-10 mb-2"><i class="bi bi-cash-coin text-success"></i></div>
            <div class="fw-bold text-success" style="font-size:0.9rem">Rp {{ number_format($totalHargaPerolehan, 0, ',', '.') }}</div>
            <div class="text-muted" style="font-size:0.8rem">Total Harga Perolehan</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-info bg-opacity-10 mb-2"><i class="bi bi-wallet2 text-info"></i></div>
            <div class="fw-bold text-info" style="font-size:0.9rem">Rp {{ number_format($totalNilaiBuku, 0, ',', '.') }}</div>
            <div class="text-muted" style="font-size:0.8rem">Total Nilai Buku</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-warning bg-opacity-10 mb-2"><i class="bi bi-graph-down text-warning"></i></div>
            <div class="fw-bold text-warning" style="font-size:0.9rem">Rp {{ number_format($totalPenyusutan, 0, ',', '.') }}</div>
            <div class="text-muted" style="font-size:0.8rem">Total Akumulasi Penyusutan</div>
        </div>
    </div>
</div>

<!-- Tabel -->
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between py-3 px-4">
        <span><i class="bi bi-table me-2"></i>Daftar Aset</span>
        <small class="text-muted">{{ $assets->total() }} aset</small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="px-4">Kode / Nama Aset</th>
                        <th class="d-none d-md-table-cell">Kategori</th>
                        <th class="d-none d-lg-table-cell">Lokasi</th>
                        <th class="d-none d-sm-table-cell">Perolehan</th>
                        <th class="text-end px-3">H. Perolehan</th>
                        <th class="text-end px-3">Nilai Buku</th>
                        <th>Kondisi</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($assets as $aset)
                    <tr>
                        <td class="px-4">
                            <div class="fw-medium" style="font-size:0.875rem">{{ $aset->nama_aset }}</div>
                            <div class="text-muted" style="font-size:0.75rem">{{ $aset->kode_aset }}</div>
                        </td>
                        <td class="d-none d-md-table-cell text-muted" style="font-size:0.875rem">{{ $aset->kategori?->nama_kategori ?? '-' }}</td>
                        <td class="d-none d-lg-table-cell" style="font-size:0.875rem">{{ $aset->lokasi?->nama_cabang ?? '-' }}</td>
                        <td class="d-none d-sm-table-cell text-muted" style="font-size:0.875rem">{{ $aset->tanggal_perolehan?->format('d/m/Y') }}</td>
                        <td class="text-end px-3" style="font-size:0.875rem">Rp {{ number_format($aset->harga_perolehan, 0, ',', '.') }}</td>
                        <td class="text-end px-3 fw-medium" style="font-size:0.875rem">Rp {{ number_format($aset->nilai_buku, 0, ',', '.') }}</td>
                        <td>
                            @php
                                $kondisiBadge = match($aset->kondisi?->value) {
                                    'baik' => 'bg-success',
                                    'rusak_ringan' => 'bg-warning text-dark',
                                    'rusak_berat' => 'bg-danger',
                                    'dihapuskan' => 'bg-secondary',
                                    default => 'bg-secondary',
                                };
                            @endphp
                            <span class="badge {{ $kondisiBadge }}" style="font-size:0.7rem">
                                {{ ucfirst(str_replace('_', ' ', $aset->kondisi?->value ?? '-')) }}
                            </span>
                        </td>
                        <td>
                            <a href="{{ route('aset.show', $aset) }}" class="btn btn-xs btn-outline-primary" style="font-size:0.7rem;padding:2px 8px">
                                Detail
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            <i class="bi bi-inbox me-2"></i>Tidak ada data
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($assets->hasPages())
    <div class="card-footer d-flex justify-content-center py-3">
        {{ $assets->links() }}
    </div>
    @endif
</div>
@endif

@endsection
