@extends('layouts.app')
@section('title', 'Laporan Audit Bukti')
@section('content')
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">Audit Kepatuhan Bukti Pengeluaran</h4>
        <small class="text-muted">Pengeluaran &gt; Rp {{ number_format($threshold,0,',','.') }} — {{ $dari->format('d/m/Y') }} – {{ $sampai->format('d/m/Y') }}</small>
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
        <x-panduan-button slug="laporan-audit-bukti" />
    </div>
</div>

<form method="GET" action="{{ route('laporan.audit-bukti') }}" class="card mb-3">
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
                <select name="cabang_id" class="form-select form-select-sm">
                    <option value="">Semua Cabang</option>
                    @foreach($cabangs as $c)
                    <option value="{{ $c->id }}" @selected($cabangId == $c->id)>{{ $c->nama_cabang }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-6 col-md-2">
                <label class="form-label form-label-sm mb-1">Min. Jumlah (Rp)</label>
                <input type="number" name="threshold" class="form-control form-control-sm" value="{{ $threshold }}" min="0" step="50000">
            </div>
            <div class="col-6 col-md-2 d-flex align-items-end gap-2">
                <div class="form-check mb-1">
                    <input class="form-check-input" type="checkbox" name="tanpa_bukti" value="1" id="chkTanpaBukti" @checked($hanyaTanpaBukti)>
                    <label class="form-check-label small" for="chkTanpaBukti">Hanya belum upload</label>
                </div>
            </div>
            <div class="col-auto"><button class="btn btn-primary btn-sm">Filter</button></div>
        </div>
    </div>
</form>

{{-- Stats --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-primary bg-opacity-10 mb-2"><i class="bi bi-list-check text-primary"></i></div>
            <div class="fw-bold">{{ $totalAtas }}</div>
            <div class="text-muted" style="font-size:0.8rem">Total Transaksi</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-success bg-opacity-10 mb-2"><i class="bi bi-check-circle text-success"></i></div>
            <div class="fw-bold text-success">{{ $sudahUpload }} <span class="fw-normal text-muted small">({{ $pctUpload }}%)</span></div>
            <div class="text-muted" style="font-size:0.8rem">Sudah Upload Bukti</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card {{ $belumUpload > 0 ? 'border-danger' : '' }}">
            <div class="stat-icon bg-danger bg-opacity-10 mb-2"><i class="bi bi-exclamation-circle text-danger"></i></div>
            <div class="fw-bold text-danger">{{ $belumUpload }}</div>
            <div class="text-muted" style="font-size:0.8rem">Belum Upload Bukti</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-warning bg-opacity-10 mb-2"><i class="bi bi-cash-stack text-warning"></i></div>
            <div class="fw-bold">Rp {{ number_format($totalNominal,0,',','.') }}</div>
            <div class="text-muted" style="font-size:0.8rem">Total Nominal</div>
        </div>
    </div>
</div>

{{-- Tabel --}}
<div class="card">
    <div class="card-header py-2 px-3 d-flex justify-content-between">
        <h6 class="mb-0">Detail Pengeluaran</h6>
        @if($belumUpload > 0)
        <span class="badge bg-danger">{{ $belumUpload }} belum upload bukti</span>
        @endif
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Tanggal</th>
                    <th>No. Transaksi</th>
                    <th>Keterangan</th>
                    <th>Kategori</th>
                    <th>Cabang</th>
                    <th class="text-end">Jumlah</th>
                    <th class="text-center">Bukti</th>
                </tr>
            </thead>
            <tbody>
            @forelse($transaksis as $t)
            @php $hasBukti = !empty($t->bukti_path); @endphp
            <tr class="{{ !$hasBukti ? 'table-danger bg-opacity-25' : '' }}">
                <td class="small">{{ optional($t->tanggal_transaksi)->format('d/m/Y') }}</td>
                <td class="small"><code>{{ $t->nomor_transaksi }}</code></td>
                <td class="small">{{ Str::limit($t->keterangan, 40) }}</td>
                <td class="small">{{ $t->kategoriDinamis?->nama ?? '-' }}</td>
                <td class="small">{{ $t->cabang?->nama_cabang ?? '-' }}</td>
                <td class="text-end fw-semibold small">Rp {{ number_format($t->jumlah,0,',','.') }}</td>
                <td class="text-center">
                    @if($hasBukti)
                        <a href="/img/{{ $t->bukti_path }}" target="_blank" class="btn btn-xs btn-outline-success py-0 px-1">
                            <i class="bi bi-file-image"></i>
                        </a>
                    @else
                        <span class="badge bg-danger"><i class="bi bi-exclamation-triangle-fill"></i> Belum</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada pengeluaran yang cocok.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($transaksis->hasPages())
    <div class="card-footer py-2">{{ $transaksis->links() }}</div>
    @endif
</div>
@endsection
