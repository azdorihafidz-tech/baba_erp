@extends('layouts.app')

@section('title', 'Laporan Keuangan')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
        <h5 class="fw-bold mb-0"><i class="bi bi-file-earmark-text me-2 text-primary"></i>Laporan Keuangan</h5>
        <p class="text-muted mb-0 small">Laporan Laba / Rugi — {{ $cabangNama }}</p>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-printer me-1"></i>Cetak
        </button>
        <a href="{{ route('keuangan.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
        <x-panduan-button slug="keuangan-laporan" />
    </div>
</div>

{{-- Filter --}}
<div class="card mb-4">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-sm-4 col-md-3">
                <label class="form-label small fw-semibold mb-1">Periode</label>
                <input type="month" name="periode" class="form-control form-control-sm" value="{{ $periode }}">
            </div>
            @if($cabangs->count())
            <div class="col-12 col-sm-5 col-md-4">
                <label class="form-label small fw-semibold mb-1">Cabang</label>
                <select name="cabang_id" class="form-select form-select-sm">
                    <option value="">Semua Cabang</option>
                    @foreach($cabangs as $c)
                    <option value="{{ $c->id }}" @selected(request('cabang_id') == $c->id)>{{ $c->nama_cabang }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-search me-1"></i>Tampilkan
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Laporan --}}
<div class="card">
    <div class="card-header d-flex justify-content-between">
        <span class="fw-bold">Laporan Laba / Rugi</span>
        <span class="text-muted small">Periode: {{ \Carbon\Carbon::parse($periode . '-01')->format('F Y') }}</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered mb-0">

                {{-- PEMASUKAN --}}
                <thead>
                    <tr class="table-success">
                        <th colspan="2" class="py-2 px-3">PEMASUKAN</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pemasukan as $label => $jumlah)
                    <tr>
                        <td class="ps-4">{{ $label }}</td>
                        <td class="text-end fw-semibold text-success">Rp {{ number_format($jumlah, 0, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="2" class="text-muted text-center py-3">Tidak ada pemasukan</td></tr>
                    @endforelse
                    <tr class="table-success fw-bold">
                        <td>Total Pemasukan</td>
                        <td class="text-end text-success">Rp {{ number_format($totalPemasukan, 0, ',', '.') }}</td>
                    </tr>
                </tbody>

                {{-- PENGELUARAN --}}
                <thead>
                    <tr class="table-danger">
                        <th colspan="2" class="py-2 px-3">PENGELUARAN</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pengeluaran as $label => $jumlah)
                    <tr>
                        <td class="ps-4">{{ $label }}</td>
                        <td class="text-end fw-semibold text-danger">Rp {{ number_format($jumlah, 0, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="2" class="text-muted text-center py-3">Tidak ada pengeluaran</td></tr>
                    @endforelse
                    <tr class="table-danger fw-bold">
                        <td>Total Pengeluaran</td>
                        <td class="text-end text-danger">Rp {{ number_format($totalPengeluaran, 0, ',', '.') }}</td>
                    </tr>
                </tbody>

                {{-- LABA/RUGI --}}
                <tfoot>
                    <tr class="{{ $labaRugi >= 0 ? 'table-primary' : 'table-warning' }}" style="font-size:1.05rem">
                        <th class="py-3 px-3">{{ $labaRugi >= 0 ? 'LABA BERSIH' : 'RUGI BERSIH' }}</th>
                        <th class="text-end py-3 px-3 {{ $labaRugi >= 0 ? 'text-primary' : 'text-danger' }}">
                            Rp {{ number_format(abs($labaRugi), 0, ',', '.') }}
                        </th>
                    </tr>
                </tfoot>

            </table>
        </div>
    </div>
</div>

@push('styles')
<style>
@media print {
    .btn, form, .card-header .btn { display: none !important; }
    .card { border: 1px solid #ccc !important; }
}
</style>
@endpush
@endsection
