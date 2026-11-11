@extends('layouts.app')

@section('title', 'Laporan Penjualan Produk')

@php
    $route = 'penjualan.laporan.produk';
    $paramDasar = $periode['params'] + array_filter(['kategori' => $kategori, 'cari' => $cari], fn ($v) => filled($v));
    $q = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',');
    $kolom = 7 + ($lihatUntung ? 3 : 0);
@endphp

@section('content')

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0" style="color:#1e293b">Laporan Penjualan Produk</h4>
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
        <x-panduan-button slug="laporan-produk-kasir" />
    </div>
</div>

@include('penjualan.laporan._periode', ['route' => $route, 'periode' => $periode, 'simpan' => ['kategori' => $kategori, 'cari' => $cari, 'urut' => $urut]])

{{-- Filter kategori + cari --}}
<form method="GET" action="{{ route($route) }}" class="row g-2 align-items-end mb-3" id="formFilterProduk">
    @foreach($periode['params'] + ['urut' => $urut] as $k => $v)
    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
    @endforeach
    <div class="col-12 col-sm-5 col-lg-3">
        <label class="small text-muted mb-1" for="filterKategori">Kategori</label>
        <select name="kategori" id="filterKategori" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">Semua kategori</option>
            @foreach($daftarKategori as $k)
            <option value="{{ $k->id }}" @selected($kategori === (int) $k->id)>{{ $k->nama_kategori }}</option>
            @endforeach
            @if($kategori && ! $daftarKategori->contains('id', $kategori))
            <option value="{{ $kategori }}" selected>{{ $namaKategori }}</option>
            @endif
        </select>
    </div>
    <div class="col-12 col-sm-7 col-lg-4">
        <label class="small text-muted mb-1" for="filterCari">Cari produk</label>
        <div class="input-group input-group-sm">
            <input type="search" name="cari" id="filterCari" class="form-control" value="{{ $cari }}" placeholder="Nama atau kode produk...">
            <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
        </div>
    </div>
    @if($kategori || $cari)
    <div class="col-auto">
        <a href="{{ route($route, $periode['params'] + ['urut' => $urut]) }}" class="btn btn-sm btn-link text-decoration-none">
            <i class="bi bi-x-circle me-1"></i>Hapus filter
        </a>
    </div>
    @endif
</form>

<h5 class="fw-bold mb-3">
    {{ $periode['label'] }}
    @if($namaKategori)<span class="badge rounded-pill text-bg-light border ms-1">{{ $namaKategori }}</span>@endif
    @if($cari)<span class="badge rounded-pill text-bg-light border ms-1">"{{ $cari }}"</span>@endif
</h5>

{{-- Kotak total --}}
<div class="row g-3 mb-3">
    <div class="col-6 {{ $lihatUntung ? 'col-lg-3' : 'col-lg-4' }}">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid var(--brand-sidebar) !important">
            <div class="card-body py-3">
                <div class="text-muted small">Produk terjual</div>
                <div class="fs-4 fw-bold">{{ number_format($baris->count(), 0, ',', '.') }} <span class="fs-6 fw-normal text-muted">jenis</span></div>
            </div>
        </div>
    </div>
    <div class="col-6 {{ $lihatUntung ? 'col-lg-3' : 'col-lg-4' }}">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #6c757d !important">
            <div class="card-body py-3">
                <div class="text-muted small">Total qty terjual</div>
                <div class="fs-4 fw-bold">{{ $q($total['qty']) }}</div>
            </div>
        </div>
    </div>
    <div class="col-12 {{ $lihatUntung ? 'col-sm-6 col-lg-3' : 'col-lg-4' }}">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid var(--brand-utama) !important">
            <div class="card-body py-3">
                <div class="text-muted small">Pendapatan produk <x-tooltip key="laporan_produk_kasir.pendapatan" /></div>
                <div class="fs-4 fw-bold" style="color:var(--brand-utama)">{{ fmt_rupiah($total['pendapatan']) }}</div>
                @if($total['diskon'] > 0)
                <div class="small text-muted">Sebelum diskon {{ fmt_rupiah($total['penjualan']) }} · diskon {{ fmt_rupiah($total['diskon']) }}</div>
                @endif
            </div>
        </div>
    </div>
    @if($lihatUntung)
    <div class="col-12 col-sm-6 col-lg-3" id="kotakKeuntungan">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #198754 !important">
            <div class="card-body py-3">
                <div class="text-muted small">Keuntungan <x-tooltip key="laporan_transaksi_kasir.keuntungan" /></div>
                <div class="fs-4 fw-bold {{ $total['keuntungan'] < 0 ? 'text-danger' : 'text-success' }}">{{ fmt_rupiah($total['keuntungan']) }}</div>
                @if($total['pendapatan'] > 0)
                <div class="small text-muted">Margin {{ round($total['keuntungan'] / $total['pendapatan'] * 100, 1) }}%</div>
                @endif
            </div>
        </div>
    </div>
    @endif
</div>

{{-- Grafik 10 teratas --}}
@if($baris->isNotEmpty())
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="fw-semibold"><i class="bi bi-trophy me-1" style="color:var(--brand-utama)"></i>10 Produk Teratas</div>
        <div class="btn-group btn-group-sm" role="group" aria-label="Urutan grafik">
            <button type="button" class="btn btn-outline-secondary lp-metrik" data-metrik="qty">Qty</button>
            <button type="button" class="btn btn-outline-secondary lp-metrik" data-metrik="pendapatan">Pendapatan</button>
            @if($lihatUntung)
            <button type="button" class="btn btn-outline-secondary lp-metrik" data-metrik="keuntungan">Keuntungan</button>
            @endif
        </div>
    </div>
    <div class="card-body">
        <div style="position:relative;height:{{ max(180, min(10, $baris->count()) * 38 + 40) }}px"><canvas id="grafikProduk"></canvas></div>
    </div>
</div>
@endif

{{-- Tabel --}}
@php
    $kepala = function (string $label, string $kunci, string $kelas = '') use ($route, $paramDasar, $urut) {
        $aktif = $urut === $kunci;
        $url = route($route, $paramDasar + ['urut' => $kunci]);
        $panah = $aktif ? ($kunci === 'nama' ? ' ▲' : ' ▼') : '';

        return '<th class="' . $kelas . '"><a href="' . e($url) . '" class="text-decoration-none ' . ($aktif ? 'text-dark fw-bold' : 'text-secondary') . '">' . e($label) . $panah . '</a></th>';
    };
@endphp
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center flex-wrap gap-1">
        <span><i class="bi bi-list-ol me-1"></i>Rincian per produk</span>
        <span class="small text-muted fw-normal">Klik judul kolom untuk mengurutkan</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0" style="font-size:0.85rem">
            <thead class="table-light">
                <tr>
                    <th style="width:36px">#</th>
                    {!! $kepala('Produk', 'nama') !!}
                    {!! $kepala('Qty', 'qty', 'text-end') !!}
                    <th class="text-end">Penjualan <x-tooltip key="laporan_produk_kasir.penjualan" /></th>
                    <th class="text-end">Diskon <x-tooltip key="laporan_produk_kasir.diskon" /></th>
                    {!! $kepala('Pendapatan', 'pendapatan', 'text-end') !!}
                    <th class="text-end">Transaksi</th>
                    @if($lihatUntung)
                    <th class="text-end">Modal</th>
                    {!! $kepala('Untung', 'keuntungan', 'text-end') !!}
                    <th class="text-end">Margin</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($baris as $i => $r)
                <tr>
                    <td class="text-muted">{{ $i + 1 }}</td>
                    <td>
                        <div class="fw-semibold">
                            {{ $r['nama'] }}
                            @if($r['varian']->isNotEmpty())
                            <button type="button" class="btn btn-sm btn-link p-0 ms-1 text-decoration-none lp-varian" data-target="varian-{{ $i }}">
                                <i class="bi bi-chevron-down"></i> {{ $r['varian']->count() }} varian
                            </button>
                            @endif
                        </div>
                        <div class="text-muted" style="font-size:0.75rem">
                            @if($r['kode']){{ $r['kode'] }} · @endif
                            <a href="{{ route($route, $periode['params'] + ['kategori' => $r['kategori_id'] ?? -1, 'urut' => $urut]) }}" class="text-muted">{{ $r['kategori'] }}</a>
                        </div>
                    </td>
                    <td class="text-end fw-semibold">{{ $q($r['qty']) }} <span class="text-muted fw-normal small">{{ $r['satuan'] }}</span></td>
                    <td class="text-end">{{ fmt_rupiah($r['penjualan']) }}</td>
                    <td class="text-end {{ $r['diskon'] > 0 ? 'text-danger' : 'text-muted' }}">{{ $r['diskon'] > 0 ? '− ' . fmt_rupiah($r['diskon']) : '-' }}</td>
                    <td class="text-end fw-semibold">{{ fmt_rupiah($r['pendapatan']) }}</td>
                    <td class="text-end text-muted">{{ number_format($r['transaksi'], 0, ',', '.') }}</td>
                    @if($lihatUntung)
                    <td class="text-end text-muted">{{ fmt_rupiah($r['hpp']) }}</td>
                    <td class="text-end {{ $r['keuntungan'] < 0 ? 'text-danger' : 'text-success' }}">{{ fmt_rupiah($r['keuntungan']) }}</td>
                    <td class="text-end">{{ $r['margin'] !== null ? $r['margin'] . '%' : '-' }}</td>
                    @endif
                </tr>
                @foreach($r['varian'] as $v)
                <tr class="varian-{{ $i }} d-none" style="background:var(--brand-latar)">
                    <td></td>
                    <td class="ps-4 small"><i class="bi bi-arrow-return-right me-1 text-muted"></i>{{ $v['nama'] }}</td>
                    <td class="text-end small">{{ $q($v['qty']) }}</td>
                    <td class="text-end small">{{ fmt_rupiah($v['penjualan']) }}</td>
                    <td class="text-end small">{{ $v['diskon'] > 0 ? '− ' . fmt_rupiah($v['diskon']) : '-' }}</td>
                    <td class="text-end small">{{ fmt_rupiah($v['pendapatan']) }}</td>
                    <td class="text-end small text-muted">{{ number_format($v['transaksi'], 0, ',', '.') }}</td>
                    @if($lihatUntung)
                    <td class="text-end small text-muted">{{ fmt_rupiah($v['hpp']) }}</td>
                    <td class="text-end small">{{ fmt_rupiah($v['keuntungan']) }}</td>
                    <td class="text-end small">{{ $v['margin'] !== null ? $v['margin'] . '%' : '-' }}</td>
                    @endif
                </tr>
                @endforeach
                @empty
                <tr><td colspan="{{ $kolom }}" class="text-center text-muted py-5">
                    <i class="bi bi-inbox d-block mb-2" style="font-size:2rem;opacity:.5"></i>Belum ada penjualan di periode ini.
                </td></tr>
                @endforelse
            </tbody>
            @if($baris->isNotEmpty())
            <tfoot class="table-light">
                <tr class="fw-bold">
                    <td colspan="2">Total</td>
                    <td class="text-end">{{ $q($total['qty']) }}</td>
                    <td class="text-end">{{ fmt_rupiah($total['penjualan']) }}</td>
                    <td class="text-end">{{ $total['diskon'] > 0 ? '− ' . fmt_rupiah($total['diskon']) : '-' }}</td>
                    <td class="text-end">{{ fmt_rupiah($total['pendapatan']) }}</td>
                    <td></td>
                    @if($lihatUntung)
                    <td class="text-end">{{ fmt_rupiah($total['hpp']) }}</td>
                    <td class="text-end">{{ fmt_rupiah($total['keuntungan']) }}</td>
                    <td class="text-end">{{ $total['pendapatan'] > 0 ? round($total['keuntungan'] / $total['pendapatan'] * 100, 1) . '%' : '-' }}</td>
                    @endif
                </tr>
                @if($biayaTambahan > 0)
                <tr class="small">
                    <td colspan="5" class="text-muted">Service charge &amp; take away fee <span class="fw-normal">(bukan milik produk mana pun)</span></td>
                    <td class="text-end">{{ fmt_rupiah($biayaTambahan) }}</td>
                    <td colspan="{{ $kolom - 6 }}"></td>
                </tr>
                <tr class="fw-bold">
                    <td colspan="5">Total pendapatan <span class="fw-normal text-muted small">(sama dengan Laporan Transaksi)</span></td>
                    <td class="text-end">{{ fmt_rupiah($total['pendapatan'] + $biayaTambahan) }}</td>
                    <td colspan="{{ $kolom - 6 }}"></td>
                </tr>
                @endif
            </tfoot>
            @endif
        </table>
    </div>
</div>

<p class="text-muted small mt-3 mb-0">
    <i class="bi bi-info-circle me-1"></i>Penjualan = harga × qty di keranjang. Diskon = diskon order dibagi ke setiap produk sesuai porsi harganya.
    Pendapatan = penjualan − diskon.
    @if($adaFilter)Saat filter kategori/pencarian aktif, service charge &amp; take away fee tidak ditampilkan.@endif
    Transaksi dibatalkan, dihapus, atau bill tersimpan yang belum dibayar tidak dihitung.
</p>

@endsection

@push('styles')
<style>
    .lp-metrik.active { background:var(--brand-sidebar); color:rgb(var(--brand-sidebar-teks-rgb)); border-color:var(--brand-sidebar); }
</style>
@endpush

@push('scripts')
<script>
// Buka/tutup rincian varian
document.querySelectorAll('.lp-varian').forEach(function (b) {
    b.addEventListener('click', function () {
        const baris = document.querySelectorAll('.' + b.dataset.target);
        const buka = baris.length && baris[0].classList.contains('d-none');
        baris.forEach(r => r.classList.toggle('d-none', !buka));
        b.querySelector('i').className = buka ? 'bi bi-chevron-up' : 'bi bi-chevron-down';
    });
});
</script>
@endpush

@if($baris->isNotEmpty())
@php
    $dataGrafik = $baris->map(fn ($r) => [
        'nama'       => $r['nama'],
        'satuan'     => $r['satuan'],
        'qty'        => $r['qty'],
        'pendapatan' => $r['pendapatan'],
    ] + ($lihatUntung ? ['keuntungan' => $r['keuntungan']] : []))->values();
@endphp
@push('scripts')
<script>
(function () {
    const kanvas = document.getElementById('grafikProduk');
    if (!kanvas || typeof Chart === 'undefined') return;

    const semua = @json($dataGrafik);
    const LABEL = { qty: 'Qty terjual', pendapatan: 'Pendapatan'@if($lihatUntung), keuntungan: 'Keuntungan'@endif };
    const WARNA = { qty: ['#1A1A1A', 'rgba(26,26,26,0.45)'], pendapatan: [BRAND.utama, `rgba(${BRAND.utamaRgb},0.4)`], keuntungan: ['#20A464', 'rgba(32,164,100,0.4)'] };
    const rp = v => 'Rp ' + Math.round(v).toLocaleString('id-ID');
    const angka = v => (+v).toLocaleString('id-ID', { maximumFractionDigits: 3 });
    const singkat = v => Math.abs(v) >= 1e6 ? (v / 1e6).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + 'jt'
        : (Math.abs(v) >= 1e3 ? Math.round(v / 1e3).toLocaleString('id-ID') + 'rb' : angka(v));

    const KUNCI = 'laporanProduk.metrik';
    let metrik = 'qty';
    try { metrik = localStorage.getItem(KUNCI) || 'qty'; } catch (e) {}
    if (!(metrik in semua[0])) metrik = 'qty';

    const teratas = () => [...semua].sort((a, b) => b[metrik] - a[metrik]).slice(0, 10);
    const format = v => metrik === 'qty' ? angka(v) : rp(v);
    const gradasi = (ctx) => {
        const area = ctx.chart.chartArea;
        const [penuh, pudar] = WARNA[metrik];
        if (!area) return penuh;
        const g = ctx.chart.ctx.createLinearGradient(area.left, 0, area.right, 0);
        g.addColorStop(0, pudar);
        g.addColorStop(1, penuh);
        return g;
    };

    // Angka di ujung batang
    const labelUjung = {
        id: 'labelUjung',
        afterDatasetsDraw(chart) {
            const { ctx } = chart;
            const meta = chart.getDatasetMeta(0);
            ctx.save();
            ctx.font = '600 11px system-ui, sans-serif';
            ctx.fillStyle = '#334155';
            ctx.textBaseline = 'middle';
            meta.data.forEach((bar, i) => {
                const v = chart.data.datasets[0].data[i];
                ctx.fillText(metrik === 'qty' ? angka(v) : singkat(v), bar.x + 6, bar.y);
            });
            ctx.restore();
        },
    };

    function isi() {
        const data = teratas();
        return {
            labels: data.map(r => r.nama.length > 28 ? r.nama.slice(0, 27) + '…' : r.nama),
            datasets: [{
                label: LABEL[metrik], data: data.map(r => r[metrik]), backgroundColor: gradasi,
                borderRadius: 8, borderSkipped: false, maxBarThickness: 26, _baris: data,
            }],
        };
    }

    const grafik = new Chart(kanvas, {
        type: 'bar',
        data: isi(),
        plugins: [labelUjung],
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 600, easing: 'easeOutQuart' },
            layout: { padding: { right: 56 } },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(26,26,26,0.95)', padding: 12, cornerRadius: 10,
                    titleFont: { size: 13, weight: '600' }, bodyFont: { size: 12 }, displayColors: false,
                    callbacks: {
                        title: items => items[0].dataset._baris[items[0].dataIndex].nama,
                        label: c => {
                            const r = c.dataset._baris[c.dataIndex];
                            const baris = ['Qty: ' + angka(r.qty) + (r.satuan ? ' ' + r.satuan : ''), 'Pendapatan: ' + rp(r.pendapatan)];
                            @if($lihatUntung)
                            baris.push('Keuntungan: ' + rp(r.keuntungan));
                            @endif
                            return baris;
                        },
                    },
                },
            },
            scales: {
                x: { beginAtZero: true, border: { display: false }, grid: { color: '#eef0f3' },
                    ticks: { color: '#64748b', font: { size: 11 }, callback: v => metrik === 'qty' ? angka(v) : singkat(v), maxTicksLimit: 6 } },
                y: { border: { display: false }, grid: { display: false }, ticks: { color: '#1e293b', font: { size: 12 } } },
            },
        },
    });

    const tombol = document.querySelectorAll('.lp-metrik');
    const tandai = () => tombol.forEach(b => b.classList.toggle('active', b.dataset.metrik === metrik));
    tandai();
    tombol.forEach(b => b.addEventListener('click', () => {
        metrik = b.dataset.metrik;
        try { localStorage.setItem(KUNCI, metrik); } catch (e) {}
        grafik.data = isi();
        grafik.update();
        tandai();
    }));
})();
</script>
@endpush
@endif
