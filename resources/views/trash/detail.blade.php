@extends('layouts.app')
@section('title', 'Detail Data Terhapus')

@push('styles')
<style>
.info-label { font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; color:#94a3b8; }
.info-value { font-size:.9rem; font-weight:500; }
.diff-table th, .diff-table td { font-size:.8rem; padding:.45rem .65rem; }
</style>
@endpush

@section('content')
@php
    $modelLabels = [
        'orders' => 'Order/Penjualan', 'purchase_orders' => 'Purchase Order', 'suppliers' => 'Supplier',
        'absensis' => 'Absensi', 'karyawans' => 'Karyawan', 'cabangs' => 'Cabang', 'shifts' => 'Shift',
        'hari_liburs' => 'Hari Libur', 'penggajians' => 'Penggajian', 'cutis' => 'Cuti', 'users' => 'Pengguna',
        'absen_devices' => 'Perangkat Absensi', 'item_categories' => 'Kategori Barang', 'pelanggans' => 'Pelanggan',
        'stocks' => 'Stok', 'stock_requests' => 'Permintaan Stok', 'stock_transfers' => 'Transfer Stok',
        'stock_batches' => 'Batch Stok (FIFO)', 'items' => 'Master Barang', 'assets' => 'Aset',
        'transaksi_keuangans' => 'Transaksi Keuangan', 'kas' => 'Kas', 'kategori_transaksis' => 'Kategori Transaksi',
        'recurring_transaksis' => 'Transaksi Berulang',
    ];
    $identifier = $record->nomor_order ?? $record->nomor_po ?? $record->nomor_transfer ?? $record->nomor_request
        ?? $record->nomor_transaksi ?? $record->nama_lengkap ?? $record->nama_pelanggan ?? $record->nama_cabang
        ?? $record->nama_kas ?? $record->nama_item ?? $record->nama_kategori ?? $record->nama_shift
        ?? $record->device_name ?? $record->nama ?? $record->name ?? "ID #{$record->id}";
@endphp

<div class="mb-4 d-flex align-items-center gap-3 flex-wrap">
    <a href="{{ route('trash.index', ['model' => $model]) }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
    <div>
        <h4 class="fw-bold mb-0">
            {{ $modelLabels[$model] ?? $model }} — {{ $identifier }}
            <span class="badge bg-danger ms-1">TERHAPUS</span>
        </h4>
        <small class="text-muted">ID #{{ $record->id }}</small>
    </div>
</div>

{{-- Info Dasar: siapa hapus, kapan, alasan --}}
<div class="row g-3 mb-4">
    <div class="col-12 col-md-8">
        <div class="card h-100">
            <div class="card-header fw-semibold small"><i class="bi bi-info-circle me-1"></i>Info Penghapusan</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6 col-md-4">
                        <div class="info-label">Dihapus Oleh</div>
                        <div class="info-value">{{ $deletedByUser?->name ?? 'Sistem tidak mencatat' }}</div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="info-label">Waktu Dihapus</div>
                        <div class="info-value">{{ $record->deleted_at?->format('d/m/Y H:i:s') ?? '—' }}</div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="info-label">Dibuat</div>
                        <div class="info-value">{{ $record->created_at?->format('d/m/Y H:i') ?? '—' }}</div>
                    </div>
                    @php
                        $alasan = $record->alasan_pembatalan_kategori ?? $record->alasan ?? $record->catatan_approver ?? null;
                        $alasanDetail = $record->alasan_pembatalan_detail ?? null;
                    @endphp
                    <div class="col-12">
                        <div class="info-label">Alasan</div>
                        <div class="info-value">
                            @if($alasan)
                                {{ $alasan }}{{ $alasanDetail ? ' — ' . $alasanDetail : '' }}
                            @else
                                <span class="text-muted fw-normal">Tidak ada alasan tercatat</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="info-label mb-2">Aksi</div>
                <div class="d-grid gap-2">
                    @can('restore_data_terhapus')
                    <form method="POST" action="{{ route('trash.restore', [$model, $record->id]) }}"
                          onsubmit="return confirm('Pulihkan {{ addslashes($identifier) }}?')">
                        @csrf
                        <button type="submit" class="btn btn-success w-100">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Restore
                        </button>
                    </form>
                    @endcan
                    @can('hapus_permanen_data')
                    <button type="button" class="btn btn-outline-danger w-100"
                        onclick="bukaModalHapusPermanen('{{ route('trash.force-destroy', [$model, $record->id]) }}', '{{ addslashes($identifier) }}')">
                        <i class="bi bi-trash3-fill me-1"></i>Hapus Permanen
                    </button>
                    @endcan
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Detail Spesifik per Model --}}
<div class="card mb-4">
    <div class="card-header fw-semibold small"><i class="bi bi-file-text me-1"></i>Detail Data</div>
    <div class="card-body">
        @if($record instanceof \App\Models\Order)
            <div class="row g-3 mb-3">
                <div class="col-6 col-md-3"><div class="info-label">Cabang</div><div class="info-value">{{ $record->cabang?->nama_cabang ?? '—' }}</div></div>
                <div class="col-6 col-md-3"><div class="info-label">Pelanggan</div><div class="info-value">{{ $record->pelanggan?->nama_pelanggan ?? $record->nama_pelanggan ?? '—' }}</div></div>
                <div class="col-6 col-md-3"><div class="info-label">Kasir</div><div class="info-value">{{ $record->kasir?->name ?? '—' }}</div></div>
                <div class="col-6 col-md-3"><div class="info-label">Tanggal Order</div><div class="info-value">{{ $record->tanggal_order?->format('d/m/Y') ?? '—' }}</div></div>
                <div class="col-6 col-md-3"><div class="info-label">Status</div><div class="info-value">{{ $record->status?->value ?? $record->getRawOriginal('status') }}</div></div>
                <div class="col-6 col-md-3"><div class="info-label">Tipe Pembayaran</div><div class="info-value">{{ $record->tipe_pembayaran ?? '—' }}</div></div>
                <div class="col-6 col-md-3"><div class="info-label">Total Bayar</div><div class="info-value">Rp {{ number_format($record->total_bayar ?? 0, 0, ',', '.') }}</div></div>
                <div class="col-6 col-md-3"><div class="info-label">Kas</div><div class="info-value">{{ $record->kas?->nama_kas ?? '—' }}</div></div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Item</th><th class="text-end">Qty</th><th class="text-end">Harga</th><th class="text-end">Subtotal</th></tr></thead>
                    <tbody>
                        @forelse($record->items ?? [] as $it)
                        <tr>
                            <td>{{ $it->nama_item ?? $it->item?->nama_item ?? '—' }}</td>
                            <td class="text-end">{{ rtrim(rtrim(number_format($it->qty ?? 0, 3, ',', '.'), '0'), ',') }} {{ $it->satuan }}</td>
                            <td class="text-end">Rp {{ number_format($it->harga_satuan ?? 0, 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($it->total_harga ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">Tidak ada item</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        @elseif($record instanceof \App\Models\PurchaseOrder)
            <div class="row g-3 mb-3">
                <div class="col-6 col-md-3"><div class="info-label">Cabang</div><div class="info-value">{{ $record->cabang?->nama_cabang ?? '—' }}</div></div>
                <div class="col-6 col-md-3"><div class="info-label">Supplier</div><div class="info-value">{{ $record->supplier?->nama_supplier ?? '—' }}</div></div>
                <div class="col-6 col-md-3"><div class="info-label">Status</div><div class="info-value">{{ $record->status?->value ?? $record->getRawOriginal('status') }}</div></div>
                <div class="col-6 col-md-3"><div class="info-label">Total Harga</div><div class="info-value">Rp {{ number_format($record->total_harga ?? 0, 0, ',', '.') }}</div></div>
                <div class="col-6 col-md-3"><div class="info-label">Dibuat Oleh</div><div class="info-value">{{ $record->createdBy?->name ?? '—' }}</div></div>
                <div class="col-6 col-md-3"><div class="info-label">Disetujui Oleh</div><div class="info-value">{{ $record->approvedBy?->name ?? '—' }}</div></div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Item</th><th class="text-end">Qty Pesan</th><th class="text-end">Harga</th><th class="text-end">Subtotal</th></tr></thead>
                    <tbody>
                        @forelse($record->items ?? [] as $it)
                        <tr>
                            <td>{{ $it->item?->nama_item ?? '—' }}</td>
                            <td class="text-end">{{ $it->qty_pesan }}</td>
                            <td class="text-end">Rp {{ number_format($it->harga_satuan ?? 0, 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($it->total_harga ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">Tidak ada item</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        @elseif($record instanceof \App\Models\Kas)
            <div class="row g-3 mb-3">
                <div class="col-6 col-md-3"><div class="info-label">Cabang</div><div class="info-value">{{ $record->cabang?->nama_cabang ?? '—' }}</div></div>
                <div class="col-6 col-md-3"><div class="info-label">Tipe</div><div class="info-value">{{ ucfirst($record->tipe_kas ?? '—') }}</div></div>
                <div class="col-6 col-md-3"><div class="info-label">Saldo Awal</div><div class="info-value">Rp {{ number_format($record->saldo_awal ?? 0, 0, ',', '.') }}</div></div>
                <div class="col-6 col-md-3"><div class="info-label">Saldo Sekarang (terakhir)</div><div class="info-value">Rp {{ number_format($record->saldo_sekarang ?? 0, 0, ',', '.') }}</div></div>
            </div>
            <div class="fw-semibold small mb-2">Riwayat Transaksi Terakhir</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Tanggal</th><th>Tipe</th><th>Keterangan</th><th class="text-end">Jumlah</th></tr></thead>
                    <tbody>
                        @forelse($kasRiwayat ?? [] as $t)
                        <tr>
                            <td>{{ $t->tanggal_transaksi?->format('d/m/Y') }}</td>
                            <td>{{ $t->tipe?->label() ?? $t->tipe }}</td>
                            <td>{{ $t->keterangan }}</td>
                            <td class="text-end">Rp {{ number_format($t->jumlah, 0, ',', '.') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">Tidak ada riwayat transaksi</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        @elseif($record instanceof \App\Models\TransaksiKeuangan)
            @php
                $isSetoran = in_array($record->kategoriDinamis?->kode, ['SETOR-OUT', 'SETOR-IN']);
            @endphp
            @if($isSetoran)
            <div class="alert alert-info small mb-3"><i class="bi bi-send me-1"></i>Ini adalah baris <strong>Setoran</strong> ({{ $record->kategoriDinamis?->kode }}) — pasangannya (kas asal/tujuan) tercatat sebagai row terpisah via <code>setoran_pair_id</code>.</div>
            @endif
            <div class="row g-3 mb-3">
                <div class="col-6 col-md-3"><div class="info-label">Cabang</div><div class="info-value">{{ $record->cabang?->nama_cabang ?? '—' }}</div></div>
                <div class="col-6 col-md-3"><div class="info-label">Tipe</div><div class="info-value">{{ $record->tipe?->label() ?? $record->tipe }}</div></div>
                <div class="col-6 col-md-3"><div class="info-label">Kategori</div><div class="info-value">{{ $record->kategoriDinamis?->nama ?? $record->kategori?->label() ?? '—' }}</div></div>
                <div class="col-6 col-md-3"><div class="info-label">Kas</div><div class="info-value">{{ $record->kas?->nama_kas ?? '— (non-cash)' }}</div></div>
                <div class="col-6 col-md-3"><div class="info-label">Jumlah</div><div class="info-value">Rp {{ number_format($record->jumlah ?? 0, 0, ',', '.') }}</div></div>
                <div class="col-6 col-md-3"><div class="info-label">Tanggal Transaksi</div><div class="info-value">{{ $record->tanggal_transaksi?->format('d/m/Y') ?? '—' }}</div></div>
                <div class="col-6 col-md-3"><div class="info-label">Dibuat Oleh</div><div class="info-value">{{ $record->createdBy?->name ?? '—' }}</div></div>
            </div>
            @if($referensi)
            <div class="border rounded p-3" style="background:#f8fafc">
                <div class="info-label mb-1">Referensi ({{ $referensi['type'] }})</div>
                @if($referensi['record'])
                    @php $r = $referensi['record']; @endphp
                    <div class="info-value">
                        {{ $r->nomor_order ?? $r->nomor_po ?? $r->nama_kas ?? "ID #{$r->id}" }}
                    </div>
                @else
                    <div class="text-muted small">Data referensi tidak ditemukan (mungkin sudah dihapus permanen).</div>
                @endif
            </div>
            @endif

        @elseif($record instanceof \App\Models\Cuti)
            <div class="row g-3">
                <div class="col-6 col-md-3"><div class="info-label">Karyawan</div><div class="info-value">{{ $record->karyawan?->nama_lengkap ?? '—' }}</div></div>
                <div class="col-6 col-md-3"><div class="info-label">Cabang</div><div class="info-value">{{ $record->cabang?->nama_cabang ?? '—' }}</div></div>
                <div class="col-6 col-md-3"><div class="info-label">Tipe</div><div class="info-value">{{ $record->label_tipe ?? $record->tipe }}</div></div>
                <div class="col-6 col-md-3"><div class="info-label">Status</div><div class="info-value">{{ ucfirst($record->status ?? '—') }}</div></div>
                <div class="col-6 col-md-3"><div class="info-label">Tanggal Mulai</div><div class="info-value">{{ $record->tanggal_mulai?->format('d/m/Y') ?? '—' }}</div></div>
                <div class="col-6 col-md-3"><div class="info-label">Tanggal Selesai</div><div class="info-value">{{ $record->tanggal_selesai?->format('d/m/Y') ?? '—' }}</div></div>
                <div class="col-6 col-md-3"><div class="info-label">Disetujui Oleh</div><div class="info-value">{{ $record->approvedBy?->name ?? '—' }}</div></div>
            </div>

        @elseif($record instanceof \App\Models\Karyawan)
            <div class="row g-3">
                <div class="col-6 col-md-3"><div class="info-label">NIK</div><div class="info-value">{{ $record->nik ?? '—' }}</div></div>
                <div class="col-6 col-md-3"><div class="info-label">Cabang</div><div class="info-value">{{ $record->cabang?->nama_cabang ?? '—' }}</div></div>
                <div class="col-6 col-md-3"><div class="info-label">Jabatan</div><div class="info-value">{{ $record->jabatan ?? '—' }}</div></div>
                <div class="col-6 col-md-3"><div class="info-label">Status</div><div class="info-value">{{ ucfirst($record->status ?? '—') }}</div></div>
                <div class="col-6 col-md-3"><div class="info-label">Shift</div><div class="info-value">{{ $record->shift?->nama_shift ?? '—' }}</div></div>
                <div class="col-6 col-md-3"><div class="info-label">Akun User</div><div class="info-value">{{ $record->user?->name ?? 'Belum ada' }}</div></div>
            </div>

        @elseif($record instanceof \App\Models\Pelanggan)
            <div class="row g-3">
                <div class="col-6 col-md-4"><div class="info-label">Kode</div><div class="info-value">{{ $record->kode_pelanggan ?? '—' }}</div></div>
                <div class="col-6 col-md-4"><div class="info-label">Telepon</div><div class="info-value">{{ $record->telepon ?? '—' }}</div></div>
                <div class="col-6 col-md-4"><div class="info-label">Jumlah Order</div><div class="info-value">{{ $record->orders()->withTrashed()->count() }}</div></div>
            </div>

        @elseif($record instanceof \App\Models\Item)
            <div class="row g-3">
                <div class="col-6 col-md-3"><div class="info-label">Kode Item</div><div class="info-value">{{ $record->kode_item ?? '—' }}</div></div>
                <div class="col-6 col-md-3"><div class="info-label">Kategori</div><div class="info-value">{{ $record->category?->nama_kategori ?? '—' }}</div></div>
                <div class="col-6 col-md-3"><div class="info-label">Tipe</div><div class="info-value">{{ ucfirst($record->tipe ?? '—') }}</div></div>
                <div class="col-6 col-md-3"><div class="info-label">Harga Jual</div><div class="info-value">Rp {{ number_format($record->harga_jual ?? 0, 0, ',', '.') }}</div></div>
            </div>

        @elseif($record instanceof \App\Models\Stock)
            <div class="row g-3">
                <div class="col-6 col-md-4"><div class="info-label">Item</div><div class="info-value">{{ $record->item?->nama_item ?? '—' }}</div></div>
                <div class="col-6 col-md-4"><div class="info-label">Lokasi</div><div class="info-value">{{ $record->lokasi?->nama_cabang ?? '—' }}</div></div>
                <div class="col-6 col-md-4"><div class="info-label">Qty (terakhir)</div><div class="info-value">{{ $record->qty ?? 0 }}</div></div>
            </div>

        @elseif($record instanceof \App\Models\Asset)
            <div class="row g-3">
                <div class="col-6 col-md-3"><div class="info-label">Kode Aset</div><div class="info-value">{{ $record->kode_aset ?? '—' }}</div></div>
                <div class="col-6 col-md-3"><div class="info-label">Kategori</div><div class="info-value">{{ $record->kategori?->nama_kategori ?? '—' }}</div></div>
                <div class="col-6 col-md-3"><div class="info-label">Lokasi</div><div class="info-value">{{ $record->lokasi?->nama_cabang ?? '—' }}</div></div>
                <div class="col-6 col-md-3"><div class="info-label">Nilai Buku</div><div class="info-value">Rp {{ number_format($record->nilai_buku ?? 0, 0, ',', '.') }}</div></div>
            </div>

        @else
            {{-- Generic fallback: seluruh attribute record dalam bentuk tabel key-value --}}
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                    <tbody>
                        @foreach($record->getAttributes() as $key => $value)
                            @if(!in_array($key, ['password', 'remember_token']))
                            <tr>
                                <td class="fw-semibold text-muted" style="width:220px">{{ $key }}</td>
                                <td>{{ Str::limit((string) $value, 200) }}</td>
                            </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

{{-- Activity Log --}}
<div class="card mb-4">
    <div class="card-header fw-semibold small d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history me-1"></i>Activity Log</span>
        <span class="text-muted">{{ $activities->count() }} entri</span>
    </div>
    <div class="table-responsive">
        <table class="table diff-table mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Waktu</th>
                    <th>Aksi</th>
                    <th>Oleh</th>
                    <th>Deskripsi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($activities as $log)
                @php
                    $eventClass = match($log->event) {
                        'created' => 'text-success', 'updated' => 'text-primary',
                        'deleted' => 'text-danger',  'restored' => 'text-warning',
                        default   => 'text-secondary',
                    };
                @endphp
                <tr>
                    <td style="white-space:nowrap">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                    <td><span class="{{ $eventClass }} fw-bold">{{ ucfirst($log->event ?? 'log') }}</span></td>
                    <td>{{ $log->causer?->name ?? '<Sistem>' }}</td>
                    <td>{{ $log->description }}</td>
                </tr>
                @empty
                <tr><td colspan="4" class="text-center text-muted py-4">Belum ada activity log untuk data ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@can('hapus_permanen_data')
@include('trash._modal-hapus-permanen')
@endcan

@endsection
