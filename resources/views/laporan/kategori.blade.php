@extends('layouts.app')
@section('title', 'Laporan Per Kategori')
@section('content')
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">Laporan Per Kategori</h4>
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
        <x-panduan-button slug="laporan-kategori" />
    </div>
</div>

<x-date-range-filter action="{{ route('laporan.kategori') }}" :dari="$dari->toDateString()" :sampai="$sampai->toDateString()" session-key="lap_kategori">
    <div class="row g-2 mb-2">
        @if(auth()->user()->canAccessAllBranches())
        <div class="col-12 col-sm-6 col-md-3">
            <label class="form-label form-label-sm mb-1">Cabang</label>
            <select name="cabang_id" class="form-select form-select-sm">
                <option value="">Semua Cabang</option>
                @foreach($cabangs as $c)
                <option value="{{ $c->id }}" @selected($cabangId == $c->id)>{{ $c->nama_cabang }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <div class="col-12 col-sm-6 col-md-2">
            <label class="form-label form-label-sm mb-1">Tipe</label>
            <select name="tipe" class="form-select form-select-sm">
                <option value="">Semua</option>
                <option value="pemasukan"   @selected($tipe==='pemasukan')>Pemasukan</option>
                <option value="pengeluaran" @selected($tipe==='pengeluaran')>Pengeluaran</option>
            </select>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <label class="form-label form-label-sm mb-1">Parent Kategori</label>
            <select name="parent_id" class="form-select form-select-sm">
                <option value="">Semua Kategori</option>
                @foreach($parentKategoris as $pk)
                <option value="{{ $pk->id }}" @selected($parentId == $pk->id)>{{ $pk->nama }}</option>
                @endforeach
            </select>
        </div>
    </div>
</x-date-range-filter>

<div class="row g-3 mb-3">
    <div class="col-6">
        <div class="stat-card">
            <div class="stat-icon bg-success bg-opacity-10 mb-2"><i class="bi bi-arrow-down-circle text-success"></i></div>
            <div class="fw-bold text-success">Rp {{ number_format($totalPemasukan,0,',','.') }}</div>
            <div class="text-muted" style="font-size:0.8rem">Total Pemasukan</div>
        </div>
    </div>
    <div class="col-6">
        <div class="stat-card">
            <div class="stat-icon bg-danger bg-opacity-10 mb-2"><i class="bi bi-arrow-up-circle text-danger"></i></div>
            <div class="fw-bold text-danger">Rp {{ number_format($totalPengeluaran,0,',','.') }}</div>
            <div class="text-muted" style="font-size:0.8rem">Total Pengeluaran</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    {{-- Pie Chart --}}
    <div class="col-12 col-md-5">
        <div class="card h-100">
            <div class="card-header py-2 px-3"><h6 class="mb-0">Distribusi Pengeluaran</h6></div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <div style="height:220px;width:100%;max-width:300px">
                    <canvas id="chartPie"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabel per kategori --}}
    <div class="col-12 col-md-7">
        <div class="card h-100">
            <div class="card-header py-2 px-3"><h6 class="mb-0">Detail per Kategori</h6></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Parent</th><th>Kategori</th><th>Tipe</th><th class="text-end">Transaksi</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                    @forelse($rows as $r)
                    <tr>
                        <td class="small text-muted">{{ $r->parent_nama ?? '-' }}</td>
                        <td class="small fw-semibold">{{ $r->kategori_nama ?? 'Lainnya' }}</td>
                        <td><span class="badge bg-{{ $r->tipe === 'pemasukan' ? 'success' : 'danger' }} bg-opacity-75">{{ ucfirst($r->tipe) }}</span></td>
                        <td class="text-end small">{{ $r->jumlah_transaksi }}</td>
                        <td class="text-end fw-semibold small">Rp {{ number_format($r->total,0,',','.') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center py-4 text-muted">Belum ada data.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const pieColors = ['#0d6efd','#dc3545','#198754','#fd7e14','#6f42c1','#20c997','#ffc107','#0dcaf0'];
new Chart(document.getElementById('chartPie'), {
    type: 'pie',
    data: {
        labels: @json($pieLabels),
        datasets: [{ data: @json($pieValues), backgroundColor: pieColors.slice(0, {{ count($pieLabels) }}), borderWidth: 1 }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { font: { size: 10 }, boxWidth: 10 } },
            tooltip: { callbacks: { label: c => c.label + ': Rp ' + new Intl.NumberFormat('id-ID').format(c.raw) + ' (' + c.dataset.data.reduce((a,b)=>a+b,0) > 0 ? Math.round(c.raw / c.dataset.data.reduce((a,b)=>a+b,0) * 100) : 0 + '%)' } }
        }
    }
});
</script>
@endpush
