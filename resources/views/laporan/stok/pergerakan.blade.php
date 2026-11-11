@extends('layouts.app')

@section('title', 'Laporan Pergerakan Stok')

@section('content')

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0" style="color:#1e293b">Pergerakan Stok</h4>
        <p class="text-muted mb-0" style="font-size:0.875rem">History keluar masuk & transfer stok</p>
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
            <span class="d-none d-sm-inline">Stok</span>
        </a>
    </div>
</div>

<x-date-range-filter
    action="{{ route('laporan.stok.pergerakan') }}"
    :dari="$dari->toDateString()"
    :sampai="$sampai->toDateString()"
    session-key="laporanstokpergerakan">
    <div class="row g-2 mb-2">
        @if(auth()->user()->canAccessAllBranches())
        <div class="col-12 col-sm-6 col-md-3">
            <label class="form-label form-label-sm mb-1">Lokasi</label>
            <select name="lokasi_id" class="form-select form-select-sm">
                <option value="">Semua Lokasi</option>
                @foreach($cabangs as $cab)
                <option value="{{ $cab->id }}" {{ request('lokasi_id') == $cab->id ? 'selected' : '' }}>{{ $cab->nama_cabang }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <div class="col-12 col-sm-6 col-md-3">
            <label class="form-label form-label-sm mb-1">Tipe Pergerakan</label>
            <select name="tipe" class="form-select form-select-sm">
                <option value="">Semua Tipe</option>
                <option value="masuk" {{ request('tipe') === 'masuk' ? 'selected' : '' }}>Masuk</option>
                <option value="keluar" {{ request('tipe') === 'keluar' ? 'selected' : '' }}>Keluar</option>
                <option value="transfer" {{ request('tipe') === 'transfer' ? 'selected' : '' }}>Transfer</option>
                <option value="adjustment" {{ request('tipe') === 'adjustment' ? 'selected' : '' }}>Adjustment</option>
            </select>
        </div>
    </div>
</x-date-range-filter>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <div class="stat-card">
            <div class="stat-icon bg-success bg-opacity-10 mb-2"><i class="bi bi-arrow-down-circle text-success"></i></div>
            <div class="fw-bold" style="font-size:1.2rem;color:#1e293b">{{ number_format($totalMasuk, 2, ',', '.') }}</div>
            <div class="text-muted" style="font-size:0.8rem">Total Masuk</div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="stat-card">
            <div class="stat-icon bg-danger bg-opacity-10 mb-2"><i class="bi bi-arrow-up-circle text-danger"></i></div>
            <div class="fw-bold" style="font-size:1.2rem;color:#1e293b">{{ number_format($totalKeluar, 2, ',', '.') }}</div>
            <div class="text-muted" style="font-size:0.8rem">Total Keluar</div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="stat-card">
            <div class="stat-icon bg-info bg-opacity-10 mb-2"><i class="bi bi-arrow-left-right text-info"></i></div>
            <div class="fw-bold" style="font-size:1.5rem;color:#1e293b">{{ $totalTransfer }}</div>
            <div class="text-muted" style="font-size:0.8rem">Transfer</div>
        </div>
    </div>
</div>

<!-- Tabel -->
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between py-3 px-4">
        <span><i class="bi bi-table me-2"></i>Riwayat Pergerakan</span>
        <small class="text-muted">{{ $movements->total() }} record</small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="px-4">Tanggal</th>
                        <th>Barang</th>
                        <th>Tipe</th>
                        <th class="text-end px-3">Qty</th>
                        <th class="d-none d-md-table-cell">Asal</th>
                        <th class="d-none d-md-table-cell">Tujuan</th>
                        <th class="d-none d-lg-table-cell">User</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($movements as $mov)
                    <tr>
                        <td class="px-4" style="font-size:0.85rem">
                            {{ $mov->created_at?->format('d/m/Y') }}
                            <div class="text-muted" style="font-size:0.75rem">{{ $mov->created_at?->format('H:i') }}</div>
                        </td>
                        <td style="font-size:0.875rem">{{ $mov->item?->nama_item ?? '-' }}</td>
                        <td>
                            @php
                                $tipeBadge = match($mov->tipe?->value ?? $mov->tipe) {
                                    'masuk' => 'bg-success',
                                    'keluar' => 'bg-danger',
                                    'transfer' => 'bg-info',
                                    'adjustment' => 'bg-warning text-dark',
                                    default => 'bg-secondary',
                                };
                            @endphp
                            <span class="badge {{ $tipeBadge }}" style="font-size:0.7rem">
                                {{ ucfirst($mov->tipe?->value ?? $mov->tipe) }}
                            </span>
                        </td>
                        <td class="text-end px-3 fw-medium" style="font-size:0.875rem">
                            {{ number_format($mov->qty, 2, ',', '.') }}
                        </td>
                        <td class="d-none d-md-table-cell text-muted" style="font-size:0.85rem">
                            {{ $mov->lokasiAsal?->nama_cabang ?? '-' }}
                        </td>
                        <td class="d-none d-md-table-cell text-muted" style="font-size:0.85rem">
                            {{ $mov->lokasiTujuan?->nama_cabang ?? '-' }}
                        </td>
                        <td class="d-none d-lg-table-cell text-muted" style="font-size:0.8rem">
                            {{ $mov->user?->name ?? '-' }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            <i class="bi bi-inbox me-2"></i>Tidak ada data
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($movements->hasPages())
    <div class="card-footer d-flex justify-content-center py-3">
        {{ $movements->links() }}
    </div>
    @endif
</div>

@endsection
