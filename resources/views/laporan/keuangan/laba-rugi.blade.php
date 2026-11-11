@extends('layouts.app')

@section('title', 'Laporan Laba Rugi')

@section('content')

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0" style="color:#1e293b">Laporan Laba Rugi</h4>
        <p class="text-muted mb-0" style="font-size:0.875rem">{{ $dari->format('d/m/Y') }} — {{ $sampai->format('d/m/Y') }}</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}" class="btn btn-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i>
            <span class="d-none d-sm-inline">Export Excel</span>
        </a>
        <a href="{{ route('laporan.keuangan.arus-kas') }}" class="btn btn-outline-info btn-sm">
            <i class="bi bi-arrow-left-right me-1"></i>
            <span class="d-none d-sm-inline">Arus Kas</span>
        </a>
        <button onclick="window.print()" class="btn btn-secondary btn-sm d-none d-sm-inline-flex">
            <i class="bi bi-printer me-1"></i>Print
        </button>
        <x-panduan-button slug="laporan-keuangan" />
    </div>
</div>

<x-date-range-filter
    action="{{ route('laporan.keuangan.laba-rugi') }}"
    :dari="$dari->toDateString()"
    :sampai="$sampai->toDateString()"
    session-key="labarugi">
    @if(auth()->user()->canAccessAllBranches())
    <div class="row g-2 mb-2">
        <div class="col-12 col-sm-6 col-md-3">
            <label class="form-label form-label-sm mb-1">Cabang</label>
            <select name="cabang_id" class="form-select form-select-sm">
                <option value="">Semua Cabang</option>
                @foreach($cabangs as $cab)
                <option value="{{ $cab->id }}" {{ $cabangId == $cab->id ? 'selected' : '' }}>{{ $cab->nama_cabang }}</option>
                @endforeach
            </select>
        </div>
    </div>
    @endif
</x-date-range-filter>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-4">
        <div class="stat-card">
            <div class="stat-icon bg-success bg-opacity-10 mb-2"><i class="bi bi-arrow-down-circle text-success"></i></div>
            <div class="fw-bold text-success" style="font-size:1.1rem">Rp {{ number_format($totalPemasukan, 0, ',', '.') }}</div>
            <div class="text-muted" style="font-size:0.8rem">Total Pemasukan</div>
        </div>
    </div>
    <div class="col-12 col-sm-4">
        <div class="stat-card">
            <div class="stat-icon bg-danger bg-opacity-10 mb-2"><i class="bi bi-arrow-up-circle text-danger"></i></div>
            <div class="fw-bold text-danger" style="font-size:1.1rem">Rp {{ number_format($totalPengeluaran, 0, ',', '.') }}</div>
            <div class="text-muted" style="font-size:0.8rem">Total Pengeluaran</div>
        </div>
    </div>
    <div class="col-12 col-sm-4">
        <div class="stat-card {{ $labaRugi >= 0 ? '' : 'border-danger' }}">
            <div class="stat-icon {{ $labaRugi >= 0 ? 'bg-primary bg-opacity-10' : 'bg-danger bg-opacity-10' }} mb-2">
                <i class="bi {{ $labaRugi >= 0 ? 'bi-graph-up-arrow text-primary' : 'bi-graph-down-arrow text-danger' }}"></i>
            </div>
            <div class="fw-bold {{ $labaRugi >= 0 ? 'text-primary' : 'text-danger' }}" style="font-size:1.1rem">
                {{ $labaRugi >= 0 ? '' : '-' }}Rp {{ number_format(abs($labaRugi), 0, ',', '.') }}
            </div>
            <div class="text-muted" style="font-size:0.8rem">{{ $labaRugi >= 0 ? 'Laba' : 'Rugi' }} Bersih</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Grafik -->
    <div class="col-12 col-lg-7">
        <div class="card">
            <div class="card-header py-3 px-4">
                <i class="bi bi-bar-chart me-2 text-primary"></i>Tren Keuangan 6 Bulan
            </div>
            <div class="card-body p-3">
                <div style="position:relative;height:250px">
                    <canvas id="keuanganChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Detail Laba Rugi -->
    <div class="col-12 col-lg-5">
        <div class="card">
            <div class="card-header py-3 px-4">
                <i class="bi bi-file-earmark-text me-2"></i>Ringkasan L/R
            </div>
            <div class="card-body p-0">
                <div class="p-3 bg-success-subtle">
                    <div class="fw-semibold text-success mb-2" style="font-size:0.85rem">PEMASUKAN</div>
                    @foreach($pemasukan as $item)
                    <div class="d-flex justify-content-between" style="font-size:0.85rem">
                        <span class="text-muted">{{ $item->kategori instanceof \App\Enums\KategoriTransaksi ? $item->kategori->label() : ucfirst(str_replace('_', ' ', $item->kategori)) }}</span>
                        <span>Rp {{ number_format($item->total, 0, ',', '.') }}</span>
                    </div>
                    @endforeach
                    <div class="d-flex justify-content-between fw-bold border-top mt-2 pt-2">
                        <span>Total Pemasukan</span>
                        <span class="text-success">Rp {{ number_format($totalPemasukan, 0, ',', '.') }}</span>
                    </div>
                </div>
                <div class="p-3 bg-danger-subtle">
                    <div class="fw-semibold text-danger mb-2" style="font-size:0.85rem">PENGELUARAN</div>
                    @foreach($pengeluaran as $item)
                    <div class="d-flex justify-content-between" style="font-size:0.85rem">
                        <span class="text-muted">{{ $item->kategori instanceof \App\Enums\KategoriTransaksi ? $item->kategori->label() : ucfirst(str_replace('_', ' ', $item->kategori)) }}</span>
                        <span>Rp {{ number_format($item->total, 0, ',', '.') }}</span>
                    </div>
                    @endforeach
                    <div class="d-flex justify-content-between fw-bold border-top mt-2 pt-2">
                        <span>Total Pengeluaran</span>
                        <span class="text-danger">Rp {{ number_format($totalPengeluaran, 0, ',', '.') }}</span>
                    </div>
                </div>
                <div class="p-3 {{ $labaRugi >= 0 ? 'bg-primary-subtle' : 'bg-danger-subtle' }}">
                    <div class="d-flex justify-content-between fw-bold fs-6">
                        <span>{{ $labaRugi >= 0 ? 'LABA' : 'RUGI' }} BERSIH</span>
                        <span class="{{ $labaRugi >= 0 ? 'text-primary' : 'text-danger' }}">
                            {{ $labaRugi >= 0 ? '' : '-' }}Rp {{ number_format(abs($labaRugi), 0, ',', '.') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
const keuanganCtx = document.getElementById('keuanganChart').getContext('2d');
new Chart(keuanganCtx, {
    type: 'bar',
    data: {
        labels: @json($grafikLabels),
        datasets: [
            {
                label: 'Pemasukan',
                data: @json($grafikPemasukan),
                backgroundColor: 'rgba(34,197,94,0.7)',
                borderRadius: 4,
            },
            {
                label: 'Pengeluaran',
                data: @json($grafikPengeluaran),
                backgroundColor: 'rgba(239,68,68,0.7)',
                borderRadius: 4,
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: true, labels: { font: { size: 11 } } },
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
</script>
@endpush
