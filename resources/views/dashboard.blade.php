@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-0" style="color:#1e293b">Dashboard</h4>
        <p class="text-muted mb-0" style="font-size:0.875rem">
            {{ now()->translatedFormat('l, d F Y') }} |
            @if(isset($activeCabang) && $activeCabang)
                <i class="bi bi-geo-alt-fill text-primary"></i> {{ $activeCabang->nama_cabang }}
            @else
                <i class="bi bi-globe text-primary"></i> Semua Cabang
            @endif
        </p>
    </div>
    <div class="d-none d-sm-block">
        <span class="badge bg-primary-subtle text-primary px-3 py-2" style="font-size:0.8rem">
            <i class="bi bi-circle-fill me-1" style="font-size:0.4rem"></i>
            {{ auth()->user()->role?->label() }}
        </span>
    </div>
</div>

<!-- ===== STAT CARDS ===== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="stat-icon bg-primary bg-opacity-10">
                    <i class="bi bi-cart3 text-primary"></i>
                </div>
                <span class="badge bg-success-subtle text-success" style="font-size:0.7rem">+12%</span>
            </div>
            <div class="fw-bold" style="font-size:1.5rem;color:#1e293b">0</div>
            <div class="text-muted" style="font-size:0.8rem">Order Hari Ini</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="stat-icon bg-success bg-opacity-10">
                    <i class="bi bi-cash-stack text-success"></i>
                </div>
                <span class="badge bg-success-subtle text-success" style="font-size:0.7rem">+8%</span>
            </div>
            <div class="fw-bold" style="font-size:1.5rem;color:#1e293b">Rp 0</div>
            <div class="text-muted" style="font-size:0.8rem">Omzet Hari Ini</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="stat-icon bg-warning bg-opacity-10">
                    <i class="bi bi-boxes text-warning"></i>
                </div>
                <span class="badge bg-danger-subtle text-danger" style="font-size:0.7rem">3 Rendah</span>
            </div>
            <div class="fw-bold" style="font-size:1.5rem;color:#1e293b">0</div>
            <div class="text-muted" style="font-size:0.8rem">Item Stok</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="stat-icon bg-info bg-opacity-10">
                    <i class="bi bi-person-badge text-info"></i>
                </div>
                <span class="badge bg-success-subtle text-success" style="font-size:0.7rem">Aktif</span>
            </div>
            <div class="fw-bold" style="font-size:1.5rem;color:#1e293b">0</div>
            <div class="text-muted" style="font-size:0.8rem">Karyawan</div>
        </div>
    </div>
</div>

<!-- ===== CHARTS ROW ===== -->
<div class="row g-3 mb-4">
    <div class="col-12 col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between py-3 px-4">
                <span><i class="bi bi-graph-up me-2 text-primary"></i>Penjualan 7 Hari Terakhir</span>
                <small class="text-muted">Bulan {{ now()->translatedFormat('F Y') }}</small>
            </div>
            <div class="card-body p-3">
                <div style="position:relative;height:220px">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-header py-3 px-4">
                <i class="bi bi-pie-chart me-2 text-primary"></i>Komposisi Penjualan
            </div>
            <div class="card-body p-3 d-flex flex-column align-items-center justify-content-center">
                <div style="position:relative;height:180px;width:180px">
                    <canvas id="compositionChart"></canvas>
                </div>
                <div class="mt-3 d-flex gap-3 flex-wrap justify-content-center">
                    <small class="d-flex align-items-center gap-1">
                        <span style="width:10px;height:10px;background:#3b82f6;border-radius:50%;display:inline-block"></span>
                        Bakso
                    </small>
                    <small class="d-flex align-items-center gap-1">
                        <span style="width:10px;height:10px;background:#22c55e;border-radius:50%;display:inline-block"></span>
                        Sosis
                    </small>
                    <small class="d-flex align-items-center gap-1">
                        <span style="width:10px;height:10px;background:#f59e0b;border-radius:50%;display:inline-block"></span>
                        Tempura
                    </small>
                    <small class="d-flex align-items-center gap-1">
                        <span style="width:10px;height:10px;background:#8b5cf6;border-radius:50%;display:inline-block"></span>
                        Jasa Giling
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===== QUICK INFO ROW ===== -->
<div class="row g-3">
    <div class="col-12 col-md-6">
        <div class="card">
            <div class="card-header py-3 px-4">
                <i class="bi bi-exclamation-triangle me-2 text-warning"></i>Stok Hampir Habis
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="px-4">Barang</th>
                                <th>Lokasi</th>
                                <th class="text-end px-4">Sisa Stok</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    Semua stok aman
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6">
        <div class="card">
            <div class="card-header py-3 px-4">
                <i class="bi bi-clock-history me-2 text-info"></i>Order Terbaru
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="px-4">No. Order</th>
                                <th class="d-none d-md-table-cell">Pelanggan</th>
                                <th class="text-end px-4">Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox me-2"></i>
                                    Belum ada order hari ini
                                </td>
                            </tr>
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
// Sales Chart
const salesCtx = document.getElementById('salesChart').getContext('2d');
new Chart(salesCtx, {
    type: 'line',
    data: {
        labels: ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'],
        datasets: [{
            label: 'Penjualan',
            data: [0, 0, 0, 0, 0, 0, 0],
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59,130,246,0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#3b82f6',
            pointRadius: 4,
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
                    callback: (val) => 'Rp ' + (val/1000) + 'k',
                    font: { size: 11 }
                }
            },
            x: {
                grid: { display: false },
                ticks: { font: { size: 11 } }
            }
        }
    }
});

// Composition Chart
const compCtx = document.getElementById('compositionChart').getContext('2d');
new Chart(compCtx, {
    type: 'doughnut',
    data: {
        labels: ['Bakso', 'Sosis', 'Tempura', 'Jasa Giling'],
        datasets: [{
            data: [40, 25, 20, 15],
            backgroundColor: ['#3b82f6', '#22c55e', '#f59e0b', '#8b5cf6'],
            borderWidth: 0,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: (ctx) => ctx.label + ': ' + ctx.raw + '%'
                }
            }
        },
        cutout: '70%',
    }
});
</script>
@endpush
