@extends('layouts.app')
@section('title', 'Analisa Jam Ramai')
@section('content')

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">⏰ Analisa Jam Ramai</h4>
        <small class="text-muted">{{ $mulai->format('d/m/Y') }} — {{ $akhir->format('d/m/Y') }} — {{ $cabangNama }}</small>
    </div>
    <div class="d-flex gap-2">
        @can('laporan.jam_ramai.export')
        <a href="{{ route('laporan.jam-ramai.export-excel', request()->query()) }}" class="btn btn-outline-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i><span class="d-none d-sm-inline">Export Excel</span>
        </a>
        <a href="{{ route('laporan.jam-ramai.export-pdf', request()->query()) }}" class="btn btn-success btn-sm">
            <i class="bi bi-file-earmark-pdf me-1"></i><span class="d-none d-sm-inline">Export PDF</span>
        </a>
        @endcan
        <x-panduan-button slug="laporan-jam-ramai" />
    </div>
</div>

<div class="alert alert-secondary py-2 px-3 small mb-3">
    <i class="bi bi-info-circle me-1"></i>
    Laporan ini murni dihitung dari <strong>jam order dibuat</strong> (traffic pelanggan real) — <strong>transaksi keuangan (Kas Masuk/Keluar) sengaja TIDAK ikut dihitung</strong> karena banyak diinput belakangan atau auto-generated (bukan mencerminkan jam ramai pelanggan sesungguhnya).
</div>

@if($analisa['jumlah_hari_operasional'] > 0 && $analisa['jumlah_hari_operasional'] <= 14)
<div class="alert alert-warning py-2 px-3 small mb-3">
    <i class="bi bi-exclamation-triangle me-1"></i>
    Data pada periode ini baru mencakup <strong>{{ $analisa['jumlah_hari_operasional'] }} hari operasional</strong> — pola jam ramai di bawah masih indikatif, belum solid secara statistik. Perbesar rentang tanggal atau kumpulkan lebih banyak data sebelum mengambil keputusan besar (mis. perubahan jadwal staff permanen).
</div>
@endif

{{-- Filter --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('laporan.jam-ramai.index') }}" class="row g-2 align-items-end">
            <div class="col-6 col-sm-4 col-md-3">
                <label class="form-label form-label-sm mb-1">Dari</label>
                <input type="date" name="mulai" class="form-control form-control-sm" value="{{ $mulai->toDateString() }}">
            </div>
            <div class="col-6 col-sm-4 col-md-3">
                <label class="form-label form-label-sm mb-1">Sampai</label>
                <input type="date" name="akhir" class="form-control form-control-sm" value="{{ $akhir->toDateString() }}">
            </div>
            @if(auth()->user()->canAccessAllBranches())
            <div class="col-6 col-sm-4 col-md-3">
                <label class="form-label form-label-sm mb-1">Cabang</label>
                <select name="cabang_id" class="form-select form-select-sm">
                    <option value="">Semua Cabang (Konsolidasi)</option>
                    @foreach($cabangs as $c)
                    <option value="{{ $c->id }}" @selected($cabangId == $c->id)>{{ $c->nama_cabang }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-6 col-sm-4 col-md-3">
                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search me-1"></i>Generate</button>
            </div>
        </form>
    </div>
</div>

@if($analisa['total_transaksi'] === 0)
<div class="alert alert-light border text-center py-4">
    <i class="bi bi-inbox fs-3 d-block mb-2 text-muted"></i>
    Tidak ada order pada periode ini.
</div>
@else

{{-- Kartu Ringkasan --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="stat-card border-2 border-success">
            <div class="fw-bold text-success">{{ $analisa['jam_puncak']['label'] ?? '-' }}</div>
            <div class="text-muted" style="font-size:0.8rem">Jam Puncak ({{ $analisa['jam_puncak']['jumlah_transaksi'] ?? 0 }} transaksi)</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="fw-bold">{{ $analisa['jam_sepi']['label'] ?? '-' }}</div>
            <div class="text-muted" style="font-size:0.8rem">Jam Relatif Sepi ({{ $analisa['jam_sepi']['jumlah_transaksi'] ?? 0 }} transaksi)</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="fw-bold">{{ $analisa['total_transaksi'] }}</div>
            <div class="text-muted" style="font-size:0.8rem">Total Order</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="fw-bold">{{ $analisa['jumlah_hari_operasional'] }} hari</div>
            <div class="text-muted" style="font-size:0.8rem">Hari Operasional ({{ $analisa['jumlah_jam_aktif'] }} jam berbeda)</div>
        </div>
    </div>
</div>

{{-- Chart --}}
<div class="card mb-3">
    <div class="card-header py-2 px-3"><h6 class="mb-0">Distribusi Order per Jam</h6></div>
    <div class="card-body">
        <div style="position:relative;height:300px">
            <canvas id="chartJamRamai"></canvas>
        </div>
    </div>
</div>

{{-- Rekomendasi --}}
@if(count($analisa['rekomendasi']) > 0)
<div class="card mb-3">
    <div class="card-header py-2 px-3"><h6 class="mb-0"><i class="bi bi-lightbulb me-1"></i>Rekomendasi</h6></div>
    <div class="card-body">
        <ul class="mb-0 small">
            @foreach($analisa['rekomendasi'] as $r)
            <li class="mb-1">{{ $r }}</li>
            @endforeach
        </ul>
    </div>
</div>
@endif

{{-- Tabel Detail per Jam --}}
<div class="card">
    <div class="card-header py-2 px-3"><h6 class="mb-0">Detail per Jam</h6></div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <th>Jam</th>
                    <th class="text-end">Jumlah Order</th>
                    <th class="text-end">Total Nominal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($analisa['per_jam'] as $j)
                <tr class="{{ $analisa['jam_puncak'] && $j['jam'] === $analisa['jam_puncak']['jam'] ? 'table-success' : '' }}">
                    <td class="small">
                        {{ $j['label'] }}
                        @if($analisa['jam_puncak'] && $j['jam'] === $analisa['jam_puncak']['jam'])
                        <span class="badge bg-success ms-1">Puncak</span>
                        @endif
                    </td>
                    <td class="text-end small">{{ $j['jumlah_transaksi'] }}</td>
                    <td class="text-end small">Rp {{ number_format($j['total_nominal'], 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="table-light fw-bold">
                    <td>Total</td>
                    <td class="text-end">{{ $analisa['total_transaksi'] }}</td>
                    <td class="text-end">Rp {{ number_format($analisa['total_nominal'], 0, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endif

@endsection

@push('scripts')
<script>
(function() {
    var d = @json($analisa['per_jam']);
    var jamPuncak = @json($analisa['jam_puncak']['jam'] ?? null);
    if (d.length > 0) {
        new Chart(document.getElementById('chartJamRamai'), {
            type: 'bar',
            data: {
                labels: d.map(function(j) { return j.label; }),
                datasets: [{
                    label: 'Jumlah Order',
                    data: d.map(function(j) { return j.jumlah_transaksi; }),
                    backgroundColor: d.map(function(j) { return (jamPuncak !== null && j.jam === jamPuncak) ? '#22c55e' : '#94a3b8'; })
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });
    }
})();
</script>
@endpush
