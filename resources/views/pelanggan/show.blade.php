@extends('layouts.app')

@section('title', 'Detail Pelanggan — ' . $pelanggan->nama_pelanggan)

@section('content')
{{-- Header --}}
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <div>
        <nav aria-label="breadcrumb" class="mb-1">
            <ol class="breadcrumb breadcrumb-sm mb-0">
                <li class="breadcrumb-item"><a href="{{ route('pelanggan.index') }}">Pelanggan</a></li>
                <li class="breadcrumb-item active">{{ $pelanggan->nama_pelanggan }}</li>
            </ol>
        </nav>
        <h5 class="mb-0 fw-bold">
            <i class="bi bi-person-heart me-2 text-success"></i>{{ $pelanggan->nama_pelanggan }}
            @if(!$pelanggan->is_active)
                <span class="badge bg-secondary ms-1 fs-6">Nonaktif</span>
            @endif
        </h5>
    </div>
    <div class="d-flex gap-2">
        @can('pelanggan.edit')
        <a href="{{ route('pelanggan.edit', $pelanggan) }}" class="btn btn-sm btn-outline-warning">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        @endcan
        <a href="{{ route('penjualan.pos') }}" class="btn btn-sm btn-primary">
            <i class="bi bi-cart-plus me-1"></i>Order Baru
        </a>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- Info & Stats --}}
<div class="row g-3 mb-4">
    {{-- Profil --}}
    <div class="col-12 col-md-4">
        <div class="card h-100">
            <div class="card-header py-2"><i class="bi bi-person-lines-fill me-2"></i>Profil Pelanggan</div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted">Kode</dt>
                    <dd class="col-7 mb-2"><code class="text-success">{{ $pelanggan->kode_pelanggan }}</code></dd>

                    <dt class="col-5 text-muted">Nama</dt>
                    <dd class="col-7 mb-2 fw-semibold">{{ $pelanggan->nama_pelanggan }}</dd>

                    @if($pelanggan->telepon)
                    <dt class="col-5 text-muted">Telepon</dt>
                    <dd class="col-7 mb-2">{{ $pelanggan->telepon }}</dd>
                    @endif

                    @if($pelanggan->email)
                    <dt class="col-5 text-muted">Email</dt>
                    <dd class="col-7 mb-2">{{ $pelanggan->email }}</dd>
                    @endif

                    @if($pelanggan->kota)
                    <dt class="col-5 text-muted">Kota</dt>
                    <dd class="col-7 mb-2">{{ $pelanggan->kota }}</dd>
                    @endif

                    @if($pelanggan->alamat)
                    <dt class="col-5 text-muted">Alamat</dt>
                    <dd class="col-7 mb-2">{{ $pelanggan->alamat }}</dd>
                    @endif

                    @if($pelanggan->catatan)
                    <dt class="col-5 text-muted">Catatan</dt>
                    <dd class="col-7 mb-0">{{ $pelanggan->catatan }}</dd>
                    @endif
                </dl>
            </div>
        </div>
    </div>

    {{-- Statistik --}}
    <div class="col-12 col-md-8">
        <div class="row g-3 h-100">
            <div class="col-6 col-lg-4">
                <div class="card text-center h-100 border-primary border-opacity-50">
                    <div class="card-body">
                        <div class="fs-2 fw-bold text-primary">{{ $pelanggan->orders_count }}</div>
                        <div class="text-muted small">Total Order</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-4">
                <div class="card text-center h-100 border-success border-opacity-50">
                    <div class="card-body">
                        <div class="fs-5 fw-bold text-success">
                            <i class="bi bi-cash-coin me-1"></i>Rp {{ number_format($totalPembelian, 0, ',', '.') }}
                        </div>
                        <div class="text-muted small">Total Pembelian</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-4">
                <div class="card text-center h-100 border-info border-opacity-50">
                    <div class="card-body">
                        <div class="fs-6 fw-bold text-info">
                            {{ $orderTerakhir ? \Carbon\Carbon::parse($orderTerakhir)->format('d M Y') : '-' }}
                        </div>
                        <div class="text-muted small">Order Terakhir</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-4">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <div class="fs-5 fw-bold">
                            @if($pelanggan->orders_count > 0)
                                Rp {{ number_format($totalPembelian / $pelanggan->orders_count, 0, ',', '.') }}
                            @else
                                Rp 0
                            @endif
                        </div>
                        <div class="text-muted small">Rata-rata / Order</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@can('loyalty.view')
@if($loyaltyProgress->isNotEmpty())
{{-- Program Loyalty --}}
<div class="card mb-3">
    <div class="card-header py-2"><i class="bi bi-award me-2"></i>Program Loyalty</div>
    <div class="card-body">
        @foreach($loyaltyProgress as $progress)
        <div class="mb-3 {{ !$loop->last ? 'pb-3 border-bottom' : '' }}">
            <div class="d-flex justify-content-between align-items-center mb-1 flex-wrap gap-1">
                <span class="fw-semibold">
                    <a href="{{ route('loyalty-program.show', $progress['program']->id) }}">{{ $progress['program']->nama }}</a>
                </span>
                @if($progress['tercapai'])
                <span class="badge bg-warning text-dark">Tercapai</span>
                @else
                <span class="badge bg-secondary-subtle text-secondary">Dalam Progress</span>
                @endif
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="progress flex-grow-1" style="height:10px;">
                    <div class="progress-bar {{ $progress['tercapai'] ? 'bg-success' : 'bg-primary' }}"
                         style="width:{{ $progress['persen_progress'] }}%"></div>
                </div>
                <small class="text-nowrap">{{ number_format($progress['total_kg'], 1) }} / {{ number_format($progress['target_kg'], 0) }} {{ $progress['program']->satuan_qty }} ({{ $progress['persen_progress'] }}%)</small>
            </div>
            @if($progress['jumlah_order_tanpa_data'] > 0)
            @php
                // Bug fix 2026-09-21: teks ini dulu hardcode "berat gilingan"/
                // "kg" apapun basis program-nya — sama seperti bug yang sudah
                // difix di loyalty-program/show.blade.php (lihat CLAUDE.md).
                $tanpaDataLabelPelanggan = $progress['program']->sumber_data === 'orders.berat_daging_kg'
                    ? 'belum ada data berat gilingan'
                    : 'nilai basis-nya kosong/nol';
            @endphp
            <small class="text-warning-emphasis">
                <i class="bi bi-exclamation-triangle me-1"></i>{{ $progress['jumlah_order_tanpa_data'] }} order {{ $tanpaDataLabelPelanggan }} (dihitung 0 {{ $progress['program']->satuan_qty }})
            </small>
            @endif
            @can('loyalty.manage')
            @if($progress['tercapai'])
            <form method="POST" action="{{ route('loyalty-program.tandai-hadiah', $progress['program']->id) }}" class="mt-2"
                  onsubmit="return confirm('Tandai hadiah untuk {{ $pelanggan->nama_pelanggan }} sudah diberikan?')">
                @csrf
                <input type="hidden" name="pelanggan_id" value="{{ $pelanggan->id }}">
                <button type="submit" class="btn btn-sm btn-outline-success">
                    <i class="bi bi-gift me-1"></i>Tandai Hadiah Diberikan
                </button>
            </form>
            @endif
            @endcan
        </div>
        @endforeach
    </div>
</div>
@endif
@endcan

@can('loyalty.klaim.view')
@if($loyaltyKlaims->isNotEmpty())
{{-- Riwayat Klaim Loyalty (Event-Based) --}}
<div class="card mb-3">
    <div class="card-header py-2"><i class="bi bi-megaphone me-2"></i>Riwayat Klaim Loyalty (Event)</div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Program</th>
                    <th class="text-center">Status</th>
                    <th class="d-none d-md-table-cell text-end">Nominal</th>
                    <th class="d-none d-md-table-cell">Tanggal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($loyaltyKlaims as $klaim)
                <tr>
                    <td class="small">{{ $klaim->loyaltyProgram->nama ?? '-' }}</td>
                    <td class="text-center">
                        @switch($klaim->status)
                            @case('pending')
                                <span class="badge bg-warning-subtle text-warning">Pending</span>
                                @break
                            @case('approved')
                                <span class="badge bg-info-subtle text-info">Approved</span>
                                @break
                            @case('rejected')
                                <span class="badge bg-danger-subtle text-danger" title="{{ $klaim->rejected_reason }}">Rejected</span>
                                @break
                            @case('issued')
                                <span class="badge bg-success-subtle text-success">Issued</span>
                                @break
                        @endswitch
                    </td>
                    <td class="d-none d-md-table-cell text-end small">{{ $klaim->nominal_voucher ? 'Rp '.number_format($klaim->nominal_voucher,0,',','.') : '-' }}</td>
                    <td class="d-none d-md-table-cell small text-muted">{{ $klaim->created_at->format('d M Y') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endcan

{{-- Riwayat Order --}}
<div class="card">
    <div class="card-header d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 py-2">
        <span><i class="bi bi-receipt me-2"></i>Riwayat Order</span>
        <form method="GET" class="d-flex gap-2">
            <select name="status" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                <option value="">Semua Status</option>
                @foreach($statuses as $s)
                <option value="{{ $s->value }}" {{ request('status') === $s->value ? 'selected' : '' }}>
                    {{ $s->label() }}
                </option>
                @endforeach
            </select>
            @if(request('status'))
            <a href="{{ route('pelanggan.show', $pelanggan) }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            @endif
        </form>
    </div>

    {{-- Desktop Table --}}
    <div class="d-none d-md-block">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>No. Order</th>
                        <th>Tanggal</th>
                        <th>Cabang</th>
                        <th>Tipe</th>
                        <th class="text-end">Total</th>
                        <th>Pembayaran</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                    <tr>
                        <td><code class="text-primary">{{ $order->nomor_order }}</code></td>
                        <td>{{ $order->tanggal_order->format('d M Y') }}</td>
                        <td>{{ $order->cabang?->nama_cabang ?? '-' }}</td>
                        <td>
                            <span class="badge {{ $order->tipe_order->value === 'jasa_giling' ? 'bg-info text-dark' : 'bg-success' }} text-nowrap">
                                {{ $order->tipe_order->label() }}
                            </span>
                        </td>
                        <td class="text-end fw-semibold">Rp {{ number_format($order->total_bayar, 0, ',', '.') }}</td>
                        <td>
                            <span class="badge bg-light text-dark text-nowrap">
                                {{ $order->tipe_pembayaran->label() }}
                            </span>
                        </td>
                        <td>
                            <span class="badge {{ $order->status->badgeClass() }}">
                                {{ $order->status->label() }}
                            </span>
                        </td>
                        <td>
                            <a href="{{ route('penjualan.show', $order) }}" class="btn btn-sm btn-outline-secondary" title="Detail">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>Belum ada riwayat order
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Mobile Card View --}}
    <div class="d-md-none card-body px-2 py-2">
        @forelse($orders as $order)
        <div class="card mb-2 border">
            <div class="card-body py-2 px-3">
                <div class="d-flex justify-content-between align-items-start mb-1">
                    <div>
                        <code class="text-primary small">{{ $order->nomor_order }}</code>
                        <div class="text-muted" style="font-size: 0.75rem;">
                            {{ $order->tanggal_order->format('d M Y') }}
                            @if($order->cabang) &bull; {{ $order->cabang->nama_cabang }} @endif
                        </div>
                    </div>
                    <span class="badge {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex gap-1">
                        <span class="badge {{ $order->tipe_order->value === 'jasa_giling' ? 'bg-info text-dark' : 'bg-success' }} small">
                            {{ $order->tipe_order->label() }}
                        </span>
                        <span class="badge bg-light text-dark small">{{ $order->tipe_pembayaran->label() }}</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="fw-bold">Rp {{ number_format($order->total_bayar, 0, ',', '.') }}</span>
                        <a href="{{ route('penjualan.show', $order) }}" class="btn btn-sm btn-outline-secondary py-0 px-1">
                            <i class="bi bi-eye"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="text-center text-muted py-4">
            <i class="bi bi-inbox fs-3 d-block mb-2"></i>Belum ada riwayat order
        </div>
        @endforelse
    </div>

    @if($orders->hasPages())
    <div class="card-footer">
        {{ $orders->links() }}
    </div>
    @endif
</div>
@endsection
