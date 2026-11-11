@extends('layouts.app')
@section('title', 'Buku Besar')
@section('content')

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">📒 Buku Besar (General Ledger)</h4>
        <small class="text-muted">{{ $mulai->format('d/m/Y') }} — {{ $akhir->format('d/m/Y') }} — {{ $cabangNama }}</small>
    </div>
    <div class="d-flex gap-2">
        @can('laporan.buku_besar.export')
        @if($ledger)
        <button type="button" id="btnExportBukuBesarExcel" class="btn btn-outline-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i><span class="d-none d-sm-inline">Export Excel</span>
        </button>
        <button type="button" id="btnExportBukuBesar" class="btn btn-success btn-sm">
            <i class="bi bi-file-earmark-pdf me-1"></i><span class="d-none d-sm-inline">Export PDF</span>
        </button>
        @endif
        @endcan
        <x-panduan-button slug="buku-besar" />
    </div>
</div>

<div class="alert alert-secondary py-2 px-3 small mb-3">
    <i class="bi bi-info-circle me-1"></i>
    Scope terbatas ke akun <strong>Pendapatan/HPP/Beban</strong> (akun Aset/Kewajiban/Modal punya sumber data terpisah — lihat menu <a href="{{ route('laporan.neraca.index') }}">Neraca</a>). <strong>Saldo baris pertama periode dianggap Rp 0</strong> (bukan saldo kumulatif riil sejak akun ini ada) — konsisten dengan keterbatasan yang sama di Neraca untuk data historis.
</div>

{{-- Filter --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('laporan.buku-besar.index') }}" class="row g-2 align-items-end">
            <div class="col-12 col-sm-6 col-md-4">
                <label class="form-label form-label-sm mb-1">Akun COA</label>
                <select name="kode_akun" class="form-select form-select-sm">
                    @foreach($akunTersedia as $akun)
                    <option value="{{ $akun->kode }}" @selected($kodeAkun == $akun->kode)>{{ $akun->kode }} — {{ $akun->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-sm-3 col-md-2">
                <label class="form-label form-label-sm mb-1">Dari</label>
                <input type="date" name="mulai" class="form-control form-control-sm" value="{{ $mulai->toDateString() }}">
            </div>
            <div class="col-6 col-sm-3 col-md-2">
                <label class="form-label form-label-sm mb-1">Sampai</label>
                <input type="date" name="akhir" class="form-control form-control-sm" value="{{ $akhir->toDateString() }}">
            </div>
            @if(auth()->user()->canAccessAllBranches())
            <div class="col-6 col-sm-4 col-md-2">
                <label class="form-label form-label-sm mb-1">Cabang</label>
                <select name="cabang_id" class="form-select form-select-sm">
                    <option value="">Semua Cabang</option>
                    @foreach($cabangs as $c)
                    <option value="{{ $c->id }}" @selected($cabangId == $c->id)>{{ $c->nama_cabang }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-6 col-sm-4 col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search me-1"></i>Tampilkan</button>
            </div>
        </form>
    </div>
</div>

@if($ledger)
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="fw-bold small">{{ $ledger['akun']->kode }}</div>
            <div class="text-muted" style="font-size:0.75rem">{{ $ledger['akun']->nama }}</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="fw-bold">Rp {{ number_format($ledger['total_debit'], 0, ',', '.') }}</div>
            <div class="text-muted" style="font-size:0.8rem">Total Debit</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="fw-bold">Rp {{ number_format($ledger['total_kredit'], 0, ',', '.') }}</div>
            <div class="text-muted" style="font-size:0.8rem">Total Kredit</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="fw-bold">Rp {{ number_format($ledger['saldo_akhir'], 0, ',', '.') }}</div>
            <div class="text-muted" style="font-size:0.8rem">Saldo Akhir ({{ $ledger['akun']->saldo_normal === 'debet' ? 'Debet' : 'Kredit' }})</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header py-2 px-3"><h6 class="mb-0">Detail Transaksi</h6></div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Tanggal</th>
                    <th>Keterangan</th>
                    <th class="text-end">Debit</th>
                    <th class="text-end">Kredit</th>
                    <th class="text-end">Saldo</th>
                </tr>
            </thead>
            <tbody>
            @forelse($ledger['baris'] as $b)
                <tr>
                    <td class="small">{{ \Carbon\Carbon::parse($b['tanggal'])->format('d/m/Y') }}</td>
                    <td class="small">{{ $b['keterangan'] ?? '-' }}</td>
                    <td class="text-end small">{{ $b['debit'] > 0 ? number_format($b['debit'], 0, ',', '.') : '-' }}</td>
                    <td class="text-end small">{{ $b['kredit'] > 0 ? number_format($b['kredit'], 0, ',', '.') : '-' }}</td>
                    <td class="text-end small fw-semibold">{{ number_format($b['saldo'], 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">Tidak ada transaksi untuk akun ini di periode yang dipilih.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@else
<div class="alert alert-warning">Belum ada akun COA yang tersedia untuk Buku Besar.</div>
@endif

@endsection

@push('scripts')
<script>
(function() {
    var btnExport = document.getElementById('btnExportBukuBesar');
    if (btnExport) {
        btnExport.addEventListener('click', function() {
            var params = new URLSearchParams(window.location.search);
            if (!params.get('kode_akun')) {
                params.set('kode_akun', @json($kodeAkun));
            }
            window.location.href = '{{ route('laporan.buku-besar.export') }}?' + params.toString();
        });
    }

    var btnExportExcel = document.getElementById('btnExportBukuBesarExcel');
    if (btnExportExcel) {
        btnExportExcel.addEventListener('click', function() {
            var params = new URLSearchParams(window.location.search);
            if (!params.get('kode_akun')) {
                params.set('kode_akun', @json($kodeAkun));
            }
            window.location.href = '{{ route('laporan.buku-besar.export-excel') }}?' + params.toString();
        });
    }
})();
</script>
@endpush
