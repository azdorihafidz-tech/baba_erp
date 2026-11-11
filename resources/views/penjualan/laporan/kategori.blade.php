@extends('layouts.app')

@section('title', 'Laporan Penjualan Kategori')

@php
    $route = 'penjualan.laporan.kategori';
    $q = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',');
    $kolom = 8 + ($lihatUntung ? 3 : 0);
    $WARNA = [$brand->warna('utama'), '#1A1A1A', '#20A464', '#F4B400', '#3B82F6', '#8B5CF6', '#EC4899', '#14B8A6', '#94A3B8', '#B45309', '#0EA5E9', '#65A30D'];
    $warna = fn (int $i) => $WARNA[$i % count($WARNA)];
@endphp

@section('content')

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0" style="color:#1e293b">Laporan Penjualan Kategori</h4>
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
        <x-panduan-button slug="laporan-kategori-kasir" />
    </div>
</div>

@include('penjualan.laporan._periode', ['route' => $route, 'periode' => $periode, 'simpan' => ['urut' => $urut]])

<h5 class="fw-bold mb-3">{{ $periode['label'] }}</h5>

{{-- Kotak total --}}
<div class="row g-3 mb-3">
    <div class="col-6 {{ $lihatUntung ? 'col-lg-3' : 'col-lg-4' }}">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid var(--brand-sidebar) !important">
            <div class="card-body py-3">
                <div class="text-muted small">Kategori terjual</div>
                <div class="fs-4 fw-bold">{{ $baris->count() }} <span class="fs-6 fw-normal text-muted">kategori · {{ $baris->sum('jumlah_produk') }} produk</span></div>
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

{{-- Grafik porsi per kategori --}}
@if($baris->isNotEmpty())
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="fw-semibold"><i class="bi bi-pie-chart me-1" style="color:var(--brand-utama)"></i>Porsi per kategori</div>
        <div class="btn-group btn-group-sm" role="group" aria-label="Ukuran grafik">
            <button type="button" class="btn btn-outline-secondary lk-metrik" data-metrik="pendapatan">Pendapatan</button>
            <button type="button" class="btn btn-outline-secondary lk-metrik" data-metrik="qty">Qty</button>
            @if($lihatUntung)
            <button type="button" class="btn btn-outline-secondary lk-metrik" data-metrik="keuntungan">Keuntungan</button>
            @endif
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-md-5">
                <div style="position:relative;height:260px;max-width:300px;margin:0 auto"><canvas id="grafikKategori"></canvas></div>
            </div>
            <div class="col-12 col-md-7">
                <ul class="list-unstyled mb-0" id="legendaKategori"></ul>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Tabel --}}
@php
    $kepala = function (string $label, string $kunci, string $kelas = '') use ($route, $periode, $urut) {
        $aktif = $urut === $kunci;
        $url = route($route, $periode['params'] + ['urut' => $kunci]);
        $panah = $aktif ? ($kunci === 'nama' ? ' ▲' : ' ▼') : '';

        return '<th class="' . $kelas . '"><a href="' . e($url) . '" class="text-decoration-none ' . ($aktif ? 'text-dark fw-bold' : 'text-secondary') . '">' . e($label) . $panah . '</a></th>';
    };
@endphp
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center flex-wrap gap-1">
        <span><i class="bi bi-tags me-1"></i>Rincian per kategori <x-tooltip key="laporan_kategori_kasir.rincian" /></span>
        <span class="small text-muted fw-normal">Klik kategori untuk melihat produknya</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0" style="font-size:0.85rem">
            <thead class="table-light">
                <tr>
                    <th style="width:36px">#</th>
                    {!! $kepala('Kategori', 'nama') !!}
                    <th class="text-end">Produk</th>
                    {!! $kepala('Qty', 'qty', 'text-end') !!}
                    <th class="text-end">Penjualan <x-tooltip key="laporan_produk_kasir.penjualan" /></th>
                    <th class="text-end">Diskon <x-tooltip key="laporan_produk_kasir.diskon" /></th>
                    {!! $kepala('Pendapatan', 'pendapatan', 'text-end') !!}
                    <th class="text-end">Porsi</th>
                    @if($lihatUntung)
                    <th class="text-end">Modal</th>
                    {!! $kepala('Untung', 'keuntungan', 'text-end') !!}
                    <th class="text-end">Margin</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($baris as $i => $r)
                @php $porsi = $total['pendapatan'] > 0 ? $r['pendapatan'] / $total['pendapatan'] * 100 : 0; @endphp
                <tr role="button" class="lk-kategori" data-target="produk-{{ $i }}">
                    <td class="text-muted">{{ $i + 1 }}</td>
                    <td>
                        <span class="d-inline-block rounded-circle me-1" style="width:10px;height:10px;background:{{ $warna($i) }}"></span>
                        <span class="fw-semibold">{{ $r['kategori'] }}</span>
                        <i class="bi bi-chevron-down text-muted small ms-1 lk-panah"></i>
                    </td>
                    <td class="text-end text-muted">{{ $r['jumlah_produk'] }}</td>
                    <td class="text-end fw-semibold">{{ $q($r['qty']) }}</td>
                    <td class="text-end">{{ fmt_rupiah($r['penjualan']) }}</td>
                    <td class="text-end {{ $r['diskon'] > 0 ? 'text-danger' : 'text-muted' }}">{{ $r['diskon'] > 0 ? '− ' . fmt_rupiah($r['diskon']) : '-' }}</td>
                    <td class="text-end fw-semibold">{{ fmt_rupiah($r['pendapatan']) }}</td>
                    <td class="text-end" style="min-width:90px">
                        <div class="small">{{ number_format($porsi, 1, ',', '.') }}%</div>
                        <div class="progress" style="height:4px"><div class="progress-bar" style="width:{{ $porsi }}%;background:{{ $warna($i) }}"></div></div>
                    </td>
                    @if($lihatUntung)
                    <td class="text-end text-muted">{{ fmt_rupiah($r['hpp']) }}</td>
                    <td class="text-end {{ $r['keuntungan'] < 0 ? 'text-danger' : 'text-success' }}">{{ fmt_rupiah($r['keuntungan']) }}</td>
                    <td class="text-end">{{ $r['margin'] !== null ? $r['margin'] . '%' : '-' }}</td>
                    @endif
                </tr>
                @foreach($r['produk'] as $p)
                <tr class="produk-{{ $i }} d-none" style="background:var(--brand-latar)">
                    <td></td>
                    <td class="ps-4 small"><i class="bi bi-arrow-return-right me-1 text-muted"></i>{{ $p['nama'] }}
                        @if($p['kode'])<span class="text-muted">· {{ $p['kode'] }}</span>@endif</td>
                    <td></td>
                    <td class="text-end small">{{ $q($p['qty']) }} <span class="text-muted">{{ $p['satuan'] }}</span></td>
                    <td class="text-end small">{{ fmt_rupiah($p['penjualan']) }}</td>
                    <td class="text-end small">{{ $p['diskon'] > 0 ? '− ' . fmt_rupiah($p['diskon']) : '-' }}</td>
                    <td class="text-end small">{{ fmt_rupiah($p['pendapatan']) }}</td>
                    <td class="text-end small text-muted">{{ $r['pendapatan'] > 0 ? number_format($p['pendapatan'] / $r['pendapatan'] * 100, 1, ',', '.') . '%' : '-' }}</td>
                    @if($lihatUntung)
                    <td class="text-end small text-muted">{{ fmt_rupiah($p['hpp']) }}</td>
                    <td class="text-end small">{{ fmt_rupiah($p['keuntungan']) }}</td>
                    <td class="text-end small">{{ $p['margin'] !== null ? $p['margin'] . '%' : '-' }}</td>
                    @endif
                </tr>
                @endforeach
                @if($bolehLihatProduk)
                <tr class="produk-{{ $i }} d-none" style="background:var(--brand-latar)">
                    <td></td>
                    <td colspan="{{ $kolom - 1 }}" class="ps-4 small">
                        <a href="{{ route('penjualan.laporan.produk', $periode['params'] + ['kategori' => $r['kategori_id'] ?? -1]) }}" class="text-decoration-none">
                            <i class="bi bi-box-arrow-up-right me-1"></i>Buka kategori ini di Laporan Produk (grafik &amp; rincian varian)
                        </a>
                    </td>
                </tr>
                @endif
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
                    <td class="text-end">{{ $baris->sum('jumlah_produk') }}</td>
                    <td class="text-end">{{ $q($total['qty']) }}</td>
                    <td class="text-end">{{ fmt_rupiah($total['penjualan']) }}</td>
                    <td class="text-end">{{ $total['diskon'] > 0 ? '− ' . fmt_rupiah($total['diskon']) : '-' }}</td>
                    <td class="text-end">{{ fmt_rupiah($total['pendapatan']) }}</td>
                    <td class="text-end">100%</td>
                    @if($lihatUntung)
                    <td class="text-end">{{ fmt_rupiah($total['hpp']) }}</td>
                    <td class="text-end">{{ fmt_rupiah($total['keuntungan']) }}</td>
                    <td class="text-end">{{ $total['pendapatan'] > 0 ? round($total['keuntungan'] / $total['pendapatan'] * 100, 1) . '%' : '-' }}</td>
                    @endif
                </tr>
                @if($biayaTambahan > 0)
                <tr class="small">
                    <td colspan="6" class="text-muted">Service charge &amp; take away fee <span class="fw-normal">(bukan milik kategori mana pun)</span></td>
                    <td class="text-end">{{ fmt_rupiah($biayaTambahan) }}</td>
                    <td colspan="{{ $kolom - 7 }}"></td>
                </tr>
                <tr class="fw-bold">
                    <td colspan="6">Total pendapatan <span class="fw-normal text-muted small">(sama dengan Laporan Transaksi)</span></td>
                    <td class="text-end">{{ fmt_rupiah($total['pendapatan'] + $biayaTambahan) }}</td>
                    <td colspan="{{ $kolom - 7 }}"></td>
                </tr>
                @endif
            </tfoot>
            @endif
        </table>
    </div>
</div>

<p class="text-muted small mt-3 mb-0">
    <i class="bi bi-info-circle me-1"></i>Angka kategori = jumlah semua produk di kategori itu (sama dengan Laporan Produk).
    Diskon order dibagi ke setiap produk sesuai porsi harganya. Porsi = bagian pendapatan kategori dari total pendapatan produk.
    Transaksi dibatalkan, dihapus, atau bill tersimpan yang belum dibayar tidak dihitung.
</p>

@endsection

@push('styles')
<style>
    .lk-metrik.active { background:var(--brand-sidebar); color:rgb(var(--brand-sidebar-teks-rgb)); border-color:var(--brand-sidebar); }
    .lk-legenda { display:flex; align-items:center; gap:8px; padding:6px 4px; border-bottom:1px dashed #eee; font-size:.85rem; }
    .lk-legenda:last-child { border-bottom:0; }
    .lk-legenda .nama { flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
</style>
@endpush

@push('scripts')
<script>
// Buka/tutup produk di dalam kategori
document.querySelectorAll('.lk-kategori').forEach(function (tr) {
    tr.addEventListener('click', function (e) {
        if (e.target.closest('a')) return;
        const anak = document.querySelectorAll('.' + tr.dataset.target);
        const buka = anak.length && anak[0].classList.contains('d-none');
        anak.forEach(r => r.classList.toggle('d-none', !buka));
        const panah = tr.querySelector('.lk-panah');
        if (panah) panah.className = (buka ? 'bi bi-chevron-up' : 'bi bi-chevron-down') + ' text-muted small ms-1 lk-panah';
    });
});
</script>
@endpush

@if($baris->isNotEmpty())
@php
    $dataGrafik = $baris->values()->map(fn ($r, $i) => [
        'nama'       => $r['kategori'],
        'warna'      => $warna($i),
        'qty'        => $r['qty'],
        'pendapatan' => $r['pendapatan'],
    ] + ($lihatUntung ? ['keuntungan' => $r['keuntungan']] : []));
@endphp
@push('scripts')
<script>
(function () {
    const kanvas = document.getElementById('grafikKategori');
    if (!kanvas || typeof Chart === 'undefined') return;

    const semua = @json($dataGrafik);
    const rp = v => 'Rp ' + Math.round(v).toLocaleString('id-ID');
    const angka = v => (+v).toLocaleString('id-ID', { maximumFractionDigits: 3 });
    const singkat = v => Math.abs(v) >= 1e6 ? (v / 1e6).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + 'jt'
        : (Math.abs(v) >= 1e3 ? Math.round(v / 1e3).toLocaleString('id-ID') + 'rb' : angka(v));
    const JUDUL = { pendapatan: 'Pendapatan', qty: 'Qty'@if($lihatUntung), keuntungan: 'Keuntungan'@endif };

    const KUNCI = 'laporanKategori.metrik';
    let metrik = 'pendapatan';
    try { metrik = localStorage.getItem(KUNCI) || 'pendapatan'; } catch (e) {}
    if (!(metrik in semua[0])) metrik = 'pendapatan';

    const format = v => metrik === 'qty' ? angka(v) : rp(v);
    // Nilai negatif (rugi) tidak bisa digambar di donat → dihitung 0
    const nilai = () => semua.map(r => Math.max(0, r[metrik]));
    const jumlah = () => nilai().reduce((a, b) => a + b, 0);

    // Total di tengah donat
    const tengah = {
        id: 'tengahDonat',
        afterDraw(chart) {
            const m = chart.getDatasetMeta(0).data[0];
            if (!m) return;
            const { ctx } = chart;
            ctx.save();
            ctx.textAlign = 'center';
            ctx.fillStyle = '#64748b';
            ctx.font = '12px system-ui, sans-serif';
            ctx.fillText('Total ' + JUDUL[metrik].toLowerCase(), m.x, m.y - 10);
            ctx.fillStyle = '#1A1A1A';
            ctx.font = '700 16px system-ui, sans-serif';
            ctx.fillText(metrik === 'qty' ? angka(jumlah()) : singkat(jumlah()), m.x, m.y + 12);
            ctx.restore();
        },
    };

    const grafik = new Chart(kanvas, {
        type: 'doughnut',
        data: {
            labels: semua.map(r => r.nama),
            datasets: [{ data: nilai(), backgroundColor: semua.map(r => r.warna), borderColor: '#fff', borderWidth: 3, hoverOffset: 10 }],
        },
        plugins: [tengah],
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '66%',
            animation: { animateRotate: true, duration: 700 },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(26,26,26,0.95)', padding: 12, cornerRadius: 10, displayColors: true, usePointStyle: true,
                    titleFont: { size: 13, weight: '600' }, bodyFont: { size: 12 },
                    callbacks: {
                        label: c => {
                            const t = jumlah();
                            const v = semua[c.dataIndex][metrik];
                            return ' ' + format(v) + (t > 0 ? ' (' + (Math.max(0, v) / t * 100).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + '%)' : '');
                        },
                    },
                },
            },
        },
    });

    const legenda = document.getElementById('legendaKategori');
    function gambarLegenda() {
        const t = jumlah();
        legenda.innerHTML = '';
        semua.map((r, i) => ({ r, i })).sort((a, b) => b.r[metrik] - a.r[metrik]).forEach(({ r }) => {
            const li = document.createElement('li');
            li.className = 'lk-legenda';
            const persen = t > 0 ? Math.max(0, r[metrik]) / t * 100 : 0;
            const titik = document.createElement('span');
            titik.style.cssText = 'width:12px;height:12px;border-radius:50%;flex:none;background:' + r.warna;
            const nama = document.createElement('span');
            nama.className = 'nama';
            nama.textContent = r.nama;
            const nilaiEl = document.createElement('span');
            nilaiEl.className = 'text-muted';
            nilaiEl.textContent = format(r[metrik]);
            const persenEl = document.createElement('strong');
            persenEl.style.cssText = 'min-width:52px;text-align:right';
            persenEl.textContent = persen.toLocaleString('id-ID', { maximumFractionDigits: 1 }) + '%';
            li.append(titik, nama, nilaiEl, persenEl);
            legenda.appendChild(li);
        });
    }
    gambarLegenda();

    const tombol = document.querySelectorAll('.lk-metrik');
    const tandai = () => tombol.forEach(b => b.classList.toggle('active', b.dataset.metrik === metrik));
    tandai();
    tombol.forEach(b => b.addEventListener('click', () => {
        metrik = b.dataset.metrik;
        try { localStorage.setItem(KUNCI, metrik); } catch (e) {}
        grafik.data.datasets[0].data = nilai();
        grafik.update();
        gambarLegenda();
        tandai();
    }));
})();
</script>
@endpush
@endif
