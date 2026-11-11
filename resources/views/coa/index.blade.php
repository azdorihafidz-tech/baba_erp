@extends('layouts.app')
@section('title', 'Chart of Accounts')

@push('styles')
<style>
@media print {
    #sidebar, #topbar, .no-print { display: none !important; }
    #main-content { margin: 0 !important; }
}
.coa-level-1 { font-weight: 700; background: #f1f5f9; }
.coa-level-2 { font-weight: 600; }
.coa-level-3 { padding-left: 1.5rem !important; }
</style>
@endpush

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2 no-print">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-diagram-2 me-2 text-primary"></i>Chart of Accounts</h4>
        <small class="text-muted">Daftar kode akun standar SAK ETAP — {{ $akunList->count() }} akun</small>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>Print
        </button>
        <x-panduan-button slug="chart-of-accounts-overview" />
    </div>
</div>

<div class="card mb-3 no-print">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-12 col-md-5">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari kode atau nama akun..." value="{{ request('search') }}">
            </div>
            <div class="col-8 col-md-4">
                <select name="tipe" class="form-select form-select-sm">
                    <option value="">-- Semua Tipe --</option>
                    @foreach($tipeList as $val => $label)
                    <option value="{{ $val }}" @selected(request('tipe') === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-4 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <a href="{{ route('coa.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:90px">Kode</th>
                    <th>Nama Akun</th>
                    <th class="d-none d-md-table-cell">Tipe</th>
                    <th class="d-none d-md-table-cell text-center" style="width:100px">Saldo Normal</th>
                    <th class="d-none d-lg-table-cell">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($akunList as $akun)
                <tr class="coa-level-{{ $akun->level }}">
                    <td><code>{{ $akun->kode }}</code></td>
                    <td>{{ $akun->nama }}</td>
                    <td class="d-none d-md-table-cell">
                        <span class="badge bg-light text-dark border">{{ $tipeList[$akun->tipe] ?? $akun->tipe }}</span>
                    </td>
                    <td class="d-none d-md-table-cell text-center">
                        <span class="badge {{ $akun->saldo_normal === 'debet' ? 'bg-primary-subtle text-primary' : 'bg-success-subtle text-success' }}">
                            {{ ucfirst($akun->saldo_normal) }}
                        </span>
                    </td>
                    <td class="d-none d-lg-table-cell text-muted small">{{ $akun->keterangan ?? '—' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">Tidak ada akun yang cocok dengan filter.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
