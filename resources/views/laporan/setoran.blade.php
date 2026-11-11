@extends('layouts.app')
@section('title', 'Laporan Setoran ke Pusat')
@section('content')
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">Laporan Setoran ke Pusat</h4>
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
        <x-panduan-button slug="laporan-setoran" />
    </div>
</div>

<x-date-range-filter action="{{ route('laporan.setoran') }}" :dari="$dari->toDateString()" :sampai="$sampai->toDateString()" session-key="lap_setoran">
    <div class="row g-2 mb-2">
        @if(auth()->user()->canAccessAllBranches())
        <div class="col-12 col-sm-6 col-md-3">
            <label class="form-label form-label-sm mb-1">Cabang Asal</label>
            <select name="cabang_id" class="form-select form-select-sm">
                <option value="">Semua Cabang</option>
                @foreach($cabangs as $c)
                <option value="{{ $c->id }}" @selected($cabangId == $c->id)>{{ $c->nama_cabang }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <div class="col-12 col-sm-6 col-md-3">
            <label class="form-label form-label-sm mb-1">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">Semua Status</option>
                <option value="menunggu"   @selected($status==='menunggu')>Menunggu</option>
                <option value="diterima"   @selected($status==='diterima')>Diterima</option>
                <option value="ditolak"    @selected($status==='ditolak')>Ditolak</option>
                <option value="dibatalkan" @selected($status==='dibatalkan')>Dibatalkan</option>
            </select>
        </div>
    </div>
</x-date-range-filter>

{{-- Stat Cards --}}
<div class="row g-3 mb-3">
    @php $cards = [
        ['label'=>'Total Setoran','val'=>$totalJumlah,'color'=>'primary','icon'=>'bi-send'],
        ['label'=>'Total Diterima','val'=>$totalDiterima,'color'=>'success','icon'=>'bi-check-circle'],
        ['label'=>'Menunggu','val'=>$totalPending,'color'=>'warning','icon'=>'bi-clock'],
        ['label'=>'Ditolak/Batal','val'=>$totalDitolak,'color'=>'danger','icon'=>'bi-x-circle'],
    ]; @endphp
    @foreach($cards as $c)
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon bg-{{ $c['color'] }} bg-opacity-10 mb-2"><i class="bi {{ $c['icon'] }} text-{{ $c['color'] }}"></i></div>
            <div class="fw-bold text-{{ $c['color'] }}">Rp {{ number_format($c['val'],0,',','.') }}</div>
            <div class="text-muted" style="font-size:0.8rem">{{ $c['label'] }}</div>
        </div>
    </div>
    @endforeach
</div>

{{-- Grafik + Per Cabang --}}
<div class="row g-3 mb-3">
    <div class="col-12 col-lg-7">
        <div class="card">
            <div class="card-header py-2 px-3"><h6 class="mb-0">Setoran per Cabang</h6></div>
            <div class="card-body p-2">
                <div style="height:200px"><canvas id="chartSetoran"></canvas></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-5">
        <div class="card h-100">
            <div class="card-header py-2 px-3"><h6 class="mb-0">Rekapitulasi per Cabang</h6></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Cabang</th><th class="text-end">Jumlah</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                    @forelse($perCabang as $pc)
                    <tr>
                        <td class="small">{{ $pc->nama_cabang }}</td>
                        <td class="text-end small">{{ $pc->jumlah_setoran }}x</td>
                        <td class="text-end small fw-semibold">Rp {{ number_format($pc->total,0,',','.') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="text-center text-muted py-3 small">Belum ada data</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Tabel Rinci --}}
<div class="card">
    <div class="card-header py-2 px-3"><h6 class="mb-0">Detail Setoran</h6></div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Tanggal</th>
                    <th>No. Transaksi</th>
                    <th>Cabang Asal</th>
                    <th>Kas Asal</th>
                    <th class="text-end">Jumlah</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            @forelse($setorans as $s)
            @php
                $isDel    = !is_null($s->deleted_at);
                $statVal  = ($s->status_setoran instanceof \BackedEnum ? $s->status_setoran->value : ($s->status_setoran ?? '-'));
                $badgeMap = ['menunggu_diterima'=>'warning','diterima'=>'success','ditolak'=>'danger','dibatalkan'=>'secondary'];
                $badge    = $isDel ? 'secondary' : ($badgeMap[$statVal] ?? 'light');
                $label    = match($statVal) {
                    'menunggu_diterima' => 'Menunggu',
                    'diterima'          => 'Diterima',
                    'ditolak'           => 'Ditolak',
                    'dibatalkan'        => 'Dibatalkan',
                    default             => ucfirst($statVal),
                };
            @endphp
            <tr class="{{ $isDel ? 'table-secondary text-muted' : '' }}">
                <td class="small">{{ optional($s->tanggal_transaksi)->format('d/m/Y') }}</td>
                <td class="small"><code>{{ $s->nomor_transaksi }}</code></td>
                <td class="small">{{ $s->cabang?->nama_cabang ?? '-' }}</td>
                <td class="small">{{ $s->kas?->nama_kas ?? '-' }}</td>
                <td class="text-end fw-semibold small">Rp {{ number_format($s->jumlah,0,',','.') }}</td>
                <td><span class="badge bg-{{ $badge }}">{{ $label }}</span></td>
            </tr>
            @empty
            <tr><td colspan="6" class="text-center text-muted py-4">Belum ada data setoran.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($setorans->hasPages())
    <div class="card-footer py-2">{{ $setorans->links() }}</div>
    @endif
</div>
@endsection
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('chartSetoran'), {
    type: 'bar',
    data: {
        labels: @json($grafikLabels),
        datasets: [{ label: 'Total Setoran', data: @json($grafikData), backgroundColor: 'rgba(13,110,253,0.7)', borderRadius: 4 }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => 'Rp ' + new Intl.NumberFormat('id-ID').format(c.raw) } } },
        scales: { y: { ticks: { callback: v => 'Rp ' + (v>=1e6?(v/1e6).toFixed(1)+'jt':new Intl.NumberFormat('id-ID').format(v)) } }, x: { ticks: { font: { size: 10 } } } }
    }
});
</script>
@endpush
