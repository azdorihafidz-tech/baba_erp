@extends('layouts.app')
@section('title', 'Laporan Setoran Harian')
@section('content')

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">Setoran Harian</h4>
        <small class="text-muted">{{ $dari->format('d/m/Y') }} — {{ $sampai->format('d/m/Y') }}</small>
    </div>
    <div class="d-flex gap-2">
        @can('laporan.setoran_harian.print')
        <button type="button" id="btnPrintSetoran" class="btn btn-secondary btn-sm">
            <i class="bi bi-printer me-1"></i><span class="d-none d-sm-inline">Print</span>
        </button>
        @endcan
        @can('laporan.setoran_harian.export')
        <button type="button" id="btnExportSetoran" class="btn btn-success btn-sm" title="Export rekap ringkas per Tanggal+Cabang (beda dari tampilan detail di halaman ini)">
            <i class="bi bi-file-earmark-excel me-1"></i><span class="d-none d-sm-inline">Export Rekap (Excel)</span>
        </button>
        <button type="button" id="btnExportSetoranPdf" class="btn btn-danger btn-sm" title="Export rekap ringkas per Tanggal+Cabang (beda dari tampilan detail di halaman ini)">
            <i class="bi bi-file-earmark-pdf me-1"></i><span class="d-none d-sm-inline">Export Rekap (PDF)</span>
        </button>
        @endcan
        <x-panduan-button slug="setoran-harian" />
    </div>
</div>

{{-- Section 1: Filter --}}
<x-date-range-filter action="{{ route('laporan.setoran-harian.index') }}"
    :dari="$dari->toDateString()" :sampai="$sampai->toDateString()"
    session-key="setoranharian"
    :extra-presets="['yesterday' => 'Kemarin', 'lastmonth' => 'Bulan Lalu']">
    @if(auth()->user()->canAccessAllBranches())
    <div class="row g-2 mb-2">
        <div class="col-12 col-sm-6 col-md-3">
            <label class="form-label form-label-sm mb-1">Cabang</label>
            <select name="cabang_id" class="form-select form-select-sm">
                <option value="">Semua Cabang</option>
                @foreach($cabangs as $c)
                <option value="{{ $c->id }}" @selected($cabangId == $c->id)>{{ $c->nama_cabang }}</option>
                @endforeach
            </select>
        </div>
    </div>
    @endif
</x-date-range-filter>

{{-- Section 2: Ringkasan --}}
<div class="card border-primary mb-3">
    <div class="card-body text-center py-4">
        <div class="text-muted small text-uppercase fw-semibold mb-1">Setoran ke Pusat (Net)</div>
        <div class="fw-bold text-primary" style="font-size:2.25rem">Rp {{ number_format($ringkasan['net'], 0, ',', '.') }}</div>
        @if($cabangTerpilih)
        <div class="text-muted small mt-1"><i class="bi bi-geo-alt me-1"></i>{{ $cabangTerpilih->nama_cabang }}</div>
        @else
        <div class="text-muted small mt-1"><i class="bi bi-geo-alt me-1"></i>Semua Cabang</div>
        @endif
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-4">
        <div class="stat-card">
            <div class="stat-icon bg-info bg-opacity-10 mb-2"><i class="bi bi-receipt text-info"></i></div>
            <div class="fw-bold">{{ $ringkasan['total_order'] }}</div>
            <div class="text-muted" style="font-size:0.8rem">Total Order</div>
        </div>
    </div>
    <div class="col-6 col-lg-4">
        <div class="stat-card">
            <div class="stat-icon bg-success bg-opacity-10 mb-2"><i class="bi bi-arrow-down-circle text-success"></i></div>
            <div class="fw-bold text-success">Rp {{ number_format($ringkasan['pemasukan'], 0, ',', '.') }}</div>
            <div class="text-muted" style="font-size:0.8rem">Total Pemasukan</div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="stat-card">
            <div class="stat-icon bg-danger bg-opacity-10 mb-2"><i class="bi bi-arrow-up-circle text-danger"></i></div>
            <div class="fw-bold text-danger">Rp {{ number_format($ringkasan['pengeluaran'], 0, ',', '.') }}</div>
            <div class="text-muted" style="font-size:0.8rem">Total Pengeluaran</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    {{-- Section 3: Breakdown Pemasukan --}}
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header py-2 px-3">
                <h6 class="mb-0">Breakdown Pemasukan per Metode Bayar</h6>
            </div>
            <div class="card-body">
                @php $totalBd = collect($breakdownPemasukan)->sum('total'); @endphp
                @php $warnaMetode = ['tunai' => 'success', 'qris' => 'primary', 'transfer' => 'warning']; @endphp
                @forelse($breakdownPemasukan as $key => $b)
                @php $pct = $totalBd > 0 ? round($b['total'] / $totalBd * 100, 1) : 0; @endphp
                <div class="mb-3">
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="fw-semibold">{{ $b['label'] }}</span>
                        <span>Rp {{ number_format($b['total'], 0, ',', '.') }} <span class="text-muted">({{ $pct }}%)</span></span>
                    </div>
                    <div class="progress" style="height:8px">
                        <div class="progress-bar bg-{{ $warnaMetode[$key] ?? 'secondary' }}" style="width:{{ $pct }}%"></div>
                    </div>
                </div>
                @empty
                <p class="text-muted small text-center py-3 mb-0">Belum ada pemasukan di periode ini.</p>
                @endforelse
                @if(count($breakdownPemasukan) && $totalBd != $ringkasan['pemasukan'])
                <div class="small text-muted border-top pt-2 mt-1">
                    <i class="bi bi-info-circle me-1"></i>Breakdown ini dari data Order — bisa beda dari Total Pemasukan kalau ada transaksi pemasukan manual (non-order) di periode ini.
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Section 4: Breakdown Pengeluaran --}}
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header py-2 px-3"><h6 class="mb-0">Breakdown Pengeluaran per Kategori</h6></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Kategori</th><th class="text-end">Jumlah</th></tr></thead>
                    <tbody>
                    @forelse($breakdownPengeluaran as $b)
                    <tr>
                        <td class="small">{{ $b['label'] }}</td>
                        <td class="text-end small fw-semibold">Rp {{ number_format($b['total'], 0, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="2" class="text-center text-muted py-3 small">Belum ada pengeluaran di periode ini.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Section 5: Detail Transaksi --}}
<div class="card">
    <div class="card-header py-2 px-3">
        <ul class="nav nav-tabs card-header-tabs" id="tabDetailSetoran" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabOrder" type="button">
                    Order <span class="badge bg-secondary ms-1">{{ collect($detailOrder)->flatten(1)->count() }}</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabPengeluaran" type="button">
                    Pengeluaran <span class="badge bg-secondary ms-1">{{ collect($detailPengeluaran)->flatten(1)->count() }}</span>
                </button>
            </li>
        </ul>
    </div>
    <div class="card-body p-0">
        <div class="tab-content">
            {{-- Tab Order --}}
            <div class="tab-pane fade show active" id="tabOrder">
                @if($detailOrder->isEmpty())
                <p class="text-muted text-center py-4 mb-0">Tidak ada order di periode ini.</p>
                @elseif($detailOrder->count() === 1)
                    @foreach($detailOrder as $tanggal => $orders)
                        @include('laporan.setoran-harian._tabel-order', ['orders' => $orders])
                    @endforeach
                @else
                <div class="accordion" id="accordionOrder">
                    @foreach($detailOrder as $tanggal => $orders)
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed small" type="button"
                                data-bs-toggle="collapse" data-bs-target="#accOrder{{ $loop->index }}">
                                {{ \Carbon\Carbon::parse($tanggal)->translatedFormat('d M Y') }}
                                <span class="badge bg-light text-dark border ms-2">{{ $orders->count() }} order — Rp {{ number_format($orders->sum('total_bayar'), 0, ',', '.') }}</span>
                            </button>
                        </h2>
                        <div id="accOrder{{ $loop->index }}" class="accordion-collapse collapse" data-bs-parent="#accordionOrder">
                            <div class="accordion-body p-0">
                                @include('laporan.setoran-harian._tabel-order', ['orders' => $orders])
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>

            {{-- Tab Pengeluaran --}}
            <div class="tab-pane fade" id="tabPengeluaran">
                @if($detailPengeluaran->isEmpty())
                <p class="text-muted text-center py-4 mb-0">Tidak ada pengeluaran di periode ini.</p>
                @elseif($detailPengeluaran->count() === 1)
                    @foreach($detailPengeluaran as $tanggal => $transaksis)
                        @include('laporan.setoran-harian._tabel-pengeluaran', ['transaksis' => $transaksis])
                    @endforeach
                @else
                <div class="accordion" id="accordionPengeluaran">
                    @foreach($detailPengeluaran as $tanggal => $transaksis)
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed small" type="button"
                                data-bs-toggle="collapse" data-bs-target="#accPengeluaran{{ $loop->index }}">
                                {{ \Carbon\Carbon::parse($tanggal)->translatedFormat('d M Y') }}
                                <span class="badge bg-light text-dark border ms-2">{{ $transaksis->count() }} transaksi — Rp {{ number_format($transaksis->sum('jumlah'), 0, ',', '.') }}</span>
                            </button>
                        </h2>
                        <div id="accPengeluaran{{ $loop->index }}" class="accordion-collapse collapse" data-bs-parent="#accordionPengeluaran">
                            <div class="accordion-body p-0">
                                @include('laporan.setoran-harian._tabel-pengeluaran', ['transaksis' => $transaksis])
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function() {
    var dari   = @json($dari->toDateString());
    var sampai = @json($sampai->toDateString());

    function jumlahHari() {
        var d1 = new Date(dari), d2 = new Date(sampai);
        return Math.round((d2 - d1) / 86400000) + 1;
    }

    async function konfirmasiRangePanjang() {
        var n = jumlahHari();
        if (n <= 7) return true;
        var estHalaman = Math.max(1, Math.ceil(n / 3));
        var pesan = 'Anda akan mencetak ' + n + ' hari, kurang lebih ' + estHalaman + ' halaman. Lanjut?';
        if (typeof window.showConfirm === 'function') {
            var res = await window.showConfirm('Range Cukup Panjang', pesan, { icon: 'warning', confirmText: 'Ya, Lanjut', cancelText: 'Batal' });
            return res.isConfirmed;
        }
        return window.confirm(pesan);
    }

    var btnPrint = document.getElementById('btnPrintSetoran');
    if (btnPrint) {
        btnPrint.addEventListener('click', async function() {
            if (!(await konfirmasiRangePanjang())) return;
            var params = new URLSearchParams(window.location.search);
            window.open('{{ route('laporan.setoran-harian.print') }}?' + params.toString(), '_blank');
        });
    }

    var btnExport = document.getElementById('btnExportSetoran');
    if (btnExport) {
        btnExport.addEventListener('click', async function() {
            if (!(await konfirmasiRangePanjang())) return;
            var params = new URLSearchParams(window.location.search);
            window.location.href = '{{ route('laporan.setoran-harian.export') }}?' + params.toString();
        });
    }

    var btnExportPdf = document.getElementById('btnExportSetoranPdf');
    if (btnExportPdf) {
        btnExportPdf.addEventListener('click', async function() {
            if (!(await konfirmasiRangePanjang())) return;
            var params = new URLSearchParams(window.location.search);
            params.set('format', 'pdf');
            window.location.href = '{{ route('laporan.setoran-harian.export') }}?' + params.toString();
        });
    }
})();
</script>
@endpush
