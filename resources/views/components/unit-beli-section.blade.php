@props(['unitBeli' => null, 'isiPerUnitBeli' => null])

{{-- Sprint Unit Family (2026-09-27) — section reusable utk 3 form Master. --}}
<div class="card mb-3 unit-beli-section">
    <div class="card-header fw-semibold d-flex align-items-center gap-2">
        <span>Unit Beli <span class="text-muted small">(Opsional)</span></span>
    </div>
    <div class="card-body">
        <div class="alert alert-info small py-2 mb-3">
            <i class="bi bi-info-circle me-1"></i>
            Isi kalau kamu membeli bahan ini dalam <strong>pack/karung/dus</strong> (bukan satuan pakai).
            Contoh: sumpit dibeli per <em>pack</em>, 1 pack isi 100 pcs.
            Nanti di form Pembelian, kamu bisa langsung input <em>10 pack</em> dan sistem otomatis hitung jadi <em>1000 pcs</em>.
        </div>

        <datalist id="unitBeliSuggestions">
            <option value="pack">
            <option value="karung">
            <option value="dus">
            <option value="box">
            <option value="plastik">
            <option value="lusin">
            <option value="gross">
        </datalist>

        <div class="row g-3">
            <div class="col-12 col-md-4">
                <label class="form-label small fw-semibold">Unit Beli</label>
                <input type="text" name="unit_beli" list="unitBeliSuggestions"
                    class="form-control unit-beli-input @error('unit_beli') is-invalid @enderror"
                    value="{{ old('unit_beli', $unitBeli) }}"
                    placeholder="cth: pack, karung, dus" maxlength="20">
                @error('unit_beli')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label small fw-semibold">Isi per Unit Beli</label>
                <input type="number" name="isi_per_unit_beli"
                    class="form-control isi-per-unit-input @error('isi_per_unit_beli') is-invalid @enderror"
                    min="0.001" step="0.001"
                    value="{{ old('isi_per_unit_beli', $isiPerUnitBeli) }}"
                    placeholder="cth: 100">
                @error('isi_per_unit_beli')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-4 d-flex align-items-end">
                <div class="preview-unit-beli text-muted small" style="min-height:38px">
                    <span class="preview-empty">Preview: <em>belum diisi</em></span>
                </div>
            </div>
        </div>
    </div>
</div>

@once
<script>
(function () {
    function bind(section) {
        var unit = section.querySelector('.unit-beli-input');
        var isi = section.querySelector('.isi-per-unit-input');
        var satuan = document.querySelector('input[name="satuan"]');
        var preview = section.querySelector('.preview-unit-beli');
        if (!unit || !isi || !preview) return;

        function refresh() {
            var u = (unit.value || '').trim();
            var i = parseFloat(isi.value);
            var s = ((satuan && satuan.value) || 'unit pakai').trim();
            if (!u && !i) {
                preview.innerHTML = '<span class="preview-empty">Preview: <em>belum diisi</em></span>';
            } else if (!u || !i || i <= 0) {
                preview.innerHTML = '<span class="text-danger">Kedua field harus diisi</span>';
            } else {
                var num = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 3 }).format(i);
                preview.innerHTML = '<strong>1 ' + u + ' = ' + num + ' ' + s + '</strong>';
            }
        }

        unit.addEventListener('input', refresh);
        isi.addEventListener('input', refresh);
        if (satuan) satuan.addEventListener('input', refresh);
        refresh();
    }

    document.querySelectorAll('.unit-beli-section').forEach(bind);
})();
</script>
@endonce
