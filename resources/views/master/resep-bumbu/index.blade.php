@extends('layouts.app')

@section('title', 'Master Bumbu Pusat')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0 fw-bold"><i class="bi bi-clipboard2-data me-2 text-primary"></i>Master Bumbu Pusat</h4>
        <small class="text-muted">Referensi resep/komposisi bahan per produk — sumber utama pengelolaan tetap di form Produk Jual</small>
    </div>
    <div class="d-flex gap-2 align-items-center">
        @can('master.resep_bumbu.create')
        <a href="{{ route('master.resep-bumbu.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Tambah Resep
        </a>
        @endcan
        <x-panduan-button slug="resep-bumbu" />
    </div>
</div>

<x-info-cabang-aktif :cabang="$cabangAktif" />

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="alert alert-info py-2 small mb-3">
    <i class="bi bi-info-circle me-1"></i>
    <strong>Master Bumbu Pusat</strong> = referensi resep (komposisi bahan baku per produk) yang dipakai POS untuk auto-potong stok saat checkout.
    Resep yang SUDAH terhubung ke sebuah produk (kolom "Produk Terhubung" terisi) hanya bisa diedit lewat form
    <strong>Master → Produk Jual</strong> (section "Komposisi/Resep") — klik Edit di sini akan otomatis diarahkan ke sana.
    Halaman ini tetap berguna untuk melihat SEMUA resep sekaligus tanpa buka satu-satu form produk.
    Menonaktifkan resep hanya menyembunyikannya dari POS, order lama tetap aman.
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <x-search-box placeholder="Nama / kode resep..." col="col-12 col-sm-6 col-md-4" />
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
            </div>
            @if(request('search'))
            <div class="col-auto">
                <a href="{{ route('master.resep-bumbu.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
            @endif
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:40px">#</th>
                    <th>Nama Resep</th>
                    <th class="d-none d-md-table-cell">Kode</th>
                    <th class="d-none d-md-table-cell">Produk Terhubung</th>
                    <th class="d-none d-md-table-cell">Jenis Menu</th>
                    <th class="text-center">Jumlah Bahan</th>
                    <th class="text-center" style="min-width:90px">Status</th>
                    <th class="text-center" style="min-width:140px">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($resepBumbus as $i => $resep)
                <tr class="{{ !$resep->is_active ? 'text-muted' : '' }}">
                    <td class="small text-muted">{{ $resepBumbus->firstItem() + $i }}</td>
                    <td class="fw-semibold">{{ $resep->nama }}</td>
                    <td class="d-none d-md-table-cell">
                        <code class="text-secondary" style="font-size:0.82rem">{{ $resep->kode }}</code>
                    </td>
                    <td class="d-none d-md-table-cell">
                        @if($resep->item)
                        <span class="badge bg-primary-subtle text-primary">{{ $resep->item->nama_item }}</span>
                        @else
                        <span class="text-muted">— (belum terhubung produk)</span>
                        @endif
                    </td>
                    <td class="d-none d-md-table-cell">{{ $resep->jenisOlahan?->nama ?? '-' }}</td>
                    <td class="text-center">{{ $resep->items_count }}</td>
                    <td class="text-center">
                        @if($resep->is_active)
                        <span class="badge bg-success-subtle text-success">Aktif</span>
                        @else
                        <span class="badge bg-secondary-subtle text-secondary">Nonaktif</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <div class="d-flex gap-1 justify-content-center">
                            @can('master.resep_bumbu.edit')
                            <a href="{{ route('master.resep-bumbu.edit', $resep) }}" class="btn btn-sm btn-outline-warning px-2 py-1" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            @endcan
                            @can('master.resep_bumbu.delete')
                            @if($resep->is_active)
                            <form method="POST" action="{{ route('master.resep-bumbu.destroy', $resep) }}"
                                onsubmit="return confirm('Nonaktifkan resep &quot;{{ $resep->nama }}&quot;? Resep tidak akan tampil lagi di POS.')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger px-2 py-1" title="Nonaktifkan">
                                    <i class="bi bi-x-circle"></i>
                                </button>
                            </form>
                            @endif
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">Belum ada resep bumbu.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($resepBumbus->hasPages())
    <div class="card-footer">{{ $resepBumbus->links() }}</div>
    @endif
</div>
@endsection
