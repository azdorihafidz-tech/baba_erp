@extends('layouts.app')

@section('title', 'Stok Tidak Bergerak')

@section('content')
<div class="container-fluid">

    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1" style="font-size:0.8rem">
                    <li class="breadcrumb-item"><a href="{{ route('stok.dashboard') }}">Dashboard Stok</a></li>
                    <li class="breadcrumb-item active">Stok Tidak Bergerak</li>
                </ol>
            </nav>
            <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
                <i class="bi bi-moon-stars text-secondary"></i>
                Stok Tidak Bergerak
                <span class="badge bg-secondary" style="font-size:0.75rem">{{ $stokMatiPage->total() }} item</span>
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
                    <label class="form-label mb-1" style="font-size:0.8rem;font-weight:600">Tidak bergerak (hari)</label>
                    <select name="threshold" class="form-select form-select-sm">
                        @foreach([7 => '7 hari', 14 => '14 hari', 30 => '30 hari', 60 => '60 hari', 90 => '90 hari', 180 => '6 bulan'] as $val => $label)
                            <option value="{{ $val }}" {{ $threshold == $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary px-3">
                        <i class="bi bi-funnel-fill me-1"></i>Filter
                    </button>
                    <a href="{{ route('stok.stok-mati') }}" class="btn btn-sm btn-outline-secondary ms-1">Reset</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Summary --}}
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-2">
                <div class="fw-bold fs-5 text-secondary">{{ $stokMatiPage->total() }}</div>
                <div style="font-size:0.75rem;color:#64748b">Item Tidak Bergerak</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-2">
                <div class="fw-bold fs-5 text-danger">Rp {{ number_format($totalNilai, 0, ',', '.') }}</div>
                <div style="font-size:0.75rem;color:#64748b">Nilai Modal Tertahan</div>
            </div>
        </div>
    </div>

    @if($stokMatiPage->isEmpty())
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-check-circle-fill text-success fs-3 d-block mb-2"></i>
            <p class="mb-0 fw-semibold">Semua stok bergerak dalam {{ $threshold }} hari terakhir</p>
            <p class="mt-2 small text-muted">Tidak ada modal tertahan di stok tidak produktif</p>
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
                            <th class="d-none d-md-table-cell">Tipe</th>
                            <th class="d-none d-lg-table-cell">Lokasi</th>
                            <th class="text-end">Qty Sisa</th>
                            <th class="text-end d-none d-sm-table-cell">Nilai HPP</th>
                            <th class="text-center">Terakhir Keluar</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($stokMatiPage as $item)
                        <tr>
                            <td class="px-3">
                                <div class="fw-semibold">{{ $item->nama_item }}</div>
                                <div class="d-lg-none" style="font-size:0.73rem;color:#64748b">
                                    {{ $item->lokasi ?? 'Semua Lokasi' }}
                                </div>
                            </td>
                            <td class="d-none d-md-table-cell">
                                <span class="badge bg-secondary-subtle text-secondary" style="font-size:0.7rem">
                                    {{ str_replace('_', ' ', $item->tipe) }}
                                </span>
                            </td>
                            <td class="d-none d-lg-table-cell text-muted" style="font-size:0.8rem">
                                {{ $item->lokasi ?? '-' }}
                            </td>
                            <td class="text-end fw-semibold">
                                {{ number_format($item->qty, 2, ',', '.') }}
                                <small class="text-muted">{{ $item->satuan }}</small>
                            </td>
                            <td class="text-end d-none d-sm-table-cell fw-semibold text-danger" style="font-size:0.82rem">
                                @if($item->nilai > 0)
                                    Rp {{ number_format($item->nilai, 0, ',', '.') }}
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="text-center" style="font-size:0.8rem">
                                @if($item->pernah_keluar)
                                    <span class="text-muted">
                                        {{ \Carbon\Carbon::parse($item->last_keluar)->format('d/m/Y') }}
                                    </span>
                                    <div style="font-size:0.72rem;color:#64748b">
                                        {{ $item->hari_sejak_keluar }} hari lalu
                                    </div>
                                @else
                                    <span class="badge bg-info-subtle text-info" style="font-size:0.7rem">
                                        Belum pernah keluar
                                    </span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($item->hari_sejak_keluar > 90)
                                    <span class="badge bg-danger" style="font-size:0.7rem">Kritis</span>
                                @elseif($item->hari_sejak_keluar > 60)
                                    <span class="badge bg-warning text-dark" style="font-size:0.7rem">Perhatian</span>
                                @else
                                    <span class="badge bg-secondary" style="font-size:0.7rem">Monitor</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($stokMatiPage->hasPages())
            <div class="d-flex justify-content-center py-3 border-top">
                {{ $stokMatiPage->links() }}
            </div>
            @endif
        </div>
    </div>

    <div class="alert alert-warning border-0 mt-3" style="font-size:0.82rem">
        <i class="bi bi-lightbulb-fill me-2"></i>
        <strong>Rekomendasi:</strong> Review item dengan status "Kritis" — pertimbangkan promo, diskon khusus,
        atau investigasi penyebab tidak adanya demand. Modal yang tertahan di stok tidak produktif
        mengurangi cashflow operasional.
    </div>
    @endif

</div>
@endsection
