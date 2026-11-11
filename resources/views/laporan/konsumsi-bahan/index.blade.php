@extends('layouts.app')
@section('title', 'Laporan Konsumsi Bahan Baku')
@section('content')

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">📊 Laporan Konsumsi Bahan Baku</h4>
        <small class="text-muted">{{ $dari->format('d/m/Y') }} — {{ $sampai->format('d/m/Y') }}</small>
    </div>
    <div class="d-flex gap-2">
        @can('laporan.konsumsi_bahan.print')
        <button type="button" id="btnPrintKonsumsi" class="btn btn-secondary btn-sm">
            <i class="bi bi-printer me-1"></i><span class="d-none d-sm-inline">Print</span>
        </button>
        @endcan
        @can('laporan.konsumsi_bahan.export')
        <button type="button" id="btnExportKonsumsi" class="btn btn-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i><span class="d-none d-sm-inline">Export Excel</span>
        </button>
        @endcan
        <x-panduan-button slug="laporan-konsumsi-bahan" />
    </div>
</div>

{{-- Section: Filter --}}
<x-date-range-filter action="{{ route('laporan.konsumsi-bahan.index') }}"
    :dari="$dari->toDateString()" :sampai="$sampai->toDateString()"
    session-key="konsumsibahan"
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
            <label class="form-label form-label-sm mb-1">Tipe Item</label>
            <select name="tipe" class="form-select form-select-sm">
                <option value="">Semua Tipe</option>
                <option value="bahan_baku" @selected($tipe === 'bahan_baku')>Bahan Baku</option>
                <option value="kemasan" @selected($tipe === 'kemasan')>Kemasan</option>
                <option value="produk_jadi" @selected($tipe === 'produk_jadi')>Produk Jadi</option>
            </select>
        </div>
    </div>
</x-date-range-filter>

{{-- Section: Kartu Ringkasan --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon bg-info bg-opacity-10 mb-2"><i class="bi bi-box-seam text-info"></i></div>
            <div class="fw-bold">{{ $ringkasan['total_item_unik'] }}</div>
            <div class="text-muted" style="font-size:0.8rem">Total Item Unik</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon bg-secondary bg-opacity-10 mb-2"><i class="bi bi-receipt text-secondary"></i></div>
            <div class="fw-bold">Rp {{ number_format($ringkasan['total_omzet'], 0, ',', '.') }}</div>
            <div class="text-muted" style="font-size:0.8rem">Total Omzet</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon bg-primary bg-opacity-10 mb-2"><i class="bi bi-cash-stack text-primary"></i></div>
            <div class="fw-bold text-primary">Rp {{ number_format($ringkasan['total_hpp'], 0, ',', '.') }}</div>
            <div class="text-muted" style="font-size:0.8rem">Total HPP Periode</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon {{ $ringkasan['total_untung'] >= 0 ? 'bg-success' : 'bg-danger' }} bg-opacity-10 mb-2"><i class="bi bi-{{ $ringkasan['total_untung'] >= 0 ? 'graph-up-arrow text-success' : 'graph-down-arrow text-danger' }}"></i></div>
            <div class="fw-bold {{ $ringkasan['total_untung'] >= 0 ? 'text-success' : 'text-danger' }}">Rp {{ number_format($ringkasan['total_untung'], 0, ',', '.') }}</div>
            <div class="text-muted" style="font-size:0.8rem">Total Untung {{ $ringkasan['margin'] !== null ? '('.$ringkasan['margin'].'%)' : '' }}</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon bg-success bg-opacity-10 mb-2"><i class="bi bi-graph-up text-success"></i></div>
            @if($ringkasan['item_terbanyak_qty'])
            <div class="fw-bold small">{{ $ringkasan['item_terbanyak_qty']->nama_item }}</div>
            <div class="text-muted" style="font-size:0.75rem">{{ rtrim(rtrim(number_format($ringkasan['item_terbanyak_qty']->total_qty, 3, ',', '.'), '0'), ',') }} {{ $ringkasan['item_terbanyak_qty']->satuan }}</div>
            @else
            <div class="fw-bold small text-muted">-</div>
            @endif
            <div class="text-muted" style="font-size:0.8rem">Item Terbanyak Terpakai</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon bg-danger bg-opacity-10 mb-2"><i class="bi bi-currency-exchange text-danger"></i></div>
            @if($ringkasan['item_termahal_hpp'])
            <div class="fw-bold small">{{ $ringkasan['item_termahal_hpp']->nama_item }}</div>
            <div class="text-muted" style="font-size:0.75rem">Rp {{ number_format($ringkasan['item_termahal_hpp']->total_hpp, 0, ',', '.') }}</div>
            @else
            <div class="fw-bold small text-muted">-</div>
            @endif
            <div class="text-muted" style="font-size:0.8rem">Item HPP Termahal</div>
        </div>
    </div>
</div>

@if($breakdownPenuh->sum('total_omzet') < $ringkasan['total_omzet'])
<div class="alert alert-info py-2 px-3 small mb-3">
    <i class="bi bi-info-circle me-1"></i>
    Total Omzet di kartu ringkasan (Rp {{ number_format($ringkasan['total_omzet'], 0, ',', '.') }}) termasuk baris "Jasa Giling" tanpa item terdaftar (Rp {{ number_format($ringkasan['total_omzet'] - $breakdownPenuh->sum('total_omzet'), 0, ',', '.') }}) — makanya lebih besar dari jumlah kolom Omzet di tabel breakdown per item di bawah (yang cuma menghitung baris ber-item).
</div>
@endif

{{-- Section: Tabel Breakdown per Item --}}
<div class="card mb-3">
    <div class="card-header py-2 px-3"><h6 class="mb-0">Breakdown per Item</h6></div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    @php
                        $sortKolom = request('sort', 'total_hpp');
                        $sortDir   = request('dir', 'desc');
                        $nextDir   = fn($k) => ($sortKolom === $k && $sortDir === 'desc') ? 'asc' : 'desc';
                        $sortIcon  = fn($k) => $sortKolom !== $k ? '' : ($sortDir === 'asc' ? ' <i class="bi bi-caret-up-fill"></i>' : ' <i class="bi bi-caret-down-fill"></i>');
                    @endphp
                    <th><a href="{{ request()->fullUrlWithQuery(['sort' => 'nama_item', 'dir' => $nextDir('nama_item'), 'page' => 1]) }}" class="text-decoration-none text-body">Item{!! $sortIcon('nama_item') !!}</a></th>
                    <th class="text-center">Tipe</th>
                    <th class="text-end"><a href="{{ request()->fullUrlWithQuery(['sort' => 'total_qty', 'dir' => $nextDir('total_qty'), 'page' => 1]) }}" class="text-decoration-none text-body">Qty Terpakai{!! $sortIcon('total_qty') !!}</a></th>
                    <th class="text-end"><a href="{{ request()->fullUrlWithQuery(['sort' => 'total_omzet', 'dir' => $nextDir('total_omzet'), 'page' => 1]) }}" class="text-decoration-none text-body">Omzet{!! $sortIcon('total_omzet') !!}</a></th>
                    <th class="text-end"><a href="{{ request()->fullUrlWithQuery(['sort' => 'total_hpp', 'dir' => $nextDir('total_hpp'), 'page' => 1]) }}" class="text-decoration-none text-body">Nilai HPP{!! $sortIcon('total_hpp') !!}</a></th>
                    <th class="text-end"><a href="{{ request()->fullUrlWithQuery(['sort' => 'total_untung', 'dir' => $nextDir('total_untung'), 'page' => 1]) }}" class="text-decoration-none text-body">Untung{!! $sortIcon('total_untung') !!}</a></th>
                    <th class="text-end"><a href="{{ request()->fullUrlWithQuery(['sort' => 'margin', 'dir' => $nextDir('margin'), 'page' => 1]) }}" class="text-decoration-none text-body">Margin{!! $sortIcon('margin') !!}</a></th>
                    <th class="text-end"><a href="{{ request()->fullUrlWithQuery(['sort' => 'persentase', 'dir' => $nextDir('persentase'), 'page' => 1]) }}" class="text-decoration-none text-body">% HPP dari Total{!! $sortIcon('persentase') !!}</a></th>
                </tr>
            </thead>
            <tbody>
            @forelse($breakdown as $b)
                <tr>
                    <td class="small">{{ $b->nama_item }}</td>
                    <td class="text-center">
                        @php $badgeTipe = ['bahan_baku' => 'bg-primary-subtle text-primary', 'kemasan' => 'bg-warning-subtle text-warning', 'produk_jadi' => 'bg-success-subtle text-success']; @endphp
                        <span class="badge {{ $badgeTipe[$b->tipe] ?? 'bg-secondary-subtle text-secondary' }}">{{ ucwords(str_replace('_', ' ', $b->tipe)) }}</span>
                    </td>
                    <td class="text-end small">{{ rtrim(rtrim(number_format($b->total_qty, 3, ',', '.'), '0'), ',') }} {{ $b->satuan }}</td>
                    <td class="text-end small">Rp {{ number_format($b->total_omzet, 0, ',', '.') }}</td>
                    <td class="text-end small fw-semibold">Rp {{ number_format($b->total_hpp, 0, ',', '.') }}</td>
                    <td class="text-end small fw-semibold {{ $b->total_untung >= 0 ? 'text-success' : 'text-danger' }}">Rp {{ number_format($b->total_untung, 0, ',', '.') }}</td>
                    <td class="text-end small {{ $b->margin !== null && $b->margin >= 0 ? 'text-success' : ($b->margin !== null ? 'text-danger' : 'text-muted') }}">{{ $b->margin !== null ? $b->margin . '%' : '-' }}</td>
                    <td class="text-end small">{{ $b->persentase }}%</td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada konsumsi bahan di periode ini.</td></tr>
            @endforelse
            </tbody>
            @if($breakdown->total() > 0)
            <tfoot>
                <tr class="table-light fw-bold">
                    <td colspan="3" class="text-end">Total Halaman Ini:</td>
                    <td class="text-end">Rp {{ number_format($breakdown->sum('total_omzet'), 0, ',', '.') }}</td>
                    <td class="text-end">Rp {{ number_format($breakdown->sum('total_hpp'), 0, ',', '.') }}</td>
                    <td class="text-end {{ $breakdown->sum('total_untung') >= 0 ? 'text-success' : 'text-danger' }}">Rp {{ number_format($breakdown->sum('total_untung'), 0, ',', '.') }}</td>
                    <td colspan="2"></td>
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

{{-- Section: Chart Bar Top 10 HPP --}}
<div class="row g-3 mb-3">
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header py-2 px-3"><h6 class="mb-0">Top 10 Item — Nilai HPP</h6></div>
            <div class="card-body">
                <div style="position:relative;height:320px">
                    <canvas id="chartTop10"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- Section: Chart Trend 6 Bulan --}}
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header py-2 px-3"><h6 class="mb-0">Trend HPP 6 Bulan Terakhir</h6></div>
            <div class="card-body">
                <div style="position:relative;height:320px">
                    <canvas id="chartTrend"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

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
                @include('laporan.konsumsi-bahan._tabel-detail', ['rows' => $rows])
            @endforeach
        @else
        <div class="accordion" id="accordionDetail">
            @foreach($detail as $tanggal => $rows)
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed small" type="button"
                        data-bs-toggle="collapse" data-bs-target="#accDetail{{ $loop->index }}">
                        {{ \Carbon\Carbon::parse($tanggal)->translatedFormat('d M Y') }}
                        <span class="badge bg-light text-dark border ms-2">{{ $rows->count() }} baris — Rp {{ number_format($rows->sum('hpp'), 0, ',', '.') }}</span>
                    </button>
                </h2>
                <div id="accDetail{{ $loop->index }}" class="accordion-collapse collapse" data-bs-parent="#accordionDetail">
                    <div class="accordion-body p-0">
                        @include('laporan.konsumsi-bahan._tabel-detail', ['rows' => $rows])
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
    new Chart(document.getElementById('chartTop10'), {
        type: 'bar',
        data: {
            labels: @json($breakdownPenuh->take(10)->pluck('nama_item')),
            datasets: [{
                label: 'Nilai HPP',
                data: @json($breakdownPenuh->take(10)->pluck('total_hpp')),
                backgroundColor: 'rgba(13, 110, 253, 0.7)',
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

    new Chart(document.getElementById('chartTrend'), {
        type: 'line',
        data: {
            labels: @json($trend['labels']),
            datasets: [{
                label: 'Total HPP',
                data: @json($trend['data']),
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.15)',
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
})();

// Print & Export dengan peringatan range panjang — pola sama seperti Laporan
// Setoran Harian (estimasi halaman kasar, bukan hitungan presisi).
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

    var btnPrint = document.getElementById('btnPrintKonsumsi');
    if (btnPrint) {
        btnPrint.addEventListener('click', async function() {
            if (!(await konfirmasiRangePanjang())) return;
            var params = new URLSearchParams(window.location.search);
            window.open('{{ route('laporan.konsumsi-bahan.print') }}?' + params.toString(), '_blank');
        });
    }

    var btnExport = document.getElementById('btnExportKonsumsi');
    if (btnExport) {
        btnExport.addEventListener('click', async function() {
            if (!(await konfirmasiRangePanjang())) return;
            var params = new URLSearchParams(window.location.search);
            window.location.href = '{{ route('laporan.konsumsi-bahan.export') }}?' + params.toString();
        });
    }
})();
</script>
@endpush
