@extends('layouts.app')

@section('title', 'Produk Online')

@section('content')
@php $rp = fn ($n) => $n === null ? '—' : 'Rp ' . number_format($n, 0, ',', '.'); @endphp
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-bag-heart me-2 text-success"></i>Produk Online</h5>
    <div class="d-flex gap-2 align-items-center">
        <x-panduan-button slug="produk-online" />
        <a href="{{ route('toko-online.produk.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>Tambah Produk Online</a>
    </div>
</div>

<div class="alert alert-light border small">
    <i class="bi bi-info-circle me-1"></i>
    Produk yang tampil di menu <strong>Belanja</strong> aplikasi member. Harga yang dipakai untuk seorang member:
    <strong>harga khusus akun</strong> → <strong>harga jabatan</strong> → <strong>harga gudang</strong> (beli langsung ke gudang) → <strong>harga tetap</strong>.
    Produk tanpa harga yang berlaku tidak tampil untuk member itu.
</div>

<div class="card"><div class="table-responsive">
<table class="table table-sm align-middle mb-0">
    <thead><tr><th>Produk</th><th>Kategori</th><th class="text-end">Harga tetap <x-tooltip key="produk_online.harga_tetap" /></th><th class="text-end">Harga gudang <x-tooltip key="produk_online.harga_gudang" /></th><th class="text-center">Harga jabatan</th><th class="text-center">Harga khusus</th><th class="text-end">Stok gudang</th><th>Status</th><th></th></tr></thead>
    <tbody>
    @forelse($produk as $p)
    <tr class="{{ $p->aktif ? '' : 'text-muted' }}">
        <td>
            <div class="d-flex align-items-center gap-2">
                @if($p->fotoUrl())<img src="{{ $p->fotoUrl() }}" alt="" width="40" height="40" class="rounded object-fit-cover">@else<span class="d-inline-flex align-items-center justify-content-center rounded bg-light" style="width:40px;height:40px"><i class="bi bi-droplet text-muted"></i></span>@endif
                <div><div class="fw-semibold">{{ $p->nama_tampil }}</div><div class="small text-muted">{{ $p->item?->kode_item }} · {{ $p->item?->cabang?->nama_cabang }}</div></div>
            </div>
        </td>
        <td class="small">{{ $p->kategori ?: '—' }}</td>
        <td class="text-end">{{ $rp($p->hargaJenis('tetap')) }}</td>
        <td class="text-end">{{ $rp($p->hargaJenis('gudang')) }}</td>
        <td class="text-center">{{ $p->harga->where('jenis', 'jabatan')->count() ?: '—' }}</td>
        <td class="text-center">{{ $p->harga->where('jenis', 'akun')->count() ?: '—' }}</td>
        <td class="text-end">{{ rtrim(rtrim(number_format($stok[$p->id] ?? 0, 3, ',', '.'), '0'), ',') }} {{ $p->item?->satuan }}</td>
        <td><span class="badge bg-{{ $p->aktif ? 'success' : 'secondary' }}">{{ $p->aktif ? 'Tampil' : 'Disembunyikan' }}</span></td>
        <td class="text-end"><a href="{{ route('toko-online.produk.edit', $p) }}" class="btn btn-outline-primary btn-sm">Ubah</a></td>
    </tr>
    @empty
    <tr><td colspan="9" class="text-center text-muted py-4">Belum ada produk online. Tekan <strong>Tambah Produk Online</strong>, lalu pilih parfum botol dari gudang.</td></tr>
    @endforelse
    </tbody>
</table>
</div></div>
@endsection
