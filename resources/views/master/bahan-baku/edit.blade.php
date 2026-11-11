@extends('layouts.app')

@section('title', 'Edit ' . $item->nama_item)

@section('content')

<div class="d-flex flex-column flex-sm-row align-items-center justify-content-between gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-pencil-square me-2 text-primary"></i>Edit — {{ $item->nama_item }}</h5>
    <div class="d-flex gap-2 align-items-center">
        <a href="{{ route('master.bahan-baku.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
        <x-panduan-button slug="bahan-baku" />
    </div>
</div>

<form method="POST" action="{{ route('master.bahan-baku.update', $item) }}">
@csrf
@method('PUT')
<div class="row g-4">
    <div class="col-12 col-lg-8">
        <div class="small text-muted mb-2"><i class="bi bi-geo-alt-fill text-primary"></i> Milik cabang: <strong class="text-body">{{ $item->cabang?->nama_cabang ?? 'Semua cabang (data lama)' }}</strong></div>
        <x-peringatan-beda-cabang :cabang-id="$item->cabang_id" />
        <div class="card mb-3">
            <div class="card-header fw-semibold">Informasi Dasar</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-semibold">Kode Item *</label>
                        <input type="text" name="kode_item" class="form-control @error('kode_item') is-invalid @enderror"
                            style="text-transform:uppercase" value="{{ old('kode_item', $item->kode_item) }}" required>
                        @error('kode_item')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-8">
                        <label class="form-label small fw-semibold">Nama Item *</label>
                        <input type="text" name="nama_item" class="form-control @error('nama_item') is-invalid @enderror"
                            value="{{ old('nama_item', $item->nama_item) }}" required>
                        @error('nama_item')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-semibold">Tipe *</label>
                        <select name="tipe" class="form-select @error('tipe') is-invalid @enderror" required>
                            <option value="bahan_baku" @selected(old('tipe',$item->tipe)=='bahan_baku')>Bahan Baku</option>
                            <option value="kemasan" @selected(old('tipe',$item->tipe)=='kemasan')>Kemasan</option>
                            <option value="tambahan_gratis" @selected(old('tipe',$item->tipe)=='tambahan_gratis')>Tambahan Gratis (add-on POS)</option>
                        </select>
                        @error('tipe')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-semibold">Unit / Satuan *</label>
                        <input type="text" name="satuan" class="form-control @error('satuan') is-invalid @enderror"
                            value="{{ old('satuan', $item->satuan) }}" required>
                        @error('satuan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-semibold">Kategori</label>
                        <div class="input-group">
                            <select name="item_category_id" class="form-select">
                                <option value="">— Pilih —</option>
                                @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" @selected(old('item_category_id',$item->item_category_id)==$cat->id)>{{ $cat->nama_kategori }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalKategori" title="Tambah kategori baru">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        </div>
                    </div>
                </div>
                {{-- Sprint 4.34 — field dipindah dari Master Barang Lengkap --}}
                @php
                    $jenisAktif = old('jenis', $item->jenis instanceof \BackedEnum ? $item->jenis->value : ($item->jenis ?? 'bahan_baku'));
                @endphp
                <div class="row g-3 mt-1">
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold">Jenis Item <x-tooltip key="master_bahan_baku.jenis" /></label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="jenis" id="jenisBahanBaku" value="bahan_baku" @checked($jenisAktif === 'bahan_baku')>
                                <label class="form-check-label" for="jenisBahanBaku">Bahan Baku (produksi)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="jenis" id="jenisPerlengkapan" value="perlengkapan" @checked($jenisAktif === 'perlengkapan')>
                                <label class="form-check-label" for="jenisPerlengkapan">Perlengkapan (ATK/habis pakai)</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold">Lacak Stok <x-tooltip key="master_bahan_baku.track_stok" /></label>
                        <div class="form-check form-switch mt-1">
                            <input class="form-check-input" type="checkbox" name="track_stok" id="track_stok" value="1" @checked(old('track_stok', $item->track_stok))>
                            <label class="form-check-label" for="track_stok">Aktifkan tracking stok</label>
                        </div>
                    </div>
                </div>
                <div class="row g-3 mt-1">
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Deskripsi (opsional) <x-tooltip key="master_bahan_baku.deskripsi" /></label>
                        <textarea name="deskripsi" rows="2" class="form-control">{{ old('deskripsi', $item->deskripsi) }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <x-unit-beli-section :unitBeli="$item->unit_beli" :isiPerUnitBeli="$item->isi_per_unit_beli" />

        <div class="card mb-3">
            <div class="card-header fw-semibold">Harga &amp; Stok</div>
            <div class="card-body">
                <x-harga-standar-section :item="$item" />
                <div class="row g-3 mt-1">
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold">Qty Minimum <x-tooltip key="master_bahan_baku.qty_minimum_cabang" /></label>
                        <input type="number" name="qty_minimum" class="form-control" min="0" step="0.001" value="{{ old('qty_minimum', $item->qty_minimum) }}">
                        <div class="form-text">Muncul peringatan "stok menipis" kalau stok sama atau di bawah angka ini. 0 = tanpa peringatan.</div>
                    </div>
                </div>
            </div>
        </div>

        <x-fifo-batch-detail :batches="$batches" :item="$item" />
    </div>

    <div class="col-12 col-lg-4">
        <div class="card">
            <div class="card-body">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" {{ old('is_active', $item->is_active) ? 'checked' : '' }}>
                    <label class="form-check-label fw-semibold" for="is_active">Aktif</label>
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan Perubahan</button>
                    <a href="{{ route('master.bahan-baku.index') }}" class="btn btn-outline-secondary">Batal</a>
                </div>
            </div>
        </div>

        @can('master.bahan_baku.delete')
        <div class="card mt-3 border-danger">
            <div class="card-body">
                <h6 class="text-danger fw-bold small"><i class="bi bi-exclamation-triangle me-1"></i>Zona Berbahaya</h6>
                <button type="submit" form="formHapusBahanBaku" class="btn btn-outline-danger btn-sm w-100"
                    onclick="return confirm('Hapus {{ addslashes($item->nama_item) }} secara permanen?')">
                    <i class="bi bi-trash me-1"></i>Hapus Item
                </button>
            </div>
        </div>
        @endcan
    </div>
</div>
</form>

{{--
    Tahap 7 D'mentai (Bug 3 fix, 2026-09-14) — form hapus WAJIB sibling dari
    form utama, BUKAN nested (lihat penjelasan lengkap di
    master/produk-jual/_form.blade.php). Nested <form> di sini adalah
    penyebab bug "klik Simpan Perubahan malah menghapus item".
--}}
@can('master.bahan_baku.delete')
<form method="POST" id="formHapusBahanBaku" action="{{ route('master.bahan-baku.destroy', $item) }}">
    @csrf @method('DELETE')
</form>
@endcan

{{-- Sprint 4.34 — modal Tambah Kategori Baru (SIBLING, bukan nested — lihat CLAUDE.md [[4.14]]). --}}
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
                            placeholder="cth: Bumbu, Kemasan Plastik..."
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
