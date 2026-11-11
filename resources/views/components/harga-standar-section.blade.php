{{--
    Bagian harga Bahan Baku & Kemasan (CLAUDE.md 4.38):
    - Harga Standar per satuan pakai (kolom `harga_beli_terakhir`, manual,
      TIDAK ditimpa PO). Bisa diisi lewat "harga per unit beli" (mis. per
      pack) → dihitung otomatis ke per satuan pakai.
    - Info Harga PO Terakhir (otomatis dari PO, read-only).
    Barang milik 1 cabang (CLAUDE.md 4.39) — harga di sini milik cabang itu.
    Variabel: $item (Item|null).
--}}
@props(['item' => null, 'cabangList' => collect()])
@php
    $satuanAwal = old('satuan', $item?->satuan) ?: 'satuan';
@endphp

<div class="row g-3" id="hargaStandarSection">
    <div class="col-12 col-md-6">
        <label class="form-label small fw-semibold">
            Harga Standar per <span class="label-satuan-pakai">{{ $satuanAwal }}</span>
            <x-tooltip key="master_bahan_baku.harga_standar" />
        </label>
        <x-input-rupiah name="harga_beli_terakhir" id="inputHargaStandar"
            :value="old('harga_beli_terakhir', $item?->harga_beli_terakhir ?? 0)" />
        <div class="form-text">Harga patokan Anda. <strong>Tidak berubah otomatis</strong> saat PO diterima.</div>
    </div>
    <div class="col-12 col-md-6 wrap-harga-per-unit-beli d-none">
        <label class="form-label small fw-semibold">
            Atau isi harga per <span class="label-unit-beli">pack</span>
        </label>
        <div class="input-group">
            <span class="input-group-text bg-white text-muted">Rp</span>
            <input type="text" inputmode="numeric" class="form-control" id="inputHargaPerUnitBeli" placeholder="0" autocomplete="off">
        </div>
        <div class="form-text preview-konversi-harga"></div>
    </div>

    @if($item)
    <div class="col-12">
        <div class="small text-muted">
            <i class="bi bi-receipt me-1"></i>Harga PO Terakhir:
            @if($item->harga_po_terakhir !== null)
                <strong>Rp {{ number_format((float) $item->harga_po_terakhir, 0, ',', '.') }}</strong> / {{ $item->satuan }}
                <span>({{ $item->harga_po_terakhir_at?->format('d/m/Y') }})</span>
            @else
                <em>belum ada PO yang diterima</em>
            @endif
            <x-tooltip key="master_bahan_baku.harga_po_terakhir" />
        </div>
    </div>
    @endif

</div>

@once
@push('scripts')
<script>
(function () {
    const sec = document.getElementById('hargaStandarSection');
    if (!sec) return;
    const std = document.getElementById('inputHargaStandar');
    const perUnit = document.getElementById('inputHargaPerUnitBeli');
    const wrap = sec.querySelector('.wrap-harga-per-unit-beli');
    const preview = sec.querySelector('.preview-konversi-harga');
    const satuanEl = document.querySelector('[name="satuan"]');
    const unitEl = document.querySelector('[name="unit_beli"]');
    const isiEl = document.querySelector('[name="isi_per_unit_beli"]');
    const fmt = n => new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(n);
    const parse = v => parseInt(String(v || '').replace(/\D/g, ''), 10) || 0;

    function isi() { return parseFloat(isiEl?.value) || 0; }
    function unit() { return (unitEl?.value || '').trim(); }
    function satuan() { return (satuanEl?.value || 'satuan').trim(); }

    function refreshLabel() {
        sec.querySelectorAll('.label-satuan-pakai').forEach(el => el.textContent = satuan());
        const ada = unit() && isi() > 0;
        wrap.classList.toggle('d-none', !ada);
        sec.querySelector('.label-unit-beli').textContent = unit() || 'pack';
        refreshPreview();
    }

    function refreshPreview() {
        const harga = parse(std.value);
        if (unit() && isi() > 0) {
            preview.innerHTML = harga
                ? 'Rp ' + fmt(harga) + ' / ' + satuan() + ' = <strong>Rp ' + fmt(harga * isi()) + ' / ' + unit() + '</strong> (isi ' + fmt(isi()) + ')'
                : '';
            if (document.activeElement !== perUnit) {
                perUnit.value = harga ? fmt(Math.round(harga * isi())) : '';
            }
        }
    }

    perUnit?.addEventListener('input', function () {
        const v = parse(this.value);
        this.value = v ? fmt(v) : '';
        if (isi() > 0) {
            const perPcs = Math.round(v / isi());
            std.value = perPcs ? fmt(perPcs) : '';
            std.dispatchEvent(new Event('input', { bubbles: true }));
            preview.innerHTML = v ? 'Rp ' + fmt(v) + ' / ' + unit() + ' = <strong>Rp ' + fmt(v / isi()) + ' / ' + satuan() + '</strong>'
                + (v % isi() ? ' (dibulatkan Rp ' + fmt(perPcs) + ')' : '') : '';
        }
    });
    std?.addEventListener('input', refreshPreview);
    [satuanEl, unitEl, isiEl].forEach(el => el?.addEventListener('input', refreshLabel));
    satuanEl?.addEventListener('change', refreshLabel);
    refreshLabel();
})();
</script>
@endpush
@endonce
