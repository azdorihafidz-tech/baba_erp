@extends('layouts.app')

@section('title', 'Dashboard Keuangan')

@section('content')
<div class="container-fluid px-3 px-md-4">

    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div class="flex-grow-1">
            <h4 class="mb-0 fw-bold">
                <i class="bi bi-speedometer2 text-primary me-2"></i>Dashboard Keuangan
            </h4>
            <small class="text-muted" id="periodeLabel">{{ $periodeLabel }}</small>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            {{-- Filter Cabang (Owner) --}}
            @if($isAllBranches && $cabangOptions->isNotEmpty())
            <select id="filterCabang" class="form-select form-select-sm" style="min-width:140px;max-width:200px">
                <option value="0" @selected(!$cabangId)>Semua Cabang</option>
                @foreach($cabangOptions as $c)
                <option value="{{ $c->id }}" @selected($cabangId == $c->id)>{{ $c->nama_cabang }}</option>
                @endforeach
            </select>
            @endif

            {{-- Filter Periode --}}
            <select id="filterPeriode" class="form-select form-select-sm" style="min-width:155px">
                <option value="bulan_ini" @selected($periode==='bulan_ini')>Bulan Ini</option>
                <option value="hari_ini" @selected($periode==='hari_ini')>Hari Ini</option>
                <option value="kemarin" @selected($periode==='kemarin')>Kemarin</option>
                <option value="7_hari" @selected($periode==='7_hari')>7 Hari Terakhir</option>
                <option value="bulan_lalu" @selected($periode==='bulan_lalu')>Bulan Lalu</option>
                <option value="custom" @selected($periode==='custom')>Rentang Custom</option>
            </select>
            <x-panduan-button slug="dashboard-keuangan" />
        </div>
    </div>

    {{-- Custom Date Range (hidden by default) --}}
    <div id="customRangeRow" class="row g-2 mb-3 {{ $periode !== 'custom' ? 'd-none' : '' }}">
        <div class="col-6 col-md-3 col-lg-2">
            <input type="date" id="inputDari" class="form-control form-control-sm"
                   value="{{ $dari ?? '' }}" placeholder="Dari">
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <input type="date" id="inputSampai" class="form-control form-control-sm"
                   value="{{ $sampai ?? '' }}" placeholder="Sampai">
        </div>
        <div class="col-auto">
            <button class="btn btn-sm btn-primary" onclick="loadDashboard()">
                <i class="bi bi-funnel-fill me-1"></i>Terapkan
            </button>
        </div>
    </div>

    {{-- Alerts --}}
    <div id="alertsContainer">
        @if(!empty($alerts))
            @foreach($alerts as $alert)
            <div class="alert alert-{{ $alert['tipe'] }} alert-dismissible py-2 px-3 mb-2" role="alert">
                <small>{!! $alert['pesan'] !!}</small>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
            </div>
            @endforeach
        @endif
    </div>

    {{-- Stat Cards --}}
    <div class="row g-3 mb-3" id="statCards">
        @php
        $cards = [
            ['label' => 'Total Pemasukan', 'key' => 'pemasukan',   'value' => $pemasukan,   'trend' => $trend_pemasukan,   'color' => 'success', 'icon' => 'bi-arrow-down-circle-fill'],
            ['label' => 'Total Pengeluaran','key' => 'pengeluaran', 'value' => $pengeluaran, 'trend' => $trend_pengeluaran, 'color' => 'danger',  'icon' => 'bi-arrow-up-circle-fill'],
            ['label' => 'Selisih (Laba)',   'key' => 'selisih',    'value' => $selisih,     'trend' => $trend_selisih,     'color' => $selisih >= 0 ? 'primary' : 'warning', 'icon' => 'bi-wallet2'],
        ];
        @endphp
        @foreach($cards as $card)
        <div class="col-12 col-sm-4">
            <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid var(--bs-{{ $card['color'] }}) !important;">
                <div class="card-body py-3">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <small class="text-muted d-block mb-1">{{ $card['label'] }}</small>
                            <div class="fw-bold fs-5" id="val_{{ $card['key'] }}">
                                Rp {{ number_format($card['value'], 0, ',', '.') }}
                            </div>
                        </div>
                        <i class="bi {{ $card['icon'] }} text-{{ $card['color'] }} fs-3 opacity-50"></i>
                    </div>
                    <div class="mt-2" id="trend_{{ $card['key'] }}">
                        @php $t = $card['trend']; @endphp
                        @if($t > 0)
                        <small class="text-success"><i class="bi bi-arrow-up-short"></i>{{ $t }}% vs periode lalu</small>
                        @elseif($t < 0)
                        <small class="text-danger"><i class="bi bi-arrow-down-short"></i>{{ abs($t) }}% vs periode lalu</small>
                        @else
                        <small class="text-muted">Sama dengan periode lalu</small>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Chart 7 Hari --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white border-0 pb-0 pt-3 px-3">
            <h6 class="mb-0 fw-semibold">
                <i class="bi bi-bar-chart-fill text-primary me-2"></i>Pemasukan vs Pengeluaran — 7 Hari Terakhir
            </h6>
        </div>
        <div class="card-body px-2 pb-2">
            <div style="position:relative;height:220px">
                <canvas id="chartHarian"></canvas>
            </div>
        </div>
    </div>

    {{-- Kas Saldo + Top Pengeluaran --}}
    <div class="row g-3 mb-3">
        {{-- Saldo Kas --}}
        <div class="col-12 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pb-0 pt-3 px-3">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-safe2-fill text-success me-2"></i>Saldo Kas
                    </h6>
                </div>
                <div class="card-body p-0" id="kasList">
                    @if(!empty($kas))
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <tbody>
                            @foreach($kas as $k)
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-semibold small">{{ $k['nama'] }}</div>
                                    @if($k['cabang'])
                                    <small class="text-muted">{{ $k['cabang'] }}</small>
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    <span class="fw-bold {{ $k['low'] ? 'text-danger' : 'text-dark' }}">
                                        Rp {{ number_format($k['saldo'], 0, ',', '.') }}
                                    </span>
                                    @if($k['low'])
                                    <br><small class="text-danger"><i class="bi bi-exclamation-triangle-fill"></i> Saldo rendah</small>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <p class="text-muted text-center py-4 mb-0 small">Belum ada data kas.</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Top Pengeluaran Bulan Ini --}}
        <div class="col-12 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pb-0 pt-3 px-3">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-pie-chart-fill text-danger me-2"></i>Top Pengeluaran — Bulan Ini
                    </h6>
                </div>
                <div class="card-body" id="topPengeluaran">
                    @if(!empty($top_pengeluaran))
                    @foreach($top_pengeluaran as $tp)
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-truncate me-2" style="max-width:55%">{{ $tp['nama'] }}</span>
                            <span class="fw-semibold">Rp {{ number_format($tp['total'], 0, ',', '.') }}</span>
                        </div>
                        <div class="progress" style="height:6px">
                            <div class="progress-bar bg-danger" style="width:{{ $tp['persen'] }}%"></div>
                        </div>
                    </div>
                    @endforeach
                    @else
                    <p class="text-muted text-center py-3 mb-0 small">Belum ada pengeluaran bulan ini.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Footer info --}}
    <div class="text-end text-muted mb-3" style="font-size:0.75rem">
        <i class="bi bi-clock me-1"></i>Diperbarui: <span id="lastUpdated">{{ $updated_at }}</span>
        &nbsp;|&nbsp; Auto-refresh setiap 60 detik
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const DASHBOARD_DATA_URL = '{{ route('keuangan.dashboard.data') }}';

let chartInstance = null;

// Init chart dari data server-side (initial load)
initChart(@json($chart));

function initChart(data) {
    const ctx = document.getElementById('chartHarian');
    if (!ctx) return;
    if (chartInstance) { chartInstance.destroy(); }
    chartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [
                {
                    label: 'Pemasukan',
                    data: data.pemasukan,
                    backgroundColor: 'rgba(25,135,84,0.75)',
                    borderRadius: 4,
                },
                {
                    label: 'Pengeluaran',
                    data: data.pengeluaran,
                    backgroundColor: 'rgba(220,53,69,0.75)',
                    borderRadius: 4,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                tooltip: {
                    callbacks: {
                        label: ctx => ' Rp ' + new Intl.NumberFormat('id-ID').format(ctx.raw)
                    }
                }
            },
            scales: {
                y: {
                    ticks: {
                        font: { size: 10 },
                        callback: v => 'Rp ' + (v >= 1e6 ? (v/1e6).toFixed(1)+'jt' : new Intl.NumberFormat('id-ID').format(v))
                    },
                    grid: { color: 'rgba(0,0,0,0.05)' }
                },
                x: { ticks: { font: { size: 10 } }, grid: { display: false } }
            }
        }
    });
}

function rupiah(n) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(n));
}

function trendHtml(t) {
    if (t > 0)  return `<small class="text-success"><i class="bi bi-arrow-up-short"></i>${t}% vs periode lalu</small>`;
    if (t < 0)  return `<small class="text-danger"><i class="bi bi-arrow-down-short"></i>${Math.abs(t)}% vs periode lalu</small>`;
    return `<small class="text-muted">Sama dengan periode lalu</small>`;
}

function updateDashboard(data) {
    // Stat cards
    document.getElementById('val_pemasukan').textContent   = rupiah(data.pemasukan);
    document.getElementById('val_pengeluaran').textContent = rupiah(data.pengeluaran);
    document.getElementById('val_selisih').textContent     = rupiah(data.selisih);
    document.getElementById('trend_pemasukan').innerHTML   = trendHtml(data.trend_pemasukan);
    document.getElementById('trend_pengeluaran').innerHTML = trendHtml(data.trend_pengeluaran);
    document.getElementById('trend_selisih').innerHTML     = trendHtml(data.trend_selisih);

    // Periode label
    if (data.periode_label) {
        document.querySelector('#periodeLabel').textContent = data.periode_label;
    }

    // Chart
    initChart(data.chart);

    // Kas saldo
    const kasList = document.getElementById('kasList');
    if (data.kas && data.kas.length > 0) {
        let html = '<div class="table-responsive"><table class="table table-sm table-hover mb-0"><tbody>';
        data.kas.forEach(k => {
            html += `<tr>
                <td class="ps-3">
                    <div class="fw-semibold small">${k.nama}</div>
                    ${k.cabang ? `<small class="text-muted">${k.cabang}</small>` : ''}
                </td>
                <td class="text-end pe-3">
                    <span class="fw-bold ${k.low ? 'text-danger' : 'text-dark'}">${rupiah(k.saldo)}</span>
                    ${k.low ? '<br><small class="text-danger"><i class="bi bi-exclamation-triangle-fill"></i> Saldo rendah</small>' : ''}
                </td>
            </tr>`;
        });
        html += '</tbody></table></div>';
        kasList.innerHTML = html;
    } else {
        kasList.innerHTML = '<p class="text-muted text-center py-4 mb-0 small">Belum ada data kas.</p>';
    }

    // Top pengeluaran
    const topEl = document.getElementById('topPengeluaran');
    if (data.top_pengeluaran && data.top_pengeluaran.length > 0) {
        let html = '';
        data.top_pengeluaran.forEach(tp => {
            html += `<div class="mb-2">
                <div class="d-flex justify-content-between small mb-1">
                    <span class="text-truncate me-2" style="max-width:55%">${tp.nama}</span>
                    <span class="fw-semibold">${rupiah(tp.total)}</span>
                </div>
                <div class="progress" style="height:6px">
                    <div class="progress-bar bg-danger" style="width:${tp.persen}%"></div>
                </div>
            </div>`;
        });
        topEl.innerHTML = html;
    } else {
        topEl.innerHTML = '<p class="text-muted text-center py-3 mb-0 small">Belum ada pengeluaran bulan ini.</p>';
    }

    // Alerts
    const alertsEl = document.getElementById('alertsContainer');
    if (data.alerts && data.alerts.length > 0) {
        let html = '';
        data.alerts.forEach(a => {
            html += `<div class="alert alert-${a.tipe} alert-dismissible py-2 px-3 mb-2" role="alert">
                <small>${a.pesan}</small>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
            </div>`;
        });
        alertsEl.innerHTML = html;
    } else {
        alertsEl.innerHTML = '';
    }

    // Last updated
    if (data.updated_at) {
        document.getElementById('lastUpdated').textContent = data.updated_at;
    }
}

function buildParams() {
    const periode   = document.getElementById('filterPeriode').value;
    const cabangEl  = document.getElementById('filterCabang');
    const cabangId  = cabangEl ? cabangEl.value : '0';
    const dari      = document.getElementById('inputDari')?.value || '';
    const sampai    = document.getElementById('inputSampai')?.value || '';

    const p = new URLSearchParams({ periode });
    if (cabangId && cabangId !== '0') p.set('cabang_id', cabangId);
    if (periode === 'custom') {
        if (dari)   p.set('dari', dari);
        if (sampai) p.set('sampai', sampai);
    }
    return p;
}

function loadDashboard() {
    const params = buildParams();
    fetch(DASHBOARD_DATA_URL + '?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => updateDashboard(data))
    .catch(e => console.error('Dashboard fetch error:', e));
}

// Periode filter change
document.getElementById('filterPeriode').addEventListener('change', function () {
    const customRow = document.getElementById('customRangeRow');
    if (this.value === 'custom') {
        customRow.classList.remove('d-none');
    } else {
        customRow.classList.add('d-none');
        loadDashboard();
    }
});

// Cabang filter change
const cabangEl = document.getElementById('filterCabang');
if (cabangEl) {
    cabangEl.addEventListener('change', loadDashboard);
}

// Auto-refresh every 60 seconds
setInterval(loadDashboard, 60000);
</script>
@endpush
