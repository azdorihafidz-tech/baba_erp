@extends('layouts.app')

@section('title', 'Laporan BEP')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
        <h5 class="fw-bold mb-0"><i class="bi bi-graph-up-arrow me-2 text-primary"></i>Laporan BEP</h5>
        <p class="text-muted mb-0 small">
            {{ $cabang?->nama_cabang ?? 'Semua Cabang' }} — {{ $periode ? \Carbon\Carbon::parse($periode.'-01')->format('F Y') : '-' }}
        </p>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-printer me-1"></i>Cetak
        </button>
        @if($setting)
        <a href="{{ route('bep.setting', ['cabang_id' => $cabangId, 'periode' => $periode]) }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-gear me-1"></i>Setting
        </a>
        @endif
        <a href="{{ route('bep.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
    </div>
</div>

{{-- Filter --}}
<div class="card mb-4">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            @if($cabangs->count())
            <div class="col-12 col-sm-4 col-md-3">
                <label class="form-label small mb-1">Cabang</label>
                <select name="cabang_id" class="form-select form-select-sm">
                    <option value="">Pilih Cabang</option>
                    @foreach($cabangs as $c)
                    <option value="{{ $c->id }}" @selected($cabangId == $c->id)>{{ $c->nama_cabang }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-12 col-sm-4 col-md-3">
                <label class="form-label small mb-1">Periode</label>
                <input type="month" name="periode" class="form-control form-control-sm" value="{{ $periode }}">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary btn-sm">Tampilkan</button>
            </div>
        </form>
    </div>
</div>

@if($setting && $laporan)
{{-- Summary Cards --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card text-center py-3">
            <div class="text-muted small">Total Biaya Tetap</div>
            <div class="fw-bold text-danger">Rp {{ number_format($laporan->total_biaya_tetap, 0, ',', '.') }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center py-3">
            <div class="text-muted small">Total Pendapatan</div>
            <div class="fw-bold text-success">Rp {{ number_format($laporan->total_pendapatan, 0, ',', '.') }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center py-3">
            <div class="text-muted small">% Pencapaian BEP</div>
            <div class="fw-bold fs-4 {{ $laporan->bep_tercapai ? 'text-success' : 'text-warning' }}">
                {{ number_format($laporan->persentase_bep, 1) }}%
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center py-3 {{ $laporan->bep_tercapai ? 'border-success' : 'border-warning' }}">
            <div class="text-muted small">Status BEP</div>
            <div class="fw-bold {{ $laporan->bep_tercapai ? 'text-success' : 'text-warning' }}">
                @if($laporan->bep_tercapai)
                <i class="bi bi-check-circle me-1"></i>Tercapai
                @else
                <i class="bi bi-exclamation-circle me-1"></i>Belum Tercapai
                @endif
            </div>
            <small class="{{ $laporan->selisih_dari_bep >= 0 ? 'text-success' : 'text-danger' }}">
                {{ $laporan->selisih_dari_bep >= 0 ? 'Surplus' : 'Defisit' }}:
                Rp {{ number_format(abs($laporan->selisih_dari_bep), 0, ',', '.') }}
            </small>
        </div>
    </div>
</div>

{{-- Progress BEP --}}
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between mb-1">
            <span class="fw-semibold">Progress Pencapaian BEP</span>
            <span class="fw-bold">{{ number_format(min(100, $laporan->persentase_bep), 1) }}%</span>
        </div>
        <div class="progress" style="height:16px;border-radius:8px">
            <div class="progress-bar {{ $laporan->bep_tercapai ? 'bg-success' : 'bg-warning' }}"
                style="width:{{ min(100, $laporan->persentase_bep) }}%;transition:width 1s ease">
                {{ number_format(min(100, $laporan->persentase_bep), 0) }}%
            </div>
        </div>
    </div>
</div>

{{-- BEP Chart --}}
@if(!empty($chartData))
<div class="card mb-4">
    <div class="card-header">Grafik BEP — {{ $setting->products->first()->nama_produk }}</div>
    <div class="card-body">
        <div style="position:relative;height:300px">
            <canvas id="bepChart"></canvas>
        </div>
        <div class="text-center mt-2 text-muted small">
            <span class="me-3"><span style="color:#ef4444">■</span> Biaya Tetap</span>
            <span class="me-3"><span style="color:#f59e0b">■</span> Total Biaya</span>
            <span class="me-3"><span style="color:#22c55e">■</span> Pendapatan</span>
        </div>
    </div>
</div>
@endif

{{-- Tabel Per Produk --}}
<div class="card">
    <div class="card-header">BEP Per Produk / Jasa</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Produk / Jasa</th>
                    <th>Tipe</th>
                    <th class="text-end">Harga Jual</th>
                    <th class="text-end">Biaya Variabel</th>
                    <th class="text-end">Margin Kontribusi</th>
                    <th class="text-end">BEP Unit</th>
                    <th class="text-end">BEP Rupiah</th>
                    <th class="text-end">Target Unit</th>
                </tr>
            </thead>
            <tbody>
                @foreach($setting->products as $p)
                <tr>
                    <td class="fw-semibold">{{ $p->nama_produk }}</td>
                    <td><span class="badge bg-{{ $p->tipe === 'produk' ? 'primary' : 'info' }}">{{ $p->tipe === 'produk' ? 'Produk' : 'Jasa Giling' }}</span></td>
                    <td class="text-end">Rp {{ number_format($p->harga_jual_per_unit, 0, ',', '.') }}</td>
                    <td class="text-end">Rp {{ number_format($p->biaya_variabel_per_unit, 0, ',', '.') }}</td>
                    <td class="text-end text-success fw-semibold">Rp {{ number_format($p->margin_kontribusi ?? 0, 0, ',', '.') }}</td>
                    <td class="text-end fw-bold">{{ number_format($p->bep_unit ?? 0, 1, ',', '.') }}</td>
                    <td class="text-end text-primary fw-bold">Rp {{ number_format($p->bep_rupiah ?? 0, 0, ',', '.') }}</td>
                    <td class="text-end text-muted">{{ number_format($p->target_penjualan_unit ?? 0, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@elseif(!$setting)
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle me-1"></i>
    Belum ada setting BEP untuk cabang dan periode yang dipilih.
    <a href="{{ route('bep.setting', ['cabang_id' => $cabangId, 'periode' => $periode]) }}" class="alert-link">Buat setting BEP</a>
</div>
@endif

@push('scripts')
@if(!empty($chartData))
<script>
const chartData = @json($chartData);
const ctx = document.getElementById('bepChart');
if (ctx) {
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartData.labels,
            datasets: [
                {
                    label: 'Biaya Tetap',
                    data: chartData.fixedCost,
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239,68,68,0.05)',
                    borderWidth: 2,
                    pointRadius: 0,
                    fill: false,
                    borderDash: [5, 5],
                },
                {
                    label: 'Total Biaya',
                    data: chartData.totalCost,
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245,158,11,0.05)',
                    borderWidth: 2,
                    pointRadius: 0,
                    fill: false,
                },
                {
                    label: 'Pendapatan',
                    data: chartData.revenue,
                    borderColor: '#22c55e',
                    backgroundColor: 'rgba(34,197,94,0.05)',
                    borderWidth: 2,
                    pointRadius: 0,
                    fill: false,
                },
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (ctx) => ctx.dataset.label + ': Rp ' + new Intl.NumberFormat('id-ID').format(ctx.parsed.y)
                    }
                }
            },
            scales: {
                x: { title: { display: true, text: 'Unit Terjual' } },
                y: {
                    title: { display: true, text: 'Rupiah (Rp)' },
                    ticks: {
                        callback: (v) => 'Rp ' + new Intl.NumberFormat('id-ID').format(v)
                    }
                }
            }
        }
    });
}
</script>
@endif
@endpush
@endsection
