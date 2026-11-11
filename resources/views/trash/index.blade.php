@extends('layouts.app')
@section('title', 'Data Terhapus')

@push('styles')
<style>
.model-tab { cursor:pointer; padding:.4rem 1rem; border-radius:20px; font-size:.8rem; font-weight:500;
    border:1px solid #e2e8f0; background:white; color:#64748b; text-decoration:none; transition:all .15s; }
.model-tab.active, .model-tab:hover { background:#3b82f6; border-color:#3b82f6; color:white; }
.model-tab .badge-count { background:#e2e8f0; color:#475569; border-radius:10px; padding:.05rem .45rem; font-size:.7rem; margin-left:.35rem; }
.model-tab.active .badge-count, .model-tab:hover .badge-count { background:rgba(255,255,255,.25); color:white; }
.model-tab.has-data:not(.active) { border-color:#fbbf24; }
.trash-row { opacity:.85; }
.trash-row:hover { opacity:1; background:#fefce8; }
</style>
@endpush

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-trash3 me-2 text-danger"></i>Data Terhapus</h4>
        <small class="text-muted">Pulihkan atau hapus permanen data yang sudah dihapus</small>
    </div>
    <x-panduan-button slug="data-terhapus" />
</div>

<div class="alert alert-warning d-flex gap-2 mb-3" style="font-size:.85rem">
    <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
    <span>Data di sini sudah di-soft delete. Klik <strong>Pulihkan</strong> untuk kembalikan, atau <strong>Hapus Permanen</strong> untuk hapus selamanya (tidak bisa diurungkan).</span>
</div>

{{-- Model tabs --}}
@php
$modelLabels = [
    'orders'           => ['Orders/Penjualan', 'bi-cart3'],
    'purchase_orders'  => ['Purchase Order', 'bi-bag-plus'],
    'suppliers'        => ['Supplier', 'bi-truck'],
    'absensis'         => ['Absensi', 'bi-calendar-check'],
    'karyawans'        => ['Karyawan', 'bi-people'],
    'cabangs'          => ['Cabang', 'bi-diagram-3'],
    'shifts'           => ['Shift', 'bi-clock'],
    'hari_liburs'      => ['Hari Libur', 'bi-calendar2-x'],
    'penggajians'      => ['Penggajian', 'bi-cash-stack'],
    'cutis'            => ['Cuti', 'bi-calendar2-week'],
    // Fase 4
    'users'            => ['Pengguna', 'bi-person-circle'],
    'absen_devices'    => ['Perangkat Absensi', 'bi-tablet'],
    'item_categories'  => ['Kategori Barang', 'bi-tags'],
    'pelanggans'       => ['Pelanggan', 'bi-people-fill'],
    // Fase 3
    'stocks'              => ['Stok', 'bi-boxes'],
    'stock_requests'      => ['Permintaan Stok', 'bi-clipboard-plus'],
    'stock_transfers'     => ['Transfer Stok', 'bi-arrow-left-right'],
    'stock_batches'       => ['Batch Stok (FIFO)', 'bi-layers'],
    'items'               => ['Master Barang', 'bi-box-seam'],
    'assets'              => ['Aset', 'bi-building-gear'],
    // Keuangan
    'transaksi_keuangans' => ['Transaksi Keuangan', 'bi-cash'],
    'kas'                 => ['Kas', 'bi-wallet2'],
    'kategori_transaksis' => ['Kategori Transaksi', 'bi-bookmark'],
    'recurring_transaksis'=> ['Transaksi Berulang', 'bi-arrow-repeat'],
    'chart_of_accounts'   => ['Chart of Accounts', 'bi-diagram-2'],
    // Tahap 7 D'mentai (Bug 4 audit sync, 2026-09-15) — 8 model ini SUDAH
    // terdaftar & berfungsi penuh (restore/detail/forceDestroy) di
    // TrashController::$models sejak Tahap 5/2, tapi TIDAK PERNAH punya tab
    // di sini -- cuma bisa diakses kalau tahu ketik manual "?model=...", jadi
    // efektif tidak pernah bisa ditemukan user lewat UI normal. Ditemukan
    // saat audit "Data Terhapus mengcover semua menu" (CLAUDE.md 4.13).
    'setorans'              => ['Setoran Kasir', 'bi-cash-coin'],
    'loyalty_programs'      => ['Program Loyalty', 'bi-gift'],
    'loyalty_pencapaian'    => ['Pencapaian Loyalty', 'bi-trophy'],
    'loyalty_klaims'        => ['Klaim Loyalty', 'bi-award'],
    'item_variants'         => ['Varian Produk', 'bi-diagram-3'],
    'item_attributes'       => ['Atribut Varian', 'bi-sliders'],
    'item_attribute_values' => ['Nilai Atribut Varian', 'bi-list-ul'],
    'order_payments'        => ['Pembayaran Order (Split)', 'bi-credit-card'],
];
@endphp

@php $totalTerhapus = collect($counts ?? [])->sum(); @endphp
<div class="d-flex flex-wrap gap-2 mb-3">
    @foreach($modelLabels as $key => [$label, $icon])
    @php $jumlah = $counts[$key] ?? 0; @endphp
    <a href="{{ route('trash.index', ['model' => $key]) }}"
       class="model-tab {{ $activeModel === $key ? 'active' : '' }} {{ $jumlah > 0 ? 'has-data' : '' }}">
        <i class="bi {{ $icon }} me-1"></i>{{ $label }}
        <span class="badge-count">{{ $jumlah }}</span>
    </a>
    @endforeach
</div>
@if($totalTerhapus === 0)
<div class="alert alert-light border small mb-3">
    <i class="bi bi-info-circle me-1"></i>Tidak ada data terhapus sama sekali di seluruh kategori saat ini.
</div>
@endif

{{-- Table --}}
<div class="card">
    @if($rows instanceof \Illuminate\Pagination\LengthAwarePaginator && $rows->count() > 0)
    <div class="card-header small fw-semibold d-flex justify-content-between align-items-center">
        <span>
            <i class="bi bi-{{ $modelLabels[$activeModel][1] ?? 'list' }} me-1"></i>
            {{ $modelLabels[$activeModel][0] ?? $activeModel }}
        </span>
        <span class="text-muted">{{ $rows->total() }} record terhapus</span>
    </div>
    @endif

    <div class="table-responsive">
        <table class="table align-middle mb-0 small">
            <thead class="table-light text-muted" style="font-size:.78rem">
                <tr>
                    <th style="width:60px">ID</th>
                    <th>Data</th>
                    <th>Dihapus</th>
                    <th style="width:160px" class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                <tr class="trash-row">
                    <td class="text-muted">#{{ $row->id }}</td>
                    <td>
                        @php
                            $identifier = $row->nama_lengkap
                                ?? $row->nama_pelanggan
                                ?? $row->nama_cabang
                                ?? $row->nama_kategori
                                ?? $row->nama_shift
                                ?? $row->device_name
                                ?? $row->nama
                                ?? $row->name
                                ?? $row->nomor_transaksi
                                ?? $row->nomor_po
                                ?? $row->nomor_order
                                ?? "ID #{$row->id}";
                        @endphp
                        <div class="fw-semibold">{{ $identifier }}</div>
                        <div class="text-muted" style="font-size:.72rem">
                            @foreach(array_slice($row->toArray(), 0, 3) as $k => $v)
                                @if(!in_array($k, ['id','deleted_at','created_at','updated_at','password','remember_token']) && !is_null($v) && !is_array($v))
                                <span>{{ $k }}: {{ Str::limit((string)$v, 30) }}</span>
                                @if(!$loop->last) &bull; @endif
                                @endif
                            @endforeach
                        </div>
                    </td>
                    <td class="text-muted" style="white-space:nowrap">
                        @if($row->deleted_at)
                        <div>{{ $row->deleted_at->format('d/m/Y') }}</div>
                        <div style="font-size:.72rem">{{ $row->deleted_at->format('H:i') }}</div>
                        @else
                        <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end">
                            <a href="{{ route('trash.detail', [$activeModel, $row->id]) }}"
                               class="btn btn-sm btn-outline-primary py-0 px-2" title="Lihat detail lengkap">
                                <i class="bi bi-eye"></i>
                            </a>
                            @can('restore_data_terhapus')
                            <form method="POST" action="{{ route('trash.restore', [$activeModel, $row->id]) }}"
                                  onsubmit="return confirm('Pulihkan {{ addslashes($identifier) }}?')">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-success py-0 px-2" title="Pulihkan data ini">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </button>
                            </form>
                            @endcan
                            @can('hapus_permanen_data')
                            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" title="Hapus permanen"
                                onclick="bukaModalHapusPermanen('{{ route('trash.force-destroy', [$activeModel, $row->id]) }}', '{{ addslashes($identifier) }}')">
                                <i class="bi bi-trash3-fill"></i>
                            </button>
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="text-center py-5 text-muted">
                        <i class="bi bi-check-circle display-6 d-block mb-2 text-success opacity-50"></i>
                        Tidak ada data terhapus di kategori <strong>{{ $modelLabels[$activeModel][0] ?? $activeModel }}</strong>.
                        @if($totalTerhapus > 0)
                        <div class="mt-1" style="font-size:.8rem">Coba filter kategori lain — ada {{ $totalTerhapus }} data terhapus di kategori lain (lihat badge angka di tab atas).</div>
                        @endif
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($rows instanceof \Illuminate\Pagination\LengthAwarePaginator && $rows->hasPages())
    <div class="card-footer py-2">
        {{ $rows->links() }}
    </div>
    @endif
</div>

@if(session('success'))
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div class="toast show align-items-center text-bg-success border-0">
        <div class="d-flex">
            <div class="toast-body">{{ session('success') }}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>
@endif

@can('hapus_permanen_data')
@include('trash._modal-hapus-permanen')
@endcan

@endsection
