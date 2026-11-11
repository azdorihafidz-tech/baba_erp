@extends('layouts.app')

@section('title', 'Laporan Arus Kas')

@section('content')

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0" style="color:#1e293b">Laporan Arus Kas</h4>
        <p class="text-muted mb-0" style="font-size:0.875rem">Rekap kas masuk & keluar per periode</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}" class="btn btn-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i>
            <span class="d-none d-sm-inline">Export Excel</span>
        </a>
        <a href="{{ route('laporan.keuangan.laba-rugi') }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-file-earmark-bar-graph me-1"></i>
            <span class="d-none d-sm-inline">Laba Rugi</span>
        </a>
    </div>
</div>

<x-date-range-filter
    action="{{ route('laporan.keuangan.arus-kas') }}"
    :dari="$dari->toDateString()"
    :sampai="$sampai->toDateString()"
    session-key="aruskaslaporan">
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

<!-- Summary -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <div class="stat-card">
            <div class="stat-icon bg-success bg-opacity-10 mb-2"><i class="bi bi-arrow-down-circle text-success"></i></div>
            <div class="fw-bold text-success" style="font-size:1rem">Rp {{ number_format($totalMasuk, 0, ',', '.') }}</div>
            <div class="text-muted" style="font-size:0.8rem">Total Masuk</div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="stat-card">
            <div class="stat-icon bg-danger bg-opacity-10 mb-2"><i class="bi bi-arrow-up-circle text-danger"></i></div>
            <div class="fw-bold text-danger" style="font-size:1rem">Rp {{ number_format($totalKeluar, 0, ',', '.') }}</div>
            <div class="text-muted" style="font-size:0.8rem">Total Keluar</div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="stat-card">
            <div class="stat-icon {{ $saldo >= 0 ? 'bg-primary bg-opacity-10' : 'bg-danger bg-opacity-10' }} mb-2">
                <i class="bi bi-wallet {{ $saldo >= 0 ? 'text-primary' : 'text-danger' }}"></i>
            </div>
            <div class="fw-bold {{ $saldo >= 0 ? 'text-primary' : 'text-danger' }}" style="font-size:1rem">
                {{ $saldo >= 0 ? '' : '-' }}Rp {{ number_format(abs($saldo), 0, ',', '.') }}
            </div>
            <div class="text-muted" style="font-size:0.8rem">Saldo Bersih</div>
        </div>
    </div>
</div>

<!-- Tabel Transaksi -->
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between py-3 px-4">
        <span><i class="bi bi-table me-2"></i>Riwayat Transaksi Kas</span>
        <small class="text-muted">{{ $transaksis->total() }} transaksi</small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="px-4">Tanggal</th>
                        <th class="d-none d-sm-table-cell">No. Transaksi</th>
                        <th>Tipe</th>
                        <th class="d-none d-md-table-cell">Kategori</th>
                        <th>Keterangan</th>
                        <th class="text-end px-3">Jumlah</th>
                        <th class="d-none d-lg-table-cell">Cabang</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transaksis as $trx)
                    <tr>
                        <td class="px-4" style="font-size:0.875rem">{{ $trx->tanggal_transaksi?->format('d/m/Y') }}</td>
                        <td class="d-none d-sm-table-cell" style="font-size:0.85rem">{{ $trx->nomor_transaksi }}</td>
                        <td>
                            <span class="badge {{ $trx->tipe?->value === 'pemasukan' ? 'bg-success' : 'bg-danger' }}" style="font-size:0.7rem">
                                {{ $trx->tipe?->label() }}
                            </span>
                        </td>
                        <td class="d-none d-md-table-cell text-muted" style="font-size:0.85rem">
                            {{ $trx->kategori instanceof \App\Enums\KategoriTransaksi ? $trx->kategori->label() : ucfirst(str_replace('_', ' ', $trx->kategori)) }}
                        </td>
                        <td style="font-size:0.875rem">{{ $trx->keterangan }}</td>
                        <td class="text-end px-3 fw-medium {{ $trx->tipe?->value === 'pemasukan' ? 'text-success' : 'text-danger' }}" style="font-size:0.875rem">
                            {{ $trx->tipe?->value === 'pemasukan' ? '+' : '-' }}Rp {{ number_format($trx->jumlah, 0, ',', '.') }}
                        </td>
                        <td class="d-none d-lg-table-cell text-muted" style="font-size:0.85rem">{{ $trx->cabang?->nama_cabang }}</td>
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
    @if($transaksis->hasPages())
    <div class="card-footer d-flex justify-content-center py-3">
        {{ $transaksis->links() }}
    </div>
    @endif
</div>

@endsection
