@extends('layouts.app')
@section('title', 'Laporan Laba Rugi Formal')
@section('content')

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">📊 Laporan Laba Rugi Formal (SAK ETAP)</h4>
        <small class="text-muted">{{ $mulai->format('d/m/Y') }} — {{ $akhir->format('d/m/Y') }} — {{ $cabangNama }}</small>
    </div>
    <div class="d-flex gap-2">
        @can('laporan.laba_rugi_formal.export')
        <button type="button" id="btnExportLabaRugiFormalExcel" class="btn btn-outline-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i><span class="d-none d-sm-inline">Export Excel</span>
        </button>
        <button type="button" id="btnExportLabaRugiFormal" class="btn btn-success btn-sm">
            <i class="bi bi-file-earmark-pdf me-1"></i><span class="d-none d-sm-inline">Export PDF</span>
        </button>
        @endcan
        <x-panduan-button slug="laporan-laba-rugi-formal" />
    </div>
</div>

<div class="alert alert-secondary py-2 px-3 small mb-3">
    <i class="bi bi-info-circle me-1"></i>
    Laporan ini dikelompokkan per <strong>Chart of Accounts (COA)</strong> mengikuti struktur SAK ETAP — berbeda dari menu <a href="{{ route('laporan.laba-rugi.index') }}">Laporan Laba Rugi</a> existing (analisis gross profit per item/kategori/jenis olahan/order). Semua akun leaf ditampilkan termasuk yang belum pernah dipakai (Rp 0) supaya struktur tetap lengkap.
</div>

{{-- Filter --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('laporan.laba-rugi-formal.index') }}" class="row g-2 align-items-end">
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
                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search me-1"></i>Tampilkan</button>
            </div>
        </form>
    </div>
</div>

{{-- Ringkasan --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="fw-bold">Rp {{ number_format($labaRugi['pendapatan']['total'], 0, ',', '.') }}</div>
            <div class="text-muted" style="font-size:0.8rem">Total Pendapatan</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="fw-bold">Rp {{ number_format($labaRugi['laba_kotor'], 0, ',', '.') }}</div>
            <div class="text-muted" style="font-size:0.8rem">Laba Kotor</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="fw-bold">Rp {{ number_format($labaRugi['laba_usaha'], 0, ',', '.') }}</div>
            <div class="text-muted" style="font-size:0.8rem">Laba Usaha</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card border-2 {{ $labaRugi['laba_bersih_setelah_pajak'] >= 0 ? 'border-success' : 'border-danger' }}">
            <div class="fw-bold {{ $labaRugi['laba_bersih_setelah_pajak'] >= 0 ? 'text-success' : 'text-danger' }}" style="font-size:1.15rem">Rp {{ number_format($labaRugi['laba_bersih_setelah_pajak'], 0, ',', '.') }}</div>
            <div class="text-muted" style="font-size:0.8rem">Laba Bersih Setelah Pajak</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <tbody>
                <tr class="table-light"><td colspan="2" class="fw-semibold">PENDAPATAN</td></tr>
                @foreach($labaRugi['pendapatan']['detail'] as $d)
                <tr><td class="small ps-4">{{ $d['kode'] }} — {{ $d['nama'] }}</td><td class="text-end small">Rp {{ number_format($d['jumlah'], 0, ',', '.') }}</td></tr>
                @endforeach
                <tr class="fw-semibold"><td>Total Pendapatan</td><td class="text-end">Rp {{ number_format($labaRugi['pendapatan']['total'], 0, ',', '.') }}</td></tr>

                <tr class="table-light"><td colspan="2" class="fw-semibold pt-3">HARGA POKOK PENJUALAN (HPP)</td></tr>
                @foreach($labaRugi['hpp']['detail'] as $d)
                <tr><td class="small ps-4">{{ $d['kode'] }} — {{ $d['nama'] }}</td><td class="text-end small">Rp {{ number_format($d['jumlah'], 0, ',', '.') }}</td></tr>
                @endforeach
                <tr class="fw-semibold"><td>Total HPP</td><td class="text-end">(Rp {{ number_format($labaRugi['hpp']['total'], 0, ',', '.') }})</td></tr>

                <tr class="table-primary fw-bold"><td>LABA KOTOR</td><td class="text-end">Rp {{ number_format($labaRugi['laba_kotor'], 0, ',', '.') }}</td></tr>

                <tr class="table-light"><td colspan="2" class="fw-semibold pt-3">BEBAN OPERASIONAL</td></tr>
                @foreach($labaRugi['beban_operasional']['detail'] as $d)
                <tr><td class="small ps-4">{{ $d['kode'] }} — {{ $d['nama'] }}</td><td class="text-end small">Rp {{ number_format($d['jumlah'], 0, ',', '.') }}</td></tr>
                @endforeach
                <tr class="fw-semibold"><td>Total Beban Operasional</td><td class="text-end">(Rp {{ number_format($labaRugi['beban_operasional']['total'], 0, ',', '.') }})</td></tr>

                <tr class="table-primary fw-bold"><td>LABA USAHA</td><td class="text-end">Rp {{ number_format($labaRugi['laba_usaha'], 0, ',', '.') }}</td></tr>

                <tr class="table-light"><td colspan="2" class="fw-semibold pt-3">PENDAPATAN LAIN-LAIN</td></tr>
                @foreach($labaRugi['pendapatan_lain']['detail'] as $d)
                <tr><td class="small ps-4">{{ $d['kode'] }} — {{ $d['nama'] }}</td><td class="text-end small">Rp {{ number_format($d['jumlah'], 0, ',', '.') }}</td></tr>
                @endforeach
                <tr class="fw-semibold"><td>Total Pendapatan Lain-lain</td><td class="text-end">Rp {{ number_format($labaRugi['pendapatan_lain']['total'], 0, ',', '.') }}</td></tr>

                <tr class="table-light"><td colspan="2" class="fw-semibold pt-3">BEBAN LAIN-LAIN</td></tr>
                @foreach($labaRugi['beban_lain']['detail'] as $d)
                <tr><td class="small ps-4">{{ $d['kode'] }} — {{ $d['nama'] }}</td><td class="text-end small">Rp {{ number_format($d['jumlah'], 0, ',', '.') }}</td></tr>
                @endforeach
                <tr class="fw-semibold"><td>Total Beban Lain-lain</td><td class="text-end">(Rp {{ number_format($labaRugi['beban_lain']['total'], 0, ',', '.') }})</td></tr>

                <tr class="table-info fw-bold"><td>LABA BERSIH SEBELUM PAJAK</td><td class="text-end">Rp {{ number_format($labaRugi['laba_bersih_sebelum_pajak'], 0, ',', '.') }}</td></tr>
                <tr><td class="small ps-4">Pajak Penghasilan</td><td class="text-end small">(Rp {{ number_format($labaRugi['pajak_penghasilan'], 0, ',', '.') }})</td></tr>
                <tr class="table-success fw-bold"><td>LABA BERSIH SETELAH PAJAK</td><td class="text-end">Rp {{ number_format($labaRugi['laba_bersih_setelah_pajak'], 0, ',', '.') }}</td></tr>
            </tbody>
        </table>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function() {
    var btnExport = document.getElementById('btnExportLabaRugiFormal');
    if (btnExport) {
        btnExport.addEventListener('click', function() {
            var params = new URLSearchParams(window.location.search);
            window.location.href = '{{ route('laporan.laba-rugi-formal.export') }}?' + params.toString();
        });
    }

    var btnExportExcel = document.getElementById('btnExportLabaRugiFormalExcel');
    if (btnExportExcel) {
        btnExportExcel.addEventListener('click', function() {
            var params = new URLSearchParams(window.location.search);
            window.location.href = '{{ route('laporan.laba-rugi-formal.export-excel') }}?' + params.toString();
        });
    }
})();
</script>
@endpush
