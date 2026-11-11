@extends('layouts.app')
@section('title', 'Laporan Laba Rugi')
@section('content')

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">📈 Laporan Laba Rugi</h4>
        <small class="text-muted">{{ $dari->format('d/m/Y') }} — {{ $sampai->format('d/m/Y') }}</small>
    </div>
    <div class="d-flex gap-2">
        @can('laporan.laba_rugi.print')
        <button type="button" id="btnPrintLabaRugi" class="btn btn-secondary btn-sm">
            <i class="bi bi-printer me-1"></i><span class="d-none d-sm-inline">Print</span>
        </button>
        @endcan
        @can('laporan.laba_rugi.export')
        <button type="button" id="btnExportLabaRugi" class="btn btn-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i><span class="d-none d-sm-inline">Export Excel</span>
        </button>
        <button type="button" id="btnExportLabaRugiPdfRingkas" class="btn btn-outline-danger btn-sm" title="PDF ringkas 1 halaman (tanpa breakdown/detail)">
            <i class="bi bi-file-earmark-pdf me-1"></i><span class="d-none d-sm-inline">PDF Ringkas</span>
        </button>
        <button type="button" id="btnExportLabaRugiPdfDetail" class="btn btn-danger btn-sm" title="PDF lengkap dgn breakdown + detail transaksi (landscape)">
            <i class="bi bi-file-earmark-pdf me-1"></i><span class="d-none d-sm-inline">PDF Detail</span>
        </button>
        @endcan
        <x-panduan-button slug="laporan-laba-rugi" />
    </div>
</div>

{{-- Section: Filter --}}
<x-date-range-filter action="{{ route('laporan.laba-rugi.index') }}"
    :dari="$dari->toDateString()" :sampai="$sampai->toDateString()"
    session-key="labarugi"
    :extra-presets="['yesterday' => 'Kemarin', 'lastmonth' => 'Bulan Lalu']">
    <div class="row g-2 mb-2">
        @if(auth()->user()->canAccessAllBranches())
        <div class="col-12 col-sm-6 col-md-3">
            <label class="form-label form-label-sm mb-1">Cabang</label>
            <select name="cabang_id" class="form-select form-select-sm">
                <option value="">Semua Cabang</option>
                @foreach($cabangs as $c)
                <option value="{{ $c->id }}" @selected($cabangId == $c->id)>{{ $c->nama_cabang }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <div class="col-12 col-sm-6 col-md-3">
            <label class="form-label form-label-sm mb-1">Level Breakdown</label>
            <select name="level" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="item" @selected($level === 'item')>Per Item</option>
                <option value="kategori" @selected($level === 'kategori')>Per Kategori</option>
                <option value="jenis_olahan" @selected($level === 'jenis_olahan')>Per Jenis Menu</option>
                <option value="order" @selected($level === 'order')>Per Order</option>
            </select>
        </div>
    </div>
</x-date-range-filter>

{{-- Section: Kartu Ringkasan (5 metrik) --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-lg">
        <div class="stat-card">
            <div class="stat-icon bg-info bg-opacity-10 mb-2"><i class="bi bi-receipt text-info"></i></div>
            <div class="fw-bold">{{ $ringkasan['total_order'] }}</div>
            <div class="text-muted" style="font-size:0.8rem">Total Order</div>
        </div>
    </div>
    <div class="col-6 col-lg">
        <div class="stat-card">
            <div class="stat-icon bg-secondary bg-opacity-10 mb-2"><i class="bi bi-cash-stack text-secondary"></i></div>
            <div class="fw-bold">Rp {{ number_format($ringkasan['total_omzet'], 0, ',', '.') }}</div>
            <div class="text-muted" style="font-size:0.8rem">Total Omzet</div>
        </div>
    </div>
    <div class="col-6 col-lg">
        <div class="stat-card">
            <div class="stat-icon bg-primary bg-opacity-10 mb-2"><i class="bi bi-basket3 text-primary"></i></div>
            <div class="fw-bold text-primary">Rp {{ number_format($ringkasan['total_hpp'], 0, ',', '.') }}</div>
            <div class="text-muted" style="font-size:0.8rem">Total HPP</div>
        </div>
    </div>
    <div class="col-6 col-lg">
        <div class="stat-card border-2 {{ $ringkasan['total_untung'] >= 0 ? 'border-success' : 'border-danger' }}">
            <div class="stat-icon {{ $ringkasan['total_untung'] >= 0 ? 'bg-success' : 'bg-danger' }} bg-opacity-10 mb-2"><i class="bi bi-{{ $ringkasan['total_untung'] >= 0 ? 'graph-up-arrow text-success' : 'graph-down-arrow text-danger' }}"></i></div>
            <div class="fw-bold {{ $ringkasan['total_untung'] >= 0 ? 'text-success' : 'text-danger' }}" style="font-size:1.15rem">Rp {{ number_format($ringkasan['total_untung'], 0, ',', '.') }}</div>
            <div class="text-muted" style="font-size:0.8rem">Total Untung</div>
        </div>
    </div>
    <div class="col-6 col-lg">
        <div class="stat-card">
            <div class="stat-icon {{ ($ringkasan['margin'] ?? 0) >= 0 ? 'bg-success' : 'bg-danger' }} bg-opacity-10 mb-2"><i class="bi bi-percent {{ ($ringkasan['margin'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}"></i></div>
            <div class="fw-bold {{ ($ringkasan['margin'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">{{ $ringkasan['margin'] !== null ? $ringkasan['margin'] . '%' : '-' }}</div>
            <div class="text-muted" style="font-size:0.8rem">Margin</div>
        </div>
    </div>
</div>

@if($ringkasan['total_untung'] < 0)
<div class="alert alert-danger py-2 px-3 small mb-3">
    <i class="bi bi-exclamation-triangle me-1"></i>
    Total Untung periode ini <strong>negatif</strong> — omzet lebih kecil dari HPP bahan. Cek breakdown di bawah untuk tahu item/kategori penyebabnya.
</div>
@endif

{{-- Section: Tabel Breakdown (kolom menyesuaikan level) --}}
<div class="card mb-3">
    <div class="card-header py-2 px-3">
        <h6 class="mb-0">
            Breakdown
            @switch($level)
                @case('kategori') Per Kategori @break
                @case('jenis_olahan') Per Jenis Menu @break
                @case('order') Per Order @break
                @default Per Item
            @endswitch
        </h6>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            @php
                $sortKolom = request('sort', 'total_untung');
                $sortDir   = request('dir', 'desc');
                $nextDir   = fn($k) => ($sortKolom === $k && $sortDir === 'desc') ? 'asc' : 'desc';
                $sortIcon  = fn($k) => $sortKolom !== $k ? '' : ($sortDir === 'asc' ? ' <i class="bi bi-caret-up-fill"></i>' : ' <i class="bi bi-caret-down-fill"></i>');
                $sortLink  = fn($k) => request()->fullUrlWithQuery(['sort' => $k, 'dir' => $nextDir($k), 'page' => 1]);
            @endphp
            <thead class="table-light">
                <tr>
                    @if($level === 'item')
                        <th>Item</th>
                        <th class="text-center">Tipe</th>
                        <th class="text-end">Qty</th>
                    @elseif($level === 'kategori')
                        <th>Kategori</th>
                    @elseif($level === 'jenis_olahan')
                        <th>Jenis Menu</th>
                    @else
                        <th>No Order</th>
                        <th>Tanggal</th>
                        <th>Pelanggan</th>
                        <th>Kasir</th>
                    @endif
                    <th class="text-end"><a href="{{ $sortLink('total_omzet') }}" class="text-decoration-none text-body">Omzet{!! $sortIcon('total_omzet') !!}</a></th>
                    <th class="text-end"><a href="{{ $sortLink('total_hpp') }}" class="text-decoration-none text-body">HPP{!! $sortIcon('total_hpp') !!}</a></th>
                    <th class="text-end"><a href="{{ $sortLink('total_untung') }}" class="text-decoration-none text-body">Untung{!! $sortIcon('total_untung') !!}</a></th>
                    <th class="text-end"><a href="{{ $sortLink('margin') }}" class="text-decoration-none text-body">Margin{!! $sortIcon('margin') !!}</a></th>
                </tr>
            </thead>
            <tbody>
            @php $kolomCount = $level === 'item' ? 7 : ($level === 'order' ? 8 : 5); @endphp
            @forelse($breakdown as $b)
                <tr>
                    @if($level === 'item')
                        <td class="small">{{ $b->nama_item }}</td>
                        <td class="text-center">
                            @php $badgeTipe = ['bahan_baku' => 'bg-primary-subtle text-primary', 'kemasan' => 'bg-warning-subtle text-warning', 'produk_jadi' => 'bg-success-subtle text-success']; @endphp
                            <span class="badge {{ $badgeTipe[$b->tipe] ?? 'bg-secondary-subtle text-secondary' }}">{{ ucwords(str_replace('_', ' ', $b->tipe)) }}</span>
                        </td>
                        <td class="text-end small">{{ rtrim(rtrim(number_format($b->total_qty, 3, ',', '.'), '0'), ',') }} {{ $b->satuan }}</td>
                    @elseif($level === 'kategori')
                        <td class="small">
                            @php $badgeKategori = ['bahan_baku' => 'bg-primary-subtle text-primary', 'kemasan' => 'bg-warning-subtle text-warning', 'produk_jadi' => 'bg-success-subtle text-success', 'jasa' => 'bg-info-subtle text-info']; @endphp
                            <span class="badge {{ $badgeKategori[$b->kategori] ?? 'bg-secondary-subtle text-secondary' }}">{{ ucwords(str_replace('_', ' ', $b->kategori)) }}</span>
                        </td>
                    @elseif($level === 'jenis_olahan')
                        <td class="small">{{ $b->jenis_olahan === '-' ? '-' : ucfirst($b->jenis_olahan) }}</td>
                    @else
                        <td class="small"><a href="{{ route('penjualan.show', $b->order_id) }}">{{ $b->nomor_order }}</a></td>
                        <td class="small">{{ \Carbon\Carbon::parse($b->tanggal_order)->format('d/m/Y') }}</td>
                        <td class="small">{{ $b->nama_pelanggan ?? 'Umum' }}</td>
                        <td class="small">{{ $b->kasir_nama ?? '-' }}</td>
                    @endif
                    <td class="text-end small">Rp {{ number_format($b->total_omzet, 0, ',', '.') }}</td>
                    <td class="text-end small">Rp {{ number_format($b->total_hpp, 0, ',', '.') }}</td>
                    <td class="text-end small fw-semibold {{ $b->total_untung >= 0 ? 'text-success' : 'text-danger' }}">Rp {{ number_format($b->total_untung, 0, ',', '.') }}</td>
                    <td class="text-end small {{ $b->margin !== null && $b->margin >= 0 ? 'text-success' : ($b->margin !== null ? 'text-danger' : 'text-muted') }}">{{ $b->margin !== null ? $b->margin . '%' : '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ $kolomCount }}" class="text-center text-muted py-4">Tidak ada data di periode ini.</td></tr>
            @endforelse
            </tbody>
            @if($breakdown->total() > 0)
            <tfoot>
                <tr class="table-light fw-bold">
                    <td colspan="{{ $level === 'item' ? 3 : ($level === 'order' ? 4 : 1) }}" class="text-end">Total Halaman Ini:</td>
                    <td class="text-end">Rp {{ number_format($breakdown->sum('total_omzet'), 0, ',', '.') }}</td>
                    <td class="text-end">Rp {{ number_format($breakdown->sum('total_hpp'), 0, ',', '.') }}</td>
                    <td class="text-end {{ $breakdown->sum('total_untung') >= 0 ? 'text-success' : 'text-danger' }}">Rp {{ number_format($breakdown->sum('total_untung'), 0, ',', '.') }}</td>
                    <td></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
    @if($breakdown->hasPages())
    <div class="card-body py-2">
        {{ $breakdown->onEachSide(1)->links() }}
    </div>
    @endif
</div>

{{-- Section: Chart Bar Top 10 Untung + Chart Trend --}}
<div class="row g-3 mb-3">
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header py-2 px-3"><h6 class="mb-0">Top 10 Item — Untung Terbesar</h6></div>
            <div class="card-body">
                <div style="position:relative;height:320px">
                    <canvas id="chartTopUntung"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header py-2 px-3"><h6 class="mb-0">Trend Untung 6 Bulan Terakhir</h6></div>
            <div class="card-body">
                <div style="position:relative;height:320px">
                    <canvas id="chartTrendUntung"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Section: Chart Pie Kontribusi per Kategori (khusus level Per Kategori) --}}
@if($level === 'kategori')
<div class="row g-3 mb-3">
    <div class="col-12 col-lg-6 mx-lg-auto">
        <div class="card">
            <div class="card-header py-2 px-3"><h6 class="mb-0">Kontribusi Untung per Kategori</h6></div>
            <div class="card-body">
                <div style="position:relative;height:300px">
                    <canvas id="chartPieKategori"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Section: Detail Transaksi --}}
<div class="card">
    <div class="card-header py-2 px-3">
        <h6 class="mb-0">Detail Transaksi <span class="badge bg-secondary ms-1">{{ collect($detail)->flatten(1)->count() }}</span></h6>
    </div>
    <div class="card-body p-0">
        @if($detail->isEmpty())
        <p class="text-muted text-center py-4 mb-0">Tidak ada transaksi di periode ini.</p>
        @elseif($detail->count() === 1)
            @foreach($detail as $tanggal => $rows)
                @include('laporan.laba-rugi._tabel-detail', ['rows' => $rows])
            @endforeach
        @else
        <div class="accordion" id="accordionDetailLabaRugi">
            @foreach($detail as $tanggal => $rows)
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed small" type="button"
                        data-bs-toggle="collapse" data-bs-target="#accLabaRugi{{ $loop->index }}">
                        {{ \Carbon\Carbon::parse($tanggal)->translatedFormat('d M Y') }}
                        <span class="badge bg-light text-dark border ms-2">{{ $rows->count() }} baris — Untung Rp {{ number_format($rows->sum('untung'), 0, ',', '.') }}</span>
                    </button>
                </h2>
                <div id="accLabaRugi{{ $loop->index }}" class="accordion-collapse collapse" data-bs-parent="#accordionDetailLabaRugi">
                    <div class="accordion-body p-0">
                        @include('laporan.laba-rugi._tabel-detail', ['rows' => $rows])
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>

@endsection

@push('scripts')
<script>
(function() {
    new Chart(document.getElementById('chartTopUntung'), {
        type: 'bar',
        data: {
            labels: @json($topUntung->pluck('nama_item')),
            datasets: [{
                label: 'Untung',
                data: @json($topUntung->pluck('total_untung')),
                backgroundColor: @json($topUntung->pluck('total_untung'))
                    .map(v => v >= 0 ? 'rgba(25, 135, 84, 0.7)' : 'rgba(220, 53, 69, 0.7)'),
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: c => 'Rp ' + new Intl.NumberFormat('id-ID').format(c.raw) } }
            },
            scales: {
                x: { ticks: { callback: v => 'Rp ' + new Intl.NumberFormat('id-ID', { notation: 'compact' }).format(v) } }
            }
        }
    });

    new Chart(document.getElementById('chartTrendUntung'), {
        type: 'line',
        data: {
            labels: @json($trend['labels']),
            datasets: [{
                label: 'Untung',
                data: @json($trend['data']),
                borderColor: '#198754',
                backgroundColor: 'rgba(25, 135, 84, 0.15)',
                fill: true,
                tension: 0.3,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: c => 'Rp ' + new Intl.NumberFormat('id-ID').format(c.raw) } }
            },
            scales: {
                y: { ticks: { callback: v => 'Rp ' + new Intl.NumberFormat('id-ID', { notation: 'compact' }).format(v) } }
            }
        }
    });

    @if($level === 'kategori')
    const pieColors = { bahan_baku: '#0d6efd', kemasan: '#ffc107', produk_jadi: '#198754', jasa: '#0dcaf0' };
    const pieLabels = @json($breakdownPenuh->pluck('kategori'));
    new Chart(document.getElementById('chartPieKategori'), {
        type: 'pie',
        data: {
            labels: pieLabels.map(k => k.charAt(0).toUpperCase() + k.slice(1).replace('_', ' ')),
            datasets: [{
                data: @json($breakdownPenuh->pluck('total_untung')),
                backgroundColor: pieLabels.map(k => pieColors[k] || '#6c757d'),
                borderWidth: 1,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { font: { size: 10 }, boxWidth: 10 } },
                tooltip: { callbacks: { label: c => c.label + ': Rp ' + new Intl.NumberFormat('id-ID').format(c.raw) } }
            }
        }
    });
    @endif
})();

// Print & Export dengan peringatan range panjang — pola sama seperti Laporan
// Setoran Harian / Konsumsi Bahan Baku.
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

    var btnPrint = document.getElementById('btnPrintLabaRugi');
    if (btnPrint) {
        btnPrint.addEventListener('click', async function() {
            if (!(await konfirmasiRangePanjang())) return;
            var params = new URLSearchParams(window.location.search);
            window.open('{{ route('laporan.laba-rugi.print') }}?' + params.toString(), '_blank');
        });
    }

    var btnExport = document.getElementById('btnExportLabaRugi');
    if (btnExport) {
        btnExport.addEventListener('click', async function() {
            if (!(await konfirmasiRangePanjang())) return;
            var params = new URLSearchParams(window.location.search);
            window.location.href = '{{ route('laporan.laba-rugi.export') }}?' + params.toString();
        });
    }

    var btnPdfRingkas = document.getElementById('btnExportLabaRugiPdfRingkas');
    if (btnPdfRingkas) {
        btnPdfRingkas.addEventListener('click', async function() {
            if (!(await konfirmasiRangePanjang())) return;
            var params = new URLSearchParams(window.location.search);
            params.set('format', 'pdf');
            params.set('varian', 'ringkas');
            window.location.href = '{{ route('laporan.laba-rugi.export') }}?' + params.toString();
        });
    }

    var btnPdfDetail = document.getElementById('btnExportLabaRugiPdfDetail');
    if (btnPdfDetail) {
        btnPdfDetail.addEventListener('click', async function() {
            if (!(await konfirmasiRangePanjang())) return;
            var params = new URLSearchParams(window.location.search);
            params.set('format', 'pdf');
            params.set('varian', 'detail');
            window.location.href = '{{ route('laporan.laba-rugi.export') }}?' + params.toString();
        });
    }
})();
</script>
@endpush
