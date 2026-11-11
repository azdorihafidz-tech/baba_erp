@extends('layouts.app')
@section('title', 'Laporan BEP Otomatis')
@section('content')

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">📐 Laporan BEP Otomatis</h4>
        <small class="text-muted">{{ $mulai->format('d/m/Y') }} — {{ $akhir->format('d/m/Y') }} — {{ $cabangNama }}</small>
    </div>
    <div class="d-flex gap-2">
        @can('laporan.bep_otomatis.export')
        <button type="button" id="btnExportBepOtomatisExcel" class="btn btn-outline-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i><span class="d-none d-sm-inline">Export Excel</span>
        </button>
        <button type="button" id="btnExportBepOtomatis" class="btn btn-success btn-sm">
            <i class="bi bi-file-earmark-pdf me-1"></i><span class="d-none d-sm-inline">Export PDF</span>
        </button>
        @endcan
        <x-panduan-button slug="laporan-bep-otomatis" />
    </div>
</div>

<div class="alert alert-secondary py-2 px-3 small mb-3">
    <i class="bi bi-info-circle me-1"></i>
    Laporan ini hitung BEP <strong>langsung dari transaksi real</strong> (tanpa setup manual) khusus lini <strong>Jasa Giling</strong> — Biaya Tetap dari kategori transaksi tertandai "Tetap", Biaya Variabel/HPP dari data FIFO penjualan aktual. Untuk BEP per produk lain (produk jadi) dengan setup manual, lihat menu <a href="{{ route('bep.index') }}">BEP</a> existing.
</div>

{{-- Filter --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('laporan.bep-otomatis.index') }}" class="row g-2 align-items-end">
            <div class="col-6 col-sm-4 col-md-3">
                <label class="form-label form-label-sm mb-1">Dari</label>
                <input type="date" name="mulai" class="form-control form-control-sm" value="{{ $mulai->toDateString() }}">
            </div>
            <div class="col-6 col-sm-4 col-md-3">
                <label class="form-label form-label-sm mb-1">Sampai</label>
                <input type="date" name="akhir" class="form-control form-control-sm" value="{{ $akhir->toDateString() }}">
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
                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search me-1"></i>Generate</button>
            </div>
        </form>
    </div>
</div>

@if(!$bep['bisa_bep'])
<div class="alert alert-danger py-2 px-3 small mb-3">
    <i class="bi bi-exclamation-triangle me-1"></i>
    <strong>BEP tidak bisa dihitung</strong> — Harga Jual rata² per kg (Rp {{ number_format($bep['harga_jual_per_unit'],0,',','.') }}) lebih kecil atau sama dengan Biaya Variabel per kg (Rp {{ number_format($bep['biaya_variabel_per_unit'],0,',','.') }}). Margin kontribusi negatif/nol berarti setiap kg terjual justru menambah rugi — cek harga jual atau HPP bahan.
</div>
@elseif($bep['volume_aktual'] <= 0)
<div class="alert alert-warning py-2 px-3 small mb-3">
    <i class="bi bi-exclamation-triangle me-1"></i>
    Belum ada penjualan jasa giling di periode ini — Biaya Variabel/Harga Jual per kg tidak bisa dihitung (butuh minimal 1 transaksi).
</div>
@endif

{{-- Kartu Ringkasan --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="fw-bold">{{ number_format($bep['bep_unit'] ?? 0, 2, ',', '.') }} kg</div>
            <div class="text-muted" style="font-size:0.8rem">BEP Unit</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="fw-bold">Rp {{ number_format($bep['bep_rupiah'] ?? 0, 0, ',', '.') }}</div>
            <div class="text-muted" style="font-size:0.8rem">BEP Rupiah</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="fw-bold">{{ number_format($bep['volume_aktual'], 2, ',', '.') }} kg</div>
            <div class="text-muted" style="font-size:0.8rem">Volume Aktual</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        @php $pct = $bep['persentase_tercapai']; @endphp
        <div class="stat-card border-2 {{ $pct !== null && $pct >= 100 ? 'border-success' : 'border-warning' }}">
            <div class="fw-bold {{ $pct !== null && $pct >= 100 ? 'text-success' : 'text-warning' }}" style="font-size:1.15rem">{{ $pct !== null ? number_format($pct, 1, ',', '.') . '%' : '-' }}</div>
            <div class="text-muted" style="font-size:0.8rem">% Tercapai dari BEP</div>
        </div>
    </div>
</div>

@if($pct !== null)
<div class="progress mb-3" style="height:10px">
    <div class="progress-bar {{ $pct >= 100 ? 'bg-success' : 'bg-warning' }}" style="width: {{ min(100, max(0, $pct)) }}%"></div>
</div>
@endif

<div class="row g-3 mb-3">
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header py-2 px-3"><h6 class="mb-0">Komponen Perhitungan</h6></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><td class="small">Biaya Tetap (periode ini)</td><td class="text-end small fw-semibold">Rp {{ number_format($bep['biaya_tetap'], 0, ',', '.') }}</td></tr>
                        <tr><td class="small">Biaya Variabel / kg (HPP jasa giling)</td><td class="text-end small">Rp {{ number_format($bep['biaya_variabel_per_unit'], 2, ',', '.') }}</td></tr>
                        <tr><td class="small">Harga Jual rata² / kg</td><td class="text-end small">Rp {{ number_format($bep['harga_jual_per_unit'], 2, ',', '.') }}</td></tr>
                        <tr class="fw-semibold"><td class="small">Margin Kontribusi / kg</td><td class="text-end small {{ $bep['margin_kontribusi_per_unit'] >= 0 ? 'text-success' : 'text-danger' }}">Rp {{ number_format($bep['margin_kontribusi_per_unit'], 2, ',', '.') }}</td></tr>
                        <tr><td class="small">Total HPP Jasa Giling</td><td class="text-end small">Rp {{ number_format($bep['total_hpp_jasa_giling'], 0, ',', '.') }}</td></tr>
                        <tr><td class="small">Total Omzet Jasa Giling</td><td class="text-end small">Rp {{ number_format($bep['total_omzet_jasa_giling'], 0, ',', '.') }}</td></tr>
                        <tr><td class="small">Margin of Safety</td><td class="text-end small {{ ($bep['margin_of_safety_persen'] ?? -1) >= 0 ? 'text-success' : 'text-danger' }}">{{ $bep['margin_of_safety_persen'] !== null ? number_format($bep['margin_of_safety_persen'], 1, ',', '.') . '%' : '-' }}</td></tr>
                        <tr class="fw-semibold"><td class="small">Estimasi Laba pada Volume Aktual</td><td class="text-end small {{ $bep['estimasi_laba'] >= 0 ? 'text-success' : 'text-danger' }}">Rp {{ number_format($bep['estimasi_laba'], 0, ',', '.') }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header py-2 px-3"><h6 class="mb-0">Break-Even Chart</h6></div>
            <div class="card-body">
                <div style="position:relative;height:280px">
                    <canvas id="chartBepOtomatis"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

@if($bep['persentase_tercapai'] !== null && $bep['persentase_tercapai'] < 100)
<div class="alert alert-warning py-2 px-3 small mb-3">
    <i class="bi bi-lightbulb me-1"></i>
    <strong>Rekomendasi:</strong> Volume aktual ({{ number_format($bep['volume_aktual'],2,',','.') }} kg) masih di bawah BEP ({{ number_format($bep['bep_unit'],2,',','.') }} kg) — perlu tambahan
    {{ number_format(max(0, $bep['bep_unit'] - $bep['volume_aktual']), 2, ',', '.') }} kg lagi untuk balik modal di periode ini.
    @if($mulai->isSameMonth(now()) && $akhir->isSameMonth(now()) && now()->day <= 10)
        Catatan: masih di awal bulan (hari ke-{{ now()->day }}), wajar kalau volume masih rendah.
    @endif
</div>
@endif

{{-- Detail Biaya Tetap per Kategori --}}
<div class="card">
    <div class="card-header py-2 px-3"><h6 class="mb-0">Breakdown Biaya Tetap per Kategori</h6></div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Kategori</th><th class="text-end">Jumlah</th></tr></thead>
            <tbody>
            @forelse($bep['biaya_tetap_detail'] as $d)
                <tr><td class="small">{{ $d['nama_kategori'] }}</td><td class="text-end small">Rp {{ number_format($d['jumlah'], 0, ',', '.') }}</td></tr>
            @empty
                <tr><td colspan="2" class="text-center text-muted py-3">Tidak ada transaksi biaya tetap di periode ini.</td></tr>
            @endforelse
            </tbody>
            @if(count($bep['biaya_tetap_detail']) > 0)
            <tfoot><tr class="table-light fw-bold"><td>Total</td><td class="text-end">Rp {{ number_format($bep['biaya_tetap'], 0, ',', '.') }}</td></tr></tfoot>
            @endif
        </table>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function() {
    var d = @json($chartData);
    if (d.labels.length > 0) {
        new Chart(document.getElementById('chartBepOtomatis'), {
            type: 'line',
            data: {
                labels: d.labels,
                datasets: [
                    { label: 'Biaya Tetap', data: d.biayaTetap, borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,0.05)', borderWidth: 2, borderDash: [5,5], fill: false, pointRadius: 0 },
                    { label: 'Biaya Total', data: d.biayaTotal, borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,0.05)', borderWidth: 2, fill: false, pointRadius: 0 },
                    { label: 'Pendapatan', data: d.pendapatan, borderColor: '#22c55e', backgroundColor: 'rgba(34,197,94,0.1)', borderWidth: 2, fill: false, pointRadius: 0 }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    tooltip: { callbacks: { label: c => c.dataset.label + ': Rp ' + new Intl.NumberFormat('id-ID').format(c.raw) } }
                },
                scales: {
                    x: { title: { display: true, text: 'Volume (kg)' } },
                    y: { ticks: { callback: v => 'Rp ' + new Intl.NumberFormat('id-ID', { notation: 'compact' }).format(v) } }
                }
            }
        });
    }

    var btnExport = document.getElementById('btnExportBepOtomatis');
    if (btnExport) {
        btnExport.addEventListener('click', function() {
            var params = new URLSearchParams(window.location.search);
            window.location.href = '{{ route('laporan.bep-otomatis.export') }}?' + params.toString();
        });
    }

    var btnExportExcel = document.getElementById('btnExportBepOtomatisExcel');
    if (btnExportExcel) {
        btnExportExcel.addEventListener('click', function() {
            var params = new URLSearchParams(window.location.search);
            window.location.href = '{{ route('laporan.bep-otomatis.export-excel') }}?' + params.toString();
        });
    }
})();
</script>
@endpush
