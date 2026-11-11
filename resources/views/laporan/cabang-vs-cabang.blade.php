@extends('layouts.app')
@section('title', 'Laporan Cabang vs Cabang')
@section('content')
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-bar-chart-steps text-primary me-2"></i>Perbandingan Antar Cabang</h4>
        <small class="text-muted">{{ $dari->format('d/m/Y') }} — {{ $sampai->format('d/m/Y') }}</small>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}" class="btn btn-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i><span class="d-none d-sm-inline">Export Excel</span>
        </a>
        <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}" class="btn btn-danger btn-sm">
            <i class="bi bi-file-earmark-pdf me-1"></i><span class="d-none d-sm-inline">Export PDF</span>
        </a>
        <button onclick="window.print()" class="btn btn-secondary btn-sm d-none d-sm-inline-flex">
            <i class="bi bi-printer me-1"></i>Print
        </button>
        <x-panduan-button slug="laporan-cabang" />
    </div>
</div>

<form method="GET" action="{{ route('laporan.cabang-vs-cabang') }}" class="mb-3">
    <x-date-range-filter action="{{ route('laporan.cabang-vs-cabang') }}" :dari="$dari->toDateString()" :sampai="$sampai->toDateString()" session-key="lap_cabang">
        <div class="row g-2 mb-2">
            <div class="col-auto d-flex align-items-end">
                <div class="form-check form-switch mb-1">
                    <input class="form-check-input" type="checkbox" name="show_ho" value="1" id="chkHO" @checked($showHO) onchange="this.form.submit()">
                    <label class="form-check-label small" for="chkHO">Tampilkan Head Office di ranking</label>
                </div>
            </div>
        </div>
    </x-date-range-filter>
</form>

{{-- Summary --}}
<div class="row g-3 mb-3">
    <div class="col-4">
        <div class="stat-card">
            <div class="stat-icon bg-success bg-opacity-10 mb-2"><i class="bi bi-arrow-down-circle text-success"></i></div>
            <div class="fw-bold text-success" style="font-size:1rem">Rp {{ number_format($totalPemasukan,0,',','.') }}</div>
            <div class="text-muted" style="font-size:0.8rem">Total Pemasukan</div>
        </div>
    </div>
    <div class="col-4">
        <div class="stat-card">
            <div class="stat-icon bg-danger bg-opacity-10 mb-2"><i class="bi bi-arrow-up-circle text-danger"></i></div>
            <div class="fw-bold text-danger" style="font-size:1rem">Rp {{ number_format($totalPengeluaran,0,',','.') }}</div>
            <div class="text-muted" style="font-size:0.8rem">Total Pengeluaran</div>
        </div>
    </div>
    <div class="col-4">
        <div class="stat-card {{ $totalNet >= 0 ? '' : 'border-danger' }}">
            <div class="stat-icon {{ $totalNet>=0?'bg-primary':'bg-danger' }} bg-opacity-10 mb-2">
                <i class="bi bi-wallet2 {{ $totalNet>=0?'text-primary':'text-danger' }}"></i>
            </div>
            <div class="fw-bold {{ $totalNet>=0?'text-primary':'text-danger' }}" style="font-size:1rem">
                Rp {{ number_format(abs($totalNet),0,',','.') }}
            </div>
            <div class="text-muted" style="font-size:0.8rem">Net {{ $totalNet>=0?'Laba':'Rugi' }}</div>
        </div>
    </div>
</div>

{{-- Charts --}}
<div class="row g-3 mb-3">
    <div class="col-12 col-lg-7">
        <div class="card">
            <div class="card-header py-2 px-3"><h6 class="mb-0">Pemasukan vs Pengeluaran per Cabang</h6></div>
            <div class="card-body p-2"><div style="height:220px"><canvas id="chartBar"></canvas></div></div>
        </div>
    </div>
    <div class="col-12 col-lg-5">
        <div class="card">
            <div class="card-header py-2 px-3"><h6 class="mb-0">Kontribusi Revenue</h6></div>
            <div class="card-body p-2 d-flex align-items-center justify-content-center">
                <div style="height:220px;width:100%;max-width:260px"><canvas id="chartPie"></canvas></div>
            </div>
        </div>
    </div>
</div>

{{-- HO Summary (always shown if HO exists) --}}
@if($hoSummary)
<div class="card mb-3 border-primary border-2">
    <div class="card-header py-2 px-3 bg-primary bg-opacity-10">
        <h6 class="mb-0 text-primary"><i class="bi bi-building-fill-gear me-2"></i>Ringkasan Head Office: {{ $hoSummary['nama'] }}</h6>
    </div>
    <div class="row g-0">
        <div class="col-6 col-md-3 border-end p-3 text-center">
            <div class="text-muted small">Setoran Masuk</div>
            <div class="fw-bold text-success">Rp {{ number_format($hoSummary['setoran_masuk'],0,',','.') }}</div>
        </div>
        <div class="col-6 col-md-3 border-end p-3 text-center">
            <div class="text-muted small">Pengeluaran HO</div>
            <div class="fw-bold text-danger">Rp {{ number_format($hoSummary['pengeluaran'],0,',','.') }}</div>
        </div>
        <div class="col-6 col-md-3 border-end p-3 text-center">
            <div class="text-muted small">Pemasukan HO</div>
            <div class="fw-bold text-success">Rp {{ number_format($hoSummary['pemasukan'],0,',','.') }}</div>
        </div>
        <div class="col-6 col-md-3 p-3 text-center">
            <div class="text-muted small">Net HO</div>
            <div class="fw-bold {{ $hoSummary['net']>=0?'text-primary':'text-danger' }}">Rp {{ number_format(abs($hoSummary['net']),0,',','.') }}</div>
        </div>
    </div>
</div>
@endif

{{-- Ranking Table --}}
<div class="card">
    <div class="card-header py-2 px-3"><h6 class="mb-0">Ranking Performa Cabang</h6></div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th width="40">#</th>
                    <th>Cabang</th>
                    <th class="text-end">Pemasukan</th>
                    <th class="text-end">Pengeluaran</th>
                    <th class="text-end d-none d-md-table-cell">Setoran Keluar</th>
                    <th class="text-end d-none d-md-table-cell">Setoran Masuk</th>
                    <th class="text-end">Net</th>
                </tr>
            </thead>
            <tbody>
            @forelse($ranking as $i => $r)
            @php $isBest = $i === 0 && $r['pemasukan'] > 0; $isWorst = $i === count($ranking)-1 && count($ranking) > 1; @endphp
            <tr class="{{ $isBest ? 'table-success bg-opacity-25' : ($isWorst ? 'table-warning bg-opacity-25' : '') }}">
                <td class="text-center">
                    @if($isBest)<i class="bi bi-trophy-fill text-warning"></i>
                    @elseif($isWorst)<i class="bi bi-arrow-down-circle text-danger"></i>
                    @else {{ $i+1 }}
                    @endif
                </td>
                <td class="fw-semibold small">
                    {{ $r['nama_cabang'] }}
                    @if(($r['tipe'] ?? '') === 'head_office')
                        <span class="badge bg-primary bg-opacity-75 ms-1" style="font-size:0.65rem">HO</span>
                    @endif
                </td>
                <td class="text-end text-success small">Rp {{ number_format($r['pemasukan'],0,',','.') }}</td>
                <td class="text-end text-danger small">Rp {{ number_format($r['pengeluaran'],0,',','.') }}</td>
                <td class="text-end small d-none d-md-table-cell">Rp {{ number_format($r['setoran_keluar'],0,',','.') }}</td>
                <td class="text-end small d-none d-md-table-cell">Rp {{ number_format($r['setoran_masuk'],0,',','.') }}</td>
                <td class="text-end fw-bold small {{ $r['net']>=0?'text-primary':'text-danger' }}">
                    {{ $r['net']>=0?'':'-' }}Rp {{ number_format(abs($r['net']),0,',','.') }}
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="text-center text-muted py-4">Belum ada data.</td></tr>
            @endforelse
            </tbody>
            <tfoot class="table-light fw-bold">
                <tr>
                    <td colspan="2">TOTAL</td>
                    <td class="text-end text-success">Rp {{ number_format($totalPemasukan,0,',','.') }}</td>
                    <td class="text-end text-danger">Rp {{ number_format($totalPengeluaran,0,',','.') }}</td>
                    <td class="d-none d-md-table-cell"></td>
                    <td class="d-none d-md-table-cell"></td>
                    <td class="text-end {{ $totalNet>=0?'text-primary':'text-danger' }}">Rp {{ number_format(abs($totalNet),0,',','.') }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const pieColors = ['#0d6efd','#198754','#ffc107','#dc3545','#6f42c1','#20c997','#fd7e14','#0dcaf0'];
new Chart(document.getElementById('chartBar'), {
    type: 'bar',
    data: {
        labels: @json($grafikLabels),
        datasets: [
            { label: 'Pemasukan',   data: @json($grafikPemasukan),   backgroundColor: 'rgba(25,135,84,0.75)', borderRadius: 4 },
            { label: 'Pengeluaran', data: @json($grafikPengeluaran), backgroundColor: 'rgba(220,53,69,0.75)',  borderRadius: 4 }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { position: 'bottom', labels: { font: { size: 10 }, boxWidth: 10 } }, tooltip: { callbacks: { label: c => ' Rp ' + new Intl.NumberFormat('id-ID').format(c.raw) } } },
        scales: { y: { ticks: { callback: v => 'Rp '+(v>=1e6?(v/1e6).toFixed(1)+'jt':new Intl.NumberFormat('id-ID').format(v)) } }, x: { ticks: { font: { size: 10 } } } }
    }
});
new Chart(document.getElementById('chartPie'), {
    type: 'pie',
    data: {
        labels: @json($pieLabels),
        datasets: [{ data: @json($pieValues), backgroundColor: pieColors.slice(0, {{ count($pieLabels) }}), borderWidth: 1 }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { font: { size: 10 }, boxWidth: 10 } }, tooltip: { callbacks: { label: c => c.label + ': Rp ' + new Intl.NumberFormat('id-ID').format(c.raw) } } }
    }
});
</script>
@endpush
