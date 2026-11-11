@extends('layouts.app')

@section('title', $produk ? 'Ubah Produk Online' : 'Tambah Produk Online')

@section('content')
@php
    $hargaJab = $produk ? $produk->harga->where('jenis', 'jabatan')->mapWithKeys(fn ($h) => [$h->jabatan_id => (float) $h->harga]) : collect();
    $hargaAkun = $produk ? $produk->harga->where('jenis', 'akun')->mapWithKeys(fn ($h) => [$h->member_id => (float) $h->harga]) : collect();
    $nilai = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
@endphp
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-bag-heart me-2 text-success"></i>{{ $produk ? 'Ubah Produk Online' : 'Tambah Produk Online' }}</h5>
    <div class="d-flex gap-2 align-items-center">
        <x-panduan-button slug="produk-online" />
        <a href="{{ route('toko-online.produk.index') }}" class="btn btn-outline-secondary btn-sm">Kembali</a>
    </div>
</div>

<form method="POST" action="{{ $produk ? route('toko-online.produk.update', $produk) : route('toko-online.produk.store') }}" enctype="multipart/form-data">
@csrf
@if($produk) @method('PUT') @endif
<div class="row g-3">
    <div class="col-12 col-lg-7">
        <div class="card"><div class="card-body"><div class="row g-3">
            <div class="col-12">
                <label class="form-label small fw-semibold">Barang yang dijual * <x-tooltip key="produk_online.barang" /></label>
                <select name="item_id" id="itemId" class="form-select @error('item_id') is-invalid @enderror" required>
                    <option value="">— pilih parfum botol —</option>
                    @foreach($barang as $i)
                    <option value="{{ $i->id }}" data-nama="{{ $i->nama_item }}" @selected(old('item_id', $produk?->item_id) == $i->id)>{{ $i->nama_item }} ({{ $i->satuan }}){{ $i->category ? ' · ' . $i->category->nama_kategori : '' }}</option>
                    @endforeach
                </select>
                @error('item_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Parfum botol dibuat di Produksi › Buat Parfum. Stok yang dijual = stok barang ini di gudangnya.</div>
            </div>
            <div class="col-12 col-md-8">
                <label class="form-label small fw-semibold">Nama di aplikasi member *</label>
                <input type="text" name="nama_tampil" id="namaTampil" maxlength="150" class="form-control @error('nama_tampil') is-invalid @enderror" value="{{ old('nama_tampil', $produk?->nama_tampil) }}" required>
                @error('nama_tampil')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label small fw-semibold">Kategori</label>
                <input type="text" name="kategori" maxlength="50" list="daftarKategori" class="form-control" value="{{ old('kategori', $produk?->kategori) }}" placeholder="Pria / Wanita / Unisex">
                <datalist id="daftarKategori"><option value="Pria"><option value="Wanita"><option value="Unisex"></datalist>
            </div>
            <div class="col-12">
                <label class="form-label small fw-semibold">Deskripsi</label>
                <textarea name="deskripsi" rows="3" maxlength="2000" class="form-control" placeholder="mis. aroma segar laut, tahan 6–8 jam">{{ old('deskripsi', $produk?->deskripsi) }}</textarea>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label small fw-semibold">Foto (JPG/PNG/WEBP, maks 3 MB)</label>
                <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" class="form-control @error('foto') is-invalid @enderror">
                @error('foto')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @if($produk?->fotoUrl())<img src="{{ $produk->fotoUrl() }}" alt="" class="rounded mt-2" style="max-height:90px">@endif
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small fw-semibold">Urutan</label>
                <input type="number" name="urutan" min="0" max="9999" class="form-control" value="{{ old('urutan', $produk?->urutan ?? 0) }}">
            </div>
            <div class="col-6 col-md-3 d-flex align-items-end">
                <div class="form-check form-switch mb-2">
                    <input type="hidden" name="aktif" value="0">
                    <input class="form-check-input" type="checkbox" name="aktif" value="1" id="aktif" @checked(old('aktif', $produk?->aktif ?? true))>
                    <label class="form-check-label small" for="aktif">Tampil di aplikasi</label>
                </div>
            </div>
        </div></div></div>
    </div>

    <div class="col-12 col-lg-5">
        <div class="card"><div class="card-header bg-white small fw-semibold">Harga dasar</div><div class="card-body"><div class="row g-3">
            <div class="col-6">
                <label class="form-label small fw-semibold">Harga tetap <x-tooltip key="produk_online.harga_tetap" /></label>
                <div class="input-group"><span class="input-group-text">Rp</span><input type="number" step="1" min="0" name="harga_tetap" class="form-control" value="{{ old('harga_tetap', $nilai($produk?->hargaJenis('tetap'))) }}"></div>
            </div>
            <div class="col-6">
                <label class="form-label small fw-semibold">Harga gudang <x-tooltip key="produk_online.harga_gudang" /></label>
                <div class="input-group"><span class="input-group-text">Rp</span><input type="number" step="1" min="0" name="harga_gudang" class="form-control" value="{{ old('harga_gudang', $nilai($produk?->hargaJenis('gudang'))) }}"></div>
            </div>
            <div class="col-12 form-text mt-0">Kosongkan bila tidak dipakai.</div>
        </div></div></div>

        @if($produk)
        <div class="card mt-3"><div class="card-header bg-white small fw-semibold">Harga per jabatan <x-tooltip key="produk_online.harga_jabatan" /></div>
        <div class="card-body">
            @if($galatJabatan)
            <div class="alert alert-warning small mb-0"><i class="bi bi-exclamation-triangle me-1"></i>Daftar jabatan belum bisa diambil: {{ $galatJabatan }} Harga per jabatan yang sudah tersimpan tetap berlaku.</div>
            @foreach($hargaJab as $jid => $h)<input type="hidden" name="harga_jabatan[{{ $jid }}]" value="{{ $nilai($h) }}">@endforeach
            @else
            @foreach($jabatan as $j)
            <div class="row g-2 align-items-center mb-2">
                <div class="col-6 small fw-semibold">{{ $j['nama'] }}</div>
                <div class="col-6"><div class="input-group input-group-sm"><span class="input-group-text">Rp</span>
                    <input type="number" step="1" min="0" name="harga_jabatan[{{ $j['id'] }}]" class="form-control" value="{{ old('harga_jabatan.' . $j['id'], $nilai($hargaJab[$j['id']] ?? null)) }}" placeholder="—"></div></div>
            </div>
            @endforeach
            @endif
        </div></div>

        <div class="card mt-3"><div class="card-header bg-white small fw-semibold">Harga khusus akun <x-tooltip key="produk_online.harga_akun" /></div>
        <div class="card-body">
            <div id="daftarAkun">
                @foreach($hargaAkun as $mid => $h)
                <div class="row g-2 align-items-center mb-2 baris-akun">
                    <div class="col-6 small">{{ $akunKhusus[$mid]->label ?? ('Akun #' . $mid) }}</div>
                    <div class="col-5"><div class="input-group input-group-sm"><span class="input-group-text">Rp</span><input type="number" step="1" min="0" name="harga_akun[{{ $mid }}]" class="form-control" value="{{ $nilai($h) }}"></div></div>
                    <div class="col-1"><button type="button" class="btn btn-link text-danger p-0 hapus-akun" title="Hapus"><i class="bi bi-x-lg"></i></button></div>
                </div>
                @endforeach
            </div>
            <div class="position-relative">
                <input type="text" id="cariAkun" class="form-control form-control-sm" placeholder="Cari nama / No ID / BP-xxxxx untuk menambah…" autocomplete="off">
                <div id="hasilAkun" class="list-group position-absolute w-100 shadow-sm" style="z-index:5"></div>
            </div>
        </div></div>
        @else
        <div class="alert alert-light border small mt-3 mb-0">Harga per jabatan dan harga khusus akun diatur setelah produk disimpan.</div>
        @endif

        <button class="btn btn-primary w-100 mt-3"><i class="bi bi-check2-circle me-1"></i>Simpan</button>
    </div>
</div>
</form>
@endsection

@push('scripts')
<script>
(() => {
    const el = id => document.getElementById(id);
    // nama tampil terisi dari nama barang (bila belum diisi)
    el('itemId').addEventListener('change', e => {
        const o = e.target.selectedOptions[0];
        if (o && o.dataset.nama && !el('namaTampil').value) el('namaTampil').value = o.dataset.nama;
    });
    const daftar = el('daftarAkun');
    if (!daftar) return;
    daftar.addEventListener('click', e => { const b = e.target.closest('.hapus-akun'); if (b) b.closest('.baris-akun').remove(); });
    const esc = v => String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    let tunda = null;
    el('cariAkun').addEventListener('input', e => {
        clearTimeout(tunda);
        const q = e.target.value.trim();
        if (q.length < 2) { el('hasilAkun').innerHTML = ''; return; }
        tunda = setTimeout(async () => {
            const r = await fetch(`{{ route('toko-online.cari-akun') }}?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } });
            const j = await r.json().catch(() => ({}));
            if (!j.ok) { el('hasilAkun').innerHTML = `<div class="list-group-item small text-danger">${esc(j.pesan || 'Gagal mencari akun.')}</div>`; return; }
            el('hasilAkun').innerHTML = j.data.length ? j.data.map(a => `<button type="button" class="list-group-item list-group-item-action small pilih-akun" data-id="${a.id}" data-label="${esc((a.no_id ? a.no_id + ' · ' : '') + a.nama + ' (' + a.no_member + ')')}">
                ${esc(a.no_id || '')} · <strong>${esc(a.nama)}</strong> <span class="text-muted">${esc(a.jabatan || '')} · ${esc(a.no_member)}</span></button>`).join('')
                : '<div class="list-group-item small text-muted">Tidak ada akun aktif yang cocok.</div>';
        }, 350);
    });
    el('hasilAkun').addEventListener('click', e => {
        const b = e.target.closest('.pilih-akun');
        if (!b) return;
        if (!daftar.querySelector(`[name="harga_akun[${b.dataset.id}]"]`)) {
            daftar.insertAdjacentHTML('beforeend', `<div class="row g-2 align-items-center mb-2 baris-akun"><div class="col-6 small">${b.dataset.label}</div>
                <div class="col-5"><div class="input-group input-group-sm"><span class="input-group-text">Rp</span><input type="number" step="1" min="0" name="harga_akun[${b.dataset.id}]" class="form-control" required></div></div>
                <div class="col-1"><button type="button" class="btn btn-link text-danger p-0 hapus-akun" title="Hapus"><i class="bi bi-x-lg"></i></button></div></div>`);
        }
        el('hasilAkun').innerHTML = ''; el('cariAkun').value = '';
    });
})();
</script>
@endpush
