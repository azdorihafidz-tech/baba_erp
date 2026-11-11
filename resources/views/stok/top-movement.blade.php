@extends('layouts.app')

@section('title', 'Top Movement Stok')

@section('content')
<div class="container-fluid">

    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1" style="font-size:0.8rem">
                    <li class="breadcrumb-item"><a href="{{ route('stok.dashboard') }}">Dashboard Stok</a></li>
                    <li class="breadcrumb-item active">Top Movement</li>
                </ol>
            </nav>
            <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
                <i class="bi bi-trophy text-success"></i>
                Top Movement Stok
                <span class="badge bg-success" style="font-size:0.75rem">{{ $hari }} hari terakhir</span>
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
                    <label class="form-label mb-1" style="font-size:0.8rem;font-weight:600">Periode (hari)</label>
                    <select name="hari" class="form-select form-select-sm">
                        @foreach([7 => '7 hari', 30 => '30 hari', 60 => '60 hari', 90 => '90 hari', 180 => '6 bulan', 365 => '1 tahun'] as $val => $label)
                            <option value="{{ $val }}" {{ $hari == $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary px-3">
                        <i class="bi bi-funnel-fill me-1"></i>Filter
                    </button>
                    <a href="{{ route('stok.top-movement') }}" class="btn btn-sm btn-outline-secondary ms-1">Reset</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Summary --}}
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-2">
                <div class="fw-bold fs-5 text-success">{{ number_format($items->total()) }}</div>
                <div style="font-size:0.75rem;color:#64748b">Item Bergerak</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-2">
                <div class="fw-bold fs-5 text-primary">{{ number_format($totalKeluar, 2, ',', '.') }}</div>
                <div style="font-size:0.75rem;color:#64748b">Total Qty Keluar</div>
            </div>
        </div>
    </div>

    {{-- Tabel --}}
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            @php $maxQty = $items->isNotEmpty() ? max($items->max('total_keluar'), 1) : 1; @endphp
            <div class="table-responsive">
                <table class="table table-hover mb-0" style="font-size:0.85rem">
                    <thead class="table-light">
                        <tr>
                            <th class="px-3" style="width:50px">Rank</th>
                            <th>Item</th>
                            <th class="d-none d-md-table-cell">Tipe</th>
                            <th class="text-end">Total Keluar</th>
                            <th class="text-end d-none d-sm-table-cell">Jml Transaksi</th>
                            <th class="text-end d-none d-md-table-cell">Nilai HPP</th>
                            <th class="d-none d-lg-table-cell" style="width:180px">Grafik</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $idx => $item)
                        @php $rank = $items->firstItem() + $idx; @endphp
                        <tr>
                            <td class="px-3">
                                <span class="badge {{ $rank <= 3 ? 'bg-success' : 'bg-secondary-subtle text-secondary' }}" style="font-size:0.75rem">
                                    #{{ $rank }}
                                </span>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $item->nama_item }}</div>
                                <div style="font-size:0.73rem;color:#64748b">{{ $item->satuan }}</div>
                            </td>
                            <td class="d-none d-md-table-cell">
                                <span class="badge bg-secondary-subtle text-secondary" style="font-size:0.7rem">
                                    {{ str_replace('_', ' ', $item->tipe) }}
                                </span>
                            </td>
                            <td class="text-end fw-bold text-success">
                                {{ number_format($item->total_keluar, 2, ',', '.') }}
                                <small class="text-muted fw-normal">{{ $item->satuan }}</small>
                            </td>
                            <td class="text-end d-none d-sm-table-cell">
                                <span class="badge bg-info-subtle text-info">{{ number_format($item->jumlah_transaksi) }}x</span>
                            </td>
                            <td class="text-end d-none d-md-table-cell" style="font-size:0.8rem">
                                @if($item->nilai > 0)
                                    Rp {{ number_format($item->nilai, 0, ',', '.') }}
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="d-none d-lg-table-cell">
                                <div class="progress" style="height:8px;border-radius:4px">
                                    <div class="progress-bar bg-success"
                                         style="width:{{ min(($item->total_keluar / $maxQty) * 100, 100) }}%;border-radius:4px">
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="bi bi-bar-chart fs-4 d-block mb-1"></i>
                                Tidak ada movement stok dalam {{ $hari }} hari terakhir
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($items->hasPages())
            <div class="d-flex justify-content-center py-3 border-top">
                {{ $items->links() }}
            </div>
            @endif
        </div>
    </div>

</div>
@endsection
