@extends('layouts.app')

@section('title', 'Laporan Transaksi')

@php
    $route = 'penjualan.laporan.transaksi';
    $isTransaksi = $posisi['level'] === 'transaksi';
    $namaKolom = match ($posisi['level']) { 'tahun' => 'Tahun', 'bulan' => 'Bulan', 'hari' => 'Tanggal', default => 'Jam' };
    $tingkatBawah = match ($posisi['level']) { 'tahun' => 'bulan', 'bulan' => 'hari', 'hari' => 'transaksi', default => 'struk' };
@endphp

@section('content')

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0" style="color:#1e293b">Laporan Transaksi</h4>
        <p class="text-muted mb-0" style="font-size:0.875rem">
            <i class="bi bi-shop me-1"></i>{{ $cabangNama }}
            @if($semuaCabang)<span class="text-muted">— ganti cabang di pojok kanan atas</span>@endif
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}" class="btn btn-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i><span class="d-none d-sm-inline">Export Excel</span>
        </a>
        <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}" class="btn btn-danger btn-sm">
            <i class="bi bi-file-earmark-pdf me-1"></i><span class="d-none d-sm-inline">Export PDF</span>
        </a>
        <x-panduan-button slug="laporan-transaksi-kasir" />
    </div>
</div>

{{-- Pilihan cepat + rentang tanggal --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2 d-flex flex-wrap align-items-end gap-2">
        <div>
            <div class="small text-muted mb-1">Periode cepat</div>
            <div class="btn-group flex-wrap" role="group">
                @foreach([
                    'hari_ini'  => ['Hari ini', ['tanggal' => today()->toDateString()]],
                    'bulan_ini' => ['Bulan ini', ['tahun' => now()->year, 'bulan' => now()->month]],
                    'tahun_ini' => ['Tahun ini', ['tahun' => now()->year]],
                    'semua'     => ['Selama ini', ['semua' => 1]],
                ] as $key => [$label, $params])
                <a href="{{ route($route, $params) }}"
                   class="btn btn-sm {{ $cepat === $key ? 'btn-dark' : 'btn-outline-dark' }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>
        <form method="GET" action="{{ route($route) }}" class="d-flex flex-wrap align-items-end gap-2 ms-lg-auto" id="formRentang">
            <div>
                <label class="small text-muted mb-1 d-block" for="rentangDari">Dari tanggal <x-tooltip key="laporan_transaksi_kasir.rentang" /></label>
                <input type="date" id="rentangDari" name="dari" class="form-control form-control-sm" required
                       value="{{ $isian[0]?->toDateString() }}" max="{{ today()->toDateString() }}">
            </div>
            <div>
                <label class="small text-muted mb-1 d-block" for="rentangSampai">Sampai tanggal</label>
                <input type="date" id="rentangSampai" name="sampai" class="form-control form-control-sm" required
                       value="{{ $isian[1]?->toDateString() }}" max="{{ today()->toDateString() }}">
            </div>
            <button type="submit" class="btn btn-sm" style="background:var(--brand-utama);color:var(--brand-utama-teks)">
                <i class="bi bi-search me-1"></i>Tampilkan
            </button>
        </form>
    </div>
</div>

{{-- Jejak halaman + tombol kembali --}}
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
    @if($jejak['kembali'])
    <a href="{{ route($route, $jejak['kembali']) }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
    @endif
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
            @foreach($jejak['jejak'] as $j)
            @if($j['params'])
            <li class="breadcrumb-item"><a href="{{ route($route, $j['params']) }}">{{ $j['label'] }}</a></li>
            @else
            <li class="breadcrumb-item active fw-semibold" aria-current="page">{{ $j['label'] }}</li>
            @endif
            @endforeach
        </ol>
    </nav>
</div>

<h5 class="fw-bold mb-3">{{ $judul }}</h5>

{{-- Kotak total --}}
<div class="row g-3 mb-3">
    <div class="col-6 {{ $lihatUntung ? 'col-lg-4' : 'col-lg-6' }}">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid var(--brand-sidebar) !important">
            <div class="card-body py-3">
                <div class="text-muted small">Jumlah Transaksi</div>
                <div class="fs-4 fw-bold">{{ number_format($total['transaksi'], 0, ',', '.') }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 {{ $lihatUntung ? 'col-lg-4' : 'col-lg-6' }}">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid var(--brand-utama) !important">
            <div class="card-body py-3">
                <div class="text-muted small">Pendapatan <x-tooltip key="laporan_transaksi_kasir.pendapatan" /></div>
                <div class="fs-4 fw-bold" style="color:var(--brand-utama)">{{ fmt_rupiah($total['pendapatan']) }}</div>
            </div>
        </div>
    </div>
    @if($lihatUntung)
    <div class="col-12 col-lg-4" id="kotakKeuntungan">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #198754 !important">
            <div class="card-body py-3">
                <div class="text-muted small">Keuntungan <x-tooltip key="laporan_transaksi_kasir.keuntungan" /></div>
                <div class="fs-4 fw-bold {{ $total['keuntungan'] < 0 ? 'text-danger' : 'text-success' }}">{{ fmt_rupiah($total['keuntungan']) }}</div>
            </div>
        </div>
    </div>
    @endif
</div>

<div class="row g-3">
    {{-- Grafik --}}
    <div class="col-12 col-xl-7">
        @php
            $adaData = $grafik->where('transaksi', '>', 0);
            $puncak = $adaData->sortByDesc('pendapatan')->first();
            $tersibuk = $adaData->sortByDesc('transaksi')->first();
            $satuan = $isTransaksi ? 'jam' : strtolower($namaKolom);
            $rataRata = $adaData->count() ? $adaData->sum('pendapatan') / $adaData->count() : 0;
        @endphp
        <div class="card border-0 shadow-sm h-100 lt-grafik-card">
            <div class="card-header bg-white d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="fw-semibold">
                    <i class="bi bi-graph-up me-1" style="color:var(--brand-utama)"></i>Grafik per {{ $satuan }}
                    @unless($isTransaksi)<x-tooltip key="laporan_transaksi_kasir.tingkat" />@endunless
                </div>
                @if($grafik->isNotEmpty())
                <div class="btn-group btn-group-sm" role="group" aria-label="Jenis grafik">
                    <button type="button" class="btn btn-outline-secondary lt-jenis" data-jenis="batang" title="Grafik batang"><i class="bi bi-bar-chart-fill"></i></button>
                    <button type="button" class="btn btn-outline-secondary lt-jenis" data-jenis="garis" title="Grafik garis"><i class="bi bi-graph-up"></i></button>
                </div>
                @endif
            </div>
            <div class="card-body">
                @if($adaData->isEmpty())
                <div class="text-center text-muted py-5">
                    <i class="bi bi-inbox d-block mb-2" style="font-size:2rem;opacity:.5"></i>Belum ada transaksi di periode ini.
                </div>
                @else
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <span class="lt-chip"><i class="bi bi-trophy-fill" style="color:var(--brand-utama)"></i>
                        Tertinggi: <strong>{{ $puncak['label'] ?? $puncak['label_pendek'] }}</strong> · {{ fmt_rupiah($puncak['pendapatan']) }}</span>
                    @if($isTransaksi)
                    <span class="lt-chip"><i class="bi bi-people-fill"></i>Tersibuk: <strong>{{ $tersibuk['label_pendek'] }}</strong> · {{ $tersibuk['transaksi'] }} transaksi</span>
                    @else
                    <span class="lt-chip"><i class="bi bi-calculator"></i>Rata-rata: <strong>{{ fmt_rupiah($rataRata) }}</strong> / {{ $satuan }}
                        <span class="text-muted">({{ $adaData->count() }} {{ $satuan }} ada transaksi)</span></span>
                    @endif
                </div>
                <div style="position:relative;height:340px"><canvas id="grafikTransaksi"></canvas></div>
                @unless($isTransaksi)
                <div class="text-muted small mt-2"><i class="bi bi-hand-index me-1"></i>Klik grafik atau baris tabel untuk melihat rincian per {{ $tingkatBawah }}.</div>
                @endunless
                @endif
            </div>
        </div>
    </div>

    {{-- Tabel rincian --}}
    <div class="col-12 col-xl-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-list-ul me-1"></i>{{ $isTransaksi ? 'Daftar transaksi' : 'Rincian per ' . strtolower($namaKolom) }}
                @if($isTransaksi)<span class="text-muted small fw-normal">— klik untuk melihat struk</span>@endif
            </div>
            <div class="table-responsive" style="max-height:520px">
                <table class="table table-hover table-sm align-middle mb-0" style="font-size:0.85rem">
                    <thead class="table-light" style="position:sticky;top:0;z-index:1">
                        @if($isTransaksi)
                        <tr>
                            <th>Jam</th>
                            <th>Transaksi</th>
                            <th class="text-end">Pendapatan</th>
                            @if($lihatUntung)<th class="text-end">Untung</th>@endif
                        </tr>
                        @else
                        <tr>
                            <th>{{ $namaKolom }}</th>
                            <th class="text-end">Trx</th>
                            <th class="text-end">Pendapatan</th>
                            @if($lihatUntung)<th class="text-end">Untung</th>@endif
                        </tr>
                        @endif
                    </thead>
                    <tbody>
                        @forelse($baris as $r)
                        @if($isTransaksi)
                        <tr role="button" onclick="openStrukModal({{ $r['id'] }})" title="Lihat struk">
                            <td class="text-muted">{{ $r['jam'] }}</td>
                            <td>
                                <div class="fw-semibold">{{ $r['nomor'] }}</div>
                                <div class="text-muted" style="font-size:0.75rem">
                                    @if($semuaCabang && $r['cabang']){{ $r['cabang'] }} · @endif{{ $r['pelanggan'] }}
                                    @if($r['tipe']) · {{ $r['tipe'] }}@endif
                                    @if($r['metode']) · {{ $r['metode'] }}@endif
                                </div>
                            </td>
                            <td class="text-end">{{ fmt_rupiah($r['pendapatan']) }}</td>
                            @if($lihatUntung)
                            <td class="text-end {{ $r['keuntungan'] < 0 ? 'text-danger' : 'text-success' }}">{{ fmt_rupiah($r['keuntungan']) }}</td>
                            @endif
                        </tr>
                        @else
                        <tr role="button" class="{{ $r['transaksi'] ? '' : 'text-muted' }}"
                            onclick="window.location='{{ route($route, $r['params']) }}'">
                            <td>
                                <a href="{{ route($route, $r['params']) }}" class="text-decoration-none {{ $r['transaksi'] ? 'fw-semibold' : 'text-muted' }}">
                                    {{ $posisi['level'] === 'hari' ? \App\Services\LaporanTransaksiKasirService::tanggal(\Carbon\Carbon::parse($r['params']['tanggal']), 'pendek') : $r['label'] }}
                                </a>
                            </td>
                            <td class="text-end">{{ number_format($r['transaksi'], 0, ',', '.') }}</td>
                            <td class="text-end">{{ fmt_rupiah($r['pendapatan']) }}</td>
                            @if($lihatUntung)
                            <td class="text-end {{ $r['keuntungan'] < 0 ? 'text-danger' : 'text-success' }}">{{ fmt_rupiah($r['keuntungan']) }}</td>
                            @endif
                        </tr>
                        @endif
                        @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">Belum ada transaksi.</td></tr>
                        @endforelse
                    </tbody>
                    @if($baris->isNotEmpty())
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="{{ $isTransaksi ? 2 : 1 }}">Total</td>
                            @unless($isTransaksi)<td class="text-end">{{ number_format($total['transaksi'], 0, ',', '.') }}</td>@endunless
                            <td class="text-end">{{ fmt_rupiah($total['pendapatan']) }}</td>
                            @if($lihatUntung)<td class="text-end">{{ fmt_rupiah($total['keuntungan']) }}</td>@endif
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
</div>

<p class="text-muted small mt-3 mb-0">
    <i class="bi bi-info-circle me-1"></i>Pendapatan = uang yang dibayar pelanggan (setelah diskon, termasuk service charge &amp; take away fee).
    Transaksi yang dibatalkan, dihapus, atau masih bill tersimpan belum dibayar tidak dihitung.
</p>

@endsection


@push('styles')
<style>
    .lt-chip { display:inline-flex; align-items:center; gap:6px; padding:5px 12px; border-radius:999px;
        background:var(--brand-latar); border:1px solid rgba(var(--brand-utama-rgb),0.25); font-size:.8rem; color:#1A1A1A; }
    .lt-jenis.active { background:var(--brand-sidebar); color:rgb(var(--brand-sidebar-teks-rgb)); border-color:var(--brand-sidebar); }
    .lt-grafik-card .card-header { border-bottom:1px solid #f1f1f1; }
</style>
@endpush

@push('scripts')
<script>
// "Sampai tanggal" tidak boleh sebelum "Dari tanggal"
(function () {
    const dari = document.getElementById('rentangDari');
    const sampai = document.getElementById('rentangSampai');
    if (!dari || !sampai) return;
    const sinkron = () => { sampai.min = dari.value || ''; if (sampai.value && dari.value && sampai.value < dari.value) sampai.value = dari.value; };
    dari.addEventListener('change', sinkron);
    sinkron();
})();
</script>
@endpush

@if($grafik->where('transaksi', '>', 0)->isNotEmpty())
@php
    $dataGrafik = [
        'label'      => $grafik->pluck('label_pendek')->values(),
        'judul'      => $grafik->map(fn ($g) => $g['label'] ?? $g['label_pendek'])->values(),
        'pendapatan' => $grafik->pluck('pendapatan')->values(),
        'transaksi'  => $grafik->map(fn ($g) => $g['transaksi'])->values(),
        'keuntungan' => $lihatUntung ? $grafik->pluck('keuntungan')->values() : null,
        'link'       => $isTransaksi ? null : $grafik->map(fn ($g) => route($route, $g['params']))->values(),
    ];
@endphp
@push('scripts')
<script>
(function () {
    const kanvas = document.getElementById('grafikTransaksi');
    if (!kanvas || typeof Chart === 'undefined') return;

    const d = @json($dataGrafik);
    const rp = v => 'Rp ' + Math.round(v).toLocaleString('id-ID');
    const singkat = v => Math.abs(v) >= 1e6 ? (v / 1e6).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + 'jt'
        : (Math.abs(v) >= 1e3 ? Math.round(v / 1e3).toLocaleString('id-ID') + 'rb' : v);

    // Gradasi vertikal (warna penuh di atas, memudar ke bawah)
    const gradasi = (warna, atas, bawah) => (ctx) => {
        const { chart } = ctx;
        const area = chart.chartArea;
        if (!area) return warna;
        const g = chart.ctx.createLinearGradient(0, area.bottom, 0, area.top);
        g.addColorStop(0, bawah);
        g.addColorStop(1, atas);
        return g;
    };

    const KUNCI = 'laporanTransaksi.jenisGrafik';
    let jenis = 'batang';
    try { jenis = localStorage.getItem(KUNCI) || 'batang'; } catch (e) {}

    function datasets() {
        const batang = jenis === 'batang';
        const set = [{
            type: batang ? 'bar' : 'line',
            label: 'Pendapatan',
            data: d.pendapatan,
            yAxisID: 'y',
            order: 2,
            backgroundColor: batang ? gradasi(BRAND.utama, BRAND.utama, `rgba(${BRAND.utamaRgb},0.35)`)
                                    : gradasi(BRAND.utama, `rgba(${BRAND.utamaRgb},0.35)`, `rgba(${BRAND.utamaRgb},0)`),
            hoverBackgroundColor: batang ? BRAND.utamaGelap : undefined,
            borderColor: BRAND.utama,
            borderWidth: batang ? 0 : 3,
            borderRadius: 8,
            borderSkipped: false,
            maxBarThickness: 42,
            fill: !batang,
            tension: 0.4,
            pointRadius: batang ? 0 : 3,
            pointHoverRadius: 6,
            pointBackgroundColor: '#fff',
            pointBorderColor: BRAND.utama,
            pointBorderWidth: 2,
        }];
        @if($lihatUntung)
        if (d.keuntungan) {
            set.push({
                type: batang ? 'bar' : 'line',
                label: 'Keuntungan',
                data: d.keuntungan,
                yAxisID: 'y',
                order: 3,
                backgroundColor: batang ? gradasi('#198754', '#20A464', 'rgba(32,164,100,0.35)')
                                        : gradasi('#198754', 'rgba(32,164,100,0.28)', 'rgba(32,164,100,0)'),
                hoverBackgroundColor: batang ? '#157347' : undefined,
                borderColor: '#20A464',
                borderWidth: batang ? 0 : 3,
                borderRadius: 8,
                borderSkipped: false,
                maxBarThickness: 42,
                fill: !batang,
                tension: 0.4,
                pointRadius: batang ? 0 : 3,
                pointHoverRadius: 6,
                pointBackgroundColor: '#fff',
                pointBorderColor: '#20A464',
                pointBorderWidth: 2,
            });
        }
        @endif
        set.push({
            type: 'line',
            label: 'Jumlah transaksi',
            data: d.transaksi,
            yAxisID: 'y1',
            order: 1,
            borderColor: '#1A1A1A',
            backgroundColor: '#1A1A1A',
            borderWidth: 2,
            borderDash: [6, 4],
            tension: 0.4,
            pointRadius: 3,
            pointHoverRadius: 6,
            pointBackgroundColor: '#1A1A1A',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
        });
        return set;
    }

    const grafik = new Chart(kanvas, {
        data: { labels: d.label, datasets: datasets() },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            animation: { duration: 700, easing: 'easeOutQuart' },
            layout: { padding: { top: 4 } },
            plugins: {
                legend: {
                    position: 'top', align: 'end',
                    labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 8, boxHeight: 8, padding: 16, color: '#475569', font: { size: 12 } },
                },
                tooltip: {
                    backgroundColor: 'rgba(26,26,26,0.95)',
                    titleColor: '#fff', bodyColor: '#e2e8f0', footerColor: '#FFB27A',
                    titleFont: { size: 13, weight: '600' }, bodyFont: { size: 12 }, footerFont: { size: 11, weight: 'normal' },
                    padding: 12, cornerRadius: 10, boxPadding: 6, usePointStyle: true,
                    callbacks: {
                        title: items => d.judul[items[0].dataIndex],
                        label: c => ' ' + c.dataset.label + ': ' + (c.dataset.yAxisID === 'y1' ? c.raw.toLocaleString('id-ID') : rp(c.raw)),
                        footer: () => d.link ? 'Klik untuk melihat rincian' : '',
                    },
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    border: { display: false },
                    ticks: { color: '#64748b', font: { size: 11 }, maxRotation: 0, autoSkipPadding: 8 },
                },
                y: {
                    beginAtZero: true,
                    border: { display: false },
                    grid: { color: '#eef0f3' },
                    ticks: { color: '#64748b', font: { size: 11 }, callback: singkat, maxTicksLimit: 6 },
                },
                y1: {
                    beginAtZero: true,
                    position: 'right',
                    border: { display: false },
                    grid: { drawOnChartArea: false },
                    ticks: { color: '#94a3b8', font: { size: 11 }, precision: 0, maxTicksLimit: 6 },
                    title: { display: true, text: 'transaksi', color: '#94a3b8', font: { size: 10 } },
                },
            },
            onClick: (evt, el, chart) => {
                if (!d.link) return;
                const p = chart.getElementsAtEventForMode(evt, 'index', { intersect: false }, true);
                if (p.length) window.location = d.link[p[0].index];
            },
            onHover: (evt, el) => { if (d.link) evt.native.target.style.cursor = el.length ? 'pointer' : 'default'; },
        },
    });

    // Tombol Batang / Garis
    const tombol = document.querySelectorAll('.lt-jenis');
    const tandai = () => tombol.forEach(b => b.classList.toggle('active', b.dataset.jenis === jenis));
    tandai();
    tombol.forEach(b => b.addEventListener('click', () => {
        jenis = b.dataset.jenis;
        try { localStorage.setItem(KUNCI, jenis); } catch (e) {}
        grafik.data.datasets = datasets();
        grafik.update();
        tandai();
    }));
})();

</script>
@endpush
@endif
