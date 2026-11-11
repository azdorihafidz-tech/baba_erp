@extends('layouts.app')
@section('title', 'Laporan Neraca')
@section('content')

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">⚖️ Laporan Neraca (Balance Sheet)</h4>
        <small class="text-muted">Per {{ \Carbon\Carbon::parse($neraca['tanggal'])->translatedFormat('d F Y') }} — {{ $cabangNama }}</small>
    </div>
    <div class="d-flex gap-2">
        @can('laporan.neraca.export')
        <button type="button" id="btnExportNeracaExcel" class="btn btn-outline-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i><span class="d-none d-sm-inline">Export Excel</span>
        </button>
        <button type="button" id="btnExportNeraca" class="btn btn-success btn-sm">
            <i class="bi bi-file-earmark-pdf me-1"></i><span class="d-none d-sm-inline">Export PDF</span>
        </button>
        @endcan
        <x-panduan-button slug="laporan-neraca" />
    </div>
</div>

<div class="alert alert-secondary py-2 px-3 small mb-3">
    <i class="bi bi-info-circle me-1"></i>
    Kas, Persediaan, dan Nilai Buku Aset selalu menampilkan kondisi <strong>terkini</strong> (sistem tidak menyimpan snapshot historis harian untuk ketiganya) — hanya Laba Ditahan yang menghormati tanggal cutoff yang dipilih.
</div>

{{-- Filter --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('laporan.neraca.index') }}" class="row g-2 align-items-end">
            <div class="col-6 col-sm-4 col-md-3">
                <label class="form-label form-label-sm mb-1">Per Tanggal</label>
                <input type="date" name="tanggal" class="form-control form-control-sm" value="{{ $tanggal->toDateString() }}">
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

{{-- Balance Check --}}
<div class="alert {{ $neraca['balance_check'] ? 'alert-success' : 'alert-warning' }} d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <i class="bi bi-{{ $neraca['balance_check'] ? 'check-circle-fill' : 'exclamation-triangle-fill' }} me-2"></i>
        <strong>{{ $neraca['balance_check'] ? 'Neraca Balance' : 'Neraca Belum Balance' }}</strong>
        — Total Aset Rp {{ number_format($neraca['aset']['total_aset'], 0, ',', '.') }}
        vs Total Kewajiban+Modal Rp {{ number_format($neraca['total_kewajiban_modal'], 0, ',', '.') }}
        @if(!$neraca['balance_check'])
            (Selisih: Rp {{ number_format($neraca['selisih'], 0, ',', '.') }})
        @endif
    </div>
</div>

@if(!$neraca['balance_check'])
<div class="alert alert-light border small mb-3">
    <i class="bi bi-info-circle me-1"></i>
    Selisih ini WAJAR untuk data historis yang direkam sebelum sistem ini formal double-entry (mis. aset yang didata langsung tanpa transaksi Kas pembelian yang match persis, atau modal awal yang masuk lewat jalur Transfer bukan kategori Modal). <strong>Modal Owner</strong> di bawah sengaja dibuat sebagai figure yang bisa di-adjust manual untuk "true up" seiring waktu.
</div>
@endif

<div class="row g-3">
    {{-- ASET --}}
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header py-2 px-3 bg-primary-subtle"><h6 class="mb-0 fw-bold">ASET</h6></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr class="table-light"><td colspan="2" class="fw-semibold small">Aset Lancar</td></tr>
                        <tr><td class="small ps-4">Kas & Setara Kas</td><td class="text-end small">Rp {{ number_format($neraca['aset']['lancar']['kas_setara'], 0, ',', '.') }}</td></tr>
                        <tr><td class="small ps-4">Piutang Usaha</td><td class="text-end small">Rp {{ number_format($neraca['aset']['lancar']['piutang'], 0, ',', '.') }}</td></tr>
                        <tr><td class="small ps-4">Persediaan Bahan Baku</td><td class="text-end small">Rp {{ number_format($neraca['aset']['lancar']['persediaan_bahan_baku'], 0, ',', '.') }}</td></tr>
                        <tr><td class="small ps-4">Persediaan Barang Jadi</td><td class="text-end small">Rp {{ number_format($neraca['aset']['lancar']['persediaan_barang_jadi'], 0, ',', '.') }}</td></tr>
                        <tr><td class="small ps-4">Persediaan Kemasan</td><td class="text-end small">Rp {{ number_format($neraca['aset']['lancar']['persediaan_kemasan'], 0, ',', '.') }}</td></tr>
                        <tr class="fw-semibold"><td class="small">Total Aset Lancar</td><td class="text-end small">Rp {{ number_format($neraca['aset']['lancar']['total'], 0, ',', '.') }}</td></tr>

                        <tr class="table-light"><td colspan="2" class="fw-semibold small pt-3">Aset Tetap</td></tr>
                        <tr><td class="small ps-4">Aset Bruto (Harga Perolehan)</td><td class="text-end small">Rp {{ number_format($neraca['aset']['tetap']['aset_bruto'], 0, ',', '.') }}</td></tr>
                        <tr><td class="small ps-4">Akumulasi Depresiasi</td><td class="text-end small text-danger">(Rp {{ number_format($neraca['aset']['tetap']['akumulasi_depresiasi'], 0, ',', '.') }})</td></tr>
                        <tr class="fw-semibold"><td class="small">Nilai Buku Aset Tetap</td><td class="text-end small">Rp {{ number_format($neraca['aset']['tetap']['nilai_buku'], 0, ',', '.') }}</td></tr>

                        @if(count($neraca['aset']['tetap']['detail']) > 0)
                        <tr>
                            <td colspan="2" class="p-0">
                                <button class="btn btn-link btn-sm text-decoration-none" type="button" data-bs-toggle="collapse" data-bs-target="#detailAset">
                                    <i class="bi bi-list-ul me-1"></i>Lihat Detail {{ count($neraca['aset']['tetap']['detail']) }} Aset
                                </button>
                                <div class="collapse" id="detailAset">
                                    <table class="table table-sm table-borderless mb-2">
                                        <thead><tr class="text-muted" style="font-size:0.75rem"><th>Nama</th><th class="text-end">Perolehan</th><th class="text-end">Akum. Depr.</th><th class="text-end">Nilai Buku</th></tr></thead>
                                        <tbody>
                                        @foreach($neraca['aset']['tetap']['detail'] as $d)
                                        <tr style="font-size:0.8rem">
                                            <td>{{ $d['nama'] }}</td>
                                            <td class="text-end">Rp {{ number_format($d['harga_perolehan'], 0, ',', '.') }}</td>
                                            <td class="text-end">Rp {{ number_format($d['akum_depresiasi'], 0, ',', '.') }}</td>
                                            <td class="text-end">Rp {{ number_format($d['nilai_buku'], 0, ',', '.') }}</td>
                                        </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </td>
                        </tr>
                        @endif

                        <tr class="table-primary fw-bold"><td>TOTAL ASET</td><td class="text-end">Rp {{ number_format($neraca['aset']['total_aset'], 0, ',', '.') }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- KEWAJIBAN & MODAL --}}
    <div class="col-12 col-lg-6">
        <div class="card mb-3">
            <div class="card-header py-2 px-3 bg-warning-subtle"><h6 class="mb-0 fw-bold">KEWAJIBAN</h6></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr class="table-light"><td colspan="2" class="fw-semibold small">Kewajiban Jangka Pendek</td></tr>
                        <tr><td class="small ps-4">Hutang Usaha (PO diterima, belum dibayar)</td><td class="text-end small">Rp {{ number_format($neraca['kewajiban']['jangka_pendek']['hutang_usaha'], 0, ',', '.') }}</td></tr>
                        <tr><td class="small ps-4">Hutang Pajak</td><td class="text-end small">Rp {{ number_format($neraca['kewajiban']['jangka_pendek']['hutang_pajak'], 0, ',', '.') }}</td></tr>
                        <tr class="fw-semibold"><td class="small">Total Jangka Pendek</td><td class="text-end small">Rp {{ number_format($neraca['kewajiban']['jangka_pendek']['total'], 0, ',', '.') }}</td></tr>

                        <tr class="table-light"><td colspan="2" class="fw-semibold small pt-3">Kewajiban Jangka Panjang</td></tr>
                        <tr><td class="small ps-4">Hutang Bank</td><td class="text-end small">Rp {{ number_format($neraca['kewajiban']['jangka_panjang']['hutang_bank'], 0, ',', '.') }}</td></tr>
                        <tr class="fw-semibold"><td class="small">Total Jangka Panjang</td><td class="text-end small">Rp {{ number_format($neraca['kewajiban']['jangka_panjang']['total'], 0, ',', '.') }}</td></tr>

                        <tr class="table-warning fw-bold"><td>TOTAL KEWAJIBAN</td><td class="text-end">Rp {{ number_format($neraca['kewajiban']['total_kewajiban'], 0, ',', '.') }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header py-2 px-3 bg-success-subtle d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-bold">MODAL</h6>
                @can('laporan.neraca.export')
                @auth
                @if(auth()->user()->role === \App\Enums\RoleUser::Owner)
                <button type="button" class="btn btn-outline-secondary btn-sm py-0" data-bs-toggle="modal" data-bs-target="#modalEditModalOwner">
                    <i class="bi bi-pencil"></i>
                </button>
                @endif
                @endauth
                @endcan
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><td class="small">Modal Owner</td><td class="text-end small">Rp {{ number_format($neraca['modal']['modal_owner'], 0, ',', '.') }}</td></tr>
                        <tr><td class="small">Laba Ditahan (s/d tanggal cutoff)</td><td class="text-end small {{ $neraca['modal']['laba_ditahan'] < 0 ? 'text-danger' : '' }}">Rp {{ number_format($neraca['modal']['laba_ditahan'], 0, ',', '.') }}</td></tr>
                        <tr class="table-success fw-bold"><td>TOTAL MODAL</td><td class="text-end">Rp {{ number_format($neraca['modal']['total_modal'], 0, ',', '.') }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-body py-2 px-3 d-flex align-items-center justify-content-between">
                <span class="fw-bold">TOTAL KEWAJIBAN + MODAL</span>
                <span class="fw-bold">Rp {{ number_format($neraca['total_kewajiban_modal'], 0, ',', '.') }}</span>
            </div>
        </div>
    </div>
</div>

@auth
@if(auth()->user()->role === \App\Enums\RoleUser::Owner)
<div class="modal fade" id="modalEditModalOwner" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content">
            <form method="POST" action="{{ route('laporan.neraca.update-setting') }}">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h6 class="modal-title">Edit Modal Owner</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">Figure "plug" manual untuk true-up Neraca seiring waktu. Perubahan ini berlaku global (tidak per cabang/tanggal).</p>
                    <div class="mb-2">
                        <label class="form-label form-label-sm">Modal Owner (Rp)</label>
                        <input type="number" step="1" min="0" name="modal_owner" class="form-control form-control-sm" value="{{ $neraca['modal']['modal_owner'] }}" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label form-label-sm">Catatan</label>
                        <textarea name="catatan" class="form-control form-control-sm" rows="2">{{ \App\Models\NeracaSetting::getSetting()->catatan }}</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endauth

@endsection

@push('scripts')
<script>
(function() {
    var btnExport = document.getElementById('btnExportNeraca');
    if (btnExport) {
        btnExport.addEventListener('click', function() {
            var params = new URLSearchParams(window.location.search);
            window.location.href = '{{ route('laporan.neraca.export') }}?' + params.toString();
        });
    }

    var btnExportExcel = document.getElementById('btnExportNeracaExcel');
    if (btnExportExcel) {
        btnExportExcel.addEventListener('click', function() {
            var params = new URLSearchParams(window.location.search);
            window.location.href = '{{ route('laporan.neraca.export-excel') }}?' + params.toString();
        });
    }
})();
</script>
@endpush
