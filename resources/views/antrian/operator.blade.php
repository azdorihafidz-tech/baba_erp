@extends('layouts.app')

@section('title', 'Operator Antrian — ' . $cabang->nama_cabang)

@push('styles')
<style>
.antrian-badge {
    font-size: 1.8rem; font-weight: 900; line-height: 1;
    min-width: 60px; text-align: center; display: inline-block;
}
.card-antrian { border-left: 4px solid #e2e8f0; transition: border-color 0.2s; }
.card-antrian.status-menunggu   { border-left-color: #94a3b8; }
.card-antrian.status-dikerjakan { border-left-color: #f59e0b; }
.card-antrian.status-selesai    { border-left-color: #22c55e; }
.card-antrian.status-disimpan   { border-left-color: #3b82f6; }
.card-antrian.status-diambil    { border-left-color: #cbd5e1; opacity: 0.75; }
.refresh-indicator { font-size: 0.75rem; color: #94a3b8; }
</style>
@endpush

@section('content')
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <div>
        <h5 class="mb-0 fw-bold"><i class="bi bi-list-ol me-2 text-warning"></i>Kelola Antrian</h5>
        <small class="text-muted">{{ $cabang->nama_cabang }} — {{ now()->setTimezone('Asia/Jakarta')->format('d M Y') }}</small>
    </div>
    <div class="d-flex gap-2 align-items-center">
        @if($tab === 'aktif')
        <span class="refresh-indicator" id="lastRefresh">–</span>
        @endif
        @can('antrian.lihat')
        <a href="{{ route('antrian.cek') }}" class="btn btn-sm btn-outline-info">
            <i class="bi bi-search me-1"></i>Cek Antrian
        </a>
        @endcan
        <a href="{{ route('antrian.produksi') }}" class="btn btn-sm btn-success">
            <i class="bi bi-display me-1"></i>Mode Produksi
        </a>
        <a href="{{ route('antrian.display', $cabang) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-tv me-1"></i>Display TV
        </a>
        <a href="{{ route('antrian.operator') }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-arrow-clockwise me-1"></i>Refresh
        </a>
        <x-panduan-button slug="antrian-operator" />
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show py-2" role="alert">
    {!! session('success') !!}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
    {!! session('error') !!}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- Tab Navigation --}}
<div class="d-flex gap-1 flex-wrap mb-3">
    <a href="{{ route('antrian.operator', ['tab' => 'aktif']) }}"
       class="btn btn-sm {{ $tab === 'aktif' ? 'btn-warning' : 'btn-outline-warning' }}">
        <i class="bi bi-hourglass-split me-1"></i>Aktif Hari Ini
    </a>
    <a href="{{ route('antrian.operator', ['tab' => 'selesai_hari_ini']) }}"
       class="btn btn-sm {{ $tab === 'selesai_hari_ini' ? 'btn-secondary' : 'btn-outline-secondary' }}">
        <i class="bi bi-bag-check me-1"></i>Sudah Diambil Hari Ini
    </a>
    <a href="{{ route('antrian.operator', ['tab' => 'semua', 'tanggal_dari' => $tanggalDari, 'tanggal_sampai' => $tanggalSampai]) }}"
       class="btn btn-sm {{ $tab === 'semua' ? 'btn-dark' : 'btn-outline-dark' }}">
        <i class="bi bi-archive me-1"></i>Semua / History
    </a>
</div>

{{-- Filter (hanya untuk tab semua) --}}
<form method="GET" action="{{ route('antrian.operator') }}" class="mb-3">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <div class="card">
        <div class="card-body py-2">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-sm-4 col-md-3">
                    <input type="text" name="search" class="form-control form-control-sm"
                        placeholder="Cari nomor / nama / order..."
                        value="{{ $search }}">
                </div>
                @if($tab === 'semua')
                <div class="col-6 col-md-2">
                    <input type="date" name="tanggal_dari" class="form-control form-control-sm"
                        value="{{ $tanggalDari }}">
                </div>
                <div class="col-6 col-md-2">
                    <input type="date" name="tanggal_sampai" class="form-control form-control-sm"
                        value="{{ $tanggalSampai }}">
                </div>
                @endif
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-search me-1"></i>Filter
                    </button>
                    <a href="{{ route('antrian.operator', ['tab' => $tab]) }}" class="btn btn-outline-secondary btn-sm ms-1">
                        <i class="bi bi-x-circle me-1"></i>Reset
                    </a>
                </div>
            </div>
        </div>
    </div>
</form>

{{-- Ringkasan (hanya untuk tab aktif) --}}
@if($tab === 'aktif')
@php
$byStatus = $orders->groupBy(fn($o) => $o->status_produksi?->value ?? 'lainnya');
$cntMenunggu   = $byStatus->get('menunggu', collect())->count();
$cntDikerjakan = $byStatus->get('dikerjakan', collect())->count();
$cntSelesai    = $byStatus->get('selesai', collect())->count();
$cntDisimpan   = $byStatus->get('disimpan', collect())->count();
@endphp
<div class="d-flex gap-2 flex-wrap mb-3">
    <span class="badge bg-secondary fs-6 px-3">⏳ Menunggu: {{ $cntMenunggu }}</span>
    <span class="badge bg-warning text-dark fs-6 px-3">🔧 Dikerjakan: {{ $cntDikerjakan }}</span>
    <span class="badge bg-success fs-6 px-3">✓ Selesai: {{ $cntSelesai }}</span>
    <span class="badge bg-info text-dark fs-6 px-3">📦 Di Rak: {{ $cntDisimpan }}</span>
</div>
@else
<div class="mb-2 text-muted small">
    Menampilkan <strong>{{ $orders->count() }}</strong> order
    @if($tab === 'semua')
    dari {{ \Carbon\Carbon::parse($tanggalDari)->format('d M Y') }}
    s/d {{ \Carbon\Carbon::parse($tanggalSampai)->format('d M Y') }}
    @else
    sudah diambil hari ini
    @endif
</div>
@endif

@if($orders->isEmpty())
<div class="card">
    <div class="card-body text-center text-muted py-5">
        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
        @if($tab === 'aktif') Belum ada antrian aktif hari ini.
        @elseif($tab === 'selesai_hari_ini') Belum ada order yang sudah diambil hari ini.
        @else Tidak ada order ditemukan.
        @endif
    </div>
</div>

@elseif($tab !== 'aktif')

{{-- Tab: Selesai Hari Ini / Semua — flat list --}}
<div class="row g-2">
    @foreach($orders as $order)
    @php $st = $order->status_produksi?->value ?? 'diambil'; @endphp
    <div class="col-12">
        <div class="card card-antrian status-{{ $st }}">
            <div class="card-body py-2 px-3">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div class="antrian-badge
                        @if($st==='menunggu') text-secondary
                        @elseif($st==='dikerjakan') text-warning
                        @elseif($st==='selesai') text-success
                        @elseif($st==='disimpan') text-info
                        @else text-muted @endif">
                        {{ str_pad($order->nomor_antrian, 3, '0', STR_PAD_LEFT) }}
                    </div>
                    <div class="flex-fill">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="fw-semibold">{{ $order->nama_pelanggan ?: 'Walk-in' }}</span>
                            <span class="badge
                                @if($st==='menunggu') bg-secondary
                                @elseif($st==='dikerjakan') bg-warning text-dark
                                @elseif($st==='selesai') bg-success
                                @elseif($st==='disimpan') bg-info text-dark
                                @else bg-light text-muted border @endif">
                                {{ $order->status_produksi?->label() ?? 'Diambil' }}
                            </span>
                            @if($tab === 'semua')
                            <small class="text-muted">{{ $order->tanggal_order?->format('d M') }}</small>
                            @endif
                        </div>
                        <div class="small text-muted d-flex flex-wrap gap-2">
                            <span>{{ $order->nomor_order }}</span>
                            @if($order->berat_daging_kg)
                            <span><i class="bi bi-boxes me-1"></i>{{ number_format($order->berat_daging_kg, 2) }} kg</span>
                            @endif
                            @if($order->dikerjakanOleh)
                            <span><i class="bi bi-person me-1"></i>{{ $order->dikerjakanOleh->nama_lengkap }}</span>
                            @endif
                            @if($order->waktu_selesai_kerja)
                            <span><i class="bi bi-check-circle me-1"></i>{{ $order->waktu_selesai_kerja->setTimezone('Asia/Jakarta')->format('H:i') }}</span>
                            @endif
                            @if($order->waktu_diambil)
                            <span><i class="bi bi-bag-check me-1"></i>Diambil: {{ $order->waktu_diambil->setTimezone('Asia/Jakarta')->format('H:i') }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="d-flex gap-1 flex-shrink-0">
                        <a href="{{ route('penjualan.struk', $order) }}" target="_blank"
                           class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-printer me-1"></i>
                            <span class="d-none d-sm-inline">Cetak Ulang</span>
                        </a>
                        <a href="{{ route('penjualan.show', $order) }}"
                           class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-eye"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

@else

{{-- Tab: Aktif — grouped by status --}}

@php $menunggu = $orders->filter(fn($o) => $o->status_produksi === \App\Enums\StatusProduksi::Menunggu); @endphp
@if($menunggu->isNotEmpty())
<div class="mb-3">
    <h6 class="text-secondary fw-semibold mb-2"><i class="bi bi-hourglass-split me-1"></i>Menunggu</h6>
    @foreach($menunggu as $order)
    <div class="card card-antrian status-menunggu mb-2">
        <div class="card-body py-2 px-3">
            <div class="d-flex align-items-center gap-3">
                <div class="antrian-badge text-secondary">{{ str_pad($order->nomor_antrian, 3, '0', STR_PAD_LEFT) }}</div>
                <div class="flex-fill">
                    <div class="fw-semibold">{{ $order->nama_pelanggan ?: 'Walk-in' }}</div>
                    <div class="small text-muted">
                        {{ $order->nomor_order }}
                        @if($order->berat_daging_kg)
                        <span class="ms-2"><i class="bi bi-boxes me-1"></i>{{ number_format($order->berat_daging_kg, 2) }} kg</span>
                        @endif
                    </div>
                </div>
                <div>
                    <button type="button" class="btn btn-warning btn-sm"
                        data-bs-toggle="modal" data-bs-target="#modalMulaiKerja{{ $order->id }}">
                        <i class="bi bi-play-fill me-1"></i>
                        <span class="d-none d-sm-inline">Mulai Kerjakan</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    {{-- Modal Mulai Kerja --}}
    <div class="modal fade" id="modalMulaiKerja{{ $order->id }}" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title fw-bold">Mulai Kerjakan — Antrian #{{ str_pad($order->nomor_antrian, 3, '0', STR_PAD_LEFT) }}</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="{{ route('antrian.mulai-kerja', $order) }}">
                    @csrf
                    <div class="modal-body">
                        <p class="text-muted small mb-3">
                            Pilih operator yang akan mengerjakan order <strong>{{ $order->nama_pelanggan ?: 'Walk-in' }}</strong>
                            @if($order->berat_daging_kg) ({{ number_format($order->berat_daging_kg, 2) }} kg) @endif
                        </p>
                        <label class="form-label fw-semibold">Operator / Karyawan</label>
                        <select name="karyawan_id" class="form-select" required>
                            <option value="">— Pilih Operator —</option>
                            @foreach($karyawans as $k)
                            <option value="{{ $k->id }}">{{ $k->nama_lengkap }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-warning"><i class="bi bi-play-fill me-1"></i>Mulai Kerjakan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif

@php $dikerjakan = $orders->filter(fn($o) => $o->status_produksi === \App\Enums\StatusProduksi::Dikerjakan); @endphp
@if($dikerjakan->isNotEmpty())
<div class="mb-3">
    <h6 class="text-warning fw-semibold mb-2"><i class="bi bi-tools me-1"></i>Sedang Dikerjakan</h6>
    @foreach($dikerjakan as $order)
    <div class="card card-antrian status-dikerjakan mb-2">
        <div class="card-body py-2 px-3">
            <div class="d-flex align-items-center gap-3">
                <div class="antrian-badge text-warning">{{ str_pad($order->nomor_antrian, 3, '0', STR_PAD_LEFT) }}</div>
                <div class="flex-fill">
                    <div class="fw-semibold">{{ $order->nama_pelanggan ?: 'Walk-in' }}</div>
                    <div class="small text-muted">
                        @if($order->dikerjakanOleh) <i class="bi bi-person me-1"></i>{{ $order->dikerjakanOleh->nama_lengkap }} @endif
                        @if($order->berat_daging_kg) <span class="ms-2"><i class="bi bi-boxes me-1"></i>{{ number_format($order->berat_daging_kg, 2) }} kg</span> @endif
                        @if($order->waktu_mulai_kerja) <span class="ms-2"><i class="bi bi-clock me-1"></i>{{ $order->waktu_mulai_kerja->setTimezone('Asia/Jakarta')->format('H:i') }}</span> @endif
                    </div>
                </div>
                <div>
                    <form method="POST" action="{{ route('antrian.selesai', $order) }}">
                        @csrf
                        <button type="submit" class="btn btn-success btn-sm"
                            onclick="return confirm('Tandai order ini selesai?')">
                            <i class="bi bi-check-lg me-1"></i>
                            <span class="d-none d-sm-inline">Selesai</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif

@php $selesai = $orders->filter(fn($o) => $o->status_produksi === \App\Enums\StatusProduksi::Selesai); @endphp
@if($selesai->isNotEmpty())
<div class="mb-3">
    <h6 class="text-success fw-semibold mb-2"><i class="bi bi-check-circle me-1"></i>Selesai — Siap Diambil</h6>
    @foreach($selesai as $order)
    <div class="card card-antrian status-selesai mb-2">
        <div class="card-body py-2 px-3">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <div class="antrian-badge text-success">{{ str_pad($order->nomor_antrian, 3, '0', STR_PAD_LEFT) }}</div>
                <div class="flex-fill">
                    <div class="fw-semibold">{{ $order->nama_pelanggan ?: 'Walk-in' }}</div>
                    <div class="small text-muted">
                        @if($order->waktu_selesai_kerja) Selesai jam {{ $order->waktu_selesai_kerja->setTimezone('Asia/Jakarta')->format('H:i') }} @endif
                    </div>
                </div>
                <div class="d-flex gap-1 flex-wrap">
                    <button type="button" class="btn btn-info btn-sm text-dark"
                        data-bs-toggle="modal" data-bs-target="#modalRak{{ $order->id }}">
                        <i class="bi bi-archive me-1"></i>
                        <span class="d-none d-sm-inline">Simpan di Rak</span>
                    </button>
                    <form method="POST" action="{{ route('antrian.diambil', $order) }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-success btn-sm"
                            onclick="return confirm('Konfirmasi pelanggan sudah ambil?')">
                            <i class="bi bi-bag-check me-1"></i>
                            <span class="d-none d-sm-inline">Diambil</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    {{-- Modal Simpan di Rak --}}
    <div class="modal fade" id="modalRak{{ $order->id }}" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title fw-bold">Simpan di Rak — #{{ str_pad($order->nomor_antrian, 3, '0', STR_PAD_LEFT) }}</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="{{ route('antrian.simpan-rak', $order) }}">
                    @csrf
                    <div class="modal-body">
                        <label class="form-label fw-semibold">Nomor / Lokasi Rak <span class="text-muted fw-normal">(opsional)</span></label>
                        <input type="text" name="lokasi_rak" class="form-control" maxlength="50" placeholder="contoh: A1, B3, Rak Kiri...">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-info text-dark"><i class="bi bi-archive me-1"></i>Simpan di Rak</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif

@php $disimpan = $orders->filter(fn($o) => $o->status_produksi === \App\Enums\StatusProduksi::Disimpan); @endphp
@if($disimpan->isNotEmpty())
<div class="mb-3">
    <h6 class="text-info fw-semibold mb-2"><i class="bi bi-archive me-1"></i>Di Rak — Menunggu Diambil</h6>
    @foreach($disimpan as $order)
    <div class="card card-antrian status-disimpan mb-2">
        <div class="card-body py-2 px-3">
            <div class="d-flex align-items-center gap-3">
                <div class="antrian-badge text-info">{{ str_pad($order->nomor_antrian, 3, '0', STR_PAD_LEFT) }}</div>
                <div class="flex-fill">
                    <div class="fw-semibold">{{ $order->nama_pelanggan ?: 'Walk-in' }}</div>
                    <div class="small text-muted">
                        @if($order->lokasi_rak) <i class="bi bi-archive me-1"></i>{{ $order->lokasi_rak }} @endif
                        @if($order->waktu_disimpan) <span class="ms-2"><i class="bi bi-clock me-1"></i>{{ $order->waktu_disimpan->setTimezone('Asia/Jakarta')->format('H:i') }}</span> @endif
                    </div>
                </div>
                <div>
                    <form method="POST" action="{{ route('antrian.diambil', $order) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-sm"
                            onclick="return confirm('Konfirmasi pelanggan sudah ambil?')">
                            <i class="bi bi-bag-check me-1"></i>
                            <span class="d-none d-sm-inline">Sudah Diambil</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif

@if($orders->filter(fn($o) => $o->status_produksi !== null)->isEmpty())
<div class="card">
    <div class="card-body text-center text-muted py-5">
        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
        Tidak ada antrian aktif hari ini.
    </div>
</div>
@endif

@endif {{-- end tab aktif --}}
@endsection

@push('scripts')
@if($tab === 'aktif' && config('broadcasting.connections.pusher.key'))
<script src="https://js.pusher.com/8.4.0-rc2/pusher.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.15.3/dist/echo.iife.js"></script>
@endif
<script>
@if($tab === 'aktif')
(function() {
    var PUSHER_KEY     = @json(config('broadcasting.connections.pusher.key') ?? '');
    var PUSHER_CLUSTER = @json(config('broadcasting.connections.pusher.options.cluster') ?? 'ap1');
    var CABANG_ID      = {{ $cabang->id }};
    var CSRF           = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    // Interval auto-reload: 10s default, naik jadi 30s saat Pusher connected
    var countdownMax   = 10;
    var countdown      = countdownMax;
    var reloadPending  = false;
    var el = document.getElementById('lastRefresh');

    function scheduleReload() {
        clearInterval(tickTimer);
        countdown = countdownMax;
        tickTimer = setInterval(function() {
            countdown--;
            if (el) el.textContent = 'Refresh dalam ' + countdown + 's';
            if (countdown <= 0 && !reloadPending) {
                reloadPending = true;
                window.location.reload();
            }
        }, 1000);
    }

    var tickTimer = null;
    scheduleReload();

    // Pusher real-time (jika key tersedia)
    if (PUSHER_KEY && typeof Echo !== 'undefined') {
        var echo = new Echo({
            broadcaster:  'pusher',
            key:          PUSHER_KEY,
            cluster:      PUSHER_CLUSTER,
            forceTLS:     true,
            authEndpoint: '/broadcasting/auth',
            auth: { headers: { 'X-CSRF-TOKEN': CSRF } },
        });

        function onAntrianEvent() {
            // Reload segera (debounce 800ms)
            if (reloadPending) return;
            reloadPending = true;
            setTimeout(function() { window.location.reload(); }, 800);
        }

        echo.private('antrian.' + CABANG_ID)
            .listen('.antrian.created', onAntrianEvent)
            .listen('.antrian.updated', onAntrianEvent)
            .listen('.antrian.removed', onAntrianEvent);

        echo.connector.pusher.connection.bind('connected', function () {
            // Pusher connected: slowed countdown (30s), update indicator
            countdownMax = 30;
            countdown    = countdownMax;
            if (el) {
                el.innerHTML = '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#4ade80;margin-right:4px;vertical-align:middle;"></span>Real-time aktif';
            }
        });

        echo.connector.pusher.connection.bind('disconnected', function () {
            countdownMax = 10;
            if (el) el.textContent = 'Polling (Pusher off)';
        });
    }
})();
@endif
</script>
@endpush
