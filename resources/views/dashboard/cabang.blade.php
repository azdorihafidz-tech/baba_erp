@extends('layouts.app')

@section('title', 'Dashboard Cabang')

@section('content')

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0" style="color:#1e293b">Dashboard Cabang</h4>
        <p class="text-muted mb-0" style="font-size:0.875rem">
            {{ now()->translatedFormat('l, d F Y') }}
            @if($cabang)
                &nbsp;|&nbsp;<i class="bi bi-geo-alt-fill text-primary"></i> {{ $cabang->nama_cabang }}
            @endif
        </p>
    </div>
    <div>
        <a href="{{ route('penjualan.pos') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-cart-plus me-1"></i>
            <span class="d-none d-sm-inline">Buat Order</span>
        </a>
    </div>
</div>

{{-- Closing / Tutup Buku — pengingat hari belum closing --}}
<x-closing-pengingat :cabang-id="$cabang?->id" />

{{-- ===== Sprint 4.32 — Analisis Penjualan (section baru, filter periode terpisah) ===== --}}
@isset($analisisPenjualan)
    <x-analisis-penjualan
        :data="$analisisPenjualan"
        :periodeAktif="$analisisPenjualanMeta['periode_aktif']"
        :labelPeriode="$analisisPenjualanMeta['label_periode']"
        :presetList="$analisisPenjualanMeta['preset_list']"
        :cabangId="$analisisPenjualanMeta['cabang_id']"
    />
@endisset

<!-- ===== STAT CARDS ===== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="stat-icon bg-primary bg-opacity-10">
                    <i class="bi bi-cart3 text-primary"></i>
                </div>
                <span class="badge bg-info-subtle text-info" style="font-size:0.7rem">Hari ini</span>
            </div>
            <div class="fw-bold" style="font-size:1.5rem;color:#1e293b">{{ $jumlahOrderHariIni }}</div>
            <div class="text-muted" style="font-size:0.8rem">Order Hari Ini</div>
            <div class="mt-1" style="font-size:0.75rem;color:#64748b">
                <i class="bi bi-shop-window me-1"></i>{{ $orderPerTipeTransaksi['dine_in'] ?? 0 }} Dine-in ·
                <i class="bi bi-bag-check me-1"></i>{{ $orderPerTipeTransaksi['takeaway'] ?? 0 }} Takeaway ·
                <i class="bi bi-snow me-1"></i>{{ $orderPerTipeTransaksi['frozen'] ?? 0 }} Frozen
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="stat-icon bg-success bg-opacity-10">
                    <i class="bi bi-cash-stack text-success"></i>
                </div>
                <span class="badge bg-success-subtle text-success" style="font-size:0.7rem">Hari ini</span>
            </div>
            <div class="fw-bold" style="font-size:1.3rem;color:#1e293b">Rp {{ number_format($omzetHariIni, 0, ',', '.') }}</div>
            <div class="text-muted" style="font-size:0.8rem">Omzet Hari Ini</div>
            <div class="mt-1" style="font-size:0.75rem;color:#64748b">
                Bulan ini: Rp {{ number_format($omzetBulanIni, 0, ',', '.') }}
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="stat-icon bg-warning bg-opacity-10">
                    <i class="bi bi-boxes text-warning"></i>
                </div>
                @if($stokRendah->count() > 0)
                    <span class="badge bg-danger-subtle text-danger" style="font-size:0.7rem">{{ $stokRendah->count() }} rendah</span>
                @else
                    <span class="badge bg-success-subtle text-success" style="font-size:0.7rem">Aman</span>
                @endif
            </div>
            <div class="fw-bold" style="font-size:1.5rem;color:#1e293b">{{ $stokRendah->count() }}</div>
            <div class="text-muted" style="font-size:0.8rem">Stok Perlu Restock</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="stat-icon bg-info bg-opacity-10">
                    <i class="bi bi-people text-info"></i>
                </div>
                <span class="badge bg-success-subtle text-success" style="font-size:0.7rem">Aktif</span>
            </div>
            <div class="fw-bold" style="font-size:1.5rem;color:#1e293b">{{ $jumlahKaryawan }}</div>
            <div class="text-muted" style="font-size:0.8rem">Karyawan Aktif</div>
        </div>
    </div>
</div>

<!-- ===== CHART ROW ===== -->
<div class="row g-3 mb-4">
    <div class="col-12 col-lg-8">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between py-3 px-4">
                <span><i class="bi bi-graph-up me-2 text-primary"></i>Omzet 7 Hari Terakhir</span>
                <small class="text-muted">{{ now()->translatedFormat('F Y') }}</small>
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
                <i class="bi bi-exclamation-triangle me-2 text-warning"></i>Stok Hampir Habis
            </div>
            <div class="card-body p-0">
                @if($stokRendah->count() > 0)
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="px-3">Barang</th>
                                <th class="text-end px-3">Sisa</th>
                                <th class="text-end px-3">Min</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($stokRendah as $stok)
                            <tr>
                                <td class="px-3" style="font-size:0.85rem">{{ $stok->item?->nama_item ?? '-' }}</td>
                                <td class="text-end px-3">
                                    <span class="badge bg-danger-subtle text-danger">
                                        {{ number_format($stok->qty, 0, ',', '.') }}
                                    </span>
                                </td>
                                <td class="text-end px-3 text-muted" style="font-size:0.8rem">
                                    {{ number_format($stok->qty_minimum, 0, ',', '.') }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center text-muted py-5">
                    <i class="bi bi-check-circle text-success" style="font-size:2rem"></i>
                    <p class="mt-2 mb-0">Semua stok aman</p>
                </div>
                @endif
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

<!-- ===== ORDER TERBARU ===== -->
<div class="row g-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between py-3 px-4">
                <span><i class="bi bi-clock-history me-2 text-info"></i>Order Terbaru</span>
                <a href="{{ route('penjualan.index') }}" class="btn btn-sm btn-outline-primary" style="font-size:0.75rem">
                    Lihat Semua
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="px-4">No. Order</th>
                                <th class="d-none d-md-table-cell">Pelanggan</th>
                                <th class="d-none d-sm-table-cell">Tipe</th>
                                <th class="text-end px-3">Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($orderTerbaru as $order)
                            <tr>
                                <td class="px-4">
                                    <a href="{{ route('penjualan.show', $order) }}" class="text-decoration-none fw-medium">
                                        {{ $order->nomor_order }}
                                    </a>
                                    <div class="text-muted d-sm-none" style="font-size:0.75rem">
                                        {{ $order->nama_pelanggan ?? ($order->pelanggan?->nama ?? 'Umum') }}
                                    </div>
                                </td>
                                <td class="d-none d-md-table-cell" style="font-size:0.875rem">
                                    {{ $order->nama_pelanggan ?? ($order->pelanggan?->nama ?? 'Umum') }}
                                </td>
                                <td class="d-none d-sm-table-cell">
                                    <span class="badge {{ $order->tipe_order?->value === 'jasa_giling' ? 'bg-purple-subtle text-purple bg-info-subtle text-info' : 'bg-primary-subtle text-primary' }}" style="font-size:0.7rem">
                                        {{ $order->tipe_order?->label() ?? '-' }}
                                    </span>
                                </td>
                                <td class="text-end px-3 fw-medium" style="font-size:0.875rem">
                                    Rp {{ number_format($order->total_bayar, 0, ',', '.') }}
                                </td>
                                <td>
                                    <span class="badge {{ $order->status?->badgeClass() }}" style="font-size:0.7rem">
                                        {{ $order->status?->label() }}
                                    </span>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox me-2"></i>Belum ada order hari ini
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
const salesCtx = document.getElementById('salesChart').getContext('2d');
new Chart(salesCtx, {
    type: 'line',
    data: {
        labels: @json($grafik7HariLabels),
        datasets: [{
            label: 'Omzet',
            data: @json($grafik7HariData),
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59,130,246,0.08)',
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
                    callback: (val) => 'Rp ' + (val >= 1000000 ? (val/1000000).toFixed(1)+'jt' : (val/1000)+'rb'),
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
</script>
@endpush
