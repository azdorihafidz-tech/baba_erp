@extends('layouts.app')

@section('title', 'Produksi')

@section('content')
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-fire me-2 text-success"></i>Produksi Bahan Setengah Jadi</h5>
    <div class="d-flex gap-2 align-items-center">
        <x-panduan-button slug="produksi" />
        @can('produksi.create')
        @if($gudang->isNotEmpty())
        <a href="{{ route('produksi.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>Catat Produksi</a>
        @endif
        @endcan
    </div>
</div>

@include('produksi._tabs')
@include('produksi._tanpa-gudang')

<form method="GET" class="card card-body py-2 mb-3">
    <div class="row g-2 align-items-end">
        @if($gudang->count() > 1)
        <div class="col-6 col-md-3">
            <label class="form-label small mb-0">Gudang</label>
            <select name="cabang_id" class="form-select form-select-sm">
                <option value="">Semua gudang</option>
                @foreach($gudang as $g)
                <option value="{{ $g->id }}" @selected(request('cabang_id') == $g->id)>{{ $g->nama_cabang }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <div class="col-6 col-md-3">
            <label class="form-label small mb-0">Barang hasil</label>
            <select name="item_id" class="form-select form-select-sm">
                <option value="">Semua</option>
                @foreach($hasilList as $h)
                <option value="{{ $h->id }}" @selected(request('item_id') == $h->id)>{{ $h->nama_item }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-0">Dari</label>
            <input type="date" name="dari" value="{{ request('dari') }}" class="form-control form-control-sm">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-0">Sampai</label>
            <input type="date" name="sampai" value="{{ request('sampai') }}" class="form-control form-control-sm">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-0">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">Semua</option>
                <option value="selesai" @selected(request('status') === 'selesai')>Selesai</option>
                <option value="dibatalkan" @selected(request('status') === 'dibatalkan')>Dibatalkan</option>
            </select>
        </div>
        <div class="col-12 col-md-auto d-flex gap-2">
            <button class="btn btn-outline-primary btn-sm"><i class="bi bi-funnel me-1"></i>Tampilkan</button>
            <a href="{{ route('produksi.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
        </div>
    </div>
</form>

<div class="card"><div class="table-responsive">
<table class="table table-sm align-middle mb-0">
    <thead><tr>
        <th>Kode</th><th>Tanggal</th>@if($gudang->count() > 1)<th>Gudang</th>@endif<th>Barang Hasil</th>
        <th class="text-end">Rencana</th><th class="text-end">Hasil</th><th class="text-end">Modal / unit</th><th>Status</th><th></th>
    </tr></thead>
    <tbody>
    @forelse($batches as $b)
    <tr class="{{ $b->status === 'dibatalkan' ? 'text-muted' : '' }}">
        <td class="fw-semibold">{{ $b->kode_batch }}</td>
        <td>{{ $b->tanggal->format('d/m/Y') }}</td>
        @if($gudang->count() > 1)<td>{{ $b->cabang?->nama_cabang }}</td>@endif
        <td>{{ $b->item?->nama_item }}</td>
        <td class="text-end">{{ \App\Services\ProduksiService::angka((float) $b->qty_rencana) }} {{ $b->item?->satuan }}</td>
        <td class="text-end">{{ \App\Services\ProduksiService::angka((float) $b->qty_hasil) }} {{ $b->item?->satuan }}</td>
        <td class="text-end">Rp {{ number_format($b->modal_per_unit, 0, ',', '.') }}@if($b->modal_manual) <span class="badge bg-warning text-dark" title="Modal diubah manual">M</span>@endif</td>
        <td><span class="badge bg-{{ $b->status === 'selesai' ? 'success' : 'danger' }}">{{ ucfirst($b->status) }}</span></td>
        <td class="text-end"><a href="{{ route('produksi.show', $b) }}" class="btn btn-outline-primary btn-sm">Detail</a></td>
    </tr>
    @empty
    <tr><td colspan="9" class="text-center text-muted py-4">
        Belum ada produksi.
        @can('produksi.resep.kelola') Mulai dengan membuat <a href="{{ route('produksi.resep.index') }}">Resep Produksi</a>.@endcan
    </td></tr>
    @endforelse
    </tbody>
</table>
</div></div>
<div class="mt-3">{{ $batches->links() }}</div>
@endsection
