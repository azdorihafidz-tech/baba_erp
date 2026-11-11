@extends('layouts.app')

@section('title', 'Laporan Stok')

@section('content')

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0" style="color:#1e293b">Laporan Stok</h4>
        <p class="text-muted mb-0" style="font-size:0.875rem">Kondisi stok per lokasi</p>
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
        <a href="{{ route('laporan.stok.pergerakan') }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-arrow-left-right me-1"></i>
            <span class="d-none d-sm-inline">Pergerakan</span>
        </a>
        <a href="{{ route('laporan.stok.minimum') }}" class="btn btn-outline-warning btn-sm">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <span class="d-none d-sm-inline">Stok Rendah</span>
        </a>
        <x-panduan-button slug="laporan-stok" />
    </div>
</div>

<!-- Filter -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('laporan.stok') }}">
            <div class="row g-2 align-items-end">
                @if(auth()->user()->canAccessAllBranches())
                <div class="col-12 col-sm-6 col-md-4">
                    <label class="form-label form-label-sm">Lokasi</label>
                    <select name="lokasi_id" class="form-select form-select-sm">
                        <option value="">Semua Lokasi</option>
                        @foreach($cabangs as $cab)
                        <option value="{{ $cab->id }}" {{ request('lokasi_id') == $cab->id ? 'selected' : '' }}>
                            {{ $cab->nama_cabang }} ({{ $cab->tipe?->value }})
                        </option>
                        @endforeach
                    </select>
                </div>
                @endif
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
    <div class="col-6 col-md-4">
        <div class="stat-card">
            <div class="stat-icon bg-primary bg-opacity-10 mb-2"><i class="bi bi-boxes text-primary"></i></div>
            <div class="fw-bold" style="font-size:1.5rem;color:#1e293b">{{ $totalItem }}</div>
            <div class="text-muted" style="font-size:0.8rem">Total Jenis Item</div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="stat-card">
            <div class="stat-icon bg-warning bg-opacity-10 mb-2"><i class="bi bi-exclamation-triangle text-warning"></i></div>
            <div class="fw-bold" style="font-size:1.5rem;color:#1e293b">{{ $stokRendah }}</div>
            <div class="text-muted" style="font-size:0.8rem">Stok Rendah</div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="stat-card">
            <div class="stat-icon bg-danger bg-opacity-10 mb-2"><i class="bi bi-slash-circle text-danger"></i></div>
            <div class="fw-bold" style="font-size:1.5rem;color:#1e293b">{{ $stokKosong }}</div>
            <div class="text-muted" style="font-size:0.8rem">Stok Kosong</div>
        </div>
    </div>
</div>

<!-- Tabel Stok -->
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between py-3 px-4">
        <span><i class="bi bi-table me-2"></i>Data Stok</span>
        <small class="text-muted">{{ $stocks->total() }} item</small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="px-4">Nama Barang</th>
                        <th class="d-none d-md-table-cell">Kategori</th>
                        <th class="d-none d-sm-table-cell">Satuan</th>
                        <th class="d-none d-lg-table-cell">Lokasi</th>
                        <th class="text-end px-3">Stok</th>
                        <th class="text-end d-none d-md-table-cell px-3">Setara Pack</th>
                        <th class="text-end d-none d-sm-table-cell px-3">Min</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($stocks as $stok)
                    <tr class="{{ $stok->isBelowMinimum() ? 'table-warning' : '' }}">
                        <td class="px-4 fw-medium" style="font-size:0.875rem">
                            {{ $stok->item?->nama_item ?? '-' }}
                            <div class="d-lg-none text-muted" style="font-size:0.75rem">{{ $stok->lokasi?->nama_cabang }}</div>
                        </td>
                        <td class="d-none d-md-table-cell text-muted" style="font-size:0.875rem">
                            {{ $stok->item?->category?->nama_kategori ?? '-' }}
                        </td>
                        <td class="d-none d-sm-table-cell text-muted" style="font-size:0.875rem">
                            {{ $stok->item?->satuan ?? '-' }}
                        </td>
                        <td class="d-none d-lg-table-cell" style="font-size:0.875rem">
                            {{ $stok->lokasi?->nama_cabang ?? '-' }}
                        </td>
                        <td class="text-end px-3 fw-bold" style="font-size:0.875rem">
                            {{ number_format($stok->qty, 2, ',', '.') }}
                        </td>
                        <td class="text-end d-none d-md-table-cell px-3 text-muted" style="font-size:0.75rem">
                            @if($stok->item?->hasUnitBeli())
                                @php $pack = (float) $stok->qty / (float) $stok->item->isi_per_unit_beli; @endphp
                                {{ rtrim(rtrim(number_format($pack, 2, ',', '.'), '0'), ',') }} {{ $stok->item->unit_beli }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-end d-none d-sm-table-cell px-3 text-muted" style="font-size:0.875rem">
                            {{ number_format($stok->qty_minimum, 2, ',', '.') }}
                        </td>
                        <td>
                            @if($stok->qty <= 0)
                                <span class="badge bg-danger" style="font-size:0.7rem">Kosong</span>
                            @elseif($stok->isBelowMinimum())
                                <span class="badge bg-warning text-dark" style="font-size:0.7rem">Rendah</span>
                            @else
                                <span class="badge bg-success" style="font-size:0.7rem">Normal</span>
                            @endif
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
    @if($stocks->hasPages())
    <div class="card-footer d-flex justify-content-center py-3">
        {{ $stocks->links() }}
    </div>
    @endif
</div>

@endsection
