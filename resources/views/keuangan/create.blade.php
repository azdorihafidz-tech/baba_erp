@extends('layouts.app')

@section('title', 'Tambah Transaksi Keuangan')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold">
        <i class="bi bi-plus-circle me-2 text-primary"></i>Tambah Transaksi Keuangan
    </h5>
    <a href="{{ route('keuangan.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-lg-7">
        <div class="card">
            <div class="card-header">Form Transaksi</div>
            <div class="card-body">
                <form method="POST" action="{{ route('keuangan.store') }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="po_id" id="poIdInput" value="{{ old('po_id') }}">
                    <div class="row g-3">

                        @can('kas.pilih_po.view')
                        <div class="col-12">
                            <div id="pilihPoBox" class="border rounded p-2 bg-light-subtle">
                                <label class="form-label fw-semibold mb-1">
                                    <i class="bi bi-bag-check me-1"></i>Pilih PO (opsional)
                                </label>
                                <div class="d-flex gap-2 align-items-start flex-wrap">
                                    <select id="poSelect" class="form-select form-select-sm" style="min-width:260px;flex:1">
                                        <option value="">-- Tidak dari PO / Input Manual --</option>
                                    </select>
                                    <button type="button" id="btnResetPo" class="btn btn-sm btn-outline-secondary" style="display:none">
                                        <i class="bi bi-x-lg me-1"></i>Reset
                                    </button>
                                </div>
                                <div class="form-text">Pilih PO yang sudah diterima untuk auto-isi Jumlah, Kategori, dan Keterangan di bawah — field tetap bisa diedit manual sebelum disimpan.</div>
                                <div id="pilihPoHint" class="alert alert-warning py-1 px-2 mt-2 mb-0 small" style="display:none">
                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                    Kategori ini biasanya pembayaran PO — isi <strong>Pilih PO</strong> di atas supaya status PO otomatis terupdate jadi "Sudah Dibayar" dan tidak perlu di-link manual belakangan.
                                </div>
                            </div>
                        </div>
                        @endcan

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Tanggal <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal_transaksi" class="form-control @error('tanggal_transaksi') is-invalid @enderror"
                                value="{{ old('tanggal_transaksi', date('Y-m-d')) }}" required>
                            @error('tanggal_transaksi')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Tipe <span class="text-danger">*</span> <x-tooltip key="keuangan.tipe" /></label>
                            <select name="tipe" id="tipeSelect" class="form-select @error('tipe') is-invalid @enderror" required>
                                <option value="">-- Pilih Tipe --</option>
                                @foreach($tipes as $t)
                                <option value="{{ $t->value }}" @selected(old('tipe') === $t->value)>{{ $t->label() }}</option>
                                @endforeach
                            </select>
                            @error('tipe')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">
                                Kategori <span class="text-danger">*</span> <x-tooltip key="keuangan.kategori_id" />
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1" style="font-size:0.7rem;line-height:1.4"
                                    data-bs-toggle="modal" data-bs-target="#coaCheatSheetModal" title="Cheat sheet kode akun COA">?</button>
                            </label>
                            <select name="kategori_id" id="kategoriSelect" class="form-select @error('kategori_id') is-invalid @enderror" required>
                                <option value="">-- Pilih Tipe dulu --</option>
                                @foreach($kategoris as $k)
                                <option value="{{ $k->id }}"
                                    data-tipe="{{ $k->tipe }}"
                                    data-kode="{{ $k->kode }}"
                                    @selected(old('kategori_id') == $k->id)>
                                    {{ $k->parent ? $k->parent->nama . ' › ' : '' }}{{ $k->nama }}
                                </option>
                                @endforeach
                            </select>
                            @error('kategori_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 col-md-6" id="kategoriPengeluaranWrap" style="display:none">
                            <label class="form-label fw-semibold">Kategori Pengeluaran <span class="text-danger">*</span></label>
                            <select name="kategori_pengeluaran" id="kategoriPengeluaranSelect" class="form-select @error('kategori_pengeluaran') is-invalid @enderror">
                                <option value="">-- Pilih Kategori Pengeluaran --</option>
                                @foreach($kategoriPengeluarans as $kp)
                                <option value="{{ $kp->value }}" @selected(old('kategori_pengeluaran', 'lain_lain') === $kp->value)>{{ $kp->label() }}</option>
                                @endforeach
                            </select>
                            @error('kategori_pengeluaran')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Untuk breakdown Laporan Setoran Harian.</div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Kas <span class="text-danger">*</span> <x-tooltip key="keuangan.kas_id" /></label>
                            <select name="kas_id" id="kasSelect" class="form-select @error('kas_id') is-invalid @enderror" required>
                                <option value="">-- Pilih Kas --</option>
                                @foreach($kass as $kas)
                                <option value="{{ $kas->id }}" @selected(old('kas_id') == $kas->id)>
                                    {{ $kas->nama_kas }} (Saldo: Rp {{ number_format($kas->saldo_sekarang, 0, ',', '.') }})
                                </option>
                                @endforeach
                            </select>
                            @error('kas_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Kas wajib dipilih supaya saldo kas tetap akurat.</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Keterangan <span class="text-danger">*</span></label>
                            <input type="text" name="keterangan" class="form-control @error('keterangan') is-invalid @enderror"
                                value="{{ old('keterangan') }}" placeholder="Deskripsi singkat transaksi..." required>
                            @error('keterangan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <x-input-rupiah name="jumlah" label="Jumlah" :value="old('jumlah', 0)" required />
                            <small id="jumlahLockedHint" class="text-muted d-none mt-1">
                                🔒 Nominal terkunci sesuai total PO. Klik Reset untuk edit manual.
                            </small>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">
                                Bukti / Foto
                                <small class="text-muted fw-normal">(jpg/png/pdf, maks 5MB, opsional)</small>
                                <x-tooltip key="keuangan.bukti" />
                            </label>
                            <input type="file" name="bukti" class="form-control @error('bukti') is-invalid @enderror"
                                accept=".jpg,.jpeg,.png,.pdf">
                            @error('bukti')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Catatan</label>
                            <textarea name="catatan" class="form-control" rows="1" placeholder="Catatan tambahan...">{{ old('catatan') }}</textarea>
                        </div>

                        <div class="col-12 mt-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-1"></i>Simpan Transaksi
                            </button>
                            <a href="{{ route('keuangan.index') }}" class="btn btn-outline-secondary ms-2">Batal</a>
                        </div>

                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<x-coa-cheat-sheet-modal />
@endsection

@push('scripts')
<script>
// Filter kategori berdasarkan tipe yang dipilih
const tipeSelect     = document.getElementById('tipeSelect');
const kategoriSelect = document.getElementById('kategoriSelect');
const allOptions     = Array.from(kategoriSelect.options);

function filterKategori() {
    const tipe = tipeSelect.value;
    kategoriSelect.innerHTML = '<option value="">-- Pilih Kategori --</option>';
    allOptions.forEach(opt => {
        if (!opt.value) return;
        const optTipe = opt.dataset.tipe;
        if (!tipe || optTipe === 'keduanya' || optTipe === tipe) {
            kategoriSelect.appendChild(opt.cloneNode(true));
        }
    });
}

tipeSelect.addEventListener('change', filterKategori);

// Jalankan saat load jika ada nilai lama (after validation error)
if (tipeSelect.value) filterKategori();

// Toggle Kategori Pengeluaran — cuma wajib & tampil untuk tipe Pengeluaran
const kategoriPengeluaranWrap   = document.getElementById('kategoriPengeluaranWrap');
const kategoriPengeluaranSelect = document.getElementById('kategoriPengeluaranSelect');

function toggleKategoriPengeluaran() {
    const isPengeluaran = tipeSelect.value === 'pengeluaran';
    kategoriPengeluaranWrap.style.display = isPengeluaran ? '' : 'none';
    kategoriPengeluaranSelect.required = isPengeluaran;
    if (!isPengeluaran) kategoriPengeluaranSelect.value = '';
}

tipeSelect.addEventListener('change', toggleKategoriPengeluaran);
toggleKategoriPengeluaran();

// Highlight dropdown "Pilih PO" saat kategori yang dipilih = Pembelian Bahan
// Baku (kode 'PBB') — murni UX preventif supaya kasir/admin tidak lupa link
// PO saat bayar, tidak mengubah validasi/logic simpan sama sekali.
(function () {
    const pilihPoBox = document.getElementById('pilihPoBox');
    const pilihPoHint = document.getElementById('pilihPoHint');
    if (!pilihPoBox || !pilihPoHint) return; // permission kas.pilih_po.view tidak aktif

    function toggleHighlightPo() {
        const opt = kategoriSelect.options[kategoriSelect.selectedIndex];
        const isPembelianBahan = opt && opt.dataset.kode === 'PBB';
        pilihPoBox.classList.toggle('border-warning', isPembelianBahan);
        pilihPoBox.classList.toggle('border-2', isPembelianBahan);
        pilihPoHint.style.display = isPembelianBahan ? '' : 'none';
    }

    kategoriSelect.addEventListener('change', toggleHighlightPo);
    // filterKategori() mengganti isi <select> saat tipe berubah — pasang ulang
    // pengecekan setiap kali itu terjadi juga.
    tipeSelect.addEventListener('change', toggleHighlightPo);
    toggleHighlightPo();
})();

// ============================================================
// Helper "Pilih PO" — MURNI auto-isi field yang sudah ada di atas
// (Tipe, Kategori, Kategori Pengeluaran, Keterangan, Jumlah). Setelah
// terisi, form disimpan lewat alur existing yang sama sekali tidak
// diubah. Kalau fetch gagal (koneksi lambat dsb), dropdown tetap
// tampil kosong dan kasir/admin bisa lanjut input manual seperti biasa.
// ============================================================
(function () {
    const poSelect = document.getElementById('poSelect');
    if (!poSelect) return; // permission kas.pilih_po.view tidak aktif

    const poIdInput   = document.getElementById('poIdInput');
    const btnResetPo  = document.getElementById('btnResetPo');
    const keteranganInput = document.querySelector('input[name="keterangan"]');
    const jumlahInput = document.querySelector('[data-rupiah-for="jumlah"]');
    const jumlahLockedHint = document.getElementById('jumlahLockedHint');

    let poDataById = {};
    // Guard anti-duplikat (Rule #64) — poSelect punya DUA listener 'change'
    // (native + jQuery) supaya kompatibel dengan Select2, yang men-trigger
    // perubahan lewat jQuery.trigger('change') internal (tidak terdengar
    // addEventListener native biasa). Guard ini mencegah handlePoChange()
    // jalan 2x kalau suatu saat KEDUA listener kebetulan fire untuk 1 event.
    let lastPoValue = null;

    function isiJumlah(nilai) {
        if (!jumlahInput) return;
        jumlahInput.value = String(Math.round(nilai));
        jumlahInput.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function terapkanPo(po) {
        try {
            poIdInput.value = po.id;
            btnResetPo.style.display = '';

            // Tipe = Pengeluaran. Selain dispatch 'change' (untuk listener lain
            // spt toggleHighlightPo), filterKategori() & toggleKategoriPengeluaran()
            // DIPANGGIL LANGSUNG juga (Fase 3 fix) — sebelumnya cuma mengandalkan
            // urutan 3 listener yang terpasang lewat dispatchEvent, yang secara
            // teori synchronous tapi rawan tidak konsisten di beberapa kondisi
            // browser/timing nyata (dilaporkan Owner: Tipe/Kategori/Keterangan
            // tidak ter-auto-fill walau lock nominal Fase 2 tetap aktif). Manggil
            // langsung menghilangkan ketergantungan pada urutan/keberadaan listener.
            tipeSelect.value = 'pengeluaran';
            tipeSelect.dispatchEvent(new Event('change'));
            filterKategori();
            toggleKategoriPengeluaran();

            if (po.kategori_id_default) {
                kategoriSelect.value = String(po.kategori_id_default);
                kategoriSelect.dispatchEvent(new Event('change'));
            }
            if (po.kategori_pengeluaran_default) {
                kategoriPengeluaranSelect.value = po.kategori_pengeluaran_default;
            }
            if (keteranganInput && po.keterangan_default) {
                keteranganInput.value = po.keterangan_default;
            }
            isiJumlah(po.total);

            // Nominal dikunci sesuai total PO — cegah kasir ketik selisih (Poin 3).
            // Hanya visible input yang di-readonly (bukan hidden input x-input-rupiah
            // yang benar-benar ter-submit), supaya JS auto-fill di atas tetap bisa
            // sync nilai ke hidden input meski readonly aktif.
            if (jumlahInput) {
                jumlahInput.readOnly = true;
                jumlahInput.classList.add('bg-light');
            }
            // classList (bukan style.display) — utility class Bootstrap
            // .d-block/.d-none pakai !important, akan menang lawan inline
            // style biasa kalau dicampur (Rule #64: ditemukan lewat testing
            // Playwright, hint ini sempat SELALU tampil regardless of state
            // karena class d-block lama bentrok dengan inline display:none).
            if (jumlahLockedHint) jumlahLockedHint.classList.remove('d-none');

            // Kas SENGAJA tidak disentuh — kasir wajib pilih manual supaya
            // saldo kas tetap akurat (tidak ada "kas default" yang aman ditebak).
        } catch (e) {
            console.error('terapkanPo() gagal auto-fill field:', e);
        }
    }

    // Reset SEMUA field yang di-auto-fill terapkanPo() — dipakai bareng oleh
    // tombol Reset DAN handlePoChange() saat dropdown di-clear ke "" (mis.
    // via tombol X Select2). Sebelumnya (pre-Fase3) cuma jumlah yang
    // direset, Tipe/Kategori/Keterangan tersisa terisi dan membingungkan.
    function resetPoFields() {
        poIdInput.value = '';
        btnResetPo.style.display = 'none';

        tipeSelect.value = '';
        tipeSelect.dispatchEvent(new Event('change'));
        filterKategori();
        toggleKategoriPengeluaran();

        if (keteranganInput) keteranganInput.value = '';

        if (jumlahInput) {
            jumlahInput.readOnly = false;
            jumlahInput.classList.remove('bg-light');
            jumlahInput.value = '';
            jumlahInput.dispatchEvent(new Event('input', { bubbles: true }));
        }
        if (jumlahLockedHint) jumlahLockedHint.classList.add('d-none');
    }

    // Root cause bug terapkanPo() tidak jalan (Rule #64, ditemukan lewat
    // testing Playwright): Select2 memicu perubahan pilihan lewat
    // jQuery.trigger('change') INTERNAL saat user klik opsi di widget-nya —
    // event ini TIDAK terdengar addEventListener('change', ...) native biasa
    // (dibuktikan: nativeListenerFired=false, jqueryListenerFired=true untuk
    // trigger yang identik). Fix: pasang handler di DUA jalur — native
    // (fallback kalau Select2 gagal load) DAN jQuery (menangkap trigger
    // Select2) — dengan guard lastPoValue supaya tidak dobel-proses kalau
    // suatu saat kondisi browser tertentu memicu keduanya untuk 1 event.
    function handlePoChange() {
        const val = poSelect.value;
        if (val === lastPoValue) return;
        lastPoValue = val;

        const po = poDataById[val];
        if (po) {
            terapkanPo(po);
        } else {
            resetPoFields();
        }
    }

    poSelect.addEventListener('change', handlePoChange);
    if (typeof jQuery !== 'undefined') {
        jQuery(poSelect).on('change', handlePoChange);
    }

    btnResetPo.addEventListener('click', function () {
        resetPoFields();
        // Reset guard juga — supaya kalau kasir pilih PO yang SAMA lagi
        // setelah Reset, handlePoChange() tetap mendeteksinya sebagai
        // perubahan (bukan di-skip guard karena value dianggap "sama").
        lastPoValue = '';

        if (typeof $ !== 'undefined' && $.fn && $.fn.select2 && $(poSelect).hasClass('select2-hidden-accessible')) {
            $(poSelect).val('').trigger('change.select2');
        } else {
            poSelect.value = '';
        }
    });

    fetch('{{ route('keuangan.po-pending-list') }}')
        .then(res => res.ok ? res.json() : Promise.reject())
        .then(json => {
            (json.data || []).forEach(po => {
                poDataById[po.id] = po;
                const opt = document.createElement('option');
                opt.value = po.id;
                opt.textContent = 'PO #' + po.nomor_po + ' - ' + po.supplier
                    + ' (Rp ' + new Intl.NumberFormat('id-ID').format(po.total) + ', ' + po.umur_hari + ' hari)';
                poSelect.appendChild(opt);
            });

            try {
                if (typeof $ !== 'undefined' && $.fn && $.fn.select2) {
                    $(poSelect).select2({
                        theme: 'bootstrap-5',
                        placeholder: 'Ketik nomor PO atau nama supplier...',
                        allowClear: true,
                        width: '100%',
                    });
                }
            } catch (e) {
                console.warn('Select2 gagal load, pakai dropdown PO native:', e);
            }

            // Pre-select dari query string ?po_id=X — dipakai tombol "Catat
            // Pembayaran" di Dashboard PO / Detail PO untuk quick-navigate
            // langsung dengan PO sudah terpilih.
            const preselectId = new URLSearchParams(window.location.search).get('po_id');
            if (preselectId && poDataById[preselectId]) {
                if (typeof $ !== 'undefined' && $.fn && $.fn.select2 && $(poSelect).hasClass('select2-hidden-accessible')) {
                    $(poSelect).val(preselectId).trigger('change');
                } else {
                    poSelect.value = preselectId;
                    poSelect.dispatchEvent(new Event('change'));
                }
            }
        })
        .catch(() => {
            // Fetch gagal — dropdown tetap ada opsi default "Tidak dari PO / Input Manual",
            // kasir/admin lanjut input manual seperti biasa.
        });
})();
</script>
@endpush
