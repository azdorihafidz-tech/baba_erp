@extends('layouts.app')

@section('title', 'Bahan Baku & Kemasan')

@section('content')

<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-box-seam me-2 text-warning"></i>Bahan Baku &amp; Kemasan</h5>
    <div class="d-flex gap-2 align-items-center">
        @can('master.bahan_baku.create')
        <a href="{{ route('master.bahan-baku.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle me-1"></i>Tambah Item
        </a>
        @endcan
        <x-panduan-button slug="bahan-baku" />
    </div>
</div>

<x-info-cabang-aktif :cabang="$cabangAktif" />

<div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm"><div class="card-body py-2 px-3">
            <div class="text-muted small">Total</div>
            <div class="fs-5 fw-bold">{{ $stats['total'] }}</div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm"><div class="card-body py-2 px-3">
            <div class="text-muted small">Bahan Baku</div>
            <div class="fs-5 fw-bold text-warning">{{ $stats['bahan_baku'] }}</div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm"><div class="card-body py-2 px-3">
            <div class="text-muted small">Kemasan</div>
            <div class="fs-5 fw-bold text-info">{{ $stats['kemasan'] }}</div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm"><div class="card-body py-2 px-3">
            <div class="text-muted small">Tambahan Gratis</div>
            <div class="fs-5 fw-bold text-secondary">{{ $stats['tambahan_gratis'] }}</div>
        </div></div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <x-search-box placeholder="Nama / kode item..." col="col-12 col-sm-6 col-md-3" />
            <div class="col-6 col-md-2">
                <select name="tipe" class="form-select form-select-sm">
                    <option value="">Semua Tipe</option>
                    @foreach($tipes as $val => $label)
                    <option value="{{ $val }}" @selected(request('tipe') == $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="kategori" class="form-select form-select-sm">
                    <option value="">Semua Kategori</option>
                    @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" @selected(request('kategori') == $cat->id)>{{ $cat->nama_kategori }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua Status</option>
                    <option value="aktif" @selected(request('status')=='aktif')>Aktif</option>
                    <option value="nonaktif" @selected(request('status')=='nonaktif')>Nonaktif</option>
                </select>
            </div>
            <div class="col-6 col-md-1">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-search"></i></button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Kode</th><th>Nama</th><th>Tipe</th><th>Kategori</th>
                        @unless($cabangAktif)<th>Cabang</th>@endunless
                        <th class="text-end">Harga Standar</th>
                        <th class="text-end">PO Terakhir</th>
                        <th class="text-end">Stok / Min</th><th class="text-center">Status</th><th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                    <tr>
                        <td><code>{{ $item->kode_item }}</code></td>
                        <td>{{ $item->nama_item }}</td>
                        <td><span class="badge bg-light text-dark">{{ $tipes[$item->tipe] ?? $item->tipe }}</span></td>
                        <td>{{ $item->category?->nama_kategori ?? '-' }}</td>
                        @unless($cabangAktif)<td class="small">{{ $item->cabang?->nama_cabang ?? '— belum dipecah' }}</td>@endunless
                        @php $st = $stok->get($item->id); @endphp
                        <td class="text-end">Rp {{ number_format($item->harga_beli_terakhir ?? 0, 0, ',', '.') }}<div class="small text-muted">/ {{ $item->satuan }}</div></td>
                        <td class="text-end small">@if($item->harga_po_terakhir !== null)Rp {{ number_format((float) $item->harga_po_terakhir, 0, ',', '.') }}<div class="text-muted">{{ $item->harga_po_terakhir_at?->format('d/m/Y') }}</div>@else<span class="text-muted">—</span>@endif</td>
                        <td class="text-end small {{ $st && (float) $st->qty_minimum > 0 && (float) $st->qty <= (float) $st->qty_minimum ? 'text-danger fw-semibold' : '' }}">{{ $st ? fmt_qty($st->qty) : '0' }}<div class="text-muted">min {{ fmt_qty($st?->qty_minimum ?? $item->qty_minimum ?? 0) }}</div></td>
                        <td class="text-center">
                            <span class="badge {{ $item->is_active ? 'bg-success' : 'bg-secondary' }}">
                                {{ $item->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                @can('master.bahan_baku.edit')
                                <a href="{{ route('master.bahan-baku.edit', $item) }}" class="btn btn-outline-warning" title="Ubah Data Barang"><i class="bi bi-pencil"></i></a>
                                @endcan
                                @can('master.bahan_baku.delete')
                                <button type="button" class="btn btn-outline-danger"
                                    onclick="if(confirm('Hapus {{ addslashes($item->nama_item) }}?')) document.getElementById('del-{{ $item->id }}').submit()">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <form id="del-{{ $item->id }}" method="POST" action="{{ route('master.bahan-baku.destroy', $item) }}" class="d-none">
                                    @csrf @method('DELETE')
                                </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="{{ $cabangAktif ? 9 : 10 }}" class="text-center text-muted py-4">Belum ada data</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">{{ $items->links() }}</div>

@endsection
