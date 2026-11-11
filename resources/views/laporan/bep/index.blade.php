@extends('layouts.app')

@section('title', 'Laporan BEP')

@section('content')

@php $viewMode = $viewMode ?? 'detail'; @endphp

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0" style="color:#1e293b">Analisis Break Even Point (BEP)</h4>
        <p class="text-muted mb-0" style="font-size:0.875rem">Monitoring pencapaian titik impas</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}" class="btn btn-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i>
            <span class="d-none d-sm-inline">Export Excel</span>
        </a>
        <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}" class="btn btn-danger btn-sm">
            <i class="bi bi-file-earmark-pdf me-1"></i>
            <span class="d-none d-sm-inline">Export PDF</span>
        </a>
        <a href="{{ route('laporan.bep.per-cabang') }}" class="btn btn-outline-info btn-sm">
            <i class="bi bi-bar-chart me-1"></i>
            <span class="d-none d-sm-inline">Per Cabang</span>
        </a>
        <a href="{{ route('bep.setting') }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-gear me-1"></i>
            <span class="d-none d-sm-inline">Setting BEP</span>
        </a>
        <x-panduan-button slug="laporan-bep" />
    </div>
</div>

@if($viewMode === 'per_cabang')
    {{-- ===== VIEW: PER CABANG ===== --}}
    <!-- Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('laporan.bep.per-cabang') }}">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-sm-6 col-md-3">
                        <label class="form-label form-label-sm">Periode (Bulan)</label>
                        <input type="month" name="periode" class="form-control form-control-sm" value="{{ $periode }}">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-search me-1"></i>Tampilkan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Grafik Perbandingan Cabang -->
    <div class="card mb-4">
        <div class="card-header py-3 px-4">
            <i class="bi bi-bar-chart me-2 text-primary"></i>Perbandingan BEP antar Cabang
        </div>
        <div class="card-body p-3">
            <div style="position:relative;height:280px">
                <canvas id="bepCabangChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Tabel Per Cabang -->
    <div class="card">
        <div class="card-header py-3 px-4">
            <i class="bi bi-table me-2"></i>Detail per Cabang — {{ \Carbon\Carbon::parse($periode . '-01')->translatedFormat('F Y') }}
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="px-4">Cabang</th>
                            <th class="text-end px-3">Pendapatan Aktual</th>
                            <th class="text-end px-3 d-none d-md-table-cell">Target BEP</th>
                            <th class="text-end px-3 d-none d-sm-table-cell">Biaya Tetap</th>
                            <th class="text-center">Pencapaian</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($dataCabang as $row)
                        @php $pct = round($row['pct_bep'], 1); @endphp
                        <tr>
                            <td class="px-4 fw-medium" style="font-size:0.875rem">{{ $row['cabang']->nama_cabang }}</td>
                            <td class="text-end px-3" style="font-size:0.875rem">Rp {{ number_format($row['pendapatan'], 0, ',', '.') }}</td>
                            <td class="text-end px-3 d-none d-md-table-cell" style="font-size:0.875rem">
                                {{ $row['bep_rupiah'] > 0 ? 'Rp ' . number_format($row['bep_rupiah'], 0, ',', '.') : '<span class="text-muted">Belum diset</span>' }}
                            </td>
                            <td class="text-end px-3 d-none d-sm-table-cell text-muted" style="font-size:0.875rem">
                                Rp {{ number_format($row['biaya_tetap'], 0, ',', '.') }}
                            </td>
                            <td class="text-center">
                                <div class="progress mb-1" style="height:8px">
                                    <div class="progress-bar {{ $row['tercapai'] ? 'bg-success' : ($pct >= 75 ? 'bg-warning' : 'bg-danger') }}" style="width:{{ min(100, $pct) }}%"></div>
                                </div>
                                <small class="text-muted" style="font-size:0.7rem">{{ $pct }}%</small>
                            </td>
                            <td class="text-center">
                                @if($row['bep_rupiah'] <= 0)
                                    <span class="badge bg-secondary" style="font-size:0.7rem">N/A</span>
                                @elseif($row['tercapai'])
                                    <span class="badge bg-success" style="font-size:0.7rem">BEP Tercapai</span>
                                @else
                                    <span class="badge bg-danger" style="font-size:0.7rem">Belum BEP</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

@else
    {{-- ===== VIEW: DETAIL PER CABANG/PERIODE ===== --}}
    <!-- Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('laporan.bep') }}">
                <div class="row g-2 align-items-end">
                    @if(auth()->user()->canAccessAllBranches())
                    <div class="col-12 col-sm-6 col-md-3">
                        <label class="form-label form-label-sm">Cabang</label>
                        <select name="cabang_id" class="form-select form-select-sm">
                            <option value="">Semua</option>
                            @foreach($cabangs as $cab)
                            <option value="{{ $cab->id }}" {{ ($cabangId ?? '') == $cab->id ? 'selected' : '' }}>{{ $cab->nama_cabang }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    <div class="col-12 col-sm-6 col-md-3">
                        <label class="form-label form-label-sm">Periode (Bulan)</label>
                        <input type="month" name="periode" class="form-control form-control-sm" value="{{ $periode }}">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-search me-1"></i>Tampilkan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- BEP Summary -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-primary bg-opacity-10 mb-2"><i class="bi bi-cash-stack text-primary"></i></div>
                <div class="fw-bold" style="font-size:0.9rem;color:#1e293b">Rp {{ number_format($totalBiayaTetap, 0, ',', '.') }}</div>
                <div class="text-muted" style="font-size:0.8rem">Total Biaya Tetap</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-warning bg-opacity-10 mb-2"><i class="bi bi-graph-up-arrow text-warning"></i></div>
                <div class="fw-bold" style="font-size:0.9rem;color:#1e293b">Rp {{ number_format($totalBepRupiah, 0, ',', '.') }}</div>
                <div class="text-muted" style="font-size:0.8rem">Target BEP (Rp)</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-success bg-opacity-10 mb-2"><i class="bi bi-currency-dollar text-success"></i></div>
                <div class="fw-bold text-success" style="font-size:0.9rem">Rp {{ number_format($pendapatanAktual, 0, ',', '.') }}</div>
                <div class="text-muted" style="font-size:0.8rem">Pendapatan Aktual</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <div class="stat-card {{ $bepTercapai ? '' : 'border-danger' }}">
                <div class="stat-icon {{ $bepTercapai ? 'bg-success bg-opacity-10' : 'bg-danger bg-opacity-10' }} mb-2">
                    <i class="bi {{ $bepTercapai ? 'bi-check-circle text-success' : 'bi-x-circle text-danger' }}"></i>
                </div>
                <div class="fw-bold {{ $bepTercapai ? 'text-success' : 'text-danger' }}" style="font-size:1.2rem">
                    {{ number_format($pctBep, 1) }}%
                </div>
                <div class="text-muted" style="font-size:0.8rem">Pencapaian BEP</div>
            </div>
        </div>
    </div>

    <!-- Progress BEP -->
    @if($totalBepRupiah > 0)
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between mb-2">
                <span class="fw-semibold">Progress BEP Bulan Ini</span>
                <span class="{{ $bepTercapai ? 'text-success fw-bold' : 'text-danger fw-bold' }}">
                    {{ number_format($pctBep, 1) }}% {{ $bepTercapai ? '✓ BEP Tercapai!' : '(Belum BEP)' }}
                </span>
            </div>
            <div class="progress" style="height:20px;border-radius:10px">
                <div class="progress-bar {{ $bepTercapai ? 'bg-success' : ($pctBep >= 75 ? 'bg-warning' : 'bg-danger') }}"
                     style="width:{{ min(100, $pctBep) }}%;border-radius:10px;font-size:0.75rem">
                    {{ number_format($pctBep, 0) }}%
                </div>
            </div>
            <div class="d-flex justify-content-between mt-1">
                <small class="text-muted">Rp 0</small>
                <small class="text-muted">Target: Rp {{ number_format($totalBepRupiah, 0, ',', '.') }}</small>
            </div>
        </div>
    </div>
    @endif

    <div class="row g-3 mb-4">
        <!-- Grafik BEP — Bug6 FIX: tambah selector produk -->
        @if(!empty($allProductsChartData) && count($allProductsChartData) > 0)
        <div class="col-12 col-lg-7">
            <div class="card">
                <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <span><i class="bi bi-graph-up me-2 text-primary"></i>Grafik BEP (Titik Impas)</span>
                    @if(count($allProductsChartData) > 1)
                    <select id="bepProductSelect" class="form-select form-select-sm w-auto" style="min-width:150px"
                            onchange="renderBepChart(this.value)">
                        @foreach($products as $p)
                            @if(isset($allProductsChartData[(string)$p->id]))
                            <option value="{{ $p->id }}">{{ $p->nama_produk }}</option>
                            @endif
                        @endforeach
                    </select>
                    @endif
                </div>
                <div class="card-body p-3">
                    <div style="position:relative;height:260px">
                        <canvas id="bepChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Produk BEP -->
        @if($products->isNotEmpty())
        <div class="{{ !empty($grafikLabels) ? 'col-12 col-lg-5' : 'col-12' }}">
            <div class="card">
                <div class="card-header py-3 px-4">
                    <i class="bi bi-box me-2 text-primary"></i>BEP per Produk/Jasa
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th class="px-3">Produk</th>
                                    <th class="text-end px-3">BEP (unit)</th>
                                    <th class="text-end px-3">BEP (Rp)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($products as $prod)
                                <tr>
                                    <td class="px-3" style="font-size:0.875rem">
                                        {{ $prod->nama_produk }}
                                        <div class="text-muted" style="font-size:0.75rem">
                                            MC: Rp {{ number_format($prod->margin_kontribusi, 0, ',', '.') }}/unit
                                        </div>
                                    </td>
                                    <td class="text-end px-3" style="font-size:0.875rem">{{ number_format($prod->bep_unit, 1, ',', '.') }}</td>
                                    <td class="text-end px-3 fw-medium" style="font-size:0.875rem">Rp {{ number_format($prod->bep_rupiah, 0, ',', '.') }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>

    @if(!$bepSetting)
    <div class="text-center py-5">
        <i class="bi bi-graph-up-arrow text-muted" style="font-size:3rem"></i>
        <p class="mt-3 text-muted">Belum ada setting BEP untuk periode ini.</p>
        <a href="{{ route('bep.setting') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-gear me-1"></i>Setup BEP
        </a>
    </div>
    @endif
@endif

@endsection

@push('scripts')
<script>
@if($viewMode === 'per_cabang')
const bepCabangCtx = document.getElementById('bepCabangChart')?.getContext('2d');
if (bepCabangCtx) {
    new Chart(bepCabangCtx, {
        type: 'bar',
        data: {
            labels: @json($grafikLabels ?? []),
            datasets: [
                {
                    label: 'Pendapatan Aktual',
                    data: @json($grafikPendapatan ?? []),
                    backgroundColor: 'rgba(34,197,94,0.7)',
                    borderRadius: 4,
                },
                {
                    label: 'Target BEP',
                    data: @json($grafikBepTarget ?? []),
                    backgroundColor: 'rgba(239,68,68,0.4)',
                    borderColor: '#ef4444',
                    borderWidth: 2,
                    type: 'line',
                    fill: false,
                    tension: 0,
                    pointRadius: 5,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: true, labels: { font: { size: 11 } } },
                tooltip: { callbacks: { label: (ctx) => ctx.dataset.label + ': Rp ' + ctx.raw.toLocaleString('id-ID') } }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: (val) => 'Rp ' + (val >= 1000000 ? (val/1000000).toFixed(1)+'jt' : (val/1000)+'rb'), font: { size: 10 } },
                    grid: { color: '#f1f5f9' }
                },
                x: { grid: { display: false }, ticks: { font: { size: 10 } } }
            }
        }
    });
}
@else
{{-- Bug6 FIX: chart per-produk dengan selector --}}
const allBepChartData = @json($allProductsChartData ?? []);
let bepChartInstance = null;

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: true, position: 'top', labels: { font: { size: 11 }, boxWidth: 12 } },
        tooltip: { callbacks: { label: (ctx) => ctx.dataset.label + ': Rp ' + ctx.raw.toLocaleString('id-ID') } }
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: { callback: (val) => 'Rp ' + (val >= 1000000 ? (val/1000000).toFixed(1)+'jt' : (val/1000)+'rb'), font: { size: 10 } },
            grid: { color: '#f1f5f9' }
        },
        x: { grid: { display: false }, ticks: { font: { size: 10 }, maxTicksLimit: 8 } }
    }
};

function renderBepChart(productId) {
    const d = allBepChartData[String(productId)];
    if (!d) return;
    const ctx = document.getElementById('bepChart');
    if (!ctx) return;
    if (bepChartInstance) { bepChartInstance.destroy(); }
    bepChartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: d.labels,
            datasets: [
                { label: 'Biaya Tetap', data: d.biayaTetap, borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,0.05)', borderWidth: 2, borderDash: [5,5], fill: false, pointRadius: 0 },
                { label: 'Biaya Total', data: d.biayaTotal, borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,0.05)', borderWidth: 2, fill: false, pointRadius: 0 },
                { label: 'Pendapatan', data: d.pendapatan, borderColor: '#22c55e', backgroundColor: 'rgba(34,197,94,0.1)', borderWidth: 2, fill: false, pointRadius: 0 }
            ]
        },
        options: chartOptions
    });
}

// Init dengan produk pertama
const firstProductId = Object.keys(allBepChartData)[0];
if (firstProductId) renderBepChart(firstProductId);
@endif
</script>
@endpush
