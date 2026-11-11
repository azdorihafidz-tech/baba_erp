@extends('layouts.app')

@section('title', 'Stok Minimum / Restock')

@section('content')

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0" style="color:#1e293b">Stok Minimum & Restock</h4>
        <p class="text-muted mb-0" style="font-size:0.875rem">Item yang perlu segera direstok</p>
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
        <a href="{{ route('laporan.stok') }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-boxes me-1"></i>
            <span class="d-none d-sm-inline">Semua Stok</span>
        </a>
    </div>
</div>

<!-- Filter -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('laporan.stok.minimum') }}">
            <div class="row g-2 align-items-end">
                @if(auth()->user()->canAccessAllBranches())
                <div class="col-12 col-sm-6 col-md-4">
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
                        <i class="bi bi-search me-1"></i>Filter
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Alert -->
@if($totalStokRendah > 0)
<div class="alert alert-warning d-flex align-items-center mb-4">
    <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
    <div>
        Terdapat <strong>{{ $totalStokRendah }}</strong> item di bawah stok minimum
        @if($totalKosong > 0), termasuk <strong>{{ $totalKosong }}</strong> item yang sudah <span class="text-danger fw-bold">kosong</span>.@endif
    </div>
</div>
@endif

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-6">
        <div class="stat-card border-warning">
            <div class="stat-icon bg-warning bg-opacity-10 mb-2"><i class="bi bi-exclamation-triangle text-warning"></i></div>
            <div class="fw-bold" style="font-size:1.5rem;color:#1e293b">{{ $totalStokRendah }}</div>
            <div class="text-muted" style="font-size:0.8rem">Item Stok Rendah</div>
        </div>
    </div>
    <div class="col-6">
        <div class="stat-card border-danger">
            <div class="stat-icon bg-danger bg-opacity-10 mb-2"><i class="bi bi-slash-circle text-danger"></i></div>
            <div class="fw-bold" style="font-size:1.5rem;color:#1e293b">{{ $totalKosong }}</div>
            <div class="text-muted" style="font-size:0.8rem">Item Stok Kosong</div>
        </div>
    </div>
</div>

<!-- Tabel -->
<div class="card">
    <div class="card-header py-3 px-4">
        <i class="bi bi-exclamation-triangle-fill me-2 text-warning"></i>Item yang Perlu Restock
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="px-4">Nama Barang</th>
                        <th class="d-none d-md-table-cell">Kategori</th>
                        <th class="d-none d-lg-table-cell">Lokasi</th>
                        <th class="text-end px-3">Stok Saat Ini</th>
                        <th class="text-end px-3">Stok Min</th>
                        <th class="d-none d-sm-table-cell text-end px-3">Kekurangan</th>
                        <th>Level</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($stocks as $stok)
                    @php
                        $kekurangan = max(0, $stok->qty_minimum - $stok->qty);
                        $isKosong = $stok->qty <= 0;
                    @endphp
                    <tr class="{{ $isKosong ? 'table-danger' : 'table-warning' }}">
                        <td class="px-4 fw-medium" style="font-size:0.875rem">
                            {{ $stok->item?->nama_item ?? '-' }}
                            <div class="d-lg-none text-muted" style="font-size:0.75rem">{{ $stok->lokasi?->nama_cabang }}</div>
                        </td>
                        <td class="d-none d-md-table-cell" style="font-size:0.875rem">{{ $stok->item?->category?->nama_kategori ?? '-' }}</td>
                        <td class="d-none d-lg-table-cell" style="font-size:0.875rem">{{ $stok->lokasi?->nama_cabang ?? '-' }}</td>
                        <td class="text-end px-3 fw-bold {{ $isKosong ? 'text-danger' : 'text-warning' }}">
                            {{ number_format($stok->qty, 2, ',', '.') }}
                        </td>
                        <td class="text-end px-3" style="font-size:0.875rem">
                            {{ number_format($stok->qty_minimum, 2, ',', '.') }}
                        </td>
                        <td class="d-none d-sm-table-cell text-end px-3 text-danger fw-medium" style="font-size:0.875rem">
                            -{{ number_format($kekurangan, 2, ',', '.') }}
                        </td>
                        <td>
                            @if($isKosong)
                                <span class="badge bg-danger" style="font-size:0.7rem">KOSONG</span>
                            @else
                                <span class="badge bg-warning text-dark" style="font-size:0.7rem">Rendah</span>
                            @endif
                        </td>
                        <td>
                            @if(auth()->user()->can('pembelian.create'))
                            <a href="{{ route('pembelian.create') }}" class="btn btn-xs btn-outline-primary" style="font-size:0.7rem;padding:2px 8px">
                                Beli
                            </a>
                            @else
                            <a href="{{ route('stock-request.create') }}" class="btn btn-xs btn-outline-warning" style="font-size:0.7rem;padding:2px 8px">
                                Request
                            </a>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-5">
                            <i class="bi bi-check-circle text-success" style="font-size:2rem"></i>
                            <p class="mt-2 text-muted mb-0">Semua stok dalam kondisi aman</p>
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
