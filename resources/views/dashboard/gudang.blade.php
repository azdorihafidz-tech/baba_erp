@extends('layouts.app')

@section('title', 'Dashboard Gudang Pusat')

@section('content')

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0" style="color:#1e293b">Dashboard Gudang Pusat</h4>
        <p class="text-muted mb-0" style="font-size:0.875rem">
            {{ now()->translatedFormat('l, d F Y') }}
            @if($gudangPusat)
                &nbsp;|&nbsp;<i class="bi bi-building text-primary"></i> {{ $gudangPusat->nama_cabang }}
            @endif
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('pembelian.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-bag-plus me-1"></i>
            <span class="d-none d-sm-inline">Buat PO</span>
        </a>
    </div>
</div>

<!-- ===== STAT CARDS ===== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="stat-icon bg-primary bg-opacity-10">
                    <i class="bi bi-boxes text-primary"></i>
                </div>
                <span class="badge bg-primary-subtle text-primary" style="font-size:0.7rem">Gudang</span>
            </div>
            <div class="fw-bold" style="font-size:1.5rem;color:#1e293b">{{ $totalStokGudang }}</div>
            <div class="text-muted" style="font-size:0.8rem">Jenis Item di Gudang</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="stat-icon bg-warning bg-opacity-10">
                    <i class="bi bi-bell text-warning"></i>
                </div>
                @if($stockRequestPending > 0)
                    <span class="badge bg-warning-subtle text-warning" style="font-size:0.7rem">{{ $stockRequestPending }} pending</span>
                @else
                    <span class="badge bg-success-subtle text-success" style="font-size:0.7rem">Kosong</span>
                @endif
            </div>
            <div class="fw-bold" style="font-size:1.5rem;color:#1e293b">{{ $stockRequestPending }}</div>
            <div class="text-muted" style="font-size:0.8rem">Permintaan Menunggu</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="stat-icon bg-info bg-opacity-10">
                    <i class="bi bi-bag-check text-info"></i>
                </div>
                <span class="badge bg-info-subtle text-info" style="font-size:0.7rem">Aktif</span>
            </div>
            <div class="fw-bold" style="font-size:1.5rem;color:#1e293b">{{ $poAktif }}</div>
            <div class="text-muted" style="font-size:0.8rem">PO Aktif</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="stat-icon bg-danger bg-opacity-10">
                    <i class="bi bi-exclamation-triangle text-danger"></i>
                </div>
                @if($stokRendah->count() > 0)
                    <span class="badge bg-danger-subtle text-danger" style="font-size:0.7rem">{{ $stokRendah->count() }} item</span>
                @else
                    <span class="badge bg-success-subtle text-success" style="font-size:0.7rem">Aman</span>
                @endif
            </div>
            <div class="fw-bold" style="font-size:1.5rem;color:#1e293b">{{ $stokRendah->count() }}</div>
            <div class="text-muted" style="font-size:0.8rem">Stok Rendah</div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Permintaan Pending -->
    <div class="col-12 col-lg-6">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between py-3 px-4">
                <span><i class="bi bi-bell me-2 text-warning"></i>Permintaan Stok Pending</span>
                <a href="{{ route('stock-request.index') }}" class="btn btn-sm btn-outline-warning" style="font-size:0.75rem">
                    Lihat Semua
                </a>
            </div>
            <div class="card-body p-0">
                @if($permintaanPending->count() > 0)
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="px-3">No. Request</th>
                                <th>Dari Cabang</th>
                                <th>Tanggal</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($permintaanPending->take(5) as $req)
                            <tr>
                                <td class="px-3">
                                    <a href="{{ route('stock-request.show', $req) }}" class="text-decoration-none fw-medium" style="font-size:0.85rem">
                                        {{ $req->nomor_request }}
                                    </a>
                                </td>
                                <td style="font-size:0.85rem">{{ $req->cabang?->nama_cabang ?? '-' }}</td>
                                <td style="font-size:0.85rem">{{ $req->tanggal_request?->format('d/m/Y') }}</td>
                                <td>
                                    <a href="{{ route('stock-request.show', $req) }}" class="btn btn-xs btn-outline-primary" style="font-size:0.7rem;padding:2px 8px">
                                        Proses
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center text-muted py-5">
                    <i class="bi bi-check-circle text-success" style="font-size:2rem"></i>
                    <p class="mt-2 mb-0">Tidak ada permintaan pending</p>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Transfer Terbaru -->
    <div class="col-12 col-lg-6">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between py-3 px-4">
                <span><i class="bi bi-truck me-2 text-info"></i>Transfer Terbaru</span>
                <a href="{{ route('stock-transfer.index') }}" class="btn btn-sm btn-outline-info" style="font-size:0.75rem">
                    Lihat Semua
                </a>
            </div>
            <div class="card-body p-0">
                @if($transferTerakhir->count() > 0)
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="px-3">No. Transfer</th>
                                <th class="d-none d-md-table-cell">Tujuan</th>
                                <th>Status</th>
                                <th class="d-none d-sm-table-cell">Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($transferTerakhir as $transfer)
                            <tr>
                                <td class="px-3">
                                    <a href="{{ route('stock-transfer.show', $transfer) }}" class="text-decoration-none fw-medium" style="font-size:0.85rem">
                                        {{ $transfer->nomor_transfer }}
                                    </a>
                                </td>
                                <td class="d-none d-md-table-cell" style="font-size:0.85rem">
                                    {{ $transfer->keLokasi?->nama_cabang ?? '-' }}
                                </td>
                                <td>
                                    <span class="badge {{ $transfer->status?->badgeClass() ?? 'bg-secondary' }}" style="font-size:0.7rem">
                                        {{ $transfer->status?->label() ?? $transfer->status }}
                                    </span>
                                </td>
                                <td class="d-none d-sm-table-cell text-muted" style="font-size:0.8rem">
                                    {{ $transfer->tanggal_kirim?->format('d/m/Y') ?? '-' }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center text-muted py-5">
                    <i class="bi bi-inbox" style="font-size:2rem"></i>
                    <p class="mt-2 mb-0">Belum ada transfer</p>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Stok Rendah -->
    @if($stokRendah->count() > 0)
    <div class="col-12">
        <div class="card border-danger">
            <div class="card-header py-3 px-4 bg-danger-subtle">
                <i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i>
                <span class="text-danger fw-semibold">Stok Gudang Hampir Habis ({{ $stokRendah->count() }} item)</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="px-4">Nama Barang</th>
                                <th>Satuan</th>
                                <th class="text-end px-3">Stok Saat Ini</th>
                                <th class="text-end px-3">Stok Minimum</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($stokRendah as $stok)
                            <tr>
                                <td class="px-4 fw-medium" style="font-size:0.875rem">{{ $stok->item?->nama_item ?? '-' }}</td>
                                <td class="text-muted" style="font-size:0.875rem">{{ $stok->item?->satuan ?? '-' }}</td>
                                <td class="text-end px-3">
                                    <span class="badge bg-danger">{{ number_format($stok->qty, 2, ',', '.') }}</span>
                                </td>
                                <td class="text-end px-3 text-muted" style="font-size:0.875rem">
                                    {{ number_format($stok->qty_minimum, 2, ',', '.') }}
                                </td>
                                <td>
                                    <a href="{{ route('pembelian.create') }}" class="btn btn-xs btn-outline-danger" style="font-size:0.7rem;padding:2px 8px">
                                        Beli
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

@endsection
