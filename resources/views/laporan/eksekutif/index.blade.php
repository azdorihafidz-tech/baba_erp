@extends('layouts.app')
@section('title', 'Laporan Eksekutif Keuangan')
@section('content')

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">🖥️ Laporan Eksekutif Keuangan</h4>
        <small class="text-muted">Ringkasan komprehensif untuk rapat direksi — 9 halaman: Cover, Neraca, Laba Rugi, BEP, Arus Kas, Drill-Down, Findings, Rangkuman, Simulasi</small>
    </div>
    <x-panduan-button slug="laporan-eksekutif" />
</div>

<div class="alert alert-secondary py-2 px-3 small mb-3">
    <i class="bi bi-info-circle me-1"></i>
    Laporan ini murni menyusun ulang data dari Neraca, Laba Rugi Formal, BEP Otomatis, dan Buku Besar yang sudah ada — dengan verifikasi konsistensi otomatis di Halaman 6. Semua angka bisa ditelusuri balik ke menu sumbernya.
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('laporan.eksekutif.preview') }}" target="_blank" class="row g-2 align-items-end" id="formEksekutif">
            <div class="col-6 col-sm-4 col-md-3">
                <label class="form-label form-label-sm mb-1">Per Tanggal</label>
                <input type="date" name="tanggal" class="form-control form-control-sm" value="{{ $tanggal->toDateString() }}">
            </div>
            @if(auth()->user()->canAccessAllBranches())
            <div class="col-6 col-sm-4 col-md-3">
                <label class="form-label form-label-sm mb-1">Cabang</label>
                <select name="cabang_id" class="form-select form-select-sm">
                    <option value="">Semua Cabang (Konsolidasi)</option>
                    @foreach($cabangs as $c)
                    <option value="{{ $c->id }}" @selected($cabangId == $c->id)>{{ $c->nama_cabang }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-6 col-sm-4 col-md-3">
                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-eye me-1"></i>Preview</button>
            </div>
            @can('laporan.eksekutif.export')
            <div class="col-6 col-sm-4 col-md-3">
                <button type="button" id="btnExportEksekutif" class="btn btn-success btn-sm w-100"><i class="bi bi-file-earmark-pdf me-1"></i>Export PDF</button>
            </div>
            @endcan

            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="chkPreviewInline">
                    <label class="form-check-label small" for="chkPreviewInline">
                        Tampilkan preview di halaman ini (bukan tab baru)
                    </label>
                </div>
            </div>

            <div class="col-12">
                <button class="btn btn-link btn-sm px-0" type="button" data-bs-toggle="collapse" data-bs-target="#panelSimulasi">
                    <i class="bi bi-sliders me-1"></i>Sesuaikan Skenario Simulasi Balik Modal (Halaman 9, opsional)
                </button>
                <div class="collapse" id="panelSimulasi">
                    <div class="row g-2 mt-1 p-2 bg-light rounded">
                        <div class="col-6 col-md-4">
                            <label class="form-label form-label-sm mb-1">Volume "Sedang" (kg/hari)</label>
                            <input type="number" step="0.01" name="volume_sedang" class="form-control form-control-sm" placeholder="Default: 1x BEP harian">
                        </div>
                        <div class="col-6 col-md-4">
                            <label class="form-label form-label-sm mb-1">Volume "Optimis" (kg/hari)</label>
                            <input type="number" step="0.01" name="volume_optimis" class="form-control form-control-sm" placeholder="Default: 2x BEP harian">
                        </div>
                        <div class="col-6 col-md-4">
                            <label class="form-label form-label-sm mb-1">Pangkas Biaya Tetap (%)</label>
                            <input type="number" step="1" min="0" max="100" name="pangkas_beban_persen" class="form-control form-control-sm" value="20">
                        </div>
                        <div class="col-12">
                            <small class="text-muted">Kosongkan Volume Sedang/Optimis untuk pakai default otomatis (1x/2x volume harian BEP). Bisnis masih baru (data produksi ~1,5 minggu, belum marketing aktif) — angka ini target/asumsi Anda, bukan proyeksi histori.</small>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h6 class="fw-bold">Isi Laporan (9 Halaman)</h6>
        <ol class="small mb-0">
            <li><strong>Cover Overview</strong> — Modal, Aset Tetap, Penyusutan, Transaksi &amp; Produksi, Target vs Realisasi BEP, Kas, Trend 6 Bulan, Poin Kunci</li>
            <li><strong>Posisi Keuangan (Neraca)</strong> — Aset, Kewajiban, Modal + status balance</li>
            <li><strong>Kinerja Keuangan (Laba Rugi)</strong> — breakdown per akun COA, bulan berjalan</li>
            <li><strong>Analisa BEP</strong> — BEP Unit/Rupiah, Margin of Safety, estimasi waktu balik modal</li>
            <li><strong>Arus Kas</strong> — kas masuk/keluar (termasuk &amp; exclude entri non-tunai), trend 6 bulan</li>
            <li><strong>Drill-Down &amp; Verifikasi Data</strong> — cross-check konsistensi antar laporan + breakdown semua kategori beban</li>
            <li><strong>Evidence-Based Findings</strong> — 5 temuan konkret berbasis data (struktur beban, produktivitas, hutang jatuh tempo, kasir, trend kas 30 hari)</li>
            <li><strong>Rangkuman Final</strong> — Skor Kesehatan Finansial 5 dimensi, 3 Poin Utama, Pertanyaan untuk Direksi</li>
            <li><strong>Simulasi 5 Skenario Balik Modal</strong> — Tren Aktual, Volume Sedang, Volume BEP, Volume Optimis, Kombinasi Efisiensi</li>
        </ol>
    </div>
</div>

{{-- Preview Inline — muncul cuma kalau checkbox "Tampilkan preview di halaman ini" dicentang saat submit. Tab baru (behavior lama) tetap jadi default. --}}
<div id="previewInlineContainer" class="d-none mt-3">
    <div class="card border-primary">
        <div class="card-header bg-primary-subtle d-flex align-items-center justify-content-between">
            <h6 class="mb-0"><i class="bi bi-eye me-1"></i>Preview Laporan Eksekutif</h6>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnClosePreviewInline"><i class="bi bi-x-lg me-1"></i>Tutup</button>
        </div>
        <div class="card-body p-2">
            <div id="previewInlineLoading" class="text-center py-5 d-none">
                <div class="spinner-border text-primary" role="status"></div>
                <div class="mt-2 text-muted small">Memuat preview...</div>
            </div>
            <iframe id="previewInlineFrame" class="d-none w-100" style="height:80vh; border:0;"></iframe>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function() {
    var btnExport = document.getElementById('btnExportEksekutif');
    if (btnExport) {
        btnExport.addEventListener('click', function() {
            var form = document.getElementById('formEksekutif');
            var params = new URLSearchParams(new FormData(form));
            window.location.href = '{{ route('laporan.eksekutif.export') }}?' + params.toString();
        });
    }
})();

// Preview Inline — opsional via checkbox, TIDAK mengubah behavior default
// (tab baru via target="_blank" pada <form>). Checkbox unchecked -> submit
// native berjalan seperti biasa, JS di bawah ini sama sekali tidak campur
// tangan. Checkbox checked -> preventDefault, fetch ke endpoint yang SAMA
// (laporan.eksekutif.preview, reuse penuh, bukan endpoint/logic baru), lalu
// tampilkan hasilnya dalam <iframe srcdoc> supaya <style> milik dokumen PDF
// (yang berdiri sendiri, bukan extends layouts.app) tidak bentrok dengan
// CSS halaman ini kalau di-inject langsung ke <div> biasa.
(function() {
    var form = document.getElementById('formEksekutif');
    var chkInline = document.getElementById('chkPreviewInline');
    var container = document.getElementById('previewInlineContainer');
    var loading = document.getElementById('previewInlineLoading');
    var iframe = document.getElementById('previewInlineFrame');
    var btnClose = document.getElementById('btnClosePreviewInline');

    form.addEventListener('submit', function(e) {
        if (!chkInline.checked) {
            return; // behavior lama: submit native, form target="_blank" buka tab baru
        }
        e.preventDefault();

        var params = new URLSearchParams(new FormData(form));
        var url = '{{ route('laporan.eksekutif.preview') }}?' + params.toString();

        container.classList.remove('d-none');
        loading.classList.remove('d-none');
        iframe.classList.add('d-none');
        container.scrollIntoView({ behavior: 'smooth', block: 'start' });

        fetch(url)
            .then(function(res) {
                if (!res.ok) { throw new Error('HTTP ' + res.status); }
                return res.text();
            })
            .then(function(html) {
                iframe.srcdoc = html;
                loading.classList.add('d-none');
                iframe.classList.remove('d-none');
                container.scrollIntoView({ behavior: 'smooth', block: 'start' });
            })
            .catch(function() {
                loading.classList.add('d-none');
                container.classList.add('d-none');
                window.showAlert('error', 'Gagal Memuat Preview', 'Terjadi kesalahan saat memuat preview. Coba lagi atau gunakan tombol Preview tanpa checkbox untuk buka di tab baru.');
            });
    });

    if (btnClose) {
        btnClose.addEventListener('click', function() {
            container.classList.add('d-none');
            iframe.srcdoc = '';
        });
    }
})();
</script>
@endpush
