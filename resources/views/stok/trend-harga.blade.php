@extends('layouts.app')

@section('title', 'Trend Harga Beli')

@section('content')
<div class="container-fluid">

    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1" style="font-size:0.8rem">
                    <li class="breadcrumb-item"><a href="{{ route('stok.dashboard') }}">Dashboard Stok</a></li>
                    <li class="breadcrumb-item active">Trend Harga</li>
                </ol>
            </nav>
            <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
                <i class="bi bi-graph-up-arrow text-primary"></i>
                Trend Harga Beli
                <span class="badge bg-primary" style="font-size:0.75rem">{{ $hari }} hari terakhir</span>
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
                <div class="col-12 col-sm-4 col-md-2">
                    <label class="form-label mb-1" style="font-size:0.8rem;font-weight:600">Periode (hari)</label>
                    <select name="hari" class="form-select form-select-sm">
                        @foreach([30 => '30 hari', 60 => '60 hari', 90 => '90 hari', 180 => '6 bulan', 365 => '1 tahun'] as $val => $label)
                            <option value="{{ $val }}" {{ $hari == $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-sm-4 col-md-2">
                    <label class="form-label mb-1" style="font-size:0.8rem;font-weight:600">Min. Perubahan (%)</label>
                    <input type="number" name="threshold" value="{{ $threshold }}"
                           class="form-control form-control-sm" min="1" max="100" step="1">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary px-3">
                        <i class="bi bi-funnel-fill me-1"></i>Filter
                    </button>
                    <a href="{{ route('stok.trend-harga') }}" class="btn btn-sm btn-outline-secondary ms-1">Reset</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Summary --}}
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-2">
                <div class="fw-bold fs-5 text-primary">{{ $trendsPage->total() }}</div>
                <div style="font-size:0.75rem;color:#64748b">Item dengan Perubahan Harga</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-2">
                <div class="fw-bold fs-5" style="color:#64748b">&ge;{{ $threshold }}%</div>
                <div style="font-size:0.75rem;color:#64748b">Threshold Tampilkan</div>
            </div>
        </div>
    </div>

    {{-- Info jika kosong --}}
    @if($trendsPage->isEmpty())
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-check-circle-fill text-success fs-3 d-block mb-2"></i>
            <p class="mb-0 fw-semibold">Tidak ada perubahan harga &ge;{{ $threshold }}%</p>
            <p class="mb-0 small">dalam {{ $hari }} hari terakhir</p>
            <p class="mt-2 small text-muted">
                Harga beli stabil — tidak perlu adjustment harga jual saat ini
            </p>
        </div>
    </div>
    @else
    {{-- Tabel --}}
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" style="font-size:0.85rem">
                    <thead class="table-light">
                        <tr>
                            <th class="px-3">Item</th>
                            <th>Satuan</th>
                            <th class="text-end">Harga Lama</th>
                            <th class="text-end">Harga Baru</th>
                            <th class="text-end">Perubahan</th>
                            <th class="text-center">% Naik/Turun</th>
                            <th class="d-none d-md-table-cell">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($trendsPage as $trend)
                        <tr>
                            <td class="px-3 fw-semibold">{{ $trend->nama_item }}</td>
                            <td class="text-muted">{{ $trend->satuan }}</td>
                            <td class="text-end" style="font-size:0.82rem">
                                Rp {{ number_format($trend->harga_lama, 0, ',', '.') }}
                            </td>
                            <td class="text-end fw-semibold {{ $trend->arah === 'naik' ? 'text-danger' : 'text-success' }}">
                                Rp {{ number_format($trend->harga_baru, 0, ',', '.') }}
                            </td>
                            <td class="text-end {{ $trend->arah === 'naik' ? 'text-danger' : 'text-success' }}" style="font-size:0.82rem">
                                {{ $trend->arah === 'naik' ? '+' : '' }}Rp {{ number_format($trend->perubahan, 0, ',', '.') }}
                            </td>
                            <td class="text-center">
                                <span class="badge {{ $trend->severity === 'danger' ? 'bg-danger' : 'bg-warning text-dark' }}">
                                    {{ $trend->arah === 'naik' ? '↑' : '↓' }}{{ number_format(abs($trend->persen), 1) }}%
                                </span>
                            </td>
                            <td class="d-none d-md-table-cell">
                                @if($trend->severity === 'danger')
                                    <span class="badge bg-danger-subtle text-danger" style="font-size:0.7rem">Signifikan</span>
                                @else
                                    <span class="badge bg-warning-subtle text-warning" style="font-size:0.7rem">Perlu Dicermati</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($trendsPage->hasPages())
            <div class="d-flex justify-content-center py-3 border-top">
                {{ $trendsPage->links() }}
            </div>
            @endif
        </div>
    </div>
    @endif

</div>
@endsection
