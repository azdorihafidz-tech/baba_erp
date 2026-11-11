@extends('layouts.app')

@section('title', 'Catat Produksi')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-fire me-2 text-success"></i>Catat Produksi</h5>
    <x-panduan-button slug="produksi" />
</div>

@include('produksi._tabs')
@include('produksi._tanpa-gudang')

@if($gudang->isNotEmpty() && $reseps->isEmpty())
<div class="alert alert-info small">
    <i class="bi bi-info-circle me-1"></i>Belum ada Resep Produksi yang aktif.
    @can('produksi.resep.kelola')<a href="{{ route('produksi.resep.create') }}">Buat resep dulu</a>, misalnya isian ayam, saus, atau chili oil.@endcan
</div>
@endif

@if($gudang->isNotEmpty() && $reseps->isNotEmpty())
<form method="POST" action="{{ route('produksi.store') }}" id="formProduksi">
    @csrf
    <div class="row g-3">
        <div class="col-12 col-lg-7">
            <div class="card"><div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold">Gudang *</label>
                        @if($gudang->count() > 1)
                        <select name="cabang_id" id="cabangId" class="form-select @error('cabang_id') is-invalid @enderror" required>
                            @foreach($gudang as $g)
                            <option value="{{ $g->id }}" @selected(old('cabang_id', $defaultId) == $g->id)>{{ $g->nama_cabang }}</option>
                            @endforeach
                        </select>
                        @else
                        <input type="hidden" name="cabang_id" id="cabangId" value="{{ $gudang->first()->id }}">
                        <div class="form-control bg-light">{{ $gudang->first()->nama_cabang }}</div>
                        @endif
                        @error('cabang_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold">Tanggal *</label>
                        <input type="date" name="tanggal" max="{{ now()->toDateString() }}" class="form-control @error('tanggal') is-invalid @enderror" value="{{ old('tanggal', now()->toDateString()) }}" required>
                        @error('tanggal')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Barang yang dibuat *</label>
                        <select name="resep_produksi_id" id="resepId" class="form-select @error('resep_produksi_id') is-invalid @enderror" required>
                            @foreach($reseps as $r)
                            <option value="{{ $r->id }}" data-gudang="{{ $r->item->cabang_id }}" @selected(old('resep_produksi_id', $pilihResep) == $r->id)>{{ $r->item->nama_item }}</option>
                            @endforeach
                        </select>
                        @error('resep_produksi_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold">Rencana dibuat * <x-tooltip key="produksi.qty_rencana" /></label>
                        <div class="input-group">
                            <input type="number" step="0.001" min="0.001" name="qty_rencana" id="qtyRencana" class="form-control @error('qty_rencana') is-invalid @enderror" value="{{ old('qty_rencana') }}" required>
                            <span class="input-group-text satuanHasil"></span>
                        </div>
                        @error('qty_rencana')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold">Hasil nyata * <x-tooltip key="produksi.qty_hasil" /></label>
                        <div class="input-group">
                            <input type="number" step="0.001" min="0.001" name="qty_hasil" id="qtyHasil" class="form-control @error('qty_hasil') is-invalid @enderror" value="{{ old('qty_hasil') }}" required>
                            <span class="input-group-text satuanHasil"></span>
                        </div>
                        @error('qty_hasil')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Catatan</label>
                        <textarea name="catatan" rows="2" maxlength="500" class="form-control">{{ old('catatan') }}</textarea>
                    </div>
                </div>
            </div></div>

            <div class="card mt-3">
                <div class="card-header bg-white small fw-semibold">Bahan yang akan dipotong dari stok gudang</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Bahan</th><th class="text-end">Dibutuhkan</th><th class="text-end">Stok gudang</th><th class="text-end">Perkiraan biaya</th></tr></thead>
                        <tbody id="tabelBahan"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-5">
            <div class="card"><div class="card-body">
                <div class="d-flex justify-content-between small mb-1"><span>Perkiraan biaya bahan</span><strong id="perkiraanBiaya">-</strong></div>
                <div class="d-flex justify-content-between small mb-2">
                    <span>Modal per <span class="satuanHasil"></span> (otomatis) <x-tooltip key="produksi.modal" /></span>
                    <strong id="perkiraanModal">-</strong>
                </div>
                <div class="form-text mb-3">Perkiraan memakai harga FIFO bahan di gudang (sama dengan komposisi Produk Jual). Angka pasti dihitung saat disimpan, dari stok FIFO yang benar-benar terpakai.</div>

                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="pakai_modal_manual" value="1" id="pakaiManual" @checked(old('pakai_modal_manual'))>
                    <label class="form-check-label small" for="pakaiManual">Isi modal per unit secara manual</label>
                </div>
                <div id="boxManual" class="{{ old('pakai_modal_manual') ? '' : 'd-none' }}">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">Rp</span>
                        <input type="number" step="1" min="0" name="modal_manual" class="form-control @error('modal_manual') is-invalid @enderror" value="{{ old('modal_manual') }}">
                        <span class="input-group-text">/ <span class="satuanHasil ms-1"></span></span>
                    </div>
                    @error('modal_manual')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div id="peringatanStok" class="alert alert-danger small mt-3 mb-0 d-none">
                    <i class="bi bi-exclamation-triangle me-1"></i>Ada bahan yang stoknya tidak cukup di gudang ini.
                </div>
            </div></div>

            <div class="mt-3 d-flex gap-2">
                <button class="btn btn-primary" id="btnSimpan"><i class="bi bi-check-circle me-1"></i>Simpan Produksi</button>
                <a href="{{ route('produksi.index') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </div>
    </div>
</form>
@endif
@endsection

@push('scripts')
<script>
(function () {
    const RESEP = @json($resepJs);
    const STOK = @json($stokJs);
    const el = id => document.getElementById(id);
    if (!el('formProduksi')) return;

    // Aturan konversi sama dengan App\Support\KonversiSatuan::keSatuanStok (pemotongan stok produksi)
    function konversi(qty, satuan, satuanStok) {
        const F = [
            { mg: 0.001, g: 1, gr: 1, gram: 1, ons: 100, kg: 1000, kilogram: 1000 },
            { ml: 1, cc: 1, l: 1000, lt: 1000, ltr: 1000, liter: 1000 },
        ];
        const s = (satuan || '').toLowerCase().trim();
        const st = (satuanStok || '').toLowerCase().trim();
        for (const f of F) { if (f[s] !== undefined && f[st] !== undefined) return qty * f[s] / f[st]; }
        if (['g', 'gr', 'gram', 'ml'].includes(s)) return qty / 1000;
        if (s === 'ons') return qty / 10;
        return qty;
    }
    const rp = n => 'Rp ' + Math.round(n).toLocaleString('id-ID');
    const angka = n => (Math.round(n * 1000) / 1000).toLocaleString('id-ID', { maximumFractionDigits: 3 });
    const esc = v => String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;');

    let hasilDiubah = !!el('qtyHasil').value;
    el('qtyHasil').addEventListener('input', () => { hasilDiubah = true; hitung(); });
    el('qtyRencana').addEventListener('input', () => {
        if (!hasilDiubah) el('qtyHasil').value = el('qtyRencana').value;
        hitung();
    });
    el('resepId').addEventListener('change', hitung);
    // Data barang terpisah per cabang (4.39): hanya resep milik gudang terpilih yang bisa dipilih
    function saringResep() {
        const g = el('cabangId').value;
        let pertama = null;
        el('resepId').querySelectorAll('option').forEach(o => {
            const cocok = !o.dataset.gudang || o.dataset.gudang === g;
            o.hidden = !cocok; o.disabled = !cocok;
            if (cocok && !pertama) pertama = o;
        });
        const pilih = el('resepId').selectedOptions[0];
        if ((!pilih || pilih.disabled) && pertama) el('resepId').value = pertama.value;
    }
    el('cabangId').addEventListener('change', () => { saringResep(); hitung(); });
    saringResep();
    el('pakaiManual').addEventListener('change', e => el('boxManual').classList.toggle('d-none', !e.target.checked));

    function hitung() {
        const r = RESEP[el('resepId').value];
        if (!r) return;
        document.querySelectorAll('.satuanHasil').forEach(s => s.textContent = r.satuan || '');
        const rencana = parseFloat(el('qtyRencana').value) || 0;
        const hasil = parseFloat(el('qtyHasil').value) || 0;
        const faktor = r.qty_hasil > 0 ? rencana / r.qty_hasil : 0;
        const stokGudang = STOK[el('cabangId').value] || {};
        let biaya = 0, kurang = false, html = '';
        r.bahan.forEach(b => {
            const butuh = Math.round(konversi(b.qty, b.satuan, b.satuan_stok) * faktor * 1000) / 1000;
            const stok = stokGudang[b.item_id] || 0;
            const cukup = stok >= butuh;
            if (!cukup) kurang = true;
            biaya += butuh * b.harga;
            html += `<tr class="${cukup ? '' : 'table-danger'}"><td>${esc(b.nama)}</td>
                <td class="text-end">${angka(butuh)} ${esc(b.satuan_stok)}</td>
                <td class="text-end">${angka(stok)} ${esc(b.satuan_stok)}${cukup ? '' : ' <i class="bi bi-x-circle text-danger"></i>'}</td>
                <td class="text-end">${b.harga > 0 ? rp(butuh * b.harga) : '<span class="text-danger small">harga kosong</span>'}</td></tr>`;
        });
        el('tabelBahan').innerHTML = html || '<tr><td colspan="4" class="text-muted text-center">Resep belum punya bahan.</td></tr>';
        el('perkiraanBiaya').textContent = rencana > 0 ? rp(biaya) : '-';
        el('perkiraanModal').textContent = (rencana > 0 && hasil > 0) ? rp(biaya / hasil) : '-';
        el('peringatanStok').classList.toggle('d-none', !(kurang && rencana > 0));
    }
    hitung();
})();
</script>
@endpush
