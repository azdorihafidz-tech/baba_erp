@extends('layouts.app')

@section('title', 'Penyesuaian Stok')

@push('styles')
<style>
.form-card      { border: 1px solid #e2e8f0; border-radius: 12px; background: white; }
.mode-section   { display:none; }
.stok-info-box  {
    background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;
    padding: .85rem 1rem; min-height: 56px; display:flex; align-items:center; gap:.5rem;
}
.stok-qty-display { font-size:1.3rem; font-weight:700; color:#1e293b; }
.stok-qty-danger  { color:#dc2626; }
.batch-row-manual { background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:.6rem .8rem; margin-bottom:.4rem; }
.fifo-preview-row { font-size:.82rem; }
.toggle-btn-group .btn { font-size:.82rem; min-height:38px; }
.selisih-badge { font-size:.92rem; font-weight:600; padding:.35rem .8rem; border-radius:8px; display:inline-flex; align-items:center; gap:.4rem; }
</style>
@endpush

@section('content')

{{-- PAGE HEADER --}}
<div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-0" style="color:#1e293b">
            <i class="bi bi-sliders me-2 text-warning"></i>Penyesuaian Stok
        </h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0" style="font-size:.8rem">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('stok.index') }}" class="text-decoration-none">Stok</a></li>
                <li class="breadcrumb-item active">Adjustment</li>
            </ol>
        </nav>
    </div>
    <x-panduan-button slug="stok-adjustment" />
</div>

<div class="alert alert-info d-flex align-items-start gap-2 mb-4" role="alert">
    <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
    <div style="font-size:.87rem">
        <strong>Tentang Adjustment Stok:</strong> Masukkan qty stok <em>fisik</em> yang seharusnya ada
        saat ini. Sistem mencatat selisih, menyinkronkan batch FIFO, dan otomatis mencatat entri keuangan
        (Beban Kerugian / Koreksi Stok Masuk).
    </div>
</div>

@if(session('error'))
<div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="row">
    <div class="col-12 col-lg-7 col-xl-6">
        <div class="form-card p-4">
            <form method="POST" action="{{ route('stok.adjustment.store') }}" id="formAdjustment">
            @csrf

                {{-- ── Lokasi ── --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold" style="font-size:.85rem">
                        Lokasi <span class="text-danger">*</span>
                        <x-tooltip key="adjustment.lokasi" />
                    </label>
                    <select name="lokasi_id" id="lokasi_id"
                        class="form-select @error('lokasi_id') is-invalid @enderror" required>
                        <option value="">— Pilih Lokasi —</option>
                        @foreach($lokasiList as $lok)
                        <option value="{{ $lok->id }}"
                            {{ old('lokasi_id', request('lokasi_id')) == $lok->id ? 'selected' : '' }}>
                            {{ $lok->nama_cabang }}
                            @if($lok->tipe?->value === 'gudang_pusat') (Gudang Pusat) @endif
                        </option>
                        @endforeach
                    </select>
                    @error('lokasi_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                {{-- ── Item ── --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold" style="font-size:.85rem">
                        Item <span class="text-danger">*</span>
                        <x-tooltip key="adjustment.item" />
                    </label>
                    <select name="item_id" id="item_id"
                        class="form-select @error('item_id') is-invalid @enderror" required>
                        <option value="">— Pilih Item —</option>
                        @php
                            $grpLabels = ['bahan_baku'=>'Bahan Baku','produk_jadi'=>'Produk Jadi','kemasan'=>'Kemasan','lainnya'=>'Lainnya'];
                            $grouped   = $items->groupBy('tipe');
                        @endphp
                        @foreach($grpLabels as $grpKey => $grpLabel)
                            @if(isset($grouped[$grpKey]) && $grouped[$grpKey]->count())
                            <optgroup label="{{ $grpLabel }}">
                                @foreach($grouped[$grpKey] as $it)
                                <option value="{{ $it->id }}"
                                    data-satuan="{{ $it->satuan }}"
                                    data-harga="{{ $it->harga_beli_terakhir ?? 0 }}"
                                    data-unit-beli="{{ $it->unit_beli }}"
                                    data-isi-per-unit="{{ $it->isi_per_unit_beli }}"
                                    {{ old('item_id', request('item_id')) == $it->id ? 'selected' : '' }}>
                                    [{{ $it->kode_item }}] {{ $it->nama_dengan_cabang }}
                                </option>
                                @endforeach
                            </optgroup>
                            @endif
                        @endforeach
                    </select>
                    @error('item_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                {{-- ── Stok Saat Ini ── --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold" style="font-size:.85rem">
                        Stok Saat Ini
                        <x-tooltip key="adjustment.stok_saat_ini" />
                    </label>
                    <div class="stok-info-box" id="stokInfoBox">
                        <span id="stokDisplay" class="text-muted" style="font-size:.9rem">
                            — Pilih item dan lokasi terlebih dahulu —
                        </span>
                    </div>
                </div>

                {{-- ── Qty Fisik ── --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold" style="font-size:.85rem">
                        Qty Stok Fisik (Hasil Hitung) <span class="text-danger">*</span>
                        <x-tooltip key="adjustment.qty_fisik" />
                    </label>
                    <div class="input-group">
                        <input type="number" name="qty_fisik" id="qty_fisik"
                            class="form-control @error('qty_fisik') is-invalid @enderror"
                            placeholder="0" min="0" step="0.001"
                            value="{{ old('qty_fisik') }}" required>
                        <select name="unit_input" id="unit_input" class="form-select" style="max-width:150px">
                            <option value="">—</option>
                        </select>
                        @error('qty_fisik') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <span id="satuanDisplay" class="d-none">—</span>
                    <div id="unitInfo" class="small text-primary mt-1"></div>
                    <div id="selisihInfo" class="mt-2"></div>
                </div>

                {{-- ══════════════════════════════════════════
                     SECTION TURUN
                ══════════════════════════════════════════ --}}
                <div id="sectionTurun" class="mode-section border border-danger-subtle rounded p-3 mb-3" style="background:#fff5f5">
                    <div class="fw-semibold mb-2" style="font-size:.85rem;color:#dc2626">
                        <i class="bi bi-arrow-down-circle-fill me-1"></i>Distribusi Batch
                        <x-tooltip key="adjustment.distribusi_turun" placement="right" />
                    </div>

                    {{-- Toggle FIFO / Manual --}}
                    <div class="mb-3">
                        <div class="toggle-btn-group btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="mode_distribusi" id="modeFifo"
                                   value="fifo" checked>
                            <label class="btn btn-outline-danger" for="modeFifo">
                                <i class="bi bi-sort-down me-1"></i>FIFO Otomatis
                            </label>
                            <input type="radio" class="btn-check" name="mode_distribusi" id="modeManual"
                                   value="manual">
                            <label class="btn btn-outline-danger" for="modeManual">
                                <i class="bi bi-ui-checks me-1"></i>Manual (pilih batch)
                            </label>
                        </div>
                    </div>

                    {{-- FIFO Preview --}}
                    <div id="fifoPreview">
                        <div class="text-muted" style="font-size:.82rem">
                            <i class="bi bi-clock-history me-1"></i>
                            <span id="fifoPreviewText">Isi qty fisik untuk melihat preview FIFO.</span>
                        </div>
                        <div id="fifoPreviewTable" class="mt-2"></div>
                    </div>

                    {{-- Manual Batch Selection --}}
                    <div id="manualBatchSection" style="display:none">
                        <div class="text-muted mb-2" style="font-size:.8rem">
                            Tentukan qty yang diambil dari masing-masing batch. Total harus sama dengan selisih.
                        </div>
                        <div id="manualBatchList"></div>
                        <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                            <span style="font-size:.82rem">Total diambil:</span>
                            <span id="manualTotal" class="fw-bold" style="font-size:.88rem">0</span>
                            <span id="manualSelisih" class="badge" style="font-size:.72rem"></span>
                        </div>
                    </div>
                </div>

                {{-- ══════════════════════════════════════════
                     SECTION NAIK
                ══════════════════════════════════════════ --}}
                <div id="sectionNaik" class="mode-section border border-success-subtle rounded p-3 mb-3" style="background:#f0fdf4">
                    <div class="fw-semibold mb-2" style="font-size:.85rem;color:#16a34a">
                        <i class="bi bi-arrow-up-circle-fill me-1"></i>Distribusi Batch
                        <x-tooltip key="adjustment.distribusi_naik" placement="right" />
                    </div>

                    {{-- Toggle Batch Baru / Batch Existing --}}
                    <div class="mb-3">
                        <div class="toggle-btn-group btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="mode_distribusi" id="modeBatchBaru"
                                   value="batch_baru">
                            <label class="btn btn-outline-success" for="modeBatchBaru">
                                <i class="bi bi-plus-circle me-1"></i>Buat Batch Baru
                            </label>
                            <input type="radio" class="btn-check" name="mode_distribusi" id="modeBatchExisting"
                                   value="batch_existing">
                            <label class="btn btn-outline-success" for="modeBatchExisting">
                                <i class="bi bi-layers me-1"></i>Tambah ke Batch Existing
                            </label>
                        </div>
                    </div>

                    {{-- Batch Baru: harga --}}
                    <div id="batchBaruSection">
                        <label class="form-label" style="font-size:.82rem">Harga Beli per Unit (opsional, override)</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">Rp</span>
                            <input type="text" name="harga_custom" id="harga_custom"
                                class="form-control"
                                placeholder="Otomatis dari Harga Standar"
                                inputmode="numeric">
                        </div>
                        <div id="hargaAutoInfo" class="form-text" style="font-size:.76rem"></div>
                    </div>

                    {{-- Batch Existing: pilih batch --}}
                    <div id="batchExistingSection" style="display:none">
                        <label class="form-label" style="font-size:.82rem">Pilih Batch yang Ditambah <span class="text-danger">*</span></label>
                        <select name="batch_id_target" id="batch_id_target" class="form-select form-select-sm">
                            <option value="">— Pilih batch (FIFO order) —</option>
                        </select>
                    </div>
                </div>

                {{-- Hidden: kalau tidak ada selisih, default mode_distribusi agar FormRequest valid --}}
                <input type="hidden" id="modeDistribusiHidden" name="mode_distribusi" value="fifo">

                {{-- ── Alasan ── --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold" style="font-size:.85rem">
                        Alasan <span class="text-danger">*</span>
                        <x-tooltip key="adjustment.alasan" />
                    </label>
                    <select name="alasan" id="alasan"
                        class="form-select @error('alasan') is-invalid @enderror" required>
                        <option value="">— Pilih alasan —</option>
                        <option value="susut"        {{ old('alasan')=='susut'        ? 'selected':'' }}>Susut (evaporasi/penyusutan alami)</option>
                        <option value="rusak"        {{ old('alasan')=='rusak'        ? 'selected':'' }}>Rusak / Kadaluarsa</option>
                        <option value="hilang"       {{ old('alasan')=='hilang'       ? 'selected':'' }}>Hilang / Dicuri</option>
                        <option value="salah_hitung" {{ old('alasan')=='salah_hitung' ? 'selected':'' }}>Salah Hitung Sebelumnya</option>
                        <option value="audit"        {{ old('alasan')=='audit'        ? 'selected':'' }}>Audit Berkala (Stock Opname)</option>
                        <option value="lainnya"      {{ old('alasan')=='lainnya'      ? 'selected':'' }}>Lainnya</option>
                    </select>
                    @error('alasan') <div class="invalid-feedback">{{ $message }}</div> @enderror

                    {{-- Hint dampak keuangan — murni info, update dinamis via JS saat alasan berubah --}}
                    <div id="alasanKeuanganHint" class="mt-2" style="display:none"></div>

                    {{-- Khusus alasan "Lainnya": user pilih sendiri apakah dicatat sebagai biaya --}}
                    <div id="catatBiayaWrap" class="form-check mt-2" style="display:none">
                        <input class="form-check-input" type="checkbox" name="catat_sebagai_biaya" value="1"
                            id="catatSebagaiBiaya" {{ old('catat_sebagai_biaya') ? 'checked' : '' }}>
                        <label class="form-check-label small" for="catatSebagaiBiaya">
                            Catat sebagai biaya rugi? (kalau dicentang, adjustment ini akan bikin transaksi keuangan)
                        </label>
                    </div>
                </div>

                {{-- ── Catatan ── --}}
                <div class="mb-4">
                    <label class="form-label fw-semibold" style="font-size:.85rem">
                        Catatan (opsional)
                        <x-tooltip key="adjustment.catatan" />
                    </label>
                    <textarea name="catatan" rows="2"
                        class="form-control @error('catatan') is-invalid @enderror"
                        placeholder="cth: Hasil stock opname 18 Jun 2026, ditemukan selisih karena..."
                        >{{ old('catatan') }}</textarea>
                    @error('catatan') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                {{-- Tombol --}}
                <div class="d-flex gap-2 flex-column flex-sm-row">
                    <button type="button" id="btnPreview"
                        class="btn btn-warning text-white fw-semibold flex-fill"
                        style="min-height:44px" disabled>
                        <i class="bi bi-eye me-2"></i>Preview & Simpan
                    </button>
                    <a href="{{ route('stok.index') }}" class="btn btn-outline-secondary flex-fill"
                       style="min-height:44px">
                        <i class="bi bi-arrow-left me-2"></i>Kembali
                    </a>
                </div>

            </form>
        </div>
    </div>

    {{-- Side info panel (desktop) --}}
    <div class="col-12 col-lg-5 col-xl-6 mt-3 mt-lg-0 d-none d-lg-block">
        <div class="form-card p-3" id="batchSidePanel">
            <div class="fw-semibold mb-2" style="font-size:.85rem;color:#475569">
                <i class="bi bi-layers me-1"></i> Batch Stok Aktif
                <x-tooltip key="adjustment.batch_stok" placement="left" />
            </div>
            <div id="batchSideContent" class="text-muted" style="font-size:.82rem">
                — Pilih item dan lokasi untuk melihat batch FIFO —
            </div>
        </div>
    </div>
</div>

{{-- ═══════════ MODAL KONFIRMASI ═══════════ --}}
<div class="modal fade modal-fullscreen-sm-down" id="modalKonfirmasi" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-shield-check me-2 text-warning"></i>Konfirmasi Adjustment
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalKonfirmasiBody">
                {{-- filled by JS --}}
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x me-1"></i>Batal
                </button>
                <button type="button" id="btnKonfirmasiSubmit"
                    class="btn btn-warning text-white fw-semibold" style="min-width:160px">
                    <i class="bi bi-check-lg me-1"></i>Konfirmasi & Simpan
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
// ── State ──────────────────────────────────────────────────────────
let currentStok   = null;   // float: qty stok saat ini
let currentBatches = [];    // array batch {id, qty_awal, qty_sisa, harga_beli_per_unit, tanggal_masuk}
let currentSatuan  = '—';
let hargaTerakhir  = 0;

// ── DOM refs ───────────────────────────────────────────────────────
const lokasiSel   = document.getElementById('lokasi_id');
const itemSel     = document.getElementById('item_id');
const qtyFisikIn  = document.getElementById('qty_fisik');
const satuanDisp  = document.getElementById('satuanDisplay');
const stokDisp    = document.getElementById('stokDisplay');
const selisihInfo = document.getElementById('selisihInfo');
const btnPreview  = document.getElementById('btnPreview');
const modeHidden  = document.getElementById('modeDistribusiHidden');

// Alasan + dampak keuangan
const alasanEl          = document.getElementById('alasan');
const alasanKeuanganHint = document.getElementById('alasanKeuanganHint');
const catatBiayaWrap    = document.getElementById('catatBiayaWrap');
const catatSebagaiBiaya = document.getElementById('catatSebagaiBiaya');

// Section turun
const secTurun    = document.getElementById('sectionTurun');
const secNaik     = document.getElementById('sectionNaik');

// Turun sub
const radioFifo   = document.getElementById('modeFifo');
const radioManual = document.getElementById('modeManual');
const fifoPreview = document.getElementById('fifoPreview');
const fifoText    = document.getElementById('fifoPreviewText');
const fifoTable   = document.getElementById('fifoPreviewTable');
const manualSec   = document.getElementById('manualBatchSection');
const manualList  = document.getElementById('manualBatchList');
const manualTotal = document.getElementById('manualTotal');
const manualSel   = document.getElementById('manualSelisih');

// Naik sub
const radioBatchBaru     = document.getElementById('modeBatchBaru');
const radioBatchExisting = document.getElementById('modeBatchExisting');
const batchBaruSec       = document.getElementById('batchBaruSection');
const batchExistingSec   = document.getElementById('batchExistingSection');
const batchIdTarget      = document.getElementById('batch_id_target');
const hargaCustomIn      = document.getElementById('harga_custom');
const hargaAutoInfo      = document.getElementById('hargaAutoInfo');

// Side panel
const batchSideContent   = document.getElementById('batchSideContent');

// ── Helpers ────────────────────────────────────────────────────────
const fmtQty = q => {
    if (q == null || isNaN(q)) return '0';
    const n = parseFloat(q);
    const [int, dec] = n.toFixed(3).split('.');
    return int.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + (dec.replace(/0+$/, '') ? ',' + dec.replace(/0+$/, '') : '');
};
const fmtRp = v => 'Rp ' + Math.round(parseFloat(v)||0).toLocaleString('id-ID');
const fmtTgl = t => {
    if (!t) return '—';
    const d = new Date(t); return d.toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric'});
};

// ── AJAX load stok + batches ───────────────────────────────────────
function loadItemData() {
    const lokasiId = lokasiSel.value, itemId = itemSel.value;
    const selOpt   = itemSel.options[itemSel.selectedIndex];

    currentSatuan  = selOpt ? (selOpt.dataset.satuan || '—') : '—';
    hargaTerakhir  = selOpt ? parseFloat(selOpt.dataset.harga || 0) : 0;
    satuanDisp.textContent = currentSatuan;

    // Sprint Unit Family: populate dropdown Unit sesuai item.
    var unitSel = document.getElementById('unit_input');
    if (unitSel && selOpt) {
        unitSel.innerHTML = '';
        var optPakai = document.createElement('option');
        optPakai.value = currentSatuan;
        optPakai.textContent = currentSatuan;
        unitSel.appendChild(optPakai);
        var ub = selOpt.dataset.unitBeli || '';
        var ipu = parseFloat(selOpt.dataset.isiPerUnit || 0);
        if (ub && ipu > 0) {
            var optBeli = document.createElement('option');
            optBeli.value = ub;
            optBeli.textContent = ub + ' (1 = ' + ipu + ' ' + currentSatuan + ')';
            unitSel.appendChild(optBeli);
        }
    }
    updateHargaAutoInfo();
    refreshUnitInfo();

    if (!lokasiId || !itemId) {
        currentStok = null; currentBatches = [];
        stokDisp.innerHTML = '<span class="text-muted">— Pilih item dan lokasi terlebih dahulu —</span>';
        batchSideContent.innerHTML = '— Pilih item dan lokasi —';
        resetSections();
        return;
    }

    stokDisp.innerHTML = '<span class="text-muted"><i class="bi bi-arrow-repeat me-1"></i>Memuat...</span>';
    batchSideContent.innerHTML = '<span class="text-muted">Memuat...</span>';

    Promise.all([
        fetch(`{{ route('api.stok.qty') }}?lokasi_id=${lokasiId}&item_id=${itemId}`,{headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.json()),
        fetch(`{{ route('api.stok.batches') }}?lokasi_id=${lokasiId}&item_id=${itemId}`,{headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.json()),
    ]).then(([qtyData, batchData]) => {
        currentStok    = parseFloat(qtyData.qty ?? 0);
        currentBatches = batchData.batches ?? [];
        if (qtyData.harga_beli_terakhir) hargaTerakhir = parseFloat(qtyData.harga_beli_terakhir);

        const isKritis = currentStok <= parseFloat(qtyData.qty_minimum || 0) && parseFloat(qtyData.qty_minimum || 0) > 0;
        stokDisp.innerHTML = `<span class="stok-qty-display ${isKritis?'stok-qty-danger':''}">${fmtQty(currentStok)}</span> <span class="text-muted">${currentSatuan}</span>${isKritis?'<span class="badge bg-danger-subtle text-danger ms-2" style="font-size:.68rem">Stok Kritis</span>':''}`;

        renderBatchSidePanel();
        updateHargaAutoInfo();
        onQtyChange();
        populateBatchExisting();
    }).catch(() => {
        stokDisp.innerHTML = '<span class="text-danger">Gagal memuat data stok.</span>';
        batchSideContent.innerHTML = '<span class="text-danger">Gagal.</span>';
    });
}

// ── Render side panel batches ──────────────────────────────────────
function renderBatchSidePanel() {
    if (!currentBatches.length) {
        batchSideContent.innerHTML = '<span class="text-muted" style="font-size:.8rem">Belum ada batch stok terdaftar untuk item ini.</span>';
        return;
    }
    let html = `<div class="table-responsive"><table class="table table-sm table-hover mb-0" style="font-size:.76rem">
        <thead class="table-light"><tr>
            <th>#</th><th>Tgl Masuk</th><th class="text-end">Sisa</th><th class="text-end">Harga/unit</th><th>Sumber</th>
        </tr></thead><tbody>`;
    currentBatches.forEach((b,i) => {
        const isNext = i === 0;
        html += `<tr${isNext?' class="table-warning"':''}>
            <td><span class="badge bg-secondary" style="font-size:.62rem">#${b.id}</span>${isNext?'<span class="badge bg-warning text-dark ms-1" style="font-size:.58rem">NEXT</span>':''}</td>
            <td>${fmtTgl(b.tanggal_masuk)}</td>
            <td class="text-end fw-semibold">${fmtQty(b.qty_sisa)}</td>
            <td class="text-end">${fmtRp(b.harga_beli_per_unit)}</td>
            <td class="text-muted">${b.referensi_type||'—'}</td>
        </tr>`;
    });
    html += '</tbody></table></div>';
    batchSideContent.innerHTML = html;
}

// Sprint Unit Family: konversi qty_fisik ke unit pakai kalau user pilih unit_beli.
function qtyFisikPakai() {
    var raw = parseFloat(qtyFisikIn.value);
    if (isNaN(raw)) return NaN;
    var unitSel = document.getElementById('unit_input');
    var selOpt = itemSel.options[itemSel.selectedIndex];
    if (!unitSel || !selOpt) return raw;
    var ub = selOpt.dataset.unitBeli || '';
    var ipu = parseFloat(selOpt.dataset.isiPerUnit || 0);
    if (unitSel.value && ub && ipu > 0 && unitSel.value.toLowerCase() === ub.toLowerCase()) {
        return raw * ipu;
    }
    return raw;
}
function refreshUnitInfo() {
    var info = document.getElementById('unitInfo');
    if (!info) return;
    var unitSel = document.getElementById('unit_input');
    var selOpt = itemSel.options[itemSel.selectedIndex];
    var raw = parseFloat(qtyFisikIn.value);
    if (!unitSel || !selOpt || isNaN(raw) || raw <= 0) { info.innerHTML = ''; return; }
    var ub = selOpt.dataset.unitBeli || '';
    var ipu = parseFloat(selOpt.dataset.isiPerUnit || 0);
    if (unitSel.value && ub && ipu > 0 && unitSel.value.toLowerCase() === ub.toLowerCase()) {
        var pakai = raw * ipu;
        info.innerHTML = '<i class="bi bi-arrow-right me-1"></i>' + raw + ' ' + ub + ' = <strong>' + pakai.toLocaleString('id-ID') + ' ' + currentSatuan + '</strong>';
    } else {
        info.innerHTML = '';
    }
}

// ── Update selisih & toggle sections ──────────────────────────────
function onQtyChange() {
    refreshUnitInfo();
    if (currentStok === null) { selisihInfo.innerHTML=''; resetSections(); return; }
    const fisik = qtyFisikPakai();
    if (isNaN(fisik)) { selisihInfo.innerHTML=''; resetSections(); return; }

    const selisih = fisik - currentStok;
    if (Math.abs(selisih) < 0.001) {
        selisihInfo.innerHTML = '<span class="text-muted">Tidak ada perubahan.</span>';
        resetSections(); btnPreview.disabled = false; return;
    }

    if (selisih < 0) {
        selisihInfo.innerHTML = `<span class="selisih-badge" style="background:#fef2f2;color:#dc2626">
            <i class="bi bi-arrow-down-circle-fill"></i> Stok berkurang <strong>${fmtQty(Math.abs(selisih))}</strong> ${currentSatuan}
        </span>`;
        showTurun(); hideSectionNaik();
        renderFifoPreview(Math.abs(selisih));
    } else {
        selisihInfo.innerHTML = `<span class="selisih-badge" style="background:#f0fdf4;color:#16a34a">
            <i class="bi bi-arrow-up-circle-fill"></i> Stok bertambah <strong>${fmtQty(selisih)}</strong> ${currentSatuan}
        </span>`;
        hideSectionTurun(); showNaik();
    }
    btnPreview.disabled = false;
}

function resetSections() {
    secTurun.style.display = 'none';
    secNaik.style.display  = 'none';
    // Disable semua radio agar form cukup kirim modeHidden (value="fifo")
    [radioFifo, radioManual, radioBatchBaru, radioBatchExisting].forEach(r => r.disabled = true);
    modeHidden.disabled = false;
    btnPreview.disabled = true;
}

function showTurun() {
    secTurun.style.display = 'block';    // override .mode-section { display:none }
    // Aktifkan hanya radio TURUN, nonaktifkan NAIK agar tidak ikut submit
    radioFifo.disabled         = false;
    radioManual.disabled       = false;
    radioBatchBaru.disabled    = true;
    radioBatchExisting.disabled = true;
    // Default ke FIFO kalau belum ada pilihan
    if (!radioFifo.checked && !radioManual.checked) radioFifo.checked = true;
    modeHidden.disabled = true;  // hidden input tidak perlu, radio yg submit
}

function hideSectionTurun() {
    secTurun.style.display = 'none';
}

function showNaik() {
    secNaik.style.display = 'block';     // override .mode-section { display:none }
    // Aktifkan hanya radio NAIK, nonaktifkan TURUN
    radioFifo.disabled          = true;
    radioManual.disabled        = true;
    radioBatchBaru.disabled     = false;
    radioBatchExisting.disabled = false;
    // Default ke batch_baru kalau belum ada pilihan
    if (!radioBatchBaru.checked && !radioBatchExisting.checked) radioBatchBaru.checked = true;
    modeHidden.disabled = true;
}

function hideSectionNaik() {
    secNaik.style.display = 'none';
}

// ── FIFO Simulation ────────────────────────────────────────────────
function simulateFifo(batches, qty) {
    let sisa   = qty;
    const plan = [];
    for (const b of batches) {
        if (sisa <= 0.001) break;
        const ambil = Math.min(parseFloat(b.qty_sisa), sisa);
        if (ambil > 0) plan.push({...b, ambil, nilai: ambil * parseFloat(b.harga_beli_per_unit)});
        sisa -= ambil;
    }
    if (sisa > 0.001) {
        plan.push({id:'?', tanggal_masuk:null, harga_beli_per_unit: hargaTerakhir,
            ambil: sisa, nilai: sisa * hargaTerakhir, referensi_type:'fallback'});
    }
    return plan;
}

function renderFifoPreview(qty) {
    const plan = simulateFifo(currentBatches, qty);
    if (!plan.length) { fifoTable.innerHTML=''; fifoText.textContent='Tidak ada batch untuk dikurangi.'; return; }

    fifoText.textContent = 'Akan mengambil dari batch berikut (FIFO):';
    let total = 0;
    let html = `<table class="table table-sm mb-0" style="font-size:.78rem">
        <thead class="table-light"><tr>
            <th>#Batch</th><th>Tgl</th><th class="text-end">Ambil</th><th class="text-end">Harga</th><th class="text-end">Nilai</th>
        </tr></thead><tbody>`;
    plan.forEach(p => {
        total += p.nilai;
        html += `<tr class="fifo-preview-row">
            <td><span class="badge bg-secondary">#${p.id}</span></td>
            <td>${fmtTgl(p.tanggal_masuk)}</td>
            <td class="text-end fw-semibold">${fmtQty(p.ambil)} ${currentSatuan}</td>
            <td class="text-end">${fmtRp(p.harga_beli_per_unit)}</td>
            <td class="text-end">${fmtRp(p.nilai)}</td>
        </tr>`;
    });
    html += `</tbody><tfoot><tr class="table-warning fw-semibold">
        <td colspan="4" class="text-end">Total Nilai Kerugian:</td>
        <td class="text-end">${fmtRp(total)}</td>
    </tr></tfoot></table>`;
    fifoTable.innerHTML = html;
}

// ── Manual Batch Section ───────────────────────────────────────────
function renderManualBatches() {
    if (!currentBatches.length) {
        manualList.innerHTML = '<div class="text-muted" style="font-size:.8rem">Tidak ada batch aktif.</div>';
        return;
    }
    const fisik = parseFloat(qtyFisikIn.value) || 0;
    const total = currentStok !== null ? currentStok - fisik : 0;

    let html = '';
    currentBatches.forEach((b, i) => {
        html += `<div class="batch-row-manual d-flex align-items-center gap-2">
            <div class="flex-grow-1" style="font-size:.8rem">
                <span class="badge bg-secondary">#${b.id}</span>
                <span class="ms-1">${fmtTgl(b.tanggal_masuk)}</span>
                <span class="text-muted ms-1">| sisa: ${fmtQty(b.qty_sisa)} | ${fmtRp(b.harga_beli_per_unit)}/unit</span>
            </div>
            <div style="width:100px">
                <input type="number" class="form-control form-control-sm manual-batch-qty text-end"
                    data-max="${parseFloat(b.qty_sisa)}"
                    data-harga="${parseFloat(b.harga_beli_per_unit)}"
                    data-batch-id="${b.id}"
                    name="batch_distribusi[${i}][qty]"
                    min="0" step="0.001" max="${parseFloat(b.qty_sisa)}"
                    value="0" placeholder="0">
                <input type="hidden" name="batch_distribusi[${i}][batch_id]" value="${b.id}">
            </div>
        </div>`;
    });
    manualList.innerHTML = html;
    document.querySelectorAll('.manual-batch-qty').forEach(inp => inp.addEventListener('input', updateManualTotal));
    updateManualTotal();
}

function updateManualTotal() {
    const fisik = parseFloat(qtyFisikIn.value) || 0;
    const selisih = currentStok !== null ? currentStok - fisik : 0;
    let total = 0;
    document.querySelectorAll('.manual-batch-qty').forEach(inp => { total += parseFloat(inp.value)||0; });
    manualTotal.textContent = fmtQty(total) + ' ' + currentSatuan;
    const diff = Math.abs(total - selisih);
    if (diff < 0.001) {
        manualSel.className = 'badge bg-success'; manualSel.textContent = '✓ Sesuai';
    } else {
        manualSel.className = 'badge bg-danger';
        manualSel.textContent = diff.toFixed(3) + (total < selisih ? ' kurang' : ' lebih');
    }
}

// ── Batch Existing dropdown ────────────────────────────────────────
function populateBatchExisting() {
    batchIdTarget.innerHTML = '<option value="">— Pilih batch (FIFO order) —</option>';
    currentBatches.forEach(b => {
        const opt = document.createElement('option');
        opt.value = b.id;
        opt.textContent = `#${b.id} | ${fmtTgl(b.tanggal_masuk)} | Sisa: ${fmtQty(b.qty_sisa)} ${currentSatuan} | ${fmtRp(b.harga_beli_per_unit)}/unit`;
        batchIdTarget.appendChild(opt);
    });
}

// ── Harga auto info ────────────────────────────────────────────────
function updateHargaAutoInfo() {
    hargaAutoInfo.textContent = hargaTerakhir > 0
        ? `Default: ${fmtRp(hargaTerakhir)}/unit (dari Harga Standar)`
        : 'Harga Standar belum diisi, isi manual.';
}

// ── Harga custom formatter ─────────────────────────────────────────
hargaCustomIn.addEventListener('input', function() {
    const raw = this.value.replace(/\./g,'').replace(/[^0-9]/g,'');
    this.value = raw ? parseInt(raw,10).toLocaleString('id-ID') : '';
});

// ── Toggle handlers ────────────────────────────────────────────────
radioFifo.addEventListener('change', () => {
    fifoPreview.style.display = ''; manualSec.style.display = 'none';
    document.querySelectorAll('[name^="batch_distribusi"]').forEach(e => e.disabled = true);
    renderFifoPreview(currentStok !== null && !isNaN(parseFloat(qtyFisikIn.value))
        ? currentStok - parseFloat(qtyFisikIn.value) : 0);
});
radioManual.addEventListener('change', () => {
    fifoPreview.style.display = 'none'; manualSec.style.display = '';
    renderManualBatches();
    document.querySelectorAll('[name^="batch_distribusi"]').forEach(e => e.disabled = false);
});
radioBatchBaru.addEventListener('change', () => {
    batchBaruSec.style.display = ''; batchExistingSec.style.display = 'none';
    batchIdTarget.required = false;
});
radioBatchExisting.addEventListener('change', () => {
    batchBaruSec.style.display = 'none'; batchExistingSec.style.display = '';
    batchIdTarget.required = true;
});

// ── Dampak keuangan per alasan (cermin StokService::adjustment()) ───
// 'salah_hitung'/'audit' = murni koreksi data, TIDAK masuk Keuangan.
// 'susut'/'rusak'/'hilang' = kejadian ekonomi riil, MASUK Keuangan.
// 'lainnya' = user pilih sendiri lewat checkbox (default TIDAK masuk).
function alasanAkanKeKeuangan() {
    const v = alasanEl.value;
    if (['susut', 'rusak', 'hilang'].includes(v)) return true;
    if (['salah_hitung', 'audit'].includes(v)) return false;
    if (v === 'lainnya') return !!(catatSebagaiBiaya && catatSebagaiBiaya.checked);
    return false;
}

function updateAlasanHint() {
    const v = alasanEl.value;
    if (!v) {
        alasanKeuanganHint.style.display = 'none';
        catatBiayaWrap.style.display = 'none';
        return;
    }
    if (['susut', 'rusak', 'hilang'].includes(v)) {
        alasanKeuanganHint.innerHTML = '<span class="badge bg-danger-subtle text-danger"><i class="bi bi-cash-coin me-1"></i>Akan tercatat sebagai biaya rugi di Keuangan</span>';
        alasanKeuanganHint.style.display = '';
        catatBiayaWrap.style.display = 'none';
    } else if (['salah_hitung', 'audit'].includes(v)) {
        alasanKeuanganHint.innerHTML = '<span class="badge bg-primary-subtle text-primary"><i class="bi bi-info-circle me-1"></i>Cuma koreksi data, TIDAK masuk Keuangan</span>';
        alasanKeuanganHint.style.display = '';
        catatBiayaWrap.style.display = 'none';
    } else if (v === 'lainnya') {
        alasanKeuanganHint.style.display = 'none';
        catatBiayaWrap.style.display = '';
    } else {
        alasanKeuanganHint.style.display = 'none';
        catatBiayaWrap.style.display = 'none';
    }
}

// ── Event listeners ────────────────────────────────────────────────
lokasiSel.addEventListener('change', loadItemData);
itemSel.addEventListener('change', loadItemData);
qtyFisikIn.addEventListener('input', onQtyChange);
var _unitSelInit = document.getElementById('unit_input');
if (_unitSelInit) _unitSelInit.addEventListener('change', onQtyChange);
alasanEl.addEventListener('change', updateAlasanHint);
if (catatSebagaiBiaya) catatSebagaiBiaya.addEventListener('change', updateAlasanHint);
updateAlasanHint(); // jalankan saat load (kalau ada old() value setelah validation error)

// ── Build modal content ────────────────────────────────────────────
function buildModalContent() {
    const lokasiNama = lokasiSel.options[lokasiSel.selectedIndex]?.text || '—';
    const itemNama   = itemSel.options[itemSel.selectedIndex]?.text || '—';
    const fisik      = parseFloat(qtyFisikIn.value) || 0;
    const selisih    = currentStok !== null ? fisik - currentStok : 0;
    const alasanSel  = document.getElementById('alasan');
    const alasanTxt  = alasanSel.options[alasanSel.selectedIndex]?.text || '';
    const catatan    = document.getElementById('catatan_field')?.value || document.querySelector('[name=catatan]')?.value || '';
    const tglHari    = new Date().toLocaleDateString('id-ID',{day:'2-digit',month:'long',year:'numeric'});

    let distHtml = '';
    let nilaiHtml = '';

    const keKeuangan = alasanAkanKeKeuangan();

    if (selisih < 0) {
        const mode = radioManual.checked ? 'Manual' : 'FIFO Otomatis';
        distHtml = `<div class="mb-1"><strong>Mode distribusi:</strong> ${mode}</div>`;
        if (radioFifo.checked) {
            const plan = simulateFifo(currentBatches, Math.abs(selisih));
            let total = 0;
            let rows = '';
            plan.forEach(p => {
                total += p.nilai;
                rows += `<div style="font-size:.8rem;padding:.2rem 0;border-bottom:1px solid #f1f5f9">
                    <span class="badge bg-secondary me-1">#${p.id}</span>
                    ${fmtQty(p.ambil)} ${currentSatuan} @ ${fmtRp(p.harga_beli_per_unit)} = <strong>${fmtRp(p.nilai)}</strong>
                </div>`;
            });
            distHtml += `<div class="mt-1 mb-2">${rows}</div>`;
            nilaiHtml = `<div class="alert ${keKeuangan ? 'alert-warning' : 'alert-info'} py-2 mb-2" style="font-size:.82rem">
                <i class="bi bi-exclamation-triangle me-1"></i>
                <strong>Nilai kerugian stok:</strong> ${fmtRp(total)}<br>
                <small>${keKeuangan
                    ? 'Akan otomatis dicatat ke Keuangan — Kategori: <em>Beban Kerugian Stok</em>'
                    : 'TIDAK akan dicatat ke Keuangan (alasan ini murni koreksi data)'}</small>
            </div>`;
        }
    } else if (selisih > 0) {
        const mode = radioBatchExisting.checked ? 'Tambah ke batch existing' : 'Buat batch baru';
        distHtml = `<div class="mb-1"><strong>Mode:</strong> ${mode}</div>`;
        const hargaCustomVal = parseFloat((hargaCustomIn.value||'').replace(/\./g,'')) || hargaTerakhir;
        const nilai = selisih * hargaCustomVal;
        if (nilai > 0) {
            nilaiHtml = `<div class="alert ${keKeuangan ? 'alert-success' : 'alert-info'} py-2 mb-2" style="font-size:.82rem">
                <i class="bi bi-arrow-up-circle me-1"></i>
                <strong>Nilai koreksi masuk:</strong> ${fmtRp(nilai)}<br>
                <small>${keKeuangan
                    ? 'Akan otomatis dicatat ke Keuangan — Kategori: <em>Koreksi Stok Masuk</em>'
                    : 'TIDAK akan dicatat ke Keuangan (alasan ini murni koreksi data)'}</small>
            </div>`;
        }
    }

    return `
        <table class="table table-sm table-borderless mb-2" style="font-size:.87rem">
            <tr><td class="text-muted" style="width:40%">Item</td><td class="fw-semibold">${itemNama}</td></tr>
            <tr><td class="text-muted">Lokasi</td><td>${lokasiNama}</td></tr>
            <tr><td class="text-muted">Stok saat ini</td><td>${fmtQty(currentStok)} ${currentSatuan}</td></tr>
            <tr><td class="text-muted">Stok fisik</td><td class="fw-semibold">${fmtQty(fisik)} ${currentSatuan}</td></tr>
            <tr><td class="text-muted">Selisih</td><td class="${selisih<0?'text-danger fw-bold':'text-success fw-bold'}">
                ${selisih>=0?'+':''}${fmtQty(selisih)} ${currentSatuan}
            </td></tr>
            <tr><td class="text-muted">Alasan</td><td>${alasanTxt}</td></tr>
            <tr><td class="text-muted">Tanggal</td><td>${tglHari}</td></tr>
        </table>
        <hr class="my-2">
        <div style="font-size:.82rem">${distHtml}</div>
        ${nilaiHtml}
    `;
}

// ── Preview button ─────────────────────────────────────────────────
btnPreview.addEventListener('click', () => {
    const alasanSel = document.getElementById('alasan');
    if (!alasanSel.value) { alasanSel.focus(); alasanSel.classList.add('is-invalid'); return; }
    alasanSel.classList.remove('is-invalid');

    const fisik   = parseFloat(qtyFisikIn.value);
    const selisih = currentStok !== null ? fisik - currentStok : 0;

    // Validasi manual mode total
    if (radioManual.checked && Math.abs(selisih) > 0.001) {
        let totalManual = 0;
        document.querySelectorAll('.manual-batch-qty').forEach(inp => totalManual += parseFloat(inp.value)||0);
        if (Math.abs(totalManual - Math.abs(selisih)) > 0.001) {
            alert(`Total manual (${fmtQty(totalManual)}) harus sama dengan selisih (${fmtQty(Math.abs(selisih))})`);
            return;
        }
    }
    if (radioBatchExisting.checked && !batchIdTarget.value) {
        batchIdTarget.classList.add('is-invalid'); batchIdTarget.focus(); return;
    }
    batchIdTarget.classList.remove('is-invalid');

    document.getElementById('modalKonfirmasiBody').innerHTML = buildModalContent();
    new bootstrap.Modal(document.getElementById('modalKonfirmasi')).show();
});

// ── Konfirmasi & Submit ────────────────────────────────────────────
document.getElementById('btnKonfirmasiSubmit').addEventListener('click', () => {
    // disable tombol agar tidak double submit
    const btn = document.getElementById('btnKonfirmasiSubmit');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Menyimpan...';
    document.getElementById('formAdjustment').submit();
});

// ── Init ───────────────────────────────────────────────────────────
if (lokasiSel.value && itemSel.value) loadItemData();
</script>
@endpush
