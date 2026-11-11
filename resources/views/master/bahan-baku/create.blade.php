@extends('layouts.app')

@section('title', 'Tambah Bahan Baku / Kemasan')

@section('content')

<div class="d-flex flex-column flex-sm-row align-items-center justify-content-between gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-plus-circle me-2 text-primary"></i>Tambah Bahan Baku / Kemasan</h5>
    <div class="d-flex gap-2 align-items-center">
        <a href="{{ route('master.bahan-baku.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
        <x-panduan-button slug="bahan-baku" />
    </div>
</div>

<form method="POST" action="{{ route('master.bahan-baku.store') }}">
@csrf
<div class="row g-4">
    <div class="col-12 col-lg-8">
        {{-- CLAUDE.md 4.39 — barang milik 1 cabang (mengikuti cabang aktif di header) --}}
        <div class="alert alert-light border d-flex flex-wrap align-items-center gap-2 py-2 mb-3">
            <i class="bi bi-geo-alt-fill text-primary"></i>
            @if($cabangAktif)
                <span>Barang ini akan menjadi milik <strong>{{ $cabangAktif->nama_cabang }}</strong> (cabang yang dipilih di pojok kanan atas).</span>
            @else
                <label for="cabang_id" class="mb-0">Milik cabang:</label>
                <select name="cabang_id" id="cabang_id" class="form-select form-select-sm w-auto @error('cabang_id') is-invalid @enderror">
                    @foreach($pilihanCabang as $c)
                        <option value="{{ $c->id }}" @selected(old('cabang_id', $pilihanCabang->firstWhere('tipe', 'gudang_pusat')?->id) == $c->id)>{{ $c->nama_cabang }}</option>
                    @endforeach
                </select>
                @error('cabang_id')<div class="text-danger small w-100">{{ $message }}</div>@enderror
            @endif
        </div>
        <div class="card mb-3">
            <div class="card-header fw-semibold">Informasi Dasar</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-semibold">Kode Item *</label>
                        <input type="text" name="kode_item" class="form-control @error('kode_item') is-invalid @enderror"
                            style="text-transform:uppercase" value="{{ old('kode_item') }}" placeholder="cth: BB-007" required>
                        @error('kode_item')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-8">
                        <label class="form-label small fw-semibold">Nama Item *</label>
                        <input type="text" name="nama_item" class="form-control @error('nama_item') is-invalid @enderror"
                            value="{{ old('nama_item') }}" required>
                        @error('nama_item')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-semibold">Tipe *</label>
                        <select name="tipe" class="form-select @error('tipe') is-invalid @enderror" required>
                            <option value="">— Pilih —</option>
                            <option value="bahan_baku" @selected(old('tipe')=='bahan_baku')>Bahan Baku</option>
                            <option value="kemasan" @selected(old('tipe')=='kemasan')>Kemasan</option>
                            <option value="tambahan_gratis" @selected(old('tipe')=='tambahan_gratis')>Tambahan Gratis (add-on POS)</option>
                        </select>
                        @error('tipe')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-semibold">Unit / Satuan *</label>
                        <input type="text" name="satuan" class="form-control @error('satuan') is-invalid @enderror"
                            value="{{ old('satuan') }}" placeholder="pcs / gram / ml / kg" required>
                        @error('satuan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-semibold">Kategori</label>
                        <div class="input-group">
                            <select name="item_category_id" class="form-select">
                                <option value="">— Pilih —</option>
                                @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" @selected(old('item_category_id')==$cat->id)>{{ $cat->nama_kategori }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalKategori" title="Tambah kategori baru">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        </div>
                    </div>
                </div>
                {{-- Sprint 4.34 — field dipindah dari Master Barang Lengkap: jenis + lacak stok --}}
                <div class="row g-3 mt-1">
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold">Jenis Item <x-tooltip key="master_bahan_baku.jenis" /></label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="jenis" id="jenisBahanBaku" value="bahan_baku" @checked(old('jenis', 'bahan_baku')=='bahan_baku')>
                                <label class="form-check-label" for="jenisBahanBaku">Bahan Baku (produksi)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="jenis" id="jenisPerlengkapan" value="perlengkapan" @checked(old('jenis')=='perlengkapan')>
                                <label class="form-check-label" for="jenisPerlengkapan">Perlengkapan (ATK/habis pakai)</label>
                            </div>
                        </div>
                        <div class="form-text" style="font-size:0.72rem">
                            Bahan baku = masuk produksi. Perlengkapan = ATK/habis pakai (nota kertas, dsb).
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold">Lacak Stok <x-tooltip key="master_bahan_baku.track_stok" /></label>
                        <div class="form-check form-switch mt-1">
                            <input class="form-check-input" type="checkbox" name="track_stok" id="track_stok" value="1" @checked(old('track_stok', '1'))>
                            <label class="form-check-label" for="track_stok">Aktifkan tracking stok</label>
                        </div>
                        <div class="form-text" style="font-size:0.72rem">
                            Nonaktifkan untuk ATK yang tidak perlu di-track qty-nya.
                        </div>
                    </div>
                </div>
                <div class="row g-3 mt-1">
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Deskripsi (opsional) <x-tooltip key="master_bahan_baku.deskripsi" /></label>
                        <textarea name="deskripsi" rows="2" class="form-control" placeholder="Catatan tambahan (opsional)">{{ old('deskripsi') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <x-unit-beli-section />

        <div class="card mb-3">
            <div class="card-header fw-semibold">Harga &amp; Stok</div>
            <div class="card-body">
                <x-harga-standar-section />
                <div class="row g-3 mt-1">
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold">Qty Minimum <x-tooltip key="master_bahan_baku.qty_minimum_cabang" /></label>
                        <input type="number" name="qty_minimum" class="form-control" min="0" step="0.001" value="{{ old('qty_minimum', 0) }}">
                        <div class="form-text">Muncul peringatan "stok menipis" kalau stok sama atau di bawah angka ini. 0 = tanpa peringatan.</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold">Stok Awal (opsional) <x-tooltip key="master_bahan_baku.stok_awal" /></label>
                        <input type="number" name="stok_awal" class="form-control" min="0" step="0.001" value="{{ old('stok_awal') }}" placeholder="0">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card">
            <div class="card-body">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" checked>
                    <label class="form-check-label fw-semibold" for="is_active">Aktif</label>
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan</button>
                    <a href="{{ route('master.bahan-baku.index') }}" class="btn btn-outline-secondary">Batal</a>
                </div>
            </div>
        </div>
    </div>
</div>
</form>

{{-- Sprint 4.34 — modal Tambah Kategori Baru (dipindah dari item/create.blade.php).
     SIBLING dari <form> utama di atas (bukan nested, lihat CLAUDE.md [[4.14]]). --}}
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
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" style="font-size:0.85rem">
                            Nama Kategori <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="nama_kategori" class="form-control"
                            placeholder="cth: Bumbu, Kemasan Plastik, Daging Segar..."
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
