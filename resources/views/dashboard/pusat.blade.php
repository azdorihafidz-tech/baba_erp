@extends('layouts.app')

@section('title', 'Dashboard Pusat')

@section('content')

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0" style="color:#1e293b">Dashboard Keseluruhan</h4>
        <p class="text-muted mb-0" style="font-size:0.875rem">
            {{ now()->translatedFormat('l, d F Y') }} &nbsp;|&nbsp;
            <i class="bi bi-globe text-primary"></i> Semua Cabang
        </p>
    </div>
    <div>
        <span class="badge bg-primary px-3 py-2">
            <i class="bi bi-building me-1"></i>{{ $cabangs->count() }} Cabang Aktif
        </span>
    </div>
</div>

<!-- Alert -->
@if($alertStokRendah > 0 || $alertPembelianMendesak > 0)
<div class="row g-2 mb-3">
    @if($alertStokRendah > 0)
    <div class="col-12 col-md-6">
        <div class="alert alert-warning d-flex align-items-center mb-0 py-2" style="font-size:0.875rem">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <span><strong>{{ $alertStokRendah }}</strong> item stok rendah di seluruh lokasi</span>
            <a href="{{ route('laporan.stok.minimum') }}" class="btn btn-warning btn-sm ms-auto py-0" style="font-size:0.75rem">Detail</a>
        </div>
    </div>
    @endif
    @if($alertPembelianMendesak > 0)
    <div class="col-12 col-md-6">
        <div class="alert alert-danger d-flex align-items-center mb-0 py-2" style="font-size:0.875rem">
            <i class="bi bi-lightning-fill me-2"></i>
            <span><strong>{{ $alertPembelianMendesak }}</strong> pembelian langsung menunggu persetujuan</span>
            <a href="{{ route('pembelian.index') }}" class="btn btn-danger btn-sm ms-auto py-0" style="font-size:0.75rem">Lihat</a>
        </div>
    </div>
    @endif
</div>
@endif

{{-- Closing / Tutup Buku — pengingat hari belum closing --}}
<x-closing-pengingat />

{{-- ===== Sprint 4.32 — Analisis Penjualan (filter periode terpisah dari widget snapshot di atas) ===== --}}
@isset($analisisPenjualan)
    <x-analisis-penjualan
        :data="$analisisPenjualan"
        :periodeAktif="$analisisPenjualanMeta['periode_aktif']"
        :labelPeriode="$analisisPenjualanMeta['label_periode']"
        :presetList="$analisisPenjualanMeta['preset_list']"
        :cabangId="$analisisPenjualanMeta['cabang_id']"
    />
@endisset

{{-- ===== Tahap 6 D'mentai — Widget Dashboard Owner (Setoran + Kas HO) ===== --}}
@if($dashboardOwner)
<div class="d-flex justify-content-between align-items-center mb-2">
    <h6 class="fw-bold mb-0 text-muted" style="font-size:0.8rem;letter-spacing:.03em;text-transform:uppercase">Ringkasan Setoran & Kas HO</h6>
    <x-panduan-button slug="dashboard-owner" />
</div>
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon bg-success bg-opacity-10 mb-2"><i class="bi bi-graph-up-arrow text-success"></i></div>
            <div class="fw-bold" style="font-size:1.4rem;color:#1e293b">Rp {{ number_format($dashboardOwner['totalOmzetHariIni'], 0, ',', '.') }}</div>
            <div class="text-muted" style="font-size:0.8rem">Total Penjualan Hari Ini</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon bg-primary bg-opacity-10 mb-2"><i class="bi bi-cash-stack text-primary"></i></div>
            <div class="fw-bold" style="font-size:1.4rem;color:#1e293b">Rp {{ number_format($totalOmzetBulanIni, 0, ',', '.') }}</div>
            <div class="text-muted" style="font-size:0.8rem">Total Penjualan Bulan Ini</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon bg-warning bg-opacity-10 mb-2"><i class="bi bi-hourglass-split text-warning"></i></div>
            <div class="fw-bold" style="font-size:1.4rem;color:#1e293b">Rp {{ number_format($dashboardOwner['uangBelumDisetor'], 0, ',', '.') }}</div>
            <div class="text-muted" style="font-size:0.8rem">Uang Belum Disetor <x-tooltip key="dashboard_owner.uang_belum_disetor" /></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon bg-info bg-opacity-10 mb-2"><i class="bi bi-safe text-info"></i></div>
            <div class="fw-bold" style="font-size:1.4rem;color:#1e293b">Rp {{ number_format($dashboardOwner['kasHoSaldo'], 0, ',', '.') }}</div>
            <div class="text-muted" style="font-size:0.8rem">Kas HO Saat Ini <x-tooltip key="dashboard_owner.kas_ho" /></div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-lg-6">
        <div class="stat-card">
            <h6 class="fw-bold mb-3" style="font-size:0.9rem">Trend Penjualan Harian (7 Hari)</h6>
            <div style="height:220px"><canvas id="trend7HariChart"></canvas></div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fw-bold mb-0" style="font-size:0.9rem">Setoran Menunggu Approval</h6>
                <a href="{{ route('setoran-kasir.index') }}" class="small">Lihat semua</a>
            </div>
            <div class="table-responsive" style="max-height:220px">
                <table class="table table-sm mb-0">
                    <tbody>
                        @forelse($dashboardOwner['setoranPending'] as $s)
                        <tr>
                            <td>{{ $s->cabang->nama_cabang }}</td>
                            <td>{{ $s->tanggal->format('d/m') }}</td>
                            <td class="text-end">Rp {{ number_format($s->total_disetor, 0, ',', '.') }}</td>
                            <td class="text-end">
                                <a href="{{ route('setoran-kasir.show', $s) }}" class="btn btn-sm btn-outline-primary py-0">Proses</a>
                            </td>
                        </tr>
                        @empty
                        <tr><td class="text-muted text-center py-3">Tidak ada setoran menunggu</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-lg-6">
        <div class="stat-card">
            <h6 class="fw-bold mb-2" style="font-size:0.9rem">Outlet dengan Stok Minimum</h6>
            <div class="table-responsive" style="max-height:220px">
                <table class="table table-sm mb-0">
                    <tbody>
                        @forelse($dashboardOwner['stokMinimumList'] as $st)
                        <tr>
                            <td>{{ $st->item->nama_item ?? '-' }}</td>
                            <td>{{ $st->lokasi->nama_cabang ?? '-' }}</td>
                            <td class="text-end text-danger">{{ $st->qty }} / min {{ $st->qty_minimum }}</td>
                        </tr>
                        @empty
                        <tr><td class="text-muted text-center py-3">Semua stok aman</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="stat-card">
            <h6 class="fw-bold mb-2" style="font-size:0.9rem">Selisih Setoran vs Sistem (&ge; Rp5.000)</h6>
            <div class="table-responsive" style="max-height:220px">
                <table class="table table-sm mb-0">
                    <tbody>
                        @forelse($dashboardOwner['setoranSelisih'] as $s)
                        <tr>
                            <td>{{ $s->cabang->nama_cabang }}</td>
                            <td>{{ $s->tanggal->format('d/m') }}</td>
                            <td class="text-end {{ $s->selisih < 0 ? 'text-danger' : 'text-warning' }}">Rp {{ number_format($s->selisih, 0, ',', '.') }}</td>
                        </tr>
                        @empty
                        <tr><td class="text-muted text-center py-3">Tidak ada selisih signifikan</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endif

<!-- ===== STAT CARDS ===== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="stat-icon bg-success bg-opacity-10">
                    <i class="bi bi-cash-stack text-success"></i>
                </div>
                <span class="badge bg-success-subtle text-success" style="font-size:0.7rem">Bulan ini</span>
            </div>
            <div class="fw-bold" style="font-size:1.1rem;color:#1e293b">Rp {{ number_format($totalOmzetBulanIni, 0, ',', '.') }}</div>
            <div class="text-muted" style="font-size:0.8rem">Total Omzet Bulan Ini</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="stat-icon bg-primary bg-opacity-10">
                    <i class="bi bi-cart3 text-primary"></i>
                </div>
                <span class="badge bg-primary-subtle text-primary" style="font-size:0.7rem">Bulan ini</span>
            </div>
            <div class="fw-bold" style="font-size:1.5rem;color:#1e293b">{{ number_format($totalOrderBulanIni, 0, ',', '.') }}</div>
            <div class="text-muted" style="font-size:0.8rem">Total Order Bulan Ini</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="stat-icon bg-info bg-opacity-10">
                    <i class="bi bi-people text-info"></i>
                </div>
                <span class="badge bg-info-subtle text-info" style="font-size:0.7rem">Aktif</span>
            </div>
            <div class="fw-bold" style="font-size:1.5rem;color:#1e293b">{{ $totalKaryawan }}</div>
            <div class="text-muted" style="font-size:0.8rem">Total Karyawan Aktif</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="stat-icon bg-warning bg-opacity-10">
                    <i class="bi bi-building text-warning"></i>
                </div>
                <span class="badge bg-warning-subtle text-warning" style="font-size:0.7rem">Aktif</span>
            </div>
            <div class="fw-bold" style="font-size:1.5rem;color:#1e293b">{{ $cabangs->count() }}</div>
            <div class="text-muted" style="font-size:0.8rem">Cabang Aktif</div>
        </div>
    </div>
</div>

<!-- ===== CHARTS ROW ===== -->
<div class="row g-3 mb-4">
    <div class="col-12 col-lg-8">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between py-3 px-4">
                <span><i class="bi bi-graph-up me-2 text-primary"></i>Tren Omzet per Cabang (6 Bulan)</span>
            </div>
            <div class="card-body p-3">
                <div style="position:relative;height:260px">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card">
            <div class="card-header py-3 px-4">
                <i class="bi bi-bar-chart me-2 text-primary"></i>Omzet Bulan Ini per Cabang
            </div>
            <div class="card-body p-3">
                <div style="position:relative;height:260px">
                    <canvas id="perCabangChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

@can('po_dashboard.view')
<x-po-status-widget :ringkasan="$poRingkasan" />
@endcan

@can('aset.view')
<x-aset-snapshot-widget :data="$asetSnapshot" />
@endcan

@can('laporan.neraca.view')
<x-neraca-snapshot-widget :neraca="$neracaSnapshot" />
@endcan

@can('dashboard.analytics.view')
<x-dashboard-analytics-widget :analytics="$analyticsSnapshot" />
@endcan

@can('laporan.jam_ramai.view')
<x-jam-ramai-widget :jamRamai="$jamRamaiSnapshot" />
@endcan

@can('loyalty.view')
<x-loyalty-widget :data="$loyaltyWidget" />
@endcan

@can('loyalty.klaim.approve')
<x-loyalty-klaim-widget :data="$loyaltyKlaimWidget" />
@include('components.perlengkapan-menipis-widget')
@endcan

<!-- ===== RANKING CABANG ===== -->
<div class="row g-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between py-3 px-4">
                <span><i class="bi bi-trophy me-2 text-warning"></i>Ranking Omzet Cabang Bulan Ini</span>
                <a href="{{ route('laporan.penjualan') }}" class="btn btn-sm btn-outline-primary" style="font-size:0.75rem">
                    Laporan Lengkap
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="px-4" style="width:60px">Rank</th>
                                <th>Cabang</th>
                                <th class="d-none d-md-table-cell">Kode</th>
                                <th class="text-end px-4">Omzet Bulan Ini</th>
                                <th class="d-none d-sm-table-cell">Progress</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $maxOmzet = collect($rankingCabang)->max('omzet') ?: 1; @endphp
                            @forelse($rankingCabang as $idx => $item)
                            <tr>
                                <td class="px-4">
                                    @if($idx === 0)
                                        <span class="badge bg-warning text-dark">1st</span>
                                    @elseif($idx === 1)
                                        <span class="badge bg-secondary">2nd</span>
                                    @elseif($idx === 2)
                                        <span class="badge bg-danger" style="background:#cd7f32!important">3rd</span>
                                    @else
                                        <span class="text-muted" style="font-size:0.875rem">{{ $idx + 1 }}</span>
                                    @endif
                                </td>
                                <td class="fw-medium" style="font-size:0.875rem">{{ $item['cabang']->nama_cabang }}</td>
                                <td class="d-none d-md-table-cell text-muted" style="font-size:0.875rem">{{ $item['cabang']->kode_cabang }}</td>
                                <td class="text-end px-4 fw-bold" style="font-size:0.875rem">
                                    Rp {{ number_format($item['omzet'], 0, ',', '.') }}
                                </td>
                                <td class="d-none d-sm-table-cell" style="min-width:120px">
                                    @php $pct = $maxOmzet > 0 ? ($item['omzet'] / $maxOmzet) * 100 : 0; @endphp
                                    <div class="progress" style="height:8px">
                                        <div class="progress-bar bg-primary" style="width:{{ $pct }}%"></div>
                                    </div>
                                    <small class="text-muted" style="font-size:0.7rem">{{ number_format($pct, 1) }}%</small>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox me-2"></i>Belum ada data penjualan bulan ini
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
@if($dashboardOwner)
// Tahap 6 D'mentai — trend penjualan harian 7 hari (widget Dashboard Owner)
const trend7Ctx = document.getElementById('trend7HariChart').getContext('2d');
new Chart(trend7Ctx, {
    type: 'line',
    data: {
        labels: @json($dashboardOwner['grafik7HariLabels']),
        datasets: [{
            label: 'Omzet',
            data: @json($dashboardOwner['grafik7HariData']),
            borderColor: '#22c55e', backgroundColor: '#22c55e20',
            borderWidth: 2, fill: true, tension: 0.4,
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { callback: v => 'Rp ' + (v/1000) + 'k' } } }
    }
});
@endif

// Tren bulanan per cabang
const trendCtx = document.getElementById('trendChart').getContext('2d');
new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: @json($grafikBulananLabels),
        datasets: @json($grafikBulananDatasets)
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'top',
                labels: { font: { size: 11 }, boxWidth: 12 }
            },
            tooltip: {
                callbacks: {
                    label: (ctx) => ctx.dataset.label + ': Rp ' + ctx.raw.toLocaleString('id-ID')
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: '#f1f5f9' },
                ticks: {
                    callback: (val) => 'Rp ' + (val >= 1000000 ? (val/1000000).toFixed(1)+'jt' : (val/1000)+'rb'),
                    font: { size: 10 }
                }
            },
            x: { grid: { display: false }, ticks: { font: { size: 10 } } }
        }
    }
});

// Per cabang bulan ini (bar)
const perCabangCtx = document.getElementById('perCabangChart').getContext('2d');
new Chart(perCabangCtx, {
    type: 'bar',
    data: {
        labels: @json($grafikPerCabangLabels),
        datasets: [{
            label: 'Omzet',
            data: @json($grafikPerCabangData),
            backgroundColor: ['#3b82f6','#22c55e','#f59e0b','#ef4444','#8b5cf6','#06b6d4'],
            borderRadius: 6,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: (ctx) => 'Rp ' + ctx.raw.toLocaleString('id-ID')
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: '#f1f5f9' },
                ticks: {
                    callback: (val) => 'Rp ' + (val >= 1000000 ? (val/1000000).toFixed(1)+'jt' : (val/1000)+'rb'),
                    font: { size: 10 }
                }
            },
            x: { grid: { display: false }, ticks: { font: { size: 10 } } }
        }
    }
});
</script>
@endpush
