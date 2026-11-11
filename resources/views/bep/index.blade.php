@extends('layouts.app')

@section('title', 'Analisis BEP')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
        <h5 class="fw-bold mb-0"><i class="bi bi-graph-up-arrow me-2 text-primary"></i>Analisis Break Even Point</h5>
        <p class="text-muted mb-0 small">Titik impas per produk dan per cabang</p>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <a href="{{ route('bep.setting') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i><span class="d-none d-sm-inline">Setting BEP Baru</span>
        </a>
        <x-panduan-button slug="bep" />
    </div>
</div>

{{-- Stats --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-calculator"></i></div>
                <div>
                    <div class="text-muted small">Periode Setting</div>
                    <div class="fw-bold fs-4">{{ $totalSettings }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-box-seam"></i></div>
                <div>
                    <div class="text-muted small">Total Produk/Jasa</div>
                    <div class="fw-bold fs-4">{{ $totalProduk }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon {{ $laporanBulanIni?->bep_tercapai ? 'bg-success' : 'bg-warning' }} bg-opacity-10 {{ $laporanBulanIni?->bep_tercapai ? 'text-success' : 'text-warning' }}">
                    <i class="bi bi-{{ $laporanBulanIni?->bep_tercapai ? 'check-circle' : 'exclamation-circle' }}"></i>
                </div>
                <div>
                    <div class="text-muted small">BEP Bulan Ini</div>
                    <div class="fw-bold">
                        @if($laporanBulanIni)
                            {{ $laporanBulanIni->bep_tercapai ? 'Tercapai ✓' : 'Belum Tercapai' }}
                            <div class="small {{ $laporanBulanIni->bep_tercapai ? 'text-success' : 'text-warning' }}">{{ number_format($laporanBulanIni->persentase_bep, 1) }}%</div>
                        @else
                            <span class="text-muted">Belum Dihitung</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Filter --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            @if($cabangs->count())
            <div class="col-12 col-sm-4 col-md-3">
                <label class="form-label small mb-1">Cabang</label>
                <select name="cabang_id" class="form-select form-select-sm">
                    <option value="">Semua Cabang</option>
                    @foreach($cabangs as $c)
                    <option value="{{ $c->id }}" @selected(request('cabang_id') == $c->id)>{{ $c->nama_cabang }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-12 col-sm-4 col-md-3">
                <label class="form-label small mb-1">Periode</label>
                <input type="month" name="periode" class="form-control form-control-sm" value="{{ request('periode') }}">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary btn-sm">Filter</button>
                <a href="{{ route('bep.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

{{-- Table --}}
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Cabang</th>
                    <th>Periode</th>
                    <th class="text-end">Total Biaya Tetap</th>
                    <th class="text-center">Jumlah Produk</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($settings as $s)
                <tr>
                    <td class="fw-semibold">{{ $s->cabang?->nama_cabang ?? '-' }}</td>
                    <td>{{ \Carbon\Carbon::parse($s->periode . '-01')->format('F Y') }}</td>
                    <td class="text-end">Rp {{ number_format($s->total_biaya_tetap, 0, ',', '.') }}</td>
                    <td class="text-center">
                        <span class="badge bg-info">{{ $s->products->count() }} produk</span>
                    </td>
                    <td>
                        <div class="d-flex gap-1 justify-content-end">
                            <a href="{{ route('bep.setting', ['cabang_id' => $s->cabang_id, 'periode' => $s->periode]) }}"
                               class="btn btn-xs btn-outline-primary" style="font-size:.75rem;padding:.2rem .5rem">
                                <i class="bi bi-gear"></i> Setting
                            </a>
                            {{-- Bug7 FIX: route bep.laporan dihapus, arahkan ke laporan.bep --}}
                            @can('laporan.bep.view')
                            <a href="{{ route('laporan.bep', ['cabang_id' => $s->cabang_id, 'periode' => $s->periode]) }}"
                               class="btn btn-xs btn-outline-success" style="font-size:.75rem;padding:.2rem .5rem">
                                <i class="bi bi-graph-up"></i> Laporan
                            </a>
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center text-muted py-4">
                    Belum ada setting BEP. <a href="{{ route('bep.setting') }}">Buat setting baru</a>.
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $settings->links() }}</div>
@endsection
