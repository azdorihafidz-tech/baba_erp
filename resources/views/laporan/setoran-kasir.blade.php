@extends('layouts.app')

@section('title', 'Laporan Setoran Kasir')

@section('content')

<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-cash-coin me-2 text-success"></i>Laporan Setoran Kasir</h5>
    <div class="d-flex gap-2 align-items-center">
        @can('laporan.setoran_kasir.export')
        <a href="{{ route('laporan.setoran-kasir.export', request()->query()) }}" class="btn btn-outline-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
        </a>
        <a href="{{ route('laporan.setoran-kasir.export', array_merge(request()->query(), ['format' => 'pdf'])) }}" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-file-earmark-pdf me-1"></i>Export PDF
        </a>
        @endcan
        <x-panduan-button slug="laporan-setoran-kasir" />
    </div>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
                <label class="form-label small">Dari <x-tooltip key="laporan_setoran_kasir.filter_tanggal" /></label>
                <input type="date" name="dari" class="form-control form-control-sm" value="{{ $dari->format('Y-m-d') }}">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small">Sampai</label>
                <input type="date" name="sampai" class="form-control form-control-sm" value="{{ $sampai->format('Y-m-d') }}">
            </div>
            @if($cabangOptions->isNotEmpty())
            <div class="col-6 col-md-3">
                <label class="form-label small">Cabang</label>
                <select name="cabang_id" class="form-select form-select-sm">
                    <option value="">Semua Cabang</option>
                    @foreach($cabangOptions as $cab)
                    <option value="{{ $cab->id }}" @selected(request('cabang_id') == $cab->id)>{{ $cab->nama_cabang }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-6 col-md-2">
                <label class="form-label small">Status <x-tooltip key="laporan_setoran_kasir.filter_status" /></label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="menunggu" @selected(request('status')=='menunggu')>Menunggu</option>
                    <option value="approved" @selected(request('status')=='approved')>Disetujui</option>
                    <option value="rejected" @selected(request('status')=='rejected')>Ditolak</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm"><div class="card-body py-2 px-3">
            <div class="text-muted small">Jumlah Setoran</div><div class="fs-5 fw-bold">{{ $stats['jumlah'] }}</div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm"><div class="card-body py-2 px-3">
            <div class="text-muted small">Total Sistem</div><div class="fs-6 fw-bold">Rp {{ number_format($stats['total_sistem'], 0, ',', '.') }}</div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm"><div class="card-body py-2 px-3">
            <div class="text-muted small">Total Disetor</div><div class="fs-6 fw-bold">Rp {{ number_format($stats['total_disetor'], 0, ',', '.') }}</div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm"><div class="card-body py-2 px-3">
            <div class="text-muted small">Total Selisih</div><div class="fs-6 fw-bold {{ $stats['total_selisih'] < 0 ? 'text-danger' : '' }}">Rp {{ number_format($stats['total_selisih'], 0, ',', '.') }}</div>
        </div></div>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr><th>Tanggal</th><th>Cabang</th><th class="text-end">Sistem</th><th class="text-end">Disetor</th><th class="text-end">Selisih</th><th class="text-center">Status</th></tr>
                </thead>
                <tbody>
                    @forelse($setorans as $s)
                    <tr>
                        <td>{{ $s->tanggal->format('d/m/Y') }}</td>
                        <td>{{ $s->cabang->nama_cabang }}</td>
                        <td class="text-end">Rp {{ number_format($s->total_penjualan_sistem, 0, ',', '.') }}</td>
                        <td class="text-end">Rp {{ number_format($s->total_disetor, 0, ',', '.') }}</td>
                        <td class="text-end">Rp {{ number_format($s->selisih, 0, ',', '.') }}</td>
                        <td class="text-center"><span class="badge {{ $s->status->badgeClass() }}">{{ $s->status->label() }}</span></td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada data</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">{{ $setorans->links() }}</div>

@endsection
