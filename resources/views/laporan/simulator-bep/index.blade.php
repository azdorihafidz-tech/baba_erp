@extends('layouts.app')
@section('title', 'Simulator BEP')
@section('content')

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">🎛️ Simulator BEP Interaktif</h4>
        <small class="text-muted">{{ $cabangNama }} — geser slider untuk eksperimen skenario, hasil dihitung real-time</small>
    </div>
    <div class="d-flex gap-2">
        @can('laporan.simulator.export')
        <button type="button" id="btnExportSnapshotExcel" class="btn btn-outline-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i><span class="d-none d-sm-inline">Export Snapshot Excel</span>
        </button>
        <button type="button" id="btnExportSnapshotPdf" class="btn btn-success btn-sm">
            <i class="bi bi-file-earmark-pdf me-1"></i><span class="d-none d-sm-inline">Export Snapshot PDF</span>
        </button>
        @endcan
        <x-panduan-button slug="simulator-bep" />
    </div>
</div>

<div class="alert alert-secondary py-2 px-3 small mb-3">
    <i class="bi bi-info-circle me-1"></i>
    Nilai awal slider diambil dari data BEP Otomatis bulan berjalan — silakan geser untuk eksperimen "bagaimana jika" (harga naik, biaya turun, volume berbeda). Perhitungan murni di browser Anda, tidak mengubah data apapun di sistem.
</div>

<div class="row g-3">
    <div class="col-12 col-lg-6">
        <div class="card">
            <div class="card-header py-2 px-3 d-flex align-items-center justify-content-between">
                <h6 class="mb-0">Parameter</h6>
                <button type="button" id="btnReset" class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Nama Simulasi</label>
                    <input type="text" class="form-control form-control-sm" id="inputNamaSimulasi" placeholder="Simulasi BEP - {{ now()->translatedFormat('d F Y') }}">
                </div>
                <div class="mb-3">
                    <label class="form-label">Target Profit (Rp, opsional)</label>
                    <input type="number" class="form-control form-control-sm" id="inputTargetProfit" min="0" placeholder="Kosongkan kalau tidak perlu">
                </div>
                <div class="mb-3">
                    <label class="form-label d-flex justify-content-between"><span>Volume Harian (kg)</span><strong id="lblVolume">-</strong></label>
                    <input type="range" class="form-range" id="sliderVolume" min="0" max="{{ max(50, round($defaultValues['volume_harian_kg'] * 5)) }}" step="0.5">
                </div>
                <div class="mb-3">
                    <label class="form-label d-flex justify-content-between"><span>Harga Jual / kg (Rp)</span><strong id="lblHarga">-</strong></label>
                    <input type="range" class="form-range" id="sliderHarga" min="0" max="{{ max(20000, round($defaultValues['harga_jual_per_kg'] * 3)) }}" step="50">
                </div>
                <div class="mb-3">
                    <label class="form-label d-flex justify-content-between"><span>Biaya Variabel / kg (Rp)</span><strong id="lblVariabel">-</strong></label>
                    <input type="range" class="form-range" id="sliderVariabel" min="0" max="{{ max(20000, round($defaultValues['biaya_variabel_per_kg'] * 3)) }}" step="50">
                </div>
                <div class="mb-1">
                    <label class="form-label d-flex justify-content-between"><span>Beban Tetap Bulanan (Rp)</span><strong id="lblBebanTetap">-</strong></label>
                    <input type="range" class="form-range" id="sliderBebanTetap" min="0" max="{{ max(20000000, round($defaultValues['biaya_tetap_bulanan'] * 3)) }}" step="10000">
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header py-2 px-3"><h6 class="mb-0">Hasil Perhitungan</h6></div>
            <div class="card-body">
                <div class="row g-3 text-center mb-3">
                    <div class="col-6">
                        <div class="text-muted" style="font-size:0.75rem">BEP Unit</div>
                        <div class="fw-bold" id="hasilBepUnit">-</div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted" style="font-size:0.75rem">BEP Rupiah</div>
                        <div class="fw-bold" id="hasilBepRupiah">-</div>
                    </div>
                </div>
                <table class="table table-sm">
                    <tbody>
                        <tr><td>Margin Kontribusi / kg</td><td class="text-end fw-semibold" id="hasilMargin">-</td></tr>
                        <tr><td>Margin Kontribusi Ratio</td><td class="text-end" id="hasilMarginRatio">-</td></tr>
                        <tr><td>Volume Bulanan (26 hari)</td><td class="text-end" id="hasilVolumeBulanan">-</td></tr>
                        <tr><td>Omzet Bulanan</td><td class="text-end" id="hasilOmzet">-</td></tr>
                        <tr><td>Laba/Rugi Bulanan</td><td class="text-end fw-bold" id="hasilLaba">-</td></tr>
                        <tr><td>Proyeksi Modal</td><td class="text-end" id="hasilProyeksiModal">-</td></tr>
                    </tbody>
                </table>
                <div id="alertMarginNegatif" class="alert alert-danger py-2 px-3 small d-none">
                    <i class="bi bi-exclamation-triangle me-1"></i>Margin kontribusi negatif/nol — BEP tidak bisa dicapai pada kombinasi harga/biaya ini.
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function() {
    var DEFAULTS = @json($defaultValues);
    var HARI_KERJA = 26;

    var els = {
        volume: document.getElementById('sliderVolume'),
        harga: document.getElementById('sliderHarga'),
        variabel: document.getElementById('sliderVariabel'),
        bebanTetap: document.getElementById('sliderBebanTetap'),
    };
    var lbl = {
        volume: document.getElementById('lblVolume'),
        harga: document.getElementById('lblHarga'),
        variabel: document.getElementById('lblVariabel'),
        bebanTetap: document.getElementById('lblBebanTetap'),
    };
    var hasil = {
        bepUnit: document.getElementById('hasilBepUnit'),
        bepRupiah: document.getElementById('hasilBepRupiah'),
        margin: document.getElementById('hasilMargin'),
        marginRatio: document.getElementById('hasilMarginRatio'),
        volumeBulanan: document.getElementById('hasilVolumeBulanan'),
        omzet: document.getElementById('hasilOmzet'),
        laba: document.getElementById('hasilLaba'),
        proyeksiModal: document.getElementById('hasilProyeksiModal'),
    };
    var alertMargin = document.getElementById('alertMarginNegatif');

    function formatRupiah(v) {
        return 'Rp ' + new Intl.NumberFormat('id-ID', {maximumFractionDigits: 0}).format(v);
    }
    function formatAngka(v, decimals) {
        return new Intl.NumberFormat('id-ID', {maximumFractionDigits: decimals || 2}).format(v);
    }

    function setDefaults() {
        els.volume.value = DEFAULTS.volume_harian_kg;
        els.harga.value = DEFAULTS.harga_jual_per_kg;
        els.variabel.value = DEFAULTS.biaya_variabel_per_kg;
        els.bebanTetap.value = DEFAULTS.biaya_tetap_bulanan;
    }

    function hitung() {
        var volumeHarian = parseFloat(els.volume.value);
        var hargaJual = parseFloat(els.harga.value);
        var biayaVariabel = parseFloat(els.variabel.value);
        var bebanTetap = parseFloat(els.bebanTetap.value);
        var modalAwal = DEFAULTS.modal_awal;

        lbl.volume.textContent = formatAngka(volumeHarian, 1) + ' kg';
        lbl.harga.textContent = formatRupiah(hargaJual);
        lbl.variabel.textContent = formatRupiah(biayaVariabel);
        lbl.bebanTetap.textContent = formatRupiah(bebanTetap);

        var margin = hargaJual - biayaVariabel;
        hasil.margin.textContent = formatRupiah(margin);
        hasil.marginRatio.textContent = hargaJual > 0 ? formatAngka((margin / hargaJual) * 100, 1) + '%' : '-';

        if (margin <= 0) {
            hasil.bepUnit.textContent = '-';
            hasil.bepRupiah.textContent = '-';
            hasil.volumeBulanan.textContent = '-';
            hasil.omzet.textContent = '-';
            hasil.laba.textContent = '-';
            hasil.proyeksiModal.textContent = '-';
            alertMargin.classList.remove('d-none');
            return;
        }
        alertMargin.classList.add('d-none');

        var bepUnit = bebanTetap / margin;
        var bepRupiah = bepUnit * hargaJual;
        var volumeBulanan = volumeHarian * HARI_KERJA;
        var omzetBulanan = volumeBulanan * hargaJual;
        var hppBulanan = volumeBulanan * biayaVariabel;
        var labaBulanan = omzetBulanan - hppBulanan - bebanTetap;

        hasil.bepUnit.textContent = formatAngka(bepUnit, 2) + ' kg';
        hasil.bepRupiah.textContent = formatRupiah(bepRupiah);
        hasil.volumeBulanan.textContent = formatAngka(volumeBulanan, 2) + ' kg';
        hasil.omzet.textContent = formatRupiah(omzetBulanan);
        hasil.laba.className = 'text-end fw-bold ' + (labaBulanan >= 0 ? 'text-success' : 'text-danger');
        hasil.laba.textContent = formatRupiah(labaBulanan);

        var epsilon = 1000;
        if (labaBulanan > epsilon && modalAwal > 0) {
            hasil.proyeksiModal.textContent = 'Balik modal ~' + formatAngka(modalAwal / labaBulanan, 1) + ' bulan';
        } else if (labaBulanan < -epsilon && modalAwal > 0) {
            hasil.proyeksiModal.textContent = 'Modal habis ~' + formatAngka(modalAwal / Math.abs(labaBulanan), 1) + ' bulan';
        } else {
            hasil.proyeksiModal.textContent = 'Impas (laba ~Rp0)';
        }
    }

    Object.values(els).forEach(function(el) {
        el.addEventListener('input', hitung);
    });
    document.getElementById('btnReset').addEventListener('click', function() {
        setDefaults();
        hitung();
    });

    function submitSnapshot(url) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = url;
        form.style.display = 'none';

        var fields = {
            _token: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            nama_simulasi: document.getElementById('inputNamaSimulasi').value,
            target_profit: document.getElementById('inputTargetProfit').value,
            cabang_id: '{{ $cabangId }}',
            volume_harian: els.volume.value,
            harga_jual: els.harga.value,
            biaya_variabel: els.variabel.value,
            beban_tetap: els.bebanTetap.value,
            modal_awal: DEFAULTS.modal_awal,
        };

        Object.keys(fields).forEach(function(key) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = fields[key];
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
    }

    var btnExcel = document.getElementById('btnExportSnapshotExcel');
    if (btnExcel) {
        btnExcel.addEventListener('click', function() {
            submitSnapshot('{{ route('laporan.simulator-bep.export-excel') }}');
        });
    }

    var btnPdf = document.getElementById('btnExportSnapshotPdf');
    if (btnPdf) {
        btnPdf.addEventListener('click', function() {
            submitSnapshot('{{ route('laporan.simulator-bep.export-pdf') }}');
        });
    }

    setDefaults();
    hitung();
})();
</script>
@endpush
