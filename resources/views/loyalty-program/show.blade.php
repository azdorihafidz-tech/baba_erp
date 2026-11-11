@extends('layouts.app')

@section('title', $loyaltyProgram->nama)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0 fw-bold">
            <i class="bi bi-award me-2 text-primary"></i>{{ $loyaltyProgram->nama }}
            @if($loyaltyProgram->tipe_program === 'event_based')
            <span class="badge bg-info-subtle text-info align-middle">Event-Based</span>
            @else
            <span class="badge bg-primary-subtle text-primary align-middle">Auto-Track</span>
            @endif
        </h4>
        @if($loyaltyProgram->tipe_program === 'auto_track')
        <small class="text-muted">Target {{ number_format($loyaltyProgram->target_qty_kg, 0, ',', '.') }} {{ $loyaltyProgram->satuan_qty }} — {{ $loyaltyProgram->periode_mulai ? $loyaltyProgram->periode_mulai->format('d M Y') : 'All-time' }} s/d {{ $loyaltyProgram->periode_akhir ? $loyaltyProgram->periode_akhir->format('d M Y') : 'sekarang' }}</small>
        @else
        <small class="text-muted">Klaim manual (1x per pelanggan) — {{ $loyaltyProgram->periode_mulai ? $loyaltyProgram->periode_mulai->format('d M Y') : 'All-time' }} s/d {{ $loyaltyProgram->periode_akhir ? $loyaltyProgram->periode_akhir->format('d M Y') : 'sekarang' }}</small>
        @endif
    </div>
    <div class="d-flex gap-2">
        @can('loyalty.manage')
        <a href="{{ route('loyalty-program.edit', $loyaltyProgram) }}" class="btn btn-outline-warning btn-sm">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        @endcan
        <a href="{{ route('loyalty-program.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

@if($loyaltyProgram->tipe_program === 'auto_track')
<div class="row g-3 mb-3">
    <div class="col-12 col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="text-muted mb-2">Hadiah</h6>
                <p class="mb-0">{{ $loyaltyProgram->hadiah }}</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card h-100 text-center">
            <div class="card-body">
                <h6 class="text-muted mb-1 small">Total Pelanggan</h6>
                <div class="fs-4 fw-bold">{{ $progressSemua->count() }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card h-100 text-center">
            <div class="card-body">
                <h6 class="text-muted mb-1 small">Sudah Tercapai</h6>
                <div class="fs-4 fw-bold text-success">{{ $progressSemua->where('tercapai', true)->count() }}</div>
            </div>
        </div>
    </div>
</div>

@php
    $sumberLabel = match($loyaltyProgram->sumber_data) {
        'orders.berat_daging_kg' => 'berat gilingan (orders.berat_daging_kg, legacy)',
        'orders.count' => 'jumlah transaksi (orders.count)',
        default => 'total belanja (orders.total_bayar)',
    };
    $tanpaDataLabel = $loyaltyProgram->sumber_data === 'orders.berat_daging_kg'
        ? 'belum diisi berat gilingan-nya'
        : 'nilai basis-nya kosong/nol';
@endphp
<div class="alert alert-info py-2 small mb-3">
    <i class="bi bi-info-circle me-1"></i>
    Progress dihitung dari <code>{{ $sumberLabel }}</code> per order, bukan jumlah baris bumbu/kemasan.
    Order yang {{ $tanpaDataLabel }} dianggap 0 {{ $loyaltyProgram->satuan_qty }} untuk sementara — kalau ada pelanggan yang progress-nya terasa kurang, cek kolom "Order Tanpa Data".
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:40px">#</th>
                    <th>Pelanggan</th>
                    <th style="min-width:180px">Progress</th>
                    <th class="text-center d-none d-md-table-cell">Order Tanpa Data</th>
                    <th class="text-center" style="min-width:120px">Status</th>
                    <th class="text-center" style="min-width:100px">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($progressSemua as $i => $row)
                @php
                    $pencapaian = $pencapaianList->get($row['pelanggan_id']);
                    $sudahHadiah = $pencapaian && $pencapaian->status === 'hadiah_diberikan';
                @endphp
                <tr>
                    <td class="small text-muted">{{ $i + 1 }}</td>
                    <td class="fw-semibold">{{ $row['nama_pelanggan'] }}</td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress flex-grow-1" style="height:8px; min-width:80px;">
                                <div class="progress-bar {{ $row['tercapai'] ? 'bg-success' : 'bg-primary' }}"
                                     style="width:{{ $row['persen_progress'] }}%"></div>
                            </div>
                            <small class="text-nowrap">{{ number_format($row['total_kg'], 1) }} / {{ number_format($row['target_kg'], 0) }} {{ $loyaltyProgram->satuan_qty }} ({{ $row['persen_progress'] }}%)</small>
                        </div>
                    </td>
                    <td class="text-center d-none d-md-table-cell">
                        @if($row['jumlah_order_tanpa_data'] > 0)
                        <span class="badge bg-warning-subtle text-warning" title="Order dgn {{ $tanpaDataLabel }}, dihitung 0 {{ $loyaltyProgram->satuan_qty }}">
                            {{ $row['jumlah_order_tanpa_data'] }} order
                        </span>
                        @else
                        <span class="text-muted small">-</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($sudahHadiah)
                        <span class="badge bg-success">Hadiah Diberikan</span>
                        @elseif($row['tercapai'])
                        <span class="badge bg-warning text-dark">Tercapai</span>
                        @else
                        <span class="badge bg-secondary-subtle text-secondary">Dalam Progress</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @can('loyalty.manage')
                        @if($row['tercapai'] && !$sudahHadiah)
                        <form method="POST" action="{{ route('loyalty-program.tandai-hadiah', $loyaltyProgram) }}"
                              onsubmit="return confirm('Tandai hadiah untuk {{ $row['nama_pelanggan'] }} sudah diberikan?')">
                            @csrf
                            <input type="hidden" name="pelanggan_id" value="{{ $row['pelanggan_id'] }}">
                            <button type="submit" class="btn btn-sm btn-outline-success px-2 py-1">
                                <i class="bi bi-gift me-1"></i>Tandai Hadiah
                            </button>
                        </form>
                        @endif
                        @endcan
                        <a href="{{ route('pelanggan.show', $row['pelanggan_id']) }}" class="btn btn-sm btn-outline-primary px-2 py-1 mt-1" title="Detail Pelanggan">
                            <i class="bi bi-person"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">Belum ada pelanggan yang order jasa giling.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@else
{{-- Event-based: daftar klaim manual + aksi approve/reject/issued --}}
<div class="row g-3 mb-3">
    <div class="col-12 col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="text-muted mb-2">Hadiah</h6>
                <p class="mb-0">{{ $loyaltyProgram->hadiah }}</p>
                @if($loyaltyProgram->nominal_voucher)
                <small class="text-muted">Nominal voucher default: Rp {{ number_format($loyaltyProgram->nominal_voucher, 0, ',', '.') }}</small>
                @endif
            </div>
        </div>
    </div>
    <div class="col-4 col-md-2">
        <div class="card h-100 text-center">
            <div class="card-body">
                <h6 class="text-muted mb-1 small">Pending</h6>
                <div class="fs-4 fw-bold text-warning">{{ $klaims->where('status', 'pending')->count() }}</div>
            </div>
        </div>
    </div>
    <div class="col-4 col-md-2">
        <div class="card h-100 text-center">
            <div class="card-body">
                <h6 class="text-muted mb-1 small">Approved</h6>
                <div class="fs-4 fw-bold text-info">{{ $klaims->whereIn('status', ['approved','issued'])->count() }}</div>
            </div>
        </div>
    </div>
    <div class="col-4 col-md-2">
        <div class="card h-100 text-center">
            <div class="card-body">
                <h6 class="text-muted mb-1 small">Issued</h6>
                <div class="fs-4 fw-bold text-success">{{ $klaims->where('status', 'issued')->count() }}</div>
            </div>
        </div>
    </div>
</div>

@include('loyalty-klaim._table', ['klaims' => $klaims, 'loyaltyProgram' => $loyaltyProgram])
@endif
@endsection
