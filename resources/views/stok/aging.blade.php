@extends('layouts.app')

@section('title', 'Stok Lama (Aging)')

@section('content')
<div class="container-fluid">

    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1" style="font-size:0.8rem">
                    <li class="breadcrumb-item"><a href="{{ route('stok.dashboard') }}">Dashboard Stok</a></li>
                    <li class="breadcrumb-item active">Stok Lama</li>
                </ol>
            </nav>
            <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
                <i class="bi bi-hourglass-split text-warning"></i>
                Stok Lama (Aging)
                <span class="badge bg-warning text-dark" style="font-size:0.75rem">{{ number_format($totalItem) }} batch</span>
            </h5>
        </div>
        <a href="{{ route('stok.dashboard') }}" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>

    {{-- Filter --}}
    <div class="card mb-3 border-0 shadow-sm">
        <div class="card-body py-2 px-3">
            <form method="GET" class="row g-2 align-items-end">
                @if(auth()->user()->canAccessAllBranches())
                <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                    <label class="form-label mb-1" style="font-size:0.8rem;font-weight:600">Lokasi</label>
                    <select name="cabang_id" class="form-select form-select-sm">
                        <option value="">Semua Lokasi</option>
                        @foreach($cabangList as $c)
                            <option value="{{ $c->id }}" {{ $cabangId == $c->id ? 'selected' : '' }}>
                                {{ $c->nama_cabang }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="col-12 col-sm-6 col-md-3 col-lg-2">
                    <label class="form-label mb-1" style="font-size:0.8rem;font-weight:600">Min. Umur (hari)</label>
                    <input type="number" name="threshold" value="{{ $threshold }}"
                           class="form-control form-control-sm" min="1" max="730">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary px-3">
                        <i class="bi bi-funnel-fill me-1"></i>Filter
                    </button>
                    <a href="{{ route('stok.aging') }}" class="btn btn-sm btn-outline-secondary ms-1">Reset</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Summary --}}
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-2">
                <div class="fw-bold fs-5 text-warning">{{ number_format($totalItem) }}</div>
                <div style="font-size:0.75rem;color:#64748b">Batch Lama</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-2">
                <div class="fw-bold fs-5 text-danger">Rp {{ number_format($totalNilai, 0, ',', '.') }}</div>
                <div style="font-size:0.75rem;color:#64748b">Total Nilai Terkunci</div>
            </div>
        </div>
    </div>

    {{-- Tabel --}}
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" style="font-size:0.85rem">
                    <thead class="table-light">
                        <tr>
                            <th class="px-3">#Batch</th>
                            <th>Item</th>
                            <th class="d-none d-md-table-cell">Tipe</th>
                            <th class="d-none d-md-table-cell">Lokasi</th>
                            <th>Tgl Masuk</th>
                            <th>Umur</th>
                            <th class="text-end">Qty Sisa</th>
                            <th class="text-end d-none d-sm-table-cell">Harga/Unit</th>
                            <th class="text-end d-none d-sm-table-cell">Nilai</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($batches as $b)
                        <tr class="{{ $b->severity === 'danger' ? 'table-danger' : 'table-warning' }}">
                            <td class="px-3 fw-semibold">#{{ $b->id }}</td>
                            <td>
                                <div class="fw-semibold">{{ $b->nama_item }}</div>
                                <div class="d-md-none" style="font-size:0.73rem;color:#64748b">{{ $b->lokasi }}</div>
                            </td>
                            <td class="d-none d-md-table-cell">
                                <span class="badge bg-secondary-subtle text-secondary" style="font-size:0.7rem">
                                    {{ str_replace('_', ' ', $b->tipe) }}
                                </span>
                            </td>
                            <td class="d-none d-md-table-cell text-muted" style="font-size:0.8rem">{{ $b->lokasi }}</td>
                            <td style="font-size:0.8rem">{{ $b->tanggal_masuk }}</td>
                            <td>
                                <span class="badge {{ $b->severity === 'danger' ? 'bg-danger' : 'bg-warning text-dark' }}">
                                    {{ $b->umur_hari }} hari
                                </span>
                            </td>
                            <td class="text-end fw-semibold">
                                {{ number_format($b->qty_sisa, 2, ',', '.') }}
                                <small class="text-muted">{{ $b->satuan }}</small>
                            </td>
                            <td class="text-end d-none d-sm-table-cell" style="font-size:0.8rem">
                                Rp {{ number_format($b->harga_beli_per_unit, 0, ',', '.') }}
                            </td>
                            <td class="text-end d-none d-sm-table-cell fw-semibold">
                                Rp {{ number_format($b->nilai, 0, ',', '.') }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">
                                <i class="bi bi-check-circle-fill text-success fs-4 d-block mb-1"></i>
                                Tidak ada batch stok yang lebih lama dari {{ $threshold }} hari
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($batches->hasPages())
            <div class="d-flex justify-content-center py-3 border-top">
                {{ $batches->links() }}
            </div>
            @endif
        </div>
    </div>

</div>
@endsection
