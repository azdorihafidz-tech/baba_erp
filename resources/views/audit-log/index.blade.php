@extends('layouts.app')
@section('title', 'Audit Log')

@push('styles')
<style>
.badge-created  { background:#dcfce7;color:#16a34a;border:1px solid #bbf7d0; }
.badge-updated  { background:#dbeafe;color:#1d4ed8;border:1px solid #bfdbfe; }
.badge-deleted  { background:#fee2e2;color:#b91c1c;border:1px solid #fecaca; }
.badge-restored { background:#fef9c3;color:#b45309;border:1px solid #fde68a; }
.badge-event    { font-size:.7rem; padding:2px 8px; border-radius:12px; font-weight:600; }
.log-row:hover  { background:#f8fafc; }
.model-chip     { font-size:.7rem; background:#f1f5f9; color:#475569; padding:2px 8px; border-radius:12px; }
</style>
@endpush

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-journal-text me-2 text-primary"></i>Audit Log</h4>
        <small class="text-muted">Rekam jejak semua perubahan data di sistem</small>
    </div>
    <div class="badge bg-secondary-subtle text-secondary">
        {{ $logs->total() }} log tersimpan
    </div>
</div>

{{-- Filter --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <x-search-box placeholder="Deskripsi / tipe subjek..." col="col-12 col-sm-6 col-md-3 col-lg-2" label="Cari" />
            <div class="col-12 col-sm-6 col-md-3 col-lg-2">
                <label class="form-label form-label-sm mb-1">Pengguna</label>
                <select name="causer_id" class="form-select form-select-sm">
                    <option value="">Semua User</option>
                    @foreach($users as $u)
                    <option value="{{ $u->id }}" {{ request('causer_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-sm-6 col-md-3 col-lg-2">
                <label class="form-label form-label-sm mb-1">Model</label>
                <select name="log_name" class="form-select form-select-sm">
                    <option value="">Semua Model</option>
                    @php
                    $modelLabels = [
                        // Keuangan & Penggajian (Fase 2)
                        'TransaksiKeuangan' => 'Kas & Transaksi',
                        'Kas'               => 'Kas',
                        'Penggajian'        => 'Penggajian',
                        'PengaturanGaji'    => 'Pengaturan Gaji',
                        // Penjualan & Pembelian (Fase 2)
                        'Order'             => 'Penjualan',
                        'OrderItem'         => 'Item Penjualan',
                        'PurchaseOrder'     => 'Pembelian',
                        'PurchaseOrderItem' => 'Item Pembelian',
                        'Supplier'          => 'Supplier',
                        // Stok & Inventory (Fase 3)
                        'Stock'             => 'Stok',
                        'StockRequest'      => 'Permintaan Stok',
                        'StockRequestItem'  => 'Item Permintaan',
                        'StockTransfer'     => 'Transfer Stok',
                        'StockTransferItem' => 'Item Transfer',
                        // Master Barang (Fase 3)
                        'Item'              => 'Master Barang',
                        'ItemCategory'      => 'Kategori Barang',
                        // Karyawan & HR (Fase 3)
                        'Karyawan'          => 'Karyawan',
                        'Absensi'           => 'Absensi',
                        // Aset (Fase 3)
                        'Asset'             => 'Aset',
                        // Master & Pendukung (Fase 4)
                        'Cabang'            => 'Cabang',
                        'User'              => 'Pengguna',
                        'Permission'        => 'Hak Akses',
                        'FaceAttendance'    => 'Log Face Recognition',
                        'AbsenDevice'       => 'Perangkat Absensi',
                        'HariLibur'         => 'Hari Libur',
                        'Shift'             => 'Shift Kerja',
                        'Pelanggan'         => 'Pelanggan',
                    ];
                    @endphp
                    @foreach($logNames as $ln)
                    <option value="{{ $ln }}" {{ request('log_name') == $ln ? 'selected' : '' }}>
                        {{ $modelLabels[$ln] ?? $ln }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-sm-6 col-md-2 col-lg-2">
                <label class="form-label form-label-sm mb-1">Aksi</label>
                <select name="event" class="form-select form-select-sm">
                    <option value="">Semua Aksi</option>
                    @foreach($events as $ev)
                    <option value="{{ $ev }}" {{ request('event') == $ev ? 'selected' : '' }}>{{ ucfirst($ev) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-sm-6 col-md-2">
                <label class="form-label form-label-sm mb-1">Dari</label>
                <input type="date" name="dari" class="form-control form-control-sm" value="{{ request('dari') }}">
            </div>
            <div class="col-12 col-sm-6 col-md-2">
                <label class="form-label form-label-sm mb-1">Sampai</label>
                <input type="date" name="sampai" class="form-control form-control-sm" value="{{ request('sampai') }}">
            </div>
            <div class="col-12 col-sm-6 col-md-auto">
                <label class="form-label form-label-sm mb-1 d-none d-md-block">&nbsp;</label>
                <div class="d-flex gap-1">
                    <button type="submit" class="btn btn-secondary btn-sm">
                        <i class="bi bi-search me-1"></i>Filter
                    </button>
                    <a href="{{ route('audit-log.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Table --}}
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light text-muted" style="font-size:.78rem">
                <tr>
                    <th style="width:44px">#</th>
                    <th>Pengguna</th>
                    <th>Aksi</th>
                    <th>Model</th>
                    <th>Deskripsi</th>
                    <th>Waktu</th>
                    <th style="width:60px"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                @php
                    $eventClass = match($log->event) {
                        'created'  => 'badge-created',
                        'updated'  => 'badge-updated',
                        'deleted'  => 'badge-deleted',
                        'restored' => 'badge-restored',
                        default    => 'bg-secondary-subtle text-secondary',
                    };
                    $eventLabel = match($log->event) {
                        'created'  => 'Buat',
                        'updated'  => 'Edit',
                        'deleted'  => 'Hapus',
                        'restored' => 'Pulihkan',
                        default    => ucfirst($log->event ?? 'log'),
                    };
                @endphp
                <tr class="log-row">
                    <td class="text-muted">{{ $loop->iteration + ($logs->currentPage() - 1) * $logs->perPage() }}</td>
                    <td>
                        <div class="fw-semibold" style="font-size:.8rem">{{ $log->causer?->name ?? '<Sistem>' }}</div>
                        @if($log->causer)
                        <div class="text-muted" style="font-size:.7rem">{{ $log->causer->role?->label() ?? '' }}</div>
                        @endif
                    </td>
                    <td>
                        <span class="badge-event {{ $eventClass }}">{{ $eventLabel }}</span>
                    </td>
                    <td>
                        <span class="model-chip">{{ $log->log_name ?? $log->subject_type }}</span>
                        @if($log->subject_id)
                        <span class="text-muted ms-1" style="font-size:.7rem">#{{ $log->subject_id }}</span>
                        @endif
                    </td>
                    <td class="text-muted" style="max-width:280px">
                        <span class="text-truncate d-block" style="max-width:280px" title="{{ $log->description }}">
                            {{ $log->description }}
                        </span>
                    </td>
                    <td class="text-muted" style="white-space:nowrap;font-size:.75rem">
                        {{ $log->created_at->format('d/m/Y') }}<br>
                        {{ $log->created_at->format('H:i:s') }}
                    </td>
                    <td>
                        <a href="{{ route('audit-log.show', $log->id) }}"
                           class="btn btn-sm btn-outline-secondary py-0 px-2" title="Lihat detail">
                            <i class="bi bi-eye" style="font-size:.8rem"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="bi bi-journal-x display-6 d-block mb-2 opacity-25"></i>
                        Belum ada audit log.
                        @if(request()->hasAny(['causer_id','log_name','event','dari','sampai']))
                        <br><a href="{{ route('audit-log.index') }}" class="btn btn-sm btn-outline-secondary mt-2">Reset Filter</a>
                        @endif
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($logs->hasPages())
    <div class="card-footer py-2">
        {{ $logs->links() }}
    </div>
    @endif
</div>
@endsection
