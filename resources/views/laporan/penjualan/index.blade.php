@extends('layouts.app')

@section('title', 'Laporan Penjualan')

@section('content')

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0" style="color:#1e293b">Laporan Penjualan</h4>
        <p class="text-muted mb-0" style="font-size:0.875rem">Rekap transaksi penjualan & jasa giling</p>
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
        <button onclick="window.print()" class="btn btn-secondary btn-sm d-none d-sm-inline-flex">
            <i class="bi bi-printer me-1"></i>Print
        </button>
        <x-panduan-button slug="laporan-penjualan" />
    </div>
</div>

<x-date-range-filter
    action="{{ route('laporan.penjualan') }}"
    :dari="$dari->toDateString()"
    :sampai="$sampai->toDateString()"
    session-key="laporanpenjualan">
    <div class="row g-2 mb-2">
        <x-search-box placeholder="Nomor order / pelanggan / cabang..." col="col-12 col-sm-6 col-md-3" label="Cari" />
        @if(auth()->user()->canAccessAllBranches())
        <div class="col-12 col-sm-6 col-md-3">
            <label class="form-label form-label-sm mb-1">Cabang</label>
            <select name="cabang_id" class="form-select form-select-sm">
                <option value="">Semua Cabang</option>
                @foreach($cabangs as $cab)
                <option value="{{ $cab->id }}" {{ request('cabang_id') == $cab->id ? 'selected' : '' }}>{{ $cab->nama_cabang }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <div class="col-12 col-sm-6 col-md-3">
            <label class="form-label form-label-sm mb-1">Tipe Order</label>
            <select name="tipe_order" class="form-select form-select-sm">
                <option value="">Semua Tipe</option>
                <option value="jasa_giling" {{ request('tipe_order') === 'jasa_giling' ? 'selected' : '' }}>Jasa Giling</option>
                <option value="produk_jadi" {{ request('tipe_order') === 'produk_jadi' ? 'selected' : '' }}>Produk Jadi</option>
            </select>
        </div>
    </div>
</x-date-range-filter>

{{-- Sprint 4.32 — Section "Analisis Periode" (ikut filter cabang+tanggal halaman) --}}
@isset($analisisPenjualan)
    <x-analisis-penjualan
        :data="$analisisPenjualan"
        :periodeAktif="$analisisPenjualanMeta['periode_aktif']"
        :labelPeriode="$analisisPenjualanMeta['label_periode']"
        :presetList="$analisisPenjualanMeta['preset_list']"
        :cabangId="$analisisPenjualanMeta['cabang_id']"
        :formAction="route('laporan.penjualan')"
    />
@endisset

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <div class="stat-card">
            <div class="stat-icon bg-success bg-opacity-10 mb-2">
                <i class="bi bi-cash-stack text-success"></i>
            </div>
            <div class="fw-bold" style="font-size:1.1rem;color:#1e293b">Rp {{ number_format($totalOmzet, 0, ',', '.') }}</div>
            <div class="text-muted" style="font-size:0.8rem">Total Omzet</div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="stat-card">
            <div class="stat-icon bg-primary bg-opacity-10 mb-2">
                <i class="bi bi-receipt text-primary"></i>
            </div>
            <div class="fw-bold" style="font-size:1.5rem;color:#1e293b">{{ number_format($totalTransaksi, 0, ',', '.') }}</div>
            <div class="text-muted" style="font-size:0.8rem">Total Transaksi</div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="stat-card">
            <div class="stat-icon bg-info bg-opacity-10 mb-2">
                <i class="bi bi-graph-up text-info"></i>
            </div>
            <div class="fw-bold" style="font-size:1.1rem;color:#1e293b">Rp {{ number_format($rataRata, 0, ',', '.') }}</div>
            <div class="text-muted" style="font-size:0.8rem">Rata-rata per Transaksi</div>
        </div>
    </div>
</div>

<!-- Grafik -->
<div class="row g-3 mb-4">
    <div class="col-12 col-lg-8">
        <div class="card">
            <div class="card-header py-3 px-4">
                <i class="bi bi-graph-up me-2 text-primary"></i>Tren Penjualan
            </div>
            <div class="card-body p-3">
                <div style="position:relative;height:250px">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card">
            <div class="card-header py-3 px-4">
                <i class="bi bi-star me-2 text-warning"></i>Produk Terlaris
            </div>
            <div class="card-body p-0">
                @if($produkTerlaris->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th class="px-3">#</th>
                                <th>Produk</th>
                                <th class="text-end px-3">Qty</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($produkTerlaris->take(8) as $idx => $prod)
                            <tr>
                                <td class="px-3 text-muted" style="font-size:0.8rem">{{ $idx + 1 }}</td>
                                <td style="font-size:0.85rem">{{ $prod->nama_item ?? '-' }}</td>
                                <td class="text-end px-3" style="font-size:0.85rem">{{ number_format($prod->total_qty, 0, ',', '.') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center text-muted py-4">Tidak ada data</div>
                @endif
            </div>
        </div>
    </div>
</div>

@can('laporan.ranking_kasir.view')
<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header py-3 px-4">
                <i class="bi bi-person-badge me-2 text-primary"></i>Ranking Kasir
            </div>
            <div class="card-body p-0">
                @if($rankingKasir->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="px-3">#</th>
                                <th>Kasir</th>
                                <th class="d-none d-md-table-cell">Cabang</th>
                                <th class="text-end">Jumlah Order</th>
                                <th class="text-end px-3">Total Omzet</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rankingKasir as $idx => $k)
                            <tr>
                                <td class="px-3">
                                    @if($idx === 0)
                                        <i class="bi bi-trophy-fill text-warning"></i>
                                    @elseif($idx === 1)
                                        <i class="bi bi-trophy-fill text-secondary"></i>
                                    @elseif($idx === 2)
                                        <i class="bi bi-trophy-fill" style="color:#cd7f32"></i>
                                    @else
                                        <span class="text-muted small">{{ $idx + 1 }}</span>
                                    @endif
                                </td>
                                <td class="small fw-semibold">{{ $k->nama_kasir }}</td>
                                <td class="small d-none d-md-table-cell">{{ $k->nama_cabang ?? '-' }}</td>
                                <td class="text-end small">{{ number_format($k->jumlah_order, 0, ',', '.') }}</td>
                                <td class="text-end px-3 small fw-semibold">Rp {{ number_format($k->total_omzet, 0, ',', '.') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center text-muted py-4">Tidak ada data kasir di periode ini</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endcan

<!-- Tabel Data -->
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between py-3 px-4">
        <span><i class="bi bi-table me-2"></i>Daftar Transaksi</span>
        <small class="text-muted">{{ $orders->total() }} transaksi</small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="px-4">No. Order</th>
                        <th>Tanggal</th>
                        <th class="d-none d-md-table-cell">Cabang</th>
                        <th class="d-none d-sm-table-cell">Pelanggan</th>
                        <th class="d-none d-lg-table-cell">Tipe</th>
                        <th class="text-end px-3">Total</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                    <tr>
                        <td class="px-4">
                            <a href="{{ route('penjualan.show', $order) }}" class="text-decoration-none fw-medium" style="font-size:0.875rem">
                                {{ $order->nomor_order }}
                            </a>
                        </td>
                        <td style="font-size:0.875rem">{{ $order->tanggal_order?->format('d/m/Y') }}</td>
                        <td class="d-none d-md-table-cell" style="font-size:0.875rem">{{ $order->cabang?->nama_cabang ?? '-' }}</td>
                        <td class="d-none d-sm-table-cell" style="font-size:0.875rem">
                            {{ $order->nama_pelanggan ?? $order->pelanggan?->nama ?? 'Umum' }}
                        </td>
                        <td class="d-none d-lg-table-cell">
                            <span class="badge {{ $order->tipe_order?->value === 'jasa_giling' ? 'bg-info-subtle text-info' : 'bg-primary-subtle text-primary' }}" style="font-size:0.7rem">
                                {{ $order->tipe_order?->label() }}
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
                        <td colspan="7" class="text-center text-muted py-4">
                            <i class="bi bi-inbox me-2"></i>Tidak ada data
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($orders->hasPages())
    <div class="card-footer d-flex justify-content-center py-3">
        {{ $orders->links() }}
    </div>
    @endif
</div>

@endsection

@push('scripts')
<script>
const trendCtx = document.getElementById('trendChart').getContext('2d');
new Chart(trendCtx, {
    type: 'bar',
    data: {
        labels: @json($grafikLabels),
        datasets: [{
            label: 'Omzet',
            data: @json($grafikData),
            backgroundColor: 'rgba(59,130,246,0.7)',
            borderColor: '#3b82f6',
            borderWidth: 1,
            borderRadius: 4,
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
