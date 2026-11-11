@extends('layouts.app')

@section('title', 'Produk Jual')

@section('content')

<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-cup-hot me-2 text-success"></i>Produk Jual</h5>
    <div class="d-flex gap-2 align-items-center">
        @can('master.produk_jual.create')
        <a href="{{ route('master.produk-jual.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle me-1"></i>Tambah Produk
        </a>
        <a href="{{ route('master.produk-jual.ambil.index') }}" class="btn btn-outline-success btn-sm" id="btnAmbilCabangLain">
            <i class="bi bi-box-arrow-in-down me-1"></i>Ambil dari Cabang Lain
        </a>
        @endcan
        <x-panduan-button slug="produk-jual" />
    </div>
</div>

<x-info-cabang-aktif :cabang="$cabangAktif" />

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <x-search-box placeholder="Nama / kode produk..." col="col-12 col-sm-6 col-md-4" />
            <div class="col-6 col-md-3">
                <select name="kategori" class="form-select form-select-sm">
                    <option value="">Semua Kategori</option>
                    @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" @selected(request('kategori') == $cat->id)>{{ $cat->nama_kategori }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua Status</option>
                    <option value="aktif" @selected(request('status')=='aktif')>Aktif</option>
                    <option value="nonaktif" @selected(request('status')=='nonaktif')>Nonaktif</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-search"></i></button>
            </div>
        </form>
    </div>
</div>

<div class="row g-3">
    @forelse($items as $item)
    <div class="col-6 col-md-4 col-lg-3">
        <div class="card h-100">
            <div style="height:140px;background:#f8fafc;display:flex;align-items:center;justify-content:center;overflow:hidden">
                @if($item->foto)
                <img src="{{ asset('storage/'.$item->foto) }}" alt="{{ $item->nama_item }}" style="width:100%;height:100%;object-fit:cover">
                @else
                <span style="font-size:2.5rem">🥟</span>
                @endif
            </div>
            <div class="card-body p-2">
                <div class="fw-semibold small">{{ $item->nama_item }}</div>
                <div class="text-muted" style="font-size:0.75rem">{{ $item->kode_item }} · {{ $item->category?->nama_kategori ?? '-' }}</div>
                <div class="fw-bold text-success mt-1">Rp {{ number_format($item->harga_jual ?? 0, 0, ',', '.') }}</div>
                @unless($cabangAktif)<div class="text-muted" style="font-size:0.72rem"><i class="bi bi-geo-alt"></i> {{ $item->cabang?->nama_cabang ?? 'Semua cabang (data lama)' }}</div>@endunless
                <div class="d-flex gap-1 flex-wrap mt-1">
                    @if($item->punya_varian)<span class="badge bg-info-subtle text-info" style="font-size:0.65rem">Varian</span>@endif
                    <span class="badge {{ $item->is_active ? 'bg-success' : 'bg-secondary' }}" style="font-size:0.65rem">{{ $item->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                </div>
            </div>
            <div class="card-footer p-2 d-flex gap-1">
                @can('master.produk_jual.edit')
                <a href="{{ route('master.produk-jual.edit', $item) }}" class="btn btn-sm btn-outline-warning flex-fill"><i class="bi bi-pencil"></i></a>
                @endcan
                @can('master.produk_jual.delete')
                <button type="button" class="btn btn-sm btn-outline-danger"
                    onclick="if(confirm('Hapus {{ addslashes($item->nama_item) }}?')) document.getElementById('del-{{ $item->id }}').submit()">
                    <i class="bi bi-trash"></i>
                </button>
                <form id="del-{{ $item->id }}" method="POST" action="{{ route('master.produk-jual.destroy', $item) }}" class="d-none">@csrf @method('DELETE')</form>
                @endcan
            </div>
        </div>
    </div>
    @empty
    <div class="col-12 text-center text-muted py-5">
        <i class="bi bi-inbox fs-3 d-block mb-2"></i>Belum ada produk jual
    </div>
    @endforelse
</div>

<div class="mt-3">{{ $items->links() }}</div>

@endsection
