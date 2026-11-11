@extends('layouts.app')

@section('title', 'Resep Produksi')

@section('content')
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-droplet me-2 text-success"></i>Produksi Parfum</h5>
    <div class="d-flex gap-2 align-items-center">
        <x-panduan-button slug="produksi" />
        @can('produksi.resep.kelola')
        <a href="{{ route('produksi.resep.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>Resep Baru</a>
        @endcan
    </div>
</div>

@include('produksi._tabs')
@include('produksi._tanpa-gudang')

<div class="alert alert-light border small">
    <i class="bi bi-info-circle me-1"></i>
    Resep Produksi = takaran bahan untuk <strong>meracik cairan parfum</strong> (bibit + fixative + alkohol, bisa diperam)
    atau <strong>mengisi botol</strong> (cairan + botol + sprayer + label + box). Barang hasilnya harus sudah terdaftar di
    <strong>Bahan Baku &amp; Kemasan</strong>. Ini berbeda dengan komposisi Produk Jual, yang dipotong saat produk terjual.
</div>

<div class="card"><div class="table-responsive">
<table class="table table-sm align-middle mb-0">
    <thead><tr><th>Barang Hasil</th>@if($gudang->count() > 1)<th>Gudang</th>@endif<th class="text-end">Hasil per resep</th><th>Bahan</th><th class="text-end">Perkiraan modal / unit <x-tooltip key="produksi.modal" /></th><th>Status</th><th></th></tr></thead>
    <tbody>
    @forelse($reseps as $r)
    @php
        $biaya = $r->items->sum(fn ($b) => \App\Support\KonversiSatuan::keSatuanStok((float) $b->qty, $b->satuan, $b->bahan?->satuan) * (float) ($harga[$b->item_id]['harga'] ?? 0));
        $perUnit = (float) $r->qty_hasil > 0 ? $biaya / (float) $r->qty_hasil : 0;
    @endphp
    <tr>
        <td class="fw-semibold">{{ $r->item?->nama_item }}</td>
        @if($gudang->count() > 1)<td>{{ $r->item?->cabang?->nama_cabang ?? '—' }}</td>@endif
        <td class="text-end">{{ \App\Services\ProduksiService::angka((float) $r->qty_hasil) }} {{ $r->item?->satuan }}</td>
        <td class="small">
            {{ $r->items->map(fn ($b) => ($b->bahan?->nama_item ?? '?') . ' ' . \App\Services\ProduksiService::angka((float) $b->qty) . ' ' . $b->satuan)->implode(', ') }}
        </td>
        <td class="text-end">Rp {{ number_format($perUnit, 0, ',', '.') }}</td>
        <td><span class="badge bg-{{ $r->is_active ? 'success' : 'secondary' }}">{{ $r->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
        <td class="text-end text-nowrap">
            @can('produksi.create')
            @if($r->is_active)
            <a href="{{ route('produksi.create', ['resep' => $r->id]) }}" class="btn btn-outline-success btn-sm">Produksi</a>
            @endif
            @endcan
            @can('produksi.resep.kelola')
            <a href="{{ route('produksi.resep.edit', $r) }}" class="btn btn-outline-primary btn-sm">Ubah</a>
            <form method="POST" action="{{ route('produksi.resep.destroy', $r) }}" class="d-inline" onsubmit="return confirm('Hapus resep produksi {{ addslashes($r->item?->nama_item) }}? Riwayat produksi tetap tersimpan.')">
                @csrf @method('DELETE')
                <button class="btn btn-outline-danger btn-sm">Hapus</button>
            </form>
            @endcan
        </td>
    </tr>
    @empty
    <tr><td colspan="7" class="text-center text-muted py-4">Belum ada resep produksi.</td></tr>
    @endforelse
    </tbody>
</table>
</div></div>
@endsection
