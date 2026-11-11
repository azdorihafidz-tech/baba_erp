@extends('layouts.app')
@section('title', 'Laporan Saldo Kas')
@section('content')
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">Laporan Saldo Kas</h4>
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
        <x-panduan-button slug="laporan-saldo-kas" />
    </div>
</div>

<form method="GET" action="{{ route('laporan.saldo-kas') }}" class="card mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
                <label class="form-label form-label-sm mb-1">Dari</label>
                <input type="date" name="dari" class="form-control form-control-sm" value="{{ $dari->toDateString() }}">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label form-label-sm mb-1">Sampai</label>
                <input type="date" name="sampai" class="form-control form-control-sm" value="{{ $sampai->toDateString() }}">
            </div>
            @if(auth()->user()->canAccessAllBranches())
            <div class="col-12 col-md-3">
                <label class="form-label form-label-sm mb-1">Cabang</label>
                <select name="cabang_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Semua Cabang</option>
                    @foreach($cabangs as $c)
                    <option value="{{ $c->id }}" @selected($cabangId == $c->id)>{{ $c->nama_cabang }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-12 col-md-3">
                <label class="form-label form-label-sm mb-1">Kas</label>
                <select name="kas_id" class="form-select form-select-sm">
                    <option value="">Semua Kas</option>
                    @foreach($kasOptions as $k)
                    <option value="{{ $k->id }}" @selected($kasId == $k->id)>
                        {{ $k->nama_kas }}{{ $k->nama_cabang ? ' ('.$k->nama_cabang.')' : '' }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-primary btn-sm">Tampilkan</button></div>
        </div>
    </div>
</form>

@if($kasReports->isEmpty())
<div class="alert alert-info">Belum ada kas aktif untuk cabang ini.</div>
@else

{{-- Grafik saldo running (untuk kas pertama) --}}
@if(!empty($grafikLabels))
<div class="card mb-3">
    <div class="card-header py-2 px-3"><h6 class="mb-0">Grafik Saldo Kas{{ count($kasReports) === 1 ? ' — '.$kasReports[0]['kas']->nama_kas : '' }}</h6></div>
    <div class="card-body p-2"><div style="height:180px"><canvas id="chartSaldo"></canvas></div></div>
</div>
@endif

{{-- Kartu per Kas --}}
@foreach($kasReports as $r)
<div class="card mb-3">
    <div class="card-header py-2 px-3 d-flex justify-content-between align-items-center flex-wrap gap-1">
        <div>
            <h6 class="mb-0 fw-bold">{{ $r['kas']->nama_kas }}</h6>
            <small class="text-muted">{{ ucfirst($r['kas']->tipe_kas) }} • {{ $r['kas']->cabang?->nama_cabang ?? '' }}</small>
        </div>
        <div class="d-flex gap-3 text-end">
            <div><small class="text-muted d-block">Saldo Awal</small><span class="fw-semibold">Rp {{ number_format($r['saldo_awal'],0,',','.') }}</span></div>
            <div><small class="text-muted d-block">Pemasukan</small><span class="fw-semibold text-success">Rp {{ number_format($r['total_pemasukan'],0,',','.') }}</span></div>
            <div><small class="text-muted d-block">Pengeluaran</small><span class="fw-semibold text-danger">Rp {{ number_format($r['total_pengeluaran'],0,',','.') }}</span></div>
            <div><small class="text-muted d-block">Saldo Akhir</small><span class="fw-bold {{ $r['saldo_akhir']>=0?'text-primary':'text-danger' }}">Rp {{ number_format($r['saldo_akhir'],0,',','.') }}</span></div>
        </div>
    </div>
    @if(!empty($r['mutasi']))
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Tanggal</th>
                    <th>No. Transaksi</th>
                    <th>Keterangan</th>
                    <th class="text-end text-danger">Keluar</th>
                    <th class="text-end text-success">Masuk</th>
                    <th class="text-end">Saldo</th>
                </tr>
            </thead>
            <tbody>
            <tr class="table-light">
                <td colspan="5" class="text-end fw-semibold small text-muted">Saldo Awal Periode</td>
                <td class="text-end fw-bold small">Rp {{ number_format($r['saldo_awal'],0,',','.') }}</td>
            </tr>
            @foreach($r['mutasi'] as $m)
            <tr>
                <td class="small">{{ Carbon\Carbon::parse($m['tanggal'])->format('d/m/Y') }}</td>
                <td class="small"><code>{{ $m['nomor'] }}</code></td>
                <td class="small">{{ Str::limit($m['keterangan'], 45) }}</td>
                <td class="text-end small text-danger">{{ $m['debit'] ? 'Rp '.number_format($m['debit'],0,',','.') : '' }}</td>
                <td class="text-end small text-success">{{ $m['kredit'] ? 'Rp '.number_format($m['kredit'],0,',','.') : '' }}</td>
                <td class="text-end fw-semibold small">Rp {{ number_format($m['saldo_running'],0,',','.') }}</td>
            </tr>
            @endforeach
            <tr class="table-light">
                <td colspan="5" class="text-end fw-semibold small text-muted">Saldo Akhir Periode</td>
                <td class="text-end fw-bold small {{ $r['saldo_akhir']>=0?'text-primary':'text-danger' }}">Rp {{ number_format($r['saldo_akhir'],0,',','.') }}</td>
            </tr>
            </tbody>
        </table>
    </div>
    @else
    <div class="card-body text-muted text-center py-3 small">Tidak ada mutasi dalam periode ini.</div>
    @endif
</div>
@endforeach
@endif
@endsection
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
@if(!empty($grafikLabels))
new Chart(document.getElementById('chartSaldo'), {
    type: 'line',
    data: {
        labels: @json($grafikLabels),
        datasets: [{ label: 'Saldo', data: @json($grafikSaldo), borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,0.08)', fill: true, tension: 0.3, pointRadius: 3 }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => 'Rp ' + new Intl.NumberFormat('id-ID').format(c.raw) } } },
        scales: { y: { ticks: { callback: v => 'Rp '+(v>=1e6?(v/1e6).toFixed(1)+'jt':new Intl.NumberFormat('id-ID').format(v)) } }, x: { ticks: { font: { size: 10 } } } }
    }
});
@endif
</script>
@endpush
