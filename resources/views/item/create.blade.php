@extends('layouts.app')

@section('title', 'Tambah Item')

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
    .guide-item { font-size: 0.82rem; padding: 0.35rem 0; border-bottom: 1px solid #f1f5f9; }
    .guide-item:last-child { border-bottom: none; }
    .guide-badge { font-size: 0.68rem; padding: 0.15rem 0.5rem; border-radius: 20px; }
</style>
@endpush

@section('content')

{{-- PAGE HEADER --}}
<div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-0" style="color:#1e293b">
            <i class="bi bi-plus-circle me-2 text-primary"></i>Tambah Item
        </h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0" style="font-size:0.8rem">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('item.index') }}" class="text-decoration-none">Item</a></li>
                <li class="breadcrumb-item active">Tambah</li>
            </ol>
        </nav>
    </div>
</div>

<form method="POST" action="{{ route('item.store') }}" id="formTambahItem">
@csrf

<div class="row g-4">
    {{-- KOLOM FORM --}}
    <div class="col-12 col-lg-8">
        <div class="form-card p-4">

            {{-- Kode & Nama --}}
            <div class="section-title"><i class="bi bi-info-circle me-1"></i>Informasi Dasar</div>
            <div class="row g-3 mb-3">
                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold" style="font-size:0.85rem">
                        Kode Item <span class="text-danger">*</span> <x-tooltip key="item.kode_item" />
                    </label>
                    <input type="text" name="kode_item" id="kode_item"
                        class="form-control @error('kode_item') is-invalid @enderror"
                        placeholder="cth: BB-001"
                        value="{{ old('kode_item') }}"
                        style="text-transform:uppercase"
                        required>
                    @error('kode_item')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text" style="font-size:0.75rem">Kode unik untuk item ini</div>
                </div>
                <div class="col-12 col-md-8">
                    <label class="form-label fw-semibold" style="font-size:0.85rem">
                        Nama Item <span class="text-danger">*</span>
                    </label>
                    <input type="text" name="nama_item"
                        class="form-control @error('nama_item') is-invalid @enderror"
                        placeholder="cth: Daging Sapi Murni"
                        value="{{ old('nama_item') }}"
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
                        Tipe Item <span class="text-danger">*</span> <x-tooltip key="item.tipe" />
                    </label>
                    <select name="tipe" class="form-select @error('tipe') is-invalid @enderror" required>
                        <option value="">— Pilih Tipe —</option>
                        <option value="bahan_baku" {{ old('tipe') === 'bahan_baku' ? 'selected' : '' }}>Bahan Baku</option>
                        <option value="kemasan" {{ old('tipe') === 'kemasan' ? 'selected' : '' }}>Kemasan</option>
                        <option value="tambahan_gratis" {{ old('tipe') === 'tambahan_gratis' ? 'selected' : '' }}>Tambahan Gratis</option>
                        <option value="produk_jual" {{ old('tipe') === 'produk_jual' ? 'selected' : '' }}>Produk Jual</option>
                        <option value="produk_tambahan" {{ old('tipe') === 'produk_tambahan' ? 'selected' : '' }}>Produk Tambahan</option>
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
                        placeholder="kg / pcs / liter / pack"
                        value="{{ old('satuan') }}"
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
                                value="bahan_baku" {{ old('jenis', 'bahan_baku') === 'bahan_baku' ? 'checked' : '' }}>
                            <label class="form-check-label" for="jenisBahanBaku" style="font-size:0.85rem">Bahan Baku</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="jenis" id="jenisPerlengkapan"
                                value="perlengkapan" {{ old('jenis') === 'perlengkapan' ? 'checked' : '' }}>
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
                            value="1" {{ old('track_stok', '1') ? 'checked' : '' }}
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
                    <label class="form-label fw-semibold" style="font-size:0.85rem">Kategori <x-tooltip key="item.item_category_id" /></label>
                    <div class="input-group">
                        <select name="item_category_id" class="form-select @error('item_category_id') is-invalid @enderror">
                            <option value="">— Pilih Kategori —</option>
                            @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ old('item_category_id') == $cat->id ? 'selected' : '' }}>
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
                    <div class="form-text" style="font-size:0.75rem">
                        Klik <i class="bi bi-plus-lg"></i> untuk tambah kategori baru
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold d-block" style="font-size:0.85rem">Status</label>
                    <div class="form-check form-switch mt-2" style="padding-left:2.5rem">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                            value="1" {{ old('is_active', '1') ? 'checked' : '' }}
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
                    <label class="form-label fw-semibold" style="font-size:0.85rem">Harga Standar <x-tooltip key="item.harga_beli_terakhir" /></label>
                    <x-input-rupiah name="harga_beli_terakhir"
                        :value="old('harga_beli_terakhir', 0)"
                        placeholder="0" />
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold" style="font-size:0.85rem">Harga Jual <x-tooltip key="item.harga_jual" /></label>
                    <x-input-rupiah name="harga_jual"
                        :value="old('harga_jual', 0)"
                        placeholder="0" />
                </div>
            </div>

            {{-- Sprint Unit Family (2026-09-27) --}}
            <div class="section-title mt-4"><i class="bi bi-box2 me-1"></i>Unit Beli <span class="text-muted small">(Opsional)</span></div>
            <x-unit-beli-section />

            {{-- Qty Minimum --}}
            <div class="section-title mt-4"><i class="bi bi-bell me-1"></i>Stok Minimum</div>
            <div class="row g-3 mb-3">
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold" style="font-size:0.85rem">Qty Minimum <x-tooltip key="item.qty_minimum" /></label>
                    <input type="number" name="qty_minimum"
                        class="form-control @error('qty_minimum') is-invalid @enderror"
                        placeholder="0"
                        min="0" step="0.001"
                        value="{{ old('qty_minimum', 0) }}">
                    @error('qty_minimum')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text" style="font-size:0.75rem">
                        Sistem akan memberi peringatan jika stok di bawah nilai ini
                    </div>
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
                    placeholder="Keterangan tambahan tentang item ini...">{{ old('deskripsi') }}</textarea>
                @error('deskripsi')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>

    {{-- KOLOM KANAN --}}
    <div class="col-12 col-lg-4">
        {{-- Panduan Tipe --}}
        <div class="form-card p-4 mb-4">
            <div class="section-title"><i class="bi bi-lightbulb me-1 text-warning"></i>Panduan Tipe Item</div>
            <div class="guide-item">
                <span class="badge bg-warning-subtle text-warning guide-badge me-2">Bahan Baku</span>
                <span class="text-muted">Kulit dimsum, isian, saus — bahan produksi. Kelola juga via menu <strong>Bahan Baku &amp; Kemasan</strong></span>
            </div>
            <div class="guide-item">
                <span class="badge bg-info-subtle text-info guide-badge me-2">Kemasan</span>
                <span class="text-muted">Kotak, plastik, sumpit</span>
            </div>
            <div class="guide-item">
                <span class="badge bg-secondary-subtle text-secondary guide-badge me-2">Tambahan Gratis</span>
                <span class="text-muted">Item 1-klik di POS, gratis (garpu, sumpit lepas)</span>
            </div>
            <div class="guide-item">
                <span class="badge bg-success-subtle text-success guide-badge me-2">Produk Jual</span>
                <span class="text-muted">Dimsum, gyoza, minuman — tampil di grid POS. Kelola juga via menu <strong>Produk Jual</strong></span>
            </div>
            <div class="guide-item">
                <span class="badge bg-primary-subtle text-primary guide-badge me-2">Produk Tambahan</span>
                <span class="text-muted">Add-on berbayar di grid POS (saus extra, dll)</span>
            </div>
        </div>

        {{-- Tombol Aksi --}}
        <div class="form-card p-4">
            <div class="section-title"><i class="bi bi-lightning me-1"></i>Aksi</div>
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary" style="min-height:44px">
                    <i class="bi bi-check-lg me-2"></i>Simpan Item
                </button>
                <a href="{{ route('item.index') }}" class="btn btn-outline-secondary" style="min-height:44px">
                    <i class="bi bi-arrow-left me-2"></i>Batal
                </a>
            </div>
            <div class="mt-3 p-3 rounded" style="background:#f8fafc;font-size:0.78rem;color:#64748b">
                <i class="bi bi-info-circle me-1 text-primary"></i>
                Pastikan kode item unik dan belum pernah digunakan sebelumnya.
            </div>
        </div>
    </div>
</div>

</form>

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
