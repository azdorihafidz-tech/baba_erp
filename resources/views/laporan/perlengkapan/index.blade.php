@extends('layouts.app')

@section('title', 'Laporan Pemakaian Perlengkapan')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
        <h5 class="fw-bold mb-0">
            <i class="bi bi-clipboard-data me-2 text-primary"></i>Laporan Pemakaian Perlengkapan
        </h5>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0" style="font-size:.8rem">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active">Laporan Pemakaian Perlengkapan</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2">
        @can('laporan.perlengkapan.print')
        <a href="{{ route('laporan.perlengkapan.print', request()->query()) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-printer me-1"></i>Print
        </a>
        @endcan
        @can('laporan.perlengkapan.export')
        <a href="{{ route('laporan.perlengkapan.export', request()->query()) }}" class="btn btn-sm btn-outline-success">
            <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
        </a>
        <a href="{{ route('laporan.perlengkapan.export-pdf', request()->query()) }}" class="btn btn-sm btn-danger">
            <i class="bi bi-file-earmark-pdf me-1"></i>Export PDF
        </a>
        @endcan
    </div>
</div>

{{-- FILTER --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-6 col-sm-3 col-md-2">
                <label class="form-label form-label-sm mb-1">Dari</label>
                <input type="date" name="dari" class="form-control form-control-sm" value="{{ $mulai->toDateString() }}">
            </div>
            <div class="col-6 col-sm-3 col-md-2">
                <label class="form-label form-label-sm mb-1">Sampai</label>
                <input type="date" name="sampai" class="form-control form-control-sm" value="{{ $akhir->toDateString() }}">
            </div>
            @if($cabangs->isNotEmpty())
            <div class="col-12 col-sm-4 col-md-2">
                <label class="form-label form-label-sm mb-1">Cabang</label>
                <select name="cabang_id" class="form-select form-select-sm">
                    <option value="">Semua Cabang</option>
                    @foreach($cabangs as $c)
                    <option value="{{ $c->id }}" @selected($cabangId == $c->id)>{{ $c->nama_cabang }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-6 col-md-1">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-filter"></i>
                </button>
            </div>
        </form>
    </div>
</div>

{{-- RINGKASAN --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-md-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <div class="text-muted small">Total Nilai</div>
                <div class="fs-5 fw-bold">Rp {{ number_format($ringkasan['total_nilai'], 0, ',', '.') }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <div class="text-muted small">Jumlah Kejadian</div>
                <div class="fs-5 fw-bold">{{ $ringkasan['jumlah_kejadian'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <div class="text-muted small">Item Berbeda Terpakai</div>
                <div class="fs-5 fw-bold">{{ $ringkasan['jumlah_item'] }}</div>
            </div>
        </div>
    </div>
</div>

{{-- BREAKDOWN PER ITEM --}}
<div class="card mb-3">
    <div class="card-header fw-semibold">Breakdown per Item</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <th>Item</th>
                    <th class="text-end">Total Qty</th>
                    <th class="text-end">Total Nilai</th>
                    <th class="text-end">Kejadian</th>
                </tr>
            </thead>
            <tbody>
                @forelse($breakdown as $b)
                <tr>
                    <td>{{ $b->nama_item }}</td>
                    <td class="text-end">{{ number_format($b->total_qty, 3) }} {{ $b->satuan }}</td>
                    <td class="text-end">Rp {{ number_format($b->total_nilai, 0, ',', '.') }}</td>
                    <td class="text-end">{{ $b->jumlah_kejadian }}</td>
                </tr>
                @empty
                <tr><td colspan="4" class="text-center text-muted py-3">Belum ada data untuk periode ini</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- DETAIL --}}
<div class="card">
    <div class="card-header fw-semibold">Detail Pemakaian</div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Tanggal</th>
                    <th>Item</th>
                    <th>Cabang</th>
                    <th class="text-end">Qty</th>
                    <th class="text-end">Nilai</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($detail as $d)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($d->tanggal_pemakaian)->format('d/m/Y') }}</td>
                    <td>{{ $d->nama_item }}</td>
                    <td>{{ $d->nama_cabang }}</td>
                    <td class="text-end">{{ number_format($d->qty, 3) }} {{ $d->satuan }}</td>
                    <td class="text-end">Rp {{ number_format($d->nilai, 0, ',', '.') }}</td>
                    <td class="small text-muted">{{ Str::limit($d->keterangan, 40) ?: '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-muted py-3">Belum ada data untuk periode ini</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
