@extends('layouts.app')

@section('title', 'Semua Barang (Overview)')

@push('styles')
<style>
    .stat-mini {
        background: #f8fafc;
        border-radius: 10px;
        padding: 0.75rem 1rem;
        border: 1px solid #e2e8f0;
    }
    .item-card {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        background: white;
        transition: box-shadow 0.15s;
    }
    .item-card:hover {
        box-shadow: 0 4px 16px rgba(0,0,0,0.08);
    }
</style>
@endpush

@section('content')

{{-- PAGE HEADER --}}
<div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-0" style="color:#1e293b">
            <i class="bi bi-box-seam me-2 text-primary"></i>Semua Barang (Overview)
        </h4>
        <small class="text-muted d-block mt-1" style="font-size:0.78rem">
            <i class="bi bi-info-circle me-1"></i>Halaman read-only. CRUD lewat menu <strong>Bahan Baku &amp; Kemasan</strong> atau <strong>Produk Jual</strong>.
        </small>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0" style="font-size:0.8rem">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active">Overview</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2 align-items-center flex-wrap">
        {{-- Sprint 4.34 (2026-10-08) — Master Barang Lengkap jadi READ-ONLY overview.
             Tambah barang diarahkan ke 2 menu khusus sesuai tipe. --}}
        @can('master.bahan_baku.create')
        <a href="{{ route('master.bahan-baku.create') }}" class="btn btn-outline-primary d-flex align-items-center gap-2" title="Buat bahan baku, kemasan, atau tambahan gratis">
            <i class="bi bi-plus-lg"></i>
            <span class="d-none d-md-inline">+ Bahan/Kemasan</span>
        </a>
        @endcan
        @can('master.produk_jual.create')
        <a href="{{ route('master.produk-jual.create') }}" class="btn btn-primary d-flex align-items-center gap-2" title="Buat produk jual untuk POS">
            <i class="bi bi-plus-lg"></i>
            <span class="d-none d-md-inline">+ Produk Jual</span>
        </a>
        @endcan
        <x-panduan-button slug="master-barang" />
    </div>
</div>

<x-info-cabang-aktif :cabang="\App\Support\CabangAktif::model()" />

{{-- STAT CARDS --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-mini d-flex align-items-center gap-3">
            <div style="width:42px;height:42px;border-radius:10px;background:#eff6ff;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="bi bi-boxes text-primary" style="font-size:1.2rem"></i>
            </div>
            <div>
                <div class="fw-bold fs-5 lh-1">{{ $stats['total'] }}</div>
                <div class="text-muted" style="font-size:0.75rem">Total Item</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-mini d-flex align-items-center gap-3">
            <div style="width:42px;height:42px;border-radius:10px;background:#fefce8;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="bi bi-basket text-warning" style="font-size:1.2rem"></i>
            </div>
            <div>
                <div class="fw-bold fs-5 lh-1">{{ $stats['bahan_baku'] }}</div>
                <div class="text-muted" style="font-size:0.75rem">Bahan Baku</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-mini d-flex align-items-center gap-3">
            <div style="width:42px;height:42px;border-radius:10px;background:#f0fdf4;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="bi bi-bag-check text-success" style="font-size:1.2rem"></i>
            </div>
            <div>
                <div class="fw-bold fs-5 lh-1">{{ $stats['produk_jual'] }}</div>
                <div class="text-muted" style="font-size:0.75rem">Produk Jual</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-mini d-flex align-items-center gap-3">
            <div style="width:42px;height:42px;border-radius:10px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="bi bi-dash-circle text-secondary" style="font-size:1.2rem"></i>
            </div>
            <div>
                <div class="fw-bold fs-5 lh-1">{{ $stats['nonaktif'] }}</div>
                <div class="text-muted" style="font-size:0.75rem">Nonaktif</div>
            </div>
        </div>
    </div>
</div>

{{-- FILTER BAR --}}
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('item.index') }}" class="row g-2 align-items-end">
            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                <label class="form-label form-label-sm mb-1">Cari</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control"
                        placeholder="Nama atau kode item..."
                        value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-6 col-sm-3 col-md-2">
                <label class="form-label form-label-sm mb-1">Tipe</label>
                <select name="tipe" class="form-select form-select-sm">
                    <option value="">Semua Tipe</option>
                    <option value="bahan_baku" {{ request('tipe') === 'bahan_baku' ? 'selected' : '' }}>Bahan Baku</option>
                    <option value="produk_jual" {{ request('tipe') === 'produk_jual' ? 'selected' : '' }}>Produk Jual</option>
                    <option value="produk_tambahan" {{ request('tipe') === 'produk_tambahan' ? 'selected' : '' }}>Produk Tambahan</option>
                    <option value="tambahan_gratis" {{ request('tipe') === 'tambahan_gratis' ? 'selected' : '' }}>Tambahan Gratis</option>
                    <option value="kemasan" {{ request('tipe') === 'kemasan' ? 'selected' : '' }}>Kemasan</option>
                    <option disabled>──────────</option>
                    <option value="produk_jadi" {{ request('tipe') === 'produk_jadi' ? 'selected' : '' }}>Produk Jadi (legacy)</option>
                    <option value="lainnya" {{ request('tipe') === 'lainnya' ? 'selected' : '' }}>Lainnya (legacy)</option>
                </select>
            </div>
            <div class="col-6 col-sm-3 col-md-2">
                <label class="form-label form-label-sm mb-1">Jenis</label>
                <select name="jenis" class="form-select form-select-sm">
                    <option value="">Semua Jenis</option>
                    <option value="bahan_baku" {{ request('jenis') === 'bahan_baku' ? 'selected' : '' }}>Bahan Baku</option>
                    <option value="perlengkapan" {{ request('jenis') === 'perlengkapan' ? 'selected' : '' }}>Perlengkapan</option>
                </select>
            </div>
            <div class="col-6 col-sm-3 col-md-2">
                <label class="form-label form-label-sm mb-1">Kategori</label>
                <select name="kategori" class="form-select form-select-sm">
                    <option value="">Semua Kategori</option>
                    @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ request('kategori') == $cat->id ? 'selected' : '' }}>
                        {{ $cat->nama_kategori }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-sm-3 col-md-2">
                <label class="form-label form-label-sm mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua Status</option>
                    <option value="aktif" {{ request('status') === 'aktif' ? 'selected' : '' }}>Aktif</option>
                    <option value="nonaktif" {{ request('status') === 'nonaktif' ? 'selected' : '' }}>Nonaktif</option>
                </select>
            </div>
            <div class="col-12 col-sm-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm px-3">
                    <i class="bi bi-filter me-1"></i>Filter
                </button>
                @if(request()->hasAny(['search','tipe','jenis','kategori','status']))
                <a href="{{ route('item.index') }}" class="btn btn-outline-secondary btn-sm px-3">
                    <i class="bi bi-x-lg me-1"></i>Reset
                </a>
                @endif
            </div>
        </form>
    </div>
</div>

{{-- TABEL - Desktop --}}
<div class="card d-none d-md-block">
    <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between">
        <span class="fw-semibold">
            <i class="bi bi-list-ul me-2 text-primary"></i>Daftar Item
        </span>
        <small class="text-muted">{{ $items->total() }} item ditemukan</small>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="px-4" style="width:90px">Kode</th>
                    <th>Nama Item</th>
                    <th class="d-none d-lg-table-cell">Kategori</th>
                    <th>Tipe</th>
                    <th class="d-none d-lg-table-cell">Jenis</th>
                    <th class="d-none d-lg-table-cell">Satuan</th>
                    <th class="text-end d-none d-lg-table-cell">Harga Standar</th>
                    <th class="text-end d-none d-xl-table-cell">Harga Jual</th>
                    <th class="text-center">Status</th>
                    <th class="text-center px-4" style="width:100px">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                <tr>
                    <td class="px-4">
                        <code class="px-2 py-1 rounded" style="background:#f1f5f9;color:#475569;font-size:0.78rem">
                            {{ $item->kode_item }}
                        </code>
                    </td>
                    <td>
                        <div class="fw-semibold" style="color:#1e293b;font-size:0.9rem">{{ $item->nama_item }}</div>
                        @if($item->deskripsi)
                        <div class="text-muted" style="font-size:0.75rem">{{ Str::limit($item->deskripsi, 50) }}</div>
                        @endif
                    </td>
                    <td class="d-none d-lg-table-cell">
                        <span class="text-muted" style="font-size:0.85rem">
                            {{ $item->category?->nama_kategori ?? '—' }}
                        </span>
                    </td>
                    <td>
                        @php
                            // Sprint 4.33 (2026-10-01) — tambah 3 tipe baru Tahap 2.5
                            // (produk_jual/produk_tambahan/tambahan_gratis). Legacy produk_jadi/
                            // lainnya tetap didukung utk backward compat data historis.
                            $tipeBadge = match($item->tipe) {
                                'bahan_baku'      => 'bg-warning-subtle text-warning',
                                'produk_jual',
                                'produk_jadi'     => 'bg-success-subtle text-success',
                                'produk_tambahan' => 'bg-primary-subtle text-primary',
                                'tambahan_gratis' => 'bg-info-subtle text-info',
                                'kemasan'         => 'bg-secondary-subtle text-secondary',
                                default           => 'bg-light text-muted',
                            };
                            $tipeLabel = match($item->tipe) {
                                'bahan_baku'      => 'Bahan Baku',
                                'produk_jual'     => 'Produk Jual',
                                'produk_jadi'     => 'Produk Jadi',
                                'produk_tambahan' => 'Produk Tambahan',
                                'tambahan_gratis' => 'Tambahan Gratis',
                                'kemasan'         => 'Kemasan',
                                default           => 'Lainnya',
                            };
                        @endphp
                        <span class="badge {{ $tipeBadge }}" style="font-size:0.72rem">{{ $tipeLabel }}</span>
                    </td>
                    <td class="d-none d-lg-table-cell">
                        <span class="badge {{ $item->jenis?->value === 'perlengkapan' ? 'bg-info-subtle text-info' : 'bg-secondary-subtle text-secondary' }}" style="font-size:0.72rem">
                            {{ $item->jenis?->label() ?? 'Bahan Baku' }}
                        </span>
                    </td>
                    <td class="d-none d-lg-table-cell">
                        <span class="text-muted" style="font-size:0.85rem">{{ $item->satuan }}</span>
                    </td>
                    <td class="text-end d-none d-lg-table-cell">
                        <span style="font-size:0.85rem">
                            @if($item->harga_beli_terakhir)
                                Rp {{ number_format($item->harga_beli_terakhir, 0, ',', '.') }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </span>
                    </td>
                    <td class="text-end d-none d-xl-table-cell">
                        <span style="font-size:0.85rem">
                            @if($item->harga_jual)
                                Rp {{ number_format($item->harga_jual, 0, ',', '.') }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </span>
                    </td>
                    <td class="text-center">
                        @if($item->is_active)
                            <span class="badge bg-success-subtle text-success" style="font-size:0.72rem">
                                <i class="bi bi-circle-fill me-1" style="font-size:0.4rem"></i>Aktif
                            </span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary" style="font-size:0.72rem">
                                <i class="bi bi-circle me-1" style="font-size:0.4rem"></i>Nonaktif
                            </span>
                        @endif
                    </td>
                    <td class="text-center px-4">
                        <div class="d-flex align-items-center justify-content-center gap-1">
                            {{-- Sprint 4.34 — tombol Edit redirect ke menu sesuai tipe
                                 (lewat ItemController::edit() yg redirect). Tombol Hapus
                                 dihapus — hapus barang lewat menu masing-masing. --}}
                            @can('item.view')
                            <a href="{{ route('item.show', $item) }}"
                                class="btn btn-sm btn-outline-info px-2 py-1"
                                title="Lihat Detail" style="min-width:32px;min-height:32px">
                                <i class="bi bi-eye"></i>
                            </a>
                            @endcan
                            @php $tipeMenu = in_array($item->tipe, ['produk_jual','produk_tambahan'], true) ? 'Produk Jual' : 'Bahan Baku'; @endphp
                            @can('item.view')
                            <a href="{{ route('item.edit', $item) }}"
                                class="btn btn-sm btn-outline-primary px-2 py-1"
                                title="Edit ({{ $tipeMenu }})" style="min-width:32px;min-height:32px">
                                <i class="bi bi-pencil"></i>
                            </a>
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" class="text-center py-5">
                        <div class="text-muted">
                            <i class="bi bi-inbox" style="font-size:2.5rem;opacity:0.3"></i>
                            <p class="mt-2 mb-0">Tidak ada item ditemukan.</p>
                            @if(request()->hasAny(['search','tipe','jenis','kategori','status']))
                                <a href="{{ route('item.index') }}" class="btn btn-sm btn-outline-primary mt-2">Reset Filter</a>
                            @else
                                {{-- Sprint 4.34 — empty state: arahkan ke 2 menu khusus --}}
                                @can('master.bahan_baku.create')
                                <a href="{{ route('master.bahan-baku.create') }}" class="btn btn-sm btn-outline-primary mt-2">
                                    <i class="bi bi-plus-lg me-1"></i>+ Bahan/Kemasan
                                </a>
                                @endcan
                                @can('master.produk_jual.create')
                                <a href="{{ route('master.produk-jual.create') }}" class="btn btn-sm btn-primary mt-2">
                                    <i class="bi bi-plus-lg me-1"></i>+ Produk Jual
                                </a>
                                @endcan
                            @endif
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($items->hasPages())
    <div class="card-footer py-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <small class="text-muted">
            Menampilkan {{ $items->firstItem() }}–{{ $items->lastItem() }} dari {{ $items->total() }} data
        </small>
        {{ $items->links('pagination::bootstrap-5') }}
    </div>
    @endif
</div>

{{-- MOBILE CARD VIEW --}}
<div class="d-md-none">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <small class="text-muted">{{ $items->total() }} item ditemukan</small>
    </div>

    @forelse($items as $item)
    <div class="item-card p-3 mb-3">
        <div class="d-flex align-items-start justify-content-between gap-2">
            <div class="flex-grow-1 min-w-0">
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <span class="fw-semibold" style="color:#1e293b">{{ $item->nama_item }}</span>
                    @php
                        // Sprint 4.33 (2026-10-01) — mobile card view, mirror desktop mapping.
                        $tipeBadge = match($item->tipe) {
                            'bahan_baku'      => 'bg-warning-subtle text-warning',
                            'produk_jual',
                            'produk_jadi'     => 'bg-success-subtle text-success',
                            'produk_tambahan' => 'bg-primary-subtle text-primary',
                            'tambahan_gratis' => 'bg-info-subtle text-info',
                            'kemasan'         => 'bg-secondary-subtle text-secondary',
                            default           => 'bg-light text-muted',
                        };
                        $tipeLabel = match($item->tipe) {
                            'bahan_baku'      => 'Bahan Baku',
                            'produk_jual'     => 'Produk Jual',
                            'produk_jadi'     => 'Produk Jadi',
                            'produk_tambahan' => 'Produk Tambahan',
                            'tambahan_gratis' => 'Tambahan Gratis',
                            'kemasan'         => 'Kemasan',
                            default           => 'Lainnya',
                        };
                    @endphp
                    <span class="badge {{ $tipeBadge }}" style="font-size:0.7rem">{{ $tipeLabel }}</span>
                    @if($item->jenis?->value === 'perlengkapan')
                    <span class="badge bg-info-subtle text-info" style="font-size:0.7rem">Perlengkapan</span>
                    @endif
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <code style="background:#f1f5f9;color:#475569;font-size:0.73rem;padding:0.15rem 0.4rem;border-radius:4px">
                        {{ $item->kode_item }}
                    </code>
                    <small class="text-muted">{{ $item->category?->nama_kategori ?? '—' }}</small>
                </div>
                @if($item->harga_beli_terakhir)
                <div class="mt-1" style="font-size:0.8rem">
                    <span class="text-muted">Harga Standar:</span>
                    <span class="fw-semibold ms-1">Rp {{ number_format($item->harga_beli_terakhir, 0, ',', '.') }}</span>
                </div>
                @endif
            </div>
            <div class="d-flex flex-column align-items-end gap-2 flex-shrink-0">
                @if($item->is_active)
                    <span class="badge bg-success-subtle text-success" style="font-size:0.7rem">Aktif</span>
                @else
                    <span class="badge bg-secondary-subtle text-secondary" style="font-size:0.7rem">Nonaktif</span>
                @endif
                <div class="d-flex gap-1">
                    @can('item.view')
                    <a href="{{ route('item.show', $item) }}"
                        class="btn btn-sm btn-outline-info px-2"
                        title="Lihat Detail" style="min-height:36px">
                        <i class="bi bi-eye"></i>
                    </a>
                    @endcan
                    {{-- Sprint 4.34 — redirect via ItemController::edit() --}}
                    @can('item.view')
                    <a href="{{ route('item.edit', $item) }}"
                        class="btn btn-sm btn-outline-primary px-2"
                        title="Edit (ke menu {{ in_array($item->tipe, ['produk_jual','produk_tambahan'], true) ? 'Produk Jual' : 'Bahan Baku' }})"
                        style="min-height:36px">
                        <i class="bi bi-pencil"></i>
                    </a>
                    @endcan
                </div>
            </div>
        </div>
    </div>
    @empty
    <div class="text-center py-5 text-muted">
        <i class="bi bi-inbox" style="font-size:2.5rem;opacity:0.3"></i>
        <p class="mt-2">Tidak ada item ditemukan.</p>
        @can('master.bahan_baku.create')
        <a href="{{ route('master.bahan-baku.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Tambah Item
        </a>
        @endcan
    </div>
    @endforelse

    @if($items->hasPages())
    <div class="mt-3">
        {{ $items->links('pagination::bootstrap-5') }}
    </div>
    @endif
</div>

@include('components.cascade-delete-modal', ['entity' => 'Barang', 'childList' => ['Stok di semua lokasi', 'Detail baris order penjualan', 'Detail baris purchase order']])

@push('scripts')
<script>
function confirmHapus(routePrefix, id, nama) {
    document.getElementById('cascadeModalEntityName').textContent = nama;
    document.getElementById('cascadeModalForm').action = '/' + routePrefix + '/' + id;
    new bootstrap.Modal(document.getElementById('cascadeDeleteModal')).show();
}
</script>
@endpush

@endsection
