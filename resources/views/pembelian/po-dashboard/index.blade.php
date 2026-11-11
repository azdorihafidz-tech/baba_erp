@extends('layouts.app')
@section('title', 'Dashboard PO')
@section('content')

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-clipboard-data me-2 text-primary"></i>Dashboard PO</h4>
        <small class="text-muted">Monitoring Purchase Order end-to-end — draft s/d dibayar</small>
    </div>
    <div class="d-flex gap-2">
        <button type="button" id="btnPrintPoDashboard" class="btn btn-secondary btn-sm">
            <i class="bi bi-printer me-1"></i><span class="d-none d-sm-inline">Print</span>
        </button>
        <button type="button" id="btnExportPoDashboard" class="btn btn-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i><span class="d-none d-sm-inline">Export Excel</span>
        </button>
        <x-panduan-button slug="po-dashboard" />
    </div>
</div>

{{-- Section: Filter --}}
<div class="card mb-4 border-0 shadow-sm">
    <div class="card-body pb-2 pt-3">
        <form method="GET" action="{{ route('pembelian.po-dashboard.index') }}">
            <div class="row g-2 mb-2">
                @if(auth()->user()->canAccessAllBranches())
                <div class="col-6 col-md-3">
                    <label class="form-label form-label-sm mb-1">Cabang</label>
                    <select name="cabang_id" class="form-select form-select-sm">
                        <option value="">Semua Cabang</option>
                        @foreach($cabangs as $c)
                        <option value="{{ $c->id }}" @selected($cabangId == $c->id)>{{ $c->nama_cabang }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="col-6 col-md-3">
                    <label class="form-label form-label-sm mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Semua Status</option>
                        @foreach(\App\Enums\StatusPurchaseOrder::cases() as $s)
                        @if($s->value !== 'dibatalkan')
                        <option value="{{ $s->value }}" @selected($status === $s->value)>{{ $s->label() }}</option>
                        @endif
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label form-label-sm mb-1">Supplier</label>
                    <select name="supplier_id" class="form-select form-select-sm">
                        <option value="">Semua Supplier</option>
                        @foreach($suppliers as $sp)
                        <option value="{{ $sp->id }}" @selected($supplierId == $sp->id)>{{ $sp->nama_supplier }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label form-label-sm mb-1">Umur Minimal (hari)</label>
                    <input type="number" name="umur_min" min="0" class="form-control form-control-sm" placeholder="Semua" value="{{ $umurMin }}">
                </div>
            </div>
            <div class="row g-2 mb-2">
                <div class="col-6 col-md-3">
                    <label class="form-label form-label-sm mb-1">Tanggal PO Dari</label>
                    <input type="date" name="dari" class="form-control form-control-sm" value="{{ $dari?->toDateString() }}">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label form-label-sm mb-1">Tanggal PO Sampai</label>
                    <input type="date" name="sampai" class="form-control form-control-sm" value="{{ $sampai?->toDateString() }}">
                </div>
                <div class="col-12 col-md-auto d-flex align-items-end gap-1">
                    <button type="submit" class="btn btn-primary btn-sm px-3">
                        <i class="bi bi-search me-1"></i>Filter
                    </button>
                    <a href="{{ route('pembelian.po-dashboard.index') }}" class="btn btn-outline-secondary btn-sm px-3">
                        <i class="bi bi-x-lg me-1"></i>Reset
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Section: Kartu Ringkasan per Status --}}
@php
    $bucketMeta = [
        'menunggu_approval' => ['label' => 'Menunggu Approval', 'icon' => 'hourglass-split', 'color' => 'secondary'],
        'perlu_dikirim'     => ['label' => 'Perlu Dikirim', 'icon' => 'box-arrow-up-right', 'color' => 'info'],
        'dalam_perjalanan'  => ['label' => 'Dalam Perjalanan', 'icon' => 'truck', 'color' => 'primary'],
        'belum_diterima'    => ['label' => 'Belum Diterima (total)', 'icon' => 'hourglass', 'color' => 'warning'],
        'belum_dibayar'     => ['label' => 'Belum Dibayar', 'icon' => 'cash-coin', 'color' => 'danger'],
    ];
@endphp
<div class="row g-3 mb-3">
    @foreach($bucketMeta as $key => $meta)
    @php $r = $ringkasan[$key]; @endphp
    <div class="col-6 col-lg">
        <div class="stat-card">
            <div class="stat-icon bg-{{ $meta['color'] }} bg-opacity-10 mb-2"><i class="bi bi-{{ $meta['icon'] }} text-{{ $meta['color'] }}"></i></div>
            <div class="fw-bold fs-5">{{ $r['count'] }}</div>
            <div class="text-muted" style="font-size:0.75rem">{{ $meta['label'] }}</div>
            <div class="small text-muted">Rp {{ number_format($r['total_nilai'], 0, ',', '.') }}</div>
            @if($r['count'] > 0)
            <span class="badge bg-{{ $r['badge_umur'] }} bg-opacity-75 mt-1" style="font-size:0.65rem">Maks {{ $r['umur_maks_hari'] }} hari</span>
            @endif
        </div>
    </div>
    @endforeach
</div>

{{-- Section: Chart Bar Per Status + Line Trend --}}
<div class="row g-3 mb-3">
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header py-2 px-3"><h6 class="mb-0">Jumlah PO per Status</h6></div>
            <div class="card-body">
                <div style="position:relative;height:300px">
                    <canvas id="chartPerStatus"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header py-2 px-3"><h6 class="mb-0">Trend PO Dibuat — 6 Bulan Terakhir</h6></div>
            <div class="card-body">
                <div style="position:relative;height:300px">
                    <canvas id="chartTrendPo"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Section: Tabel PO Aktif --}}
<div class="card">
    <div class="card-header py-2 px-3"><h6 class="mb-0">Daftar PO Aktif</h6></div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            @php
                $sortKolom = request('sort', 'umur_hari');
                $sortDir   = request('dir', 'desc');
                $nextDir   = fn($k) => ($sortKolom === $k && $sortDir === 'desc') ? 'asc' : 'desc';
                $sortIcon  = fn($k) => $sortKolom !== $k ? '' : ($sortDir === 'asc' ? ' <i class="bi bi-caret-up-fill"></i>' : ' <i class="bi bi-caret-down-fill"></i>');
                $sortLink  = fn($k) => request()->fullUrlWithQuery(['sort' => $k, 'dir' => $nextDir($k), 'page' => 1]);
            @endphp
            <thead class="table-light">
                <tr>
                    <th>Nomor PO</th>
                    <th>Supplier</th>
                    <th class="text-end"><a href="{{ $sortLink('total_harga') }}" class="text-decoration-none text-body">Total{!! $sortIcon('total_harga') !!}</a></th>
                    <th class="text-center">Status</th>
                    <th class="text-end"><a href="{{ $sortLink('umur_hari') }}" class="text-decoration-none text-body">Umur{!! $sortIcon('umur_hari') !!}</a></th>
                    <th>Cabang</th>
                    @can('po_dashboard.action')
                    <th class="text-center">Aksi</th>
                    @endcan
                </tr>
            </thead>
            <tbody>
            @forelse($breakdown as $po)
                <tr>
                    <td class="small"><a href="{{ route('pembelian.show', $po->id) }}">{{ $po->nomor_po }}</a></td>
                    <td class="small">{{ $po->nama_supplier ?? '-' }}</td>
                    <td class="text-end small">Rp {{ number_format($po->total_harga, 0, ',', '.') }}</td>
                    <td class="text-center">
                        <span class="badge {{ $po->status_badge_class }}">{{ $po->status_label }}</span>
                        @if($po->sudah_dibayar === true)
                        <span class="badge bg-success-subtle text-success d-block mt-1">Dibayar</span>
                        @elseif($po->sudah_dibayar === false)
                        <span class="badge bg-warning-subtle text-warning d-block mt-1">Belum Bayar</span>
                        @endif
                    </td>
                    <td class="text-end small">
                        <span class="badge bg-{{ $po->badge_umur }} bg-opacity-75">{{ $po->umur_hari }} hari</span>
                    </td>
                    <td class="small">{{ $po->nama_cabang ?? '-' }}</td>
                    @can('po_dashboard.action')
                    <td class="text-center">
                        @if(in_array($po->status, ['draft', 'disetujui']))
                        <a href="{{ route('pembelian.show', $po->id) }}" class="btn btn-sm btn-outline-primary">
                            {{ $po->status === 'draft' ? 'Setujui' : 'Kirim' }}
                        </a>
                        @elseif($po->status === 'dikirim_supplier')
                        <a href="{{ route('pembelian.show', $po->id) }}" class="btn btn-sm btn-outline-success">Cek/Update Terima</a>
                        @elseif($po->status === 'diterima' && !$po->sudah_dibayar)
                        <a href="{{ route('keuangan.create', ['po_id' => $po->id]) }}" class="btn btn-sm btn-success">Catat Pembayaran</a>
                        @elseif($po->status === 'diterima' && $po->sudah_dibayar)
                        <a href="{{ route('pembelian.show', $po->id) }}" class="btn btn-sm btn-outline-secondary">Lihat Detail</a>
                        @endif
                    </td>
                    @endcan
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada PO aktif sesuai filter ini.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($breakdown->hasPages())
    <div class="card-body py-2">
        {{ $breakdown->onEachSide(1)->links() }}
    </div>
    @endif
</div>

@endsection

@push('scripts')
<script>
(function() {
    new Chart(document.getElementById('chartPerStatus'), {
        type: 'bar',
        data: {
            labels: @json($chartStatus['labels']),
            datasets: [{
                label: 'Jumlah PO',
                data: @json($chartStatus['data']),
                backgroundColor: ['#6c757d', '#0dcaf0', '#0d6efd', '#198754'],
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { ticks: { stepSize: 1 } } }
        }
    });

    new Chart(document.getElementById('chartTrendPo'), {
        type: 'line',
        data: {
            labels: @json($trend['labels']),
            datasets: [{
                label: 'Jumlah PO Dibuat',
                data: @json($trend['data']),
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.15)',
                fill: true,
                tension: 0.3,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { ticks: { stepSize: 1 } } }
        }
    });

    var btnPrint = document.getElementById('btnPrintPoDashboard');
    if (btnPrint) {
        btnPrint.addEventListener('click', function() {
            var params = new URLSearchParams(window.location.search);
            window.open('{{ route('pembelian.po-dashboard.print') }}?' + params.toString(), '_blank');
        });
    }

    var btnExport = document.getElementById('btnExportPoDashboard');
    if (btnExport) {
        btnExport.addEventListener('click', function() {
            var params = new URLSearchParams(window.location.search);
            window.location.href = '{{ route('pembelian.po-dashboard.export') }}?' + params.toString();
        });
    }
})();
</script>
@endpush
