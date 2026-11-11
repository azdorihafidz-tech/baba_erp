{{--
    Tahap 2.5 D'mentai — form bersama create/edit Produk Jual.
    Variabel wajib: $formAction, $formMethod ('POST'|'PUT'), $item (null|Item),
    $categories, $bahanOptions, $hargaBahan (harga FIFO per bahan), $cabangPemilik;
    create juga $cabangAktif & $pilihanCabang (CLAUDE.md 4.39 — produk milik 1 cabang).
--}}
@php
    $isEdit = $item !== null;
    $resepAwal = $isEdit ? $item->resep?->items->map(fn($ri) => [
        'item_id' => $ri->item_id, 'nama' => $ri->item?->nama_item, 'qty_per_unit' => $ri->qty_per_unit,
        'satuan' => $ri->satuan, 'is_wajib' => $ri->is_wajib, 'mode_harga' => $ri->mode_harga,
        // Fitur Import dari Bumbu Pusat (2026-09-17)
        'resep_bumbu_ref_id' => $ri->resep_bumbu_ref_id,
        'bumbu_nama' => $ri->resepBumbuRef?->nama,
    ])->values() : collect();
    $atributAwal = $isEdit ? $item->attributes->map(fn($a) => [
        'nama' => $a->nama, 'nilai' => $a->values->pluck('nilai')->implode(', '),
    ])->values() : collect();
    $variantAwal = $isEdit ? $item->variants->map(fn($v) => [
        'signature' => $v->attributeValues->pluck('nilai')->sort()->implode('|'),
        'harga_override' => $v->harga_override,
    ])->values() : collect();
    // Harga bahan = FIFO batch tertua yang masih ada di cabang (stok habis → PO terakhir → Harga Standar)
    $bahanOptionsJs = $bahanOptions->map(fn($b) => [
        'id' => $b->id, 'nama' => $b->nama_item, 'satuan' => $b->satuan,
        'harga' => (float) ($hargaBahan[$b->id]['harga'] ?? 0),
        'info' => $hargaBahan[$b->id]['info'] ?? '',
        'habis' => (bool) ($hargaBahan[$b->id]['habis'] ?? false),
    ])->values();
@endphp

<form method="POST" action="{{ $formAction }}" enctype="multipart/form-data" id="formProdukJual">
@csrf
@if($formMethod === 'PUT') @method('PUT') @endif

<div class="row g-4">
    <div class="col-12 col-lg-8">
        {{-- CLAUDE.md 4.39 — produk milik 1 cabang (mengikuti cabang aktif di header) --}}
        @if($isEdit)
            <div class="small text-muted mb-2"><i class="bi bi-geo-alt-fill text-primary"></i> Milik cabang: <strong class="text-body">{{ $item->cabang?->nama_cabang ?? 'Semua cabang (data lama)' }}</strong></div>
            <x-peringatan-beda-cabang :cabang-id="$item->cabang_id" />
        @elseif($cabangAktif)
            <div class="alert alert-light border py-2 mb-3"><i class="bi bi-geo-alt-fill text-primary"></i> Produk ini akan menjadi milik <strong>{{ $cabangAktif->nama_cabang }}</strong> (cabang yang dipilih di pojok kanan atas). Bahan resep diambil dari bahan milik cabang ini.</div>
        @else
            <div class="alert alert-light border d-flex flex-wrap align-items-center gap-2 py-2 mb-3">
                <i class="bi bi-geo-alt-fill text-primary"></i>
                <label for="cabang_id" class="mb-0">Milik cabang:</label>
                <select name="cabang_id" id="cabang_id" class="form-select form-select-sm w-auto"
                    onchange="window.location = '{{ route('master.produk-jual.create') }}?cabang_id=' + this.value">
                    @foreach($pilihanCabang as $c)
                        <option value="{{ $c->id }}" @selected($cabangPemilik == $c->id)>{{ $c->nama_cabang }}</option>
                    @endforeach
                </select>
                <span class="small text-muted">Bahan resep diambil dari bahan milik cabang ini.</span>
            </div>
        @endif
        {{-- SECTION 1: Info Dasar --}}
        <div class="card mb-3">
            <div class="card-header fw-semibold"><i class="bi bi-info-circle me-1"></i>Informasi Dasar</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label small">Foto Produk <x-tooltip key="master_produk_jual.foto" /></label>
                        <div class="mb-2" style="width:120px;height:120px;background:#f8fafc;border-radius:8px;display:flex;align-items:center;justify-content:center;overflow:hidden">
                            <img id="fotoPreview" src="{{ $isEdit && $item->foto ? asset('storage/'.$item->foto) : '' }}"
                                style="width:100%;height:100%;object-fit:cover;{{ $isEdit && $item->foto ? '' : 'display:none' }}">
                            <span id="fotoPlaceholder" style="font-size:2.5rem;{{ $isEdit && $item->foto ? 'display:none' : '' }}">🥟</span>
                        </div>
                        <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" class="form-control form-control-sm @error('foto') is-invalid @enderror"
                            onchange="previewFoto(this)">
                        @error('foto')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text" style="font-size:0.72rem">Max 2MB, auto-resize ke 800x800px.</div>
                        @if($isEdit && $item->foto)
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" name="hapus_foto" value="1" id="hapus_foto">
                            <label class="form-check-label small text-danger" for="hapus_foto">Hapus foto</label>
                        </div>
                        @endif
                    </div>
                    <div class="col-12 col-md-8">
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-semibold">Kode Item *</label>
                                <input type="text" name="kode_item" class="form-control @error('kode_item') is-invalid @enderror"
                                    style="text-transform:uppercase" value="{{ old('kode_item', $item->kode_item ?? '') }}" required>
                                @error('kode_item')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-semibold">Tipe *</label>
                                <select name="tipe" class="form-select @error('tipe') is-invalid @enderror" required>
                                    <option value="produk_jual" @selected(old('tipe', $item->tipe ?? 'produk_jual')=='produk_jual')>Produk Jual (grid utama)</option>
                                    <option value="produk_tambahan" @selected(old('tipe', $item->tipe ?? '')=='produk_tambahan')>Produk Tambahan (add-on berbayar)</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Nama Produk *</label>
                                <input type="text" name="nama_item" class="form-control @error('nama_item') is-invalid @enderror"
                                    value="{{ old('nama_item', $item->nama_item ?? '') }}" required>
                                @error('nama_item')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-semibold">Kategori</label>
                                <div class="input-group">
                                    <select name="item_category_id" class="form-select">
                                        <option value="">— Pilih —</option>
                                        @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}" @selected(old('item_category_id', $item->item_category_id ?? null)==$cat->id)>{{ $cat->nama_kategori }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalKategori" title="Tambah kategori baru">
                                        <i class="bi bi-plus-lg"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-semibold">Satuan *</label>
                                <input type="text" name="satuan" class="form-control @error('satuan') is-invalid @enderror"
                                    value="{{ old('satuan', $item->satuan ?? 'pcs') }}" required>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold">Harga Jual *</label>
                        <x-input-rupiah name="harga_jual" :value="old('harga_jual', $item->harga_jual ?? 0)" />
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Deskripsi (utk struk)</label>
                        <textarea name="deskripsi" rows="2" class="form-control">{{ old('deskripsi', $item->deskripsi ?? '') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        {{-- SECTION 2: Komposisi / Resep --}}
        <div class="card mb-3">
            <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-list-check me-1"></i>Komposisi / Resep (opsional) <x-tooltip key="master_produk_jual.resep" /></span>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="bukaModalImportBumbu()">
                        <i class="bi bi-box-arrow-in-down"></i> Import dari Bumbu Pusat
                    </button> <x-tooltip key="master_produk_jual.import_bumbu" />
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="tambahBarisResep()">
                        <i class="bi bi-plus-lg"></i> Tambah Bahan
                    </button>
                </div>
            </div>
            <div class="card-body">
                <table class="table table-sm align-middle mb-2" id="tabelResep">
                    <thead>
                        <tr>
                            <th>Bahan</th><th style="width:100px">Qty/unit</th><th style="width:90px">Satuan</th>
                            <th style="width:170px">Harga Bahan <x-tooltip key="master_produk_jual.harga_master_preview" /></th>
                            <th style="width:120px">Subtotal</th>
                            <th style="width:40px"></th>
                        </tr>
                    </thead>
                    <tbody id="bodyResep"></tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="text-end fw-semibold">Total HPP</td>
                            <td class="fw-semibold" id="totalHppFooter">Rp 0</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
                <div class="alert alert-warning py-2 small mb-2">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Harga Bahan = harga PO dari stok yang masih ada (<strong>FIFO</strong>, batch tertua terpakai duluan). Kalau stok habis: harga PO terakhir (merah).
                    Takaran dikalikan apa adanya tanpa konversi satuan — kalau angka terlihat aneh, mis. "Rp 45.000/gram", cek harga/satuan bahan itu di menu
                    <a href="{{ route('master.bahan-baku.index') }}" target="_blank">Bahan Baku</a>.
                </div>
            </div>
        </div>

        {{-- SECTION 3: Varian --}}
        <div class="card mb-3">
            <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" name="punya_varian" value="1" id="punyaVarian"
                        {{ old('punya_varian', $item->punya_varian ?? false) ? 'checked' : '' }} onchange="toggleVarianSection()">
                    <label class="form-check-label fw-semibold" for="punyaVarian"><i class="bi bi-diagram-3 me-1"></i>Punya Varian?</label> <x-tooltip key="master_produk_jual.punya_varian" />
                </div>
                <x-panduan-button slug="item-varian" />
            </div>
            <div class="card-body" id="varianSection" style="{{ old('punya_varian', $item->punya_varian ?? false) ? '' : 'display:none' }}">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="stok_per_varian" value="1" id="stokPerVarian"
                        {{ old('stok_per_varian', $item->stok_per_varian ?? false) ? 'checked' : '' }}>
                    <label class="form-check-label small" for="stokPerVarian">Stok terpisah per varian</label>
                </div>

                <div id="bodyAtribut"></div>
                <button type="button" class="btn btn-sm btn-outline-primary mb-3" onclick="tambahBarisAtribut()">
                    <i class="bi bi-plus-lg"></i> Tambah Atribut (mis. Size, Rasa)
                </button>

                <button type="button" class="btn btn-sm btn-secondary mb-2" onclick="regenerateKombinasi()">
                    <i class="bi bi-arrow-repeat me-1"></i>Update Preview Kombinasi
                </button>
                <table class="table table-sm" id="tabelKombinasi">
                    <thead><tr><th>Kombinasi</th><th style="width:150px">Harga Override</th></tr></thead>
                    <tbody id="bodyKombinasi"></tbody>
                </table>
                <div class="form-text" style="font-size:0.72rem">Kosongkan Harga Override untuk pakai Harga Jual default.</div>
            </div>
        </div>

    </div>

    <div class="col-12 col-lg-4">
        {{-- 4.42 — input sekali, salin juga ke cabang lain (lewat layar pemeriksaan komposisi) --}}
        @php $cabangLain = $isEdit ? collect() : $pilihanCabang->where('id', '!=', $cabangPemilik)->values(); @endphp
        @if($cabangLain->isNotEmpty())
        <div class="card mb-3" id="cardSalinCabangLain">
            <div class="card-header fw-semibold small"><i class="bi bi-diagram-3 me-1"></i>Buat juga di cabang <x-tooltip key="ambil_produk.buat_juga" /></div>
            <div class="card-body py-2">
                @foreach($cabangLain as $c)
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="salin_ke_cabang[]" value="{{ $c->id }}" id="salinKe{{ $c->id }}"
                        @checked(in_array((string) $c->id, (array) old('salin_ke_cabang', []), true))>
                    <label class="form-check-label small" for="salinKe{{ $c->id }}">{{ $c->nama_cabang }}</label>
                </div>
                @endforeach
                <div class="form-text" style="font-size:0.72rem">Setelah disimpan, komposisi dicocokkan dulu ke bahan milik cabang yang dicentang (layar pemeriksaan).</div>
            </div>
        </div>
        @endif
        <div class="card">
            <div class="card-body">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                        {{ old('is_active', $item->is_active ?? true) ? 'checked' : '' }}>
                    <label class="form-check-label fw-semibold" for="is_active">Aktif (tampil di POS)</label>
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan Produk</button>
                    <a href="{{ route('master.produk-jual.index') }}" class="btn btn-outline-secondary">Batal</a>
                </div>
            </div>
        </div>

        @if($isEdit)
        @can('master.produk_jual.delete')
        <div class="card mt-3 border-danger">
            <div class="card-body">
                <h6 class="text-danger fw-bold small"><i class="bi bi-exclamation-triangle me-1"></i>Zona Berbahaya</h6>
                <button type="submit" form="formHapusProdukJual" class="btn btn-outline-danger btn-sm w-100"
                    onclick="return confirm('Hapus {{ addslashes($item->nama_item) }} secara permanen?')">
                    <i class="bi bi-trash me-1"></i>Hapus Produk
                </button>
            </div>
        </div>
        @endcan
        @endif
    </div>
</div>
</form>

{{--
    Tahap 7 D'mentai (Bug 3 fix, 2026-09-14) — form hapus WAJIB jadi SIBLING
    dari form utama, BUKAN nested di dalamnya. HTML tidak mengizinkan <form>
    bersarang: browser akan membuang tag <form> dalam tapi tetap memasukkan
    child input-nya (termasuk hidden _method=DELETE) ke form LUAR -- karena
    posisinya di DOM SETELAH hidden _method=PUT bawaan form utama, klik
    "Simpan Produk" ter-method-spoof jadi DELETE (nilai _method TERAKHIR yang
    menang di $_POST) dan produk ke-soft-delete alih-alih ter-update. Tombol
    hapus di atas pakai attribute `form="formHapusProdukJual"` (HTML5 form
    association) supaya tetap tampil visual di dalam card sidebar tanpa
    ikut jadi child DOM form ini.
--}}
@if($isEdit)
@can('master.produk_jual.delete')
<form method="POST" id="formHapusProdukJual" action="{{ route('master.produk-jual.destroy', $item) }}">
    @csrf @method('DELETE')
</form>
@endcan
@endif

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
                        cuma menampilkan kategori yang sudah punya minimal 1 produk AKTIF.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" style="font-size:0.85rem">
                            Nama Kategori <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="nama_kategori" class="form-control"
                            placeholder="cth: Dimsum, Gyoza, Drink..."
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

{{-- Modal Import dari Bumbu Pusat (2026-09-17) --}}
<div class="modal fade" id="modalImportBumbu" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="bi bi-box-arrow-in-down me-1"></i>Import dari Bumbu Pusat</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="text" class="form-control form-control-sm mb-2" id="searchBumbuPusat"
                    placeholder="Cari nama bumbu..." oninput="cariBumbuPusat(this.value)">
                <div id="listBumbuPusat" class="list-group">
                    <div class="text-center text-muted py-3 small">Ketik untuk cari, atau tunggu daftar dimuat...</div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
const BAHAN_OPTIONS = @json($bahanOptionsJs);
const RESEP_AWAL = @json($resepAwal);
const ATRIBUT_AWAL = @json($atributAwal);
const VARIANT_AWAL = @json($variantAwal);
@if($isEdit)
const KALKULATOR_URL = @json(route('master.produk-jual.kalkulator-resep', $item));
@endif
const BUMBU_PUSAT_LIST_URL = @json(route('master.produk-jual.bumbu-pusat.list', ['cabang_id' => $cabangPemilik]));
// SELALU ada, tidak bersyarat isEdit -- preview subtotal 1 Bumbu Pusat
// tidak butuh produk tersimpan, jadi harus jalan di Create maupun Edit
// (bug fix 2026-09-19, lihat CLAUDE.md 4.19). '__ID__' diganti id bumbu asli
// saat dipakai (route butuh {bumbu}, belum diketahui saat halaman di-load).
const PREVIEW_BUMBU_URL_BASE = @json(route('master.produk-jual.preview-bumbu', ['bumbu' => '__ID__']));

// Format qty jadi angka bersih tanpa trailing zero -- kolom DB DECIMAL(10,3)
// bikin Eloquent balikin string "1.000"/"0.200" (bukan angka), yang kalau
// ditaruh langsung ke value input terlihat rancu (Indonesia baca "." sbg
// pemisah ribuan, jadi "1.000" terbaca seperti "seribu" padahal cuma 1 pcs).
function formatQtyInput(v) {
    if (v === null || v === undefined || v === '') return '';
    const n = parseFloat(v);
    return isNaN(n) ? '' : String(n);
}

let resepIdx = 0;
function tambahBarisResep(data) {
    data = data || {};
    if (data.resep_bumbu_ref_id) {
        tambahBarisResepLinked(data.resep_bumbu_ref_id, data.bumbu_nama, data);
        return;
    }
    const idx = resepIdx++;
    const opsi = BAHAN_OPTIONS.map(b => `<option value="${b.id}" data-satuan="${b.satuan}" data-harga="${b.harga}" data-info="${b.info}" data-habis="${b.habis ? 1 : 0}" ${data.item_id==b.id?'selected':''}>${b.nama}</option>`).join('');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td><select name="resep[${idx}][item_id]" class="form-select form-select-sm" onchange="onBahanResepBerubah(this)">
            <option value="">— Pilih Bahan —</option>${opsi}
        </select></td>
        <td><input type="number" step="0.001" min="0.001" name="resep[${idx}][qty_per_unit]" class="form-control form-control-sm qtyResep" value="${formatQtyInput(data.qty_per_unit)}" oninput="hitungPreviewBaris(this.closest('tr'))"></td>
        <td><input type="text" name="resep[${idx}][satuan]" class="form-control form-control-sm satuanResep" value="${data.satuan ?? ''}"></td>
        <td class="small hargaMasterPreview text-muted">—</td>
        <td class="small subtotalPreview fw-semibold">—</td>
        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="hapusBarisResep(this)"><i class="bi bi-x"></i></button></td>
    `;
    document.getElementById('bodyResep').appendChild(tr);
    hitungPreviewBaris(tr);
}
RESEP_AWAL.forEach(r => tambahBarisResep(r));

function hapusBarisResep(btn) {
    btn.closest('tr').remove();
    hitungTotalHpp();
}

// ===== Live preview Harga Master & Subtotal (2026-09-19) =====
// SENGAJA belum ada konversi satuan (Sprint 2, lihat CLAUDE.md TODO) --
// takaran dikalikan APA ADANYA dgn harga master, supaya data anomali
// (mis. bahan di-satuan-kan salah di Master Bahan Baku) ketahuan user
// SEBELUM Simpan, bukan disembunyikan lewat "koreksi otomatis" yang
// justru bisa nutupin kesalahan input data aslinya.
function formatRupiahPreview(angka) {
    return 'Rp ' + Math.round(angka).toLocaleString('id-ID');
}

function onBahanResepBerubah(select) {
    const tr = select.closest('tr');
    const satuanInput = tr.querySelector('.satuanResep');
    const opt = select.selectedOptions[0];
    if (opt && opt.dataset.satuan) satuanInput.value = opt.dataset.satuan;
    hitungPreviewBaris(tr);
}

function hitungPreviewBaris(tr) {
    const selectBahan = tr.querySelector('select[name*="[item_id]"]');
    const qtyInput = tr.querySelector('.qtyResep');
    const hargaCell = tr.querySelector('.hargaMasterPreview');
    const subtotalCell = tr.querySelector('.subtotalPreview');
    if (!selectBahan || !hargaCell || !subtotalCell) return; // baris linked, skip

    const opt = selectBahan.selectedOptions[0];
    const bahanId = selectBahan.value;
    if (!bahanId || !opt) {
        hargaCell.textContent = '—';
        subtotalCell.textContent = '—';
        return;
    }

    const harga = parseFloat(opt.dataset.harga || '0');
    const satuan = opt.dataset.satuan || '';
    const qty = parseFloat(qtyInput?.value || '0');

    const info = opt.dataset.info || '';
    const habis = opt.dataset.habis === '1';
    const infoHtml = info ? '<div class="' + (habis ? 'text-danger' : 'text-muted') + '" style="font-size:0.7rem">' + info + '</div>' : '';

    if (!harga) {
        hargaCell.innerHTML = '<span class="text-danger">Rp 0 / ' + satuan + '</span>' + infoHtml;
        subtotalCell.textContent = '-';
        hitungTotalHpp();
        return;
    }

    hargaCell.innerHTML = '<span class="' + (habis ? 'text-danger' : 'text-body') + '">' + formatRupiahPreview(harga) + ' / ' + satuan + '</span>' + infoHtml;
    subtotalCell.textContent = qty > 0 ? formatRupiahPreview(qty * harga) : '-';
    hitungTotalHpp();
}

// Total HPP -- sum SEMUA .subtotalPreview (baris manual: instant client-side;
// baris linked Bumbu Pusat: hasil AJAX hitungSubtotalLinkedBaris(), lihat di
// bawah). Cell yang masih "Menghitung..."/"—" diabaikan (NaN) sampai
// nilainya siap, lalu otomatis ikut ke-total begitu AJAX selesai.
function hitungTotalHpp() {
    let total = 0;
    document.querySelectorAll('#bodyResep .subtotalPreview').forEach(cell => {
        // Bug fix 2026-09-19: formatRupiahPreview() selalu Math.round() (tidak
        // pernah ada desimal asli), jadi titik di teks SELALU pemisah ribuan
        // ("Rp 1.200") -- regex lama menyisakan titik utk "jaga-jaga desimal"
        // malah bikin parseFloat("1.200")=1.2 bukan 1200. Buang SEMUA
        // non-digit, jangan sisakan titik/minus.
        const angka = parseFloat((cell.textContent || '').replace(/[^0-9]/g, ''));
        if (!isNaN(angka)) total += angka;
    });
    const footer = document.getElementById('totalHppFooter');
    if (footer) footer.textContent = formatRupiahPreview(total);
}

// ===== Fitur Import dari Bumbu Pusat (2026-09-17) =====
// Baris linked TIDAK pakai dropdown Bahan/Satuan manual --
// hidden input resep_bumbu_ref_id + qty_per_unit (jumlah porsi bumbu per 1
// unit produk) saja. satuan dipaksa 'porsi' server-side (syncResep()).
function tambahBarisResepLinked(bumbuId, bumbuNama, data) {
    data = data || {};
    const idx = resepIdx++;
    const tr = document.createElement('tr');
    tr.className = 'table-warning';
    tr.innerHTML = `
        <td>
            <span class="badge bg-warning text-dark">🧂 Bumbu Pusat</span>
            <div class="small fw-semibold">${bumbuNama}</div>
            <input type="hidden" name="resep[${idx}][resep_bumbu_ref_id]" value="${bumbuId}">
        </td>
        <td><input type="number" step="0.001" min="0.001" name="resep[${idx}][qty_per_unit]" class="form-control form-control-sm" value="${formatQtyInput(data.qty_per_unit ?? 1)}" title="Jumlah porsi bumbu per 1 unit produk" oninput="hitungSubtotalLinkedBaris(this.closest('tr'))"></td>
        <td class="text-muted small">porsi</td>
        <td class="text-muted small">Ikut Bumbu Pusat <x-tooltip key="master_produk_jual.baris_linked" /></td>
        <td class="small subtotalPreview fw-semibold text-muted">Menghitung...</td>
        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="hapusBarisResep(this)"><i class="bi bi-x"></i></button></td>
    `;
    document.getElementById('bodyResep').appendChild(tr);
    hitungSubtotalLinkedBaris(tr);
}

// Total 1-mode (2026-09-19): baris linked butuh expand Bumbu Pusat
// server-side (kalkulator dulu punya mode terpisah utk ini, sekarang
// dikonsolidasi jadi 1 Total HPP) -- jadi subtotal-nya diambil via AJAX otomatis tiap kali
// baris ditambah/qty diubah (debounce 400ms), 1 baris = 1 kalkulator call
// jumlah=1 (qty_per_unit di baris linked sudah representasi "per 1 unit
// produk" itu sendiri). Hasil disuntik ke .subtotalPreview yang sama dgn
// baris manual, sehingga otomatis ikut ke-total oleh hitungTotalHpp() tanpa
// perlu logic terpisah.
let linkedRowSeq = 0;
const linkedSubtotalTimeout = {};
function hitungSubtotalLinkedBaris(tr) {
    const refInput = tr.querySelector('input[name*="[resep_bumbu_ref_id]"]');
    const qtyInput = tr.querySelector('input[name*="[qty_per_unit]"]');
    const cell = tr.querySelector('.subtotalPreview');
    if (!refInput || !cell) return;

    const bumbuId = refInput.value;
    if (!bumbuId) { cell.textContent = '-'; hitungTotalHpp(); return; }

    const rowKey = tr.dataset.rowKey || (tr.dataset.rowKey = 'row' + (linkedRowSeq++));
    clearTimeout(linkedSubtotalTimeout[rowKey]);
    cell.textContent = 'Menghitung...';
    linkedSubtotalTimeout[rowKey] = setTimeout(() => {
        const qty = qtyInput ? qtyInput.value : 1;
        const token = document.querySelector('#formProdukJual input[name="_token"]').value;
        // Endpoint decoupled dari Item (lihat PREVIEW_BUMBU_URL_BASE) --
        // jalan di halaman Create maupun Edit, bug fix 2026-09-19.
        fetch(PREVIEW_BUMBU_URL_BASE.replace('__ID__', bumbuId) + '?jumlah=1', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
            body: JSON.stringify({ qty_per_unit: qty }),
        })
            .then(r => r.json())
            .then(data => {
                cell.textContent = formatRupiahPreview(data.subtotal || 0);
                hitungTotalHpp();
            })
            .catch(() => { cell.textContent = '—'; hitungTotalHpp(); });
    }, 400);
}

function bukaModalImportBumbu() {
    new bootstrap.Modal(document.getElementById('modalImportBumbu')).show();
    cariBumbuPusat('');
}

let bumbuSearchTimeout = null;
function cariBumbuPusat(search) {
    clearTimeout(bumbuSearchTimeout);
    bumbuSearchTimeout = setTimeout(() => {
        fetch(BUMBU_PUSAT_LIST_URL + (BUMBU_PUSAT_LIST_URL.includes('?') ? '&' : '?') + 'search=' + encodeURIComponent(search))
            .then(r => r.json())
            .then(data => {
                const container = document.getElementById('listBumbuPusat');
                if (!data.data.length) {
                    container.innerHTML = '<div class="text-center text-muted py-3 small">Tidak ada Bumbu Pusat aktif ditemukan.</div>';
                    return;
                }
                // Sprint 4.31 (2026-09-29): render accordion inline — klik card
                // untuk lihat detail bahan + harga_beli + subtotal + total HPP.
                // Total HPP pakai rumus SAMA dgn hitungSubtotalBumbuTunggal() server.
                container.innerHTML = data.data.map((b, i) => renderKartuBumbu(b, i)).join('');
            })
            .catch(() => {
                document.getElementById('listBumbuPusat').innerHTML = '<div class="text-center text-danger py-3 small">Gagal memuat daftar Bumbu Pusat.</div>';
            });
    }, 250);
}

function pilihBumbuPusat(id, nama) {
    tambahBarisResepLinked(id, nama, { qty_per_unit: 1 });
    bootstrap.Modal.getInstance(document.getElementById('modalImportBumbu')).hide();
}

function fmtRupiah(n) {
    return 'Rp ' + Math.round(Number(n) || 0).toLocaleString('id-ID');
}
function escHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
function fmtQtyKecil(q) {
    var n = parseFloat(q); if (!isFinite(n)) return '0';
    return String(n).replace(/\.?0+$/, '');
}

function renderKartuBumbu(b, idx) {
    var idAcc = 'accBumbu_' + b.id;
    var badgeHpp = b.total_hpp > 0
        ? '<span class="badge bg-primary-subtle text-primary">Total HPP: ' + fmtRupiah(b.total_hpp) + '</span>'
        : '<span class="badge bg-secondary-subtle text-secondary">Total HPP: Rp 0</span>';
    var rows = (b.items || []).map(function (r) {
        var subCell = r.subtotal > 0 ? fmtRupiah(r.subtotal) : '<span class="text-warning">harga belum ada</span>';
        var hargaCell = (r.harga_beli > 0 ? '<span class="' + (r.habis ? 'text-danger' : '') + '">' + fmtRupiah(r.harga_beli) + '</span>' : '<span class="text-warning">—</span>')
            + (r.harga_info ? '<div class="' + (r.habis ? 'text-danger' : 'text-muted') + '" style="font-size:.68rem">' + escHtml(r.harga_info) + '</div>' : '');
        return '<tr>'
            + '<td class="small">' + escHtml(r.nama_bahan) + '<div class="text-muted" style="font-size:.7rem">' + escHtml(r.kode_bahan) + '</div></td>'
            + '<td class="text-end small">' + fmtQtyKecil(r.qty) + ' ' + escHtml(r.satuan) + '</td>'
            + '<td class="text-end small">' + hargaCell + '</td>'
            + '<td class="text-end small fw-semibold">' + subCell + '</td>'
            + '</tr>';
    }).join('');
    var bodyDetail = rows
        ? '<table class="table table-sm mb-2"><thead><tr>'
            + '<th class="small">Bahan</th><th class="text-end small">Takaran</th>'
            + '<th class="text-end small">Harga (FIFO)</th><th class="text-end small">Subtotal</th>'
            + '</tr></thead><tbody>' + rows + '</tbody></table>'
            + '<div class="d-flex justify-content-between align-items-center pt-1 border-top">'
            + '<small class="text-muted"><i class="bi bi-info-circle me-1"></i>Preview per 1 unit produksi, harga FIFO stok cabang ini.</small>'
            + '<strong class="text-primary">Total HPP: ' + fmtRupiah(b.total_hpp) + '</strong>'
            + '</div>'
        : '<div class="text-center text-muted small py-2">Bumbu ini belum punya bahan.</div>';

    return '<div class="card mb-2" style="border-color:#e2e8f0">'
        + '<div class="card-body p-2">'
        +   '<div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">'
        +     '<div class="flex-grow-1" style="cursor:pointer" data-bs-toggle="collapse" data-bs-target="#' + idAcc + '">'
        +       '<div class="fw-semibold small">' + escHtml(b.nama)
        +         ' <i class="bi bi-chevron-down text-muted"></i></div>'
        +       '<div class="small text-muted">' + escHtml(b.kode) + ' — ' + b.jumlah_bahan + ' bahan &nbsp;·&nbsp; ' + badgeHpp + '</div>'
        +     '</div>'
        +     '<button type="button" class="btn btn-sm btn-primary" '
        +       'onclick="pilihBumbuPusat(' + b.id + ', \'' + String(b.nama).replace(/'/g, "\\'") + '\')">'
        +       '<i class="bi bi-plus-circle me-1"></i>Pilih</button>'
        +   '</div>'
        +   '<div id="' + idAcc + '" class="collapse mt-2">' + bodyDetail + '</div>'
        + '</div>'
        + '</div>';
}

// ===== Varian =====
let atributIdx = 0;
function tambahBarisAtribut(data) {
    data = data || {};
    const idx = atributIdx++;
    const div = document.createElement('div');
    div.className = 'row g-2 mb-2 baris-atribut';
    div.innerHTML = `
        <div class="col-4"><input type="text" name="atribut[${idx}][nama]" class="form-control form-control-sm" placeholder="Nama (cth: Size)" value="${data.nama ?? ''}"></div>
        <div class="col-7"><input type="text" name="atribut[${idx}][nilai]" class="form-control form-control-sm" placeholder="Nilai, pisah koma (cth: S, M, L)" value="${data.nilai ?? ''}"></div>
        <div class="col-1"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.baris-atribut').remove()"><i class="bi bi-x"></i></button></div>
    `;
    document.getElementById('bodyAtribut').appendChild(div);
}
ATRIBUT_AWAL.forEach(a => tambahBarisAtribut(a));

function toggleVarianSection() {
    document.getElementById('varianSection').style.display = document.getElementById('punyaVarian').checked ? '' : 'none';
}

function regenerateKombinasi() {
    const rows = document.querySelectorAll('#bodyAtribut .baris-atribut');
    let groups = [];
    rows.forEach(row => {
        const nilaiInput = row.querySelector('input[name*="[nilai]"]').value;
        const values = nilaiInput.split(',').map(v => v.trim()).filter(v => v);
        if (values.length) groups.push(values);
    });

    let combos = [[]];
    groups.forEach(values => {
        const next = [];
        combos.forEach(combo => values.forEach(v => next.push([...combo, v])));
        combos = next;
    });

    const tbody = document.getElementById('bodyKombinasi');
    tbody.innerHTML = '';
    combos.forEach((combo, i) => {
        const sig = [...combo].sort().join('|');
        const existing = VARIANT_AWAL.find(v => v.signature === sig);
        const hargaVal = existing ? (existing.harga_override ?? '') : '';
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${combo.join(' / ')}</td>
            <td><input type="number" name="harga_override[${i}]" class="form-control form-control-sm" placeholder="default" value="${hargaVal}"></td>
        `;
        tbody.appendChild(tr);
    });
}
if (document.getElementById('punyaVarian').checked) regenerateKombinasi();

function previewFoto(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('fotoPreview').src = e.target.result;
        document.getElementById('fotoPreview').style.display = '';
        document.getElementById('fotoPlaceholder').style.display = 'none';
    };
    reader.readAsDataURL(input.files[0]);
}
</script>
@endpush
