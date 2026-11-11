@extends('layouts.app')

@section('title', 'Edit Item — ' . $item->nama_item)

@push('styles')
<style>
    .form-card { border: 1px solid #e2e8f0; border-radius: 12px; background: white; }
    .section-title {
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #64748b;
        border-bottom: 1px solid #f1f5f9;
        padding-bottom: 0.6rem;
        margin-bottom: 1rem;
    }
    .danger-zone {
        border: 1.5px solid #fecaca;
        border-radius: 12px;
        background: #fff5f5;
    }
</style>
@endpush

@section('content')

{{-- PAGE HEADER --}}
<div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-0" style="color:#1e293b">
            <i class="bi bi-pencil-square me-2 text-primary"></i>Edit Item
        </h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0" style="font-size:0.8rem">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('item.index') }}" class="text-decoration-none">Item</a></li>
                <li class="breadcrumb-item active">Edit — {{ $item->kode_item }}</li>
            </ol>
        </nav>
    </div>
</div>

{{-- FORM EDIT (terpisah dari form hapus) --}}
<form method="POST" action="{{ route('item.update', $item) }}" id="formEditItem">
@csrf
@method('PUT')

<div class="row g-4">
    {{-- KOLOM FORM --}}
    <div class="col-12 col-lg-8">
        <div class="form-card p-4">

            {{-- Kode & Nama --}}
            <div class="section-title"><i class="bi bi-info-circle me-1"></i>Informasi Dasar</div>
            <div class="row g-3 mb-3">
                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold" style="font-size:0.85rem">
                        Kode Item <span class="text-danger">*</span>
                    </label>
                    <input type="text" name="kode_item" id="kode_item"
                        class="form-control @error('kode_item') is-invalid @enderror"
                        value="{{ old('kode_item', $item->kode_item) }}"
                        style="text-transform:uppercase"
                        required>
                    @error('kode_item')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12 col-md-8">
                    <label class="form-label fw-semibold" style="font-size:0.85rem">
                        Nama Item <span class="text-danger">*</span>
                    </label>
                    <input type="text" name="nama_item"
                        class="form-control @error('nama_item') is-invalid @enderror"
                        value="{{ old('nama_item', $item->nama_item) }}"
                        required>
                    @error('nama_item')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            {{-- Tipe & Satuan --}}
            <div class="row g-3 mb-3">
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold" style="font-size:0.85rem">
                        Tipe Item <span class="text-danger">*</span>
                    </label>
                    <select name="tipe" class="form-select @error('tipe') is-invalid @enderror" required>
                        <option value="">— Pilih Tipe —</option>
                        <option value="bahan_baku" {{ old('tipe', $item->tipe) === 'bahan_baku' ? 'selected' : '' }}>Bahan Baku</option>
                        <option value="kemasan" {{ old('tipe', $item->tipe) === 'kemasan' ? 'selected' : '' }}>Kemasan</option>
                        <option value="tambahan_gratis" {{ old('tipe', $item->tipe) === 'tambahan_gratis' ? 'selected' : '' }}>Tambahan Gratis</option>
                        <option value="produk_jual" {{ old('tipe', $item->tipe) === 'produk_jual' ? 'selected' : '' }}>Produk Jual</option>
                        <option value="produk_tambahan" {{ old('tipe', $item->tipe) === 'produk_tambahan' ? 'selected' : '' }}>Produk Tambahan</option>
                        @if(in_array($item->tipe, ['produk_jadi','lainnya'], true))
                        <option value="{{ $item->tipe }}" selected>{{ $item->tipe }} (nilai lama)</option>
                        @endif
                    </select>
                    @error('tipe')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold" style="font-size:0.85rem">
                        Satuan <span class="text-danger">*</span>
                    </label>
                    <input type="text" name="satuan"
                        class="form-control @error('satuan') is-invalid @enderror"
                        value="{{ old('satuan', $item->satuan) }}"
                        required>
                    @error('satuan')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            {{-- Jenis & Lacak Stok (Fase 5 — Modul Perlengkapan) --}}
            <div class="row g-3 mb-3">
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold d-block" style="font-size:0.85rem">
                        Jenis Item <span class="text-danger">*</span>
                    </label>
                    <div class="d-flex gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="jenis" id="jenisBahanBaku"
                                value="bahan_baku" {{ old('jenis', $item->jenis?->value) === 'bahan_baku' ? 'checked' : '' }}>
                            <label class="form-check-label" for="jenisBahanBaku" style="font-size:0.85rem">Bahan Baku</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="jenis" id="jenisPerlengkapan"
                                value="perlengkapan" {{ old('jenis', $item->jenis?->value) === 'perlengkapan' ? 'checked' : '' }}>
                            <label class="form-check-label" for="jenisPerlengkapan" style="font-size:0.85rem">Perlengkapan</label>
                        </div>
                    </div>
                    @error('jenis')
                        <div class="text-danger" style="font-size:0.78rem">{{ $message }}</div>
                    @enderror
                    <div class="form-text" style="font-size:0.75rem">
                        Klasifikasi terpisah dari Tipe — untuk barang habis pakai non-produksi (masker, pulpen, tissue, dll)
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold d-block" style="font-size:0.85rem">Lacak Stok</label>
                    <div class="form-check form-switch mt-2" style="padding-left:2.5rem">
                        <input class="form-check-input" type="checkbox" name="track_stok" id="track_stok"
                            value="1" {{ old('track_stok', $item->track_stok) ? 'checked' : '' }}
                            style="width:2.5rem;height:1.25rem">
                        <label class="form-check-label fw-semibold" for="track_stok" style="font-size:0.85rem">
                            Lacak Qty Stok
                        </label>
                    </div>
                    <div class="form-text" style="font-size:0.75rem">
                        Uncheck kalau habis pakai tanpa perlu tracking qty (misal: konsumsi harian pulpen)
                    </div>
                </div>
            </div>

            {{-- Kategori & Status --}}
            <div class="row g-3 mb-3">
                <div class="col-12 col-md-8">
                    <label class="form-label fw-semibold" style="font-size:0.85rem">Kategori</label>
                    <div class="input-group">
                        <select name="item_category_id" class="form-select @error('item_category_id') is-invalid @enderror">
                            <option value="">— Pilih Kategori —</option>
                            @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ old('item_category_id', $item->item_category_id) == $cat->id ? 'selected' : '' }}>
                                {{ $cat->nama_kategori }}
                            </option>
                            @endforeach
                        </select>
                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalKategori"
                            title="Tambah kategori baru">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                        @error('item_category_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold d-block" style="font-size:0.85rem">Status</label>
                    <div class="form-check form-switch mt-2" style="padding-left:2.5rem">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                            value="1" {{ old('is_active', $item->is_active) ? 'checked' : '' }}
                            style="width:2.5rem;height:1.25rem">
                        <label class="form-check-label fw-semibold" for="is_active" style="font-size:0.85rem">
                            Aktif
                        </label>
                    </div>
                </div>
            </div>

            {{-- Harga --}}
            <div class="section-title mt-4"><i class="bi bi-currency-dollar me-1"></i>Harga</div>
            <div class="row g-3 mb-3">
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold" style="font-size:0.85rem">Harga Standar</label>
                    <x-input-rupiah name="harga_beli_terakhir" :value="old('harga_beli_terakhir', $item->harga_beli_terakhir)" />
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold" style="font-size:0.85rem">Harga Jual</label>
                    <x-input-rupiah name="harga_jual" :value="old('harga_jual', $item->harga_jual)" />
                </div>
            </div>

            {{-- Sprint Unit Family (2026-09-27) --}}
            <div class="section-title mt-4"><i class="bi bi-box2 me-1"></i>Unit Beli <span class="text-muted small">(Opsional)</span></div>
            <x-unit-beli-section :unitBeli="$item->unit_beli" :isiPerUnitBeli="$item->isi_per_unit_beli" />

            {{-- Qty Minimum --}}
            <div class="section-title mt-4"><i class="bi bi-bell me-1"></i>Stok Minimum</div>
            <div class="row g-3 mb-3">
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold" style="font-size:0.85rem">Qty Minimum</label>
                    <input type="number" name="qty_minimum"
                        class="form-control @error('qty_minimum') is-invalid @enderror"
                        min="0" step="0.001"
                        value="{{ old('qty_minimum', $item->qty_minimum) }}">
                    @error('qty_minimum')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="alert alert-warning py-2 px-3 mt-2 mb-0" style="font-size:0.78rem">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Item <strong>Perlengkapan</strong> wajib isi Qty Minimum &gt; 0 supaya notifikasi stok menipis aktif.
                    </div>
                </div>
            </div>

            {{-- Deskripsi --}}
            <div class="section-title mt-4"><i class="bi bi-card-text me-1"></i>Deskripsi</div>
            <div class="mb-0">
                <label class="form-label fw-semibold" style="font-size:0.85rem">Deskripsi (Opsional)</label>
                <textarea name="deskripsi" rows="3"
                    class="form-control @error('deskripsi') is-invalid @enderror"
                    placeholder="Keterangan tambahan...">{{ old('deskripsi', $item->deskripsi) }}</textarea>
                @error('deskripsi')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>

    {{-- KOLOM KANAN --}}
    <div class="col-12 col-lg-4">
        {{-- Info Item --}}
        <div class="form-card p-4 mb-4">
            <div class="section-title"><i class="bi bi-clock-history me-1"></i>Info Perubahan</div>
            <div style="font-size:0.82rem">
                <div class="d-flex justify-content-between py-2" style="border-bottom:1px solid #f1f5f9">
                    <span class="text-muted">Dibuat</span>
                    <span>{{ $item->created_at?->format('d/m/Y H:i') ?? '—' }}</span>
                </div>
                <div class="d-flex justify-content-between py-2">
                    <span class="text-muted">Diupdate</span>
                    <span>{{ $item->updated_at?->format('d/m/Y H:i') ?? '—' }}</span>
                </div>
            </div>
        </div>

        {{-- Tombol Aksi --}}
        <div class="form-card p-4 mb-4">
            <div class="section-title"><i class="bi bi-lightning me-1"></i>Aksi</div>
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary" style="min-height:44px">
                    <i class="bi bi-check-lg me-2"></i>Simpan Perubahan
                </button>
                <a href="{{ route('item.index') }}" class="btn btn-outline-secondary" style="min-height:44px">
                    <i class="bi bi-arrow-left me-2"></i>Batal
                </a>
            </div>
        </div>
    </div>
</div>

</form>

{{-- DANGER ZONE — FORM HAPUS TERPISAH --}}
@if($authUser->canAccessAllBranches() || $authUser->role?->value === 'admin_gudang')
<div class="row mt-2">
    <div class="col-12 col-lg-8">
        <div class="danger-zone p-4">
            <h6 class="fw-bold text-danger mb-1">
                <i class="bi bi-exclamation-triangle me-2"></i>Zona Berbahaya
            </h6>
            <p class="text-muted mb-3" style="font-size:0.85rem">
                Menghapus item bersifat permanen dan tidak dapat dibatalkan.
                Pastikan item ini tidak digunakan di transaksi manapun sebelum menghapus.
            </p>
            <form method="POST" action="{{ route('item.destroy', $item) }}" id="formHapusItem">
                @csrf
                @method('DELETE')
                <button type="submit"
                    class="btn btn-danger"
                    style="min-height:44px"
                    onclick="return confirm('PERHATIAN: Hapus item {{ addslashes($item->nama_item) }} secara permanen? Tindakan ini tidak dapat dibatalkan.')">
                    <i class="bi bi-trash3 me-2"></i>Hapus Item Ini
                </button>
            </form>
        </div>
    </div>
</div>
@endif

{{-- MODAL TAMBAH KATEGORI --}}
<div class="modal fade" id="modalKategori" tabindex="-1" aria-labelledby="modalKategoriLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="modalKategoriLabel">
                    <i class="bi bi-tag me-2 text-primary"></i>Tambah Kategori Baru
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('item.kategori.store') }}">
                @csrf
                <input type="hidden" name="_redirect_back" value="1">
                <div class="modal-body">
                    <div class="alert alert-info py-2 small mb-3">
                        <i class="bi bi-info-circle me-1"></i>
                        Kategori baru TIDAK langsung muncul sebagai filter di grid POS — chip kategori di POS
                        cuma menampilkan kategori yang sudah punya minimal 1 produk AKTIF bertipe Produk Jual/Produk Tambahan.
                        Rename atau hapus kategori belum bisa lewat halaman ini, hubungi developer kalau perlu.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" style="font-size:0.85rem">
                            Nama Kategori <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="nama_kategori" class="form-control"
                            placeholder="cth: Daging Segar, Bumbu, Kemasan Plastik..."
                            required autofocus>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold" style="font-size:0.85rem">
                            Kode Kategori <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="kode_kategori" class="form-control"
                            placeholder="cth: CAT-001"
                            style="text-transform:uppercase" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="min-height:44px;min-width:100px">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-primary" style="min-height:44px;min-width:100px">
                        <i class="bi bi-check-lg me-1"></i>Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    // Auto uppercase kode item
    document.getElementById('kode_item').addEventListener('input', function() {
        const pos = this.selectionStart;
        this.value = this.value.toUpperCase();
        this.setSelectionRange(pos, pos);
    });
</script>
@endpush
