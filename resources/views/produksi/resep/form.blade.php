@extends('layouts.app')

@section('title', $resep ? 'Ubah Resep Produksi' : 'Resep Produksi Baru')

@section('content')
@php
    $barisAwal = old('bahan', $resep?->items->map(fn ($b) => ['item_id' => $b->item_id, 'qty' => (float) $b->qty, 'satuan' => $b->satuan])->all() ?? []);
    $barisAwal = array_values(array_filter($barisAwal, fn ($b) => ! empty($b['item_id'])));
    $opsi = $bahanOptions->map(fn ($i) => [
        'id' => $i->id, 'nama' => $i->nama_item, 'kode' => $i->kode_item, 'kategori' => $i->category?->nama_kategori,
        'satuan' => $i->satuan, 'harga' => (float) ($harga[$i->id]['harga'] ?? 0),
    ])->values();
@endphp
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-journal-text me-2 text-success"></i>{{ $resep ? 'Ubah Resep Produksi: ' . $resep->item?->nama_item : 'Resep Produksi Baru' }}</h5>
    <x-panduan-button slug="produksi" />
</div>

@include('produksi._tabs')
@include('produksi._tanpa-gudang')

<form method="POST" action="{{ $resep ? route('produksi.resep.update', $resep) : route('produksi.resep.store') }}" id="formResep">
    @csrf
    @if($resep) @method('PUT') @endif

    <div class="card"><div class="card-body">
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label small fw-semibold">Gudang *</label>
                @if($resep || $gudang->count() <= 1)
                <input type="hidden" name="cabang_id" value="{{ $gudangPilih?->id }}">
                <div class="form-control bg-light">{{ $gudangPilih?->nama_cabang ?? '—' }}</div>
                @else
                <select name="cabang_id" class="form-select" onchange="window.location='{{ route('produksi.resep.create') }}?cabang_id='+this.value">
                    @foreach($gudang as $g)
                    <option value="{{ $g->id }}" @selected($gudangPilih?->id === $g->id)>{{ $g->nama_cabang }}</option>
                    @endforeach
                </select>
                @endif
                <div class="form-text">Barang hasil dan bahan diambil dari barang milik gudang ini.</div>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label small fw-semibold">Barang hasil * <x-tooltip key="produksi.barang_hasil" /></label>
                <select name="item_id" id="hasilId" class="form-select @error('item_id') is-invalid @enderror" required>
                    <option value="">— pilih barang —</option>
                    @foreach($hasilOptions as $i)
                    <option value="{{ $i->id }}" data-satuan="{{ $i->satuan }}" data-kode="{{ $i->kode_item }}" data-kategori="{{ $i->category?->nama_kategori }}"
                        @selected(old('item_id', $resep?->item_id) == $i->id)>{{ $i->nama_item }}</option>
                    @endforeach
                </select>
                @error('item_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Belum ada di daftar? Tambahkan dulu di
                    @can('master.bahan_baku.create')<a href="{{ route('master.bahan-baku.create') }}" target="_blank">Bahan Baku &amp; Kemasan</a>@else Bahan Baku &amp; Kemasan @endcan
                    (mis. "Cairan Ocean Breeze EDP", satuan ml).</div>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small fw-semibold">Hasil per resep * <x-tooltip key="produksi.qty_hasil_resep" /></label>
                <div class="input-group">
                    <input type="number" step="0.001" min="0.001" name="qty_hasil" id="qtyHasilResep" class="form-control @error('qty_hasil') is-invalid @enderror" value="{{ old('qty_hasil', $resep ? (float) $resep->qty_hasil : 1) }}" required>
                    <span class="input-group-text" id="satuanHasil">-</span>
                </div>
                @error('qty_hasil')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small fw-semibold">Lama pemeraman <x-tooltip key="produksi.lama_peram" /></label>
                <div class="input-group">
                    <input type="number" step="1" min="0" max="365" name="lama_peram_hari" class="form-control @error('lama_peram_hari') is-invalid @enderror" value="{{ old('lama_peram_hari', $resep?->lama_peram_hari ?? 0) }}">
                    <span class="input-group-text">hari</span>
                </div>
                @error('lama_peram_hari')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                <div class="form-text">0 = langsung masuk stok.</div>
            </div>
            <div class="col-6 col-md-3 d-flex align-items-end">
                <div class="form-check form-switch mb-2">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive" @checked(old('is_active', $resep?->is_active ?? true))>
                    <label class="form-check-label small" for="isActive">Resep aktif</label>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label small fw-semibold">Catatan</label>
                <textarea name="catatan" rows="2" maxlength="500" class="form-control" placeholder="Mis. urutan mencampur, suhu simpan">{{ old('catatan', $resep?->catatan) }}</textarea>
            </div>
        </div>
    </div></div>

    <div class="card mt-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="small fw-semibold">Bahan untuk 1 resep</span>
            <button type="button" class="btn btn-outline-primary btn-sm" id="btnTambah"><i class="bi bi-plus"></i> Tambah Bahan</button>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th style="min-width:240px">Bahan</th><th style="width:130px">Takaran</th><th style="width:110px">Satuan <x-tooltip key="produksi.satuan_takaran" /></th><th class="text-end" style="width:140px">Perkiraan biaya</th><th style="width:50px"></th></tr></thead>
                <tbody id="barisBahan"></tbody>
                <tfoot>
                    <tr class="fw-semibold"><td colspan="3">Total biaya bahan</td><td class="text-end" id="totalBiaya">Rp 0</td><td></td></tr>
                    <tr class="fw-semibold text-success"><td colspan="3">Perkiraan modal per <span class="satuanHasilTeks">unit</span></td><td class="text-end" id="modalPerUnit">Rp 0</td><td></td></tr>
                </tfoot>
            </table>
        </div>
        <div class="card-body pt-2 pb-2 small text-muted">Perkiraan memakai harga FIFO bahan di gudang (stok habis → harga PO terakhir, belum pernah PO → Harga Standar). Saat produksi, modal dihitung dari stok FIFO yang benar-benar terpakai.</div>
    </div>

    <datalist id="daftarSatuan"><option value="g"><option value="kg"><option value="ml"><option value="liter"><option value="pcs"><option value="ons"></datalist>

    <div class="mt-3 d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Simpan Resep</button>
        <a href="{{ route('produksi.resep.index') }}" class="btn btn-outline-secondary">Batal</a>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    const OPSI = @json($opsi);
    const AWAL = @json($barisAwal);
    const byId = Object.fromEntries(OPSI.map(o => [String(o.id), o]));
    const tbody = document.getElementById('barisBahan');
    const esc = v => String(v ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    const rp = n => 'Rp ' + Math.round(n).toLocaleString('id-ID');
    let seq = 0;

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

    function cariBahan(select) {
        try {
            if (typeof $ === 'undefined' || !$.fn || !$.fn.select2) return;
            const label = opt => {
                const k = [opt.dataset.kode, opt.dataset.kategori, opt.dataset.satuan].filter(Boolean).join(' · ');
                return $('<div>').append($('<div>').text(opt.text)).append(k ? $('<small class="text-muted">').text(k) : '');
            };
            $(select).select2({
                theme: 'bootstrap-5', width: '100%', dropdownAutoWidth: true, placeholder: 'Ketik nama / kode bahan...',
                language: { noResults: () => 'Bahan tidak ditemukan', searching: () => 'Mencari...' },
                templateResult: d => (d.element && d.element.value) ? label(d.element) : d.text,
                matcher: (params, d) => {
                    const term = $.trim(params.term || '').toLowerCase();
                    if (term === '') return d;
                    if (!d.element || !d.element.value) return null;
                    const teks = [d.text, d.element.dataset.kode, d.element.dataset.kategori].join(' ').toLowerCase();
                    return term.split(/\s+/).every(w => teks.includes(w)) ? d : null;
                },
            });
            $(select).on('select2:open', () => { const b = document.querySelector('.select2-container--open .select2-search__field'); if (b) b.focus(); });
        } catch (e) { /* dropdown biasa */ }
    }

    function tambahBaris(data = {}) {
        const i = seq++;
        const opsi = OPSI.map(o => `<option value="${o.id}" data-kode="${esc(o.kode)}" data-kategori="${esc(o.kategori)}" data-satuan="${esc(o.satuan)}" ${String(o.id) === String(data.item_id) ? 'selected' : ''}>${esc(o.nama)}</option>`).join('');
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><select name="bahan[${i}][item_id]" class="form-select form-select-sm pilihBahan"><option value="">— pilih bahan —</option>${opsi}</select></td>
            <td><input type="number" step="0.001" min="0" name="bahan[${i}][qty]" class="form-control form-control-sm qtyBahan" value="${esc(data.qty ?? '')}"></td>
            <td><input type="text" name="bahan[${i}][satuan]" list="daftarSatuan" maxlength="20" class="form-control form-control-sm satuanBahan" value="${esc(data.satuan ?? '')}"></td>
            <td class="text-end subtotal small">-</td>
            <td><button type="button" class="btn btn-outline-danger btn-sm hapusBaris" title="Hapus"><i class="bi bi-trash"></i></button></td>`;
        tbody.appendChild(tr);
        const sel = tr.querySelector('.pilihBahan');
        cariBahan(sel);
        const onPilih = () => {
            const o = byId[sel.value];
            const satuan = tr.querySelector('.satuanBahan');
            if (o && !satuan.value) satuan.value = o.satuan || '';
            hitung();
        };
        sel.addEventListener('change', onPilih);
        if (typeof $ !== 'undefined' && $.fn && $.fn.select2) $(sel).on('change', onPilih);
        tr.querySelectorAll('input').forEach(inp => inp.addEventListener('input', hitung));
        tr.querySelector('.hapusBaris').addEventListener('click', () => { tr.remove(); hitung(); });
    }

    function hitung() {
        let total = 0;
        tbody.querySelectorAll('tr').forEach(tr => {
            const o = byId[tr.querySelector('.pilihBahan').value];
            const qty = parseFloat(tr.querySelector('.qtyBahan').value) || 0;
            const cell = tr.querySelector('.subtotal');
            if (!o || qty <= 0) { cell.textContent = '-'; return; }
            const qtyStok = konversi(qty, tr.querySelector('.satuanBahan').value, o.satuan);
            const sub = qtyStok * o.harga;
            total += sub;
            cell.innerHTML = o.harga > 0 ? rp(sub) : '<span class="text-danger">Rp 0 · harga kosong</span>';
        });
        const hasil = parseFloat(document.getElementById('qtyHasilResep').value) || 0;
        document.getElementById('totalBiaya').textContent = rp(total);
        document.getElementById('modalPerUnit').textContent = hasil > 0 ? rp(total / hasil) : '-';
    }

    function satuanHasil() {
        const opt = document.getElementById('hasilId').selectedOptions[0];
        const s = opt && opt.value ? (opt.dataset.satuan || '') : '';
        document.getElementById('satuanHasil').textContent = s || '-';
        document.querySelectorAll('.satuanHasilTeks').forEach(x => x.textContent = s || 'unit');
    }

    document.getElementById('btnTambah').addEventListener('click', () => tambahBaris());
    document.getElementById('qtyHasilResep').addEventListener('input', hitung);
    const hasilSel = document.getElementById('hasilId');
    hasilSel.addEventListener('change', satuanHasil);
    cariBahan(hasilSel);
    if (typeof $ !== 'undefined' && $.fn && $.fn.select2) $(hasilSel).on('change', satuanHasil);

    // Baris awal dimuat di akhir, setelah semua fungsi siap (pelajaran 4.45)
    (AWAL.length ? AWAL : [{}, {}, {}]).forEach(b => tambahBaris(b));
    satuanHasil();
    hitung();
})();
</script>
@endpush
