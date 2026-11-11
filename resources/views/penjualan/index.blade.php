@extends('layouts.app')

@section('title', 'Riwayat Penjualan')

@section('content')
@php $isOwner = auth()->user()->role === \App\Enums\RoleUser::Owner; @endphp
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-cart3 me-2 text-success"></i>Riwayat Penjualan</h5>
    <div class="d-flex gap-2 align-items-center">
        <a href="{{ route('penjualan.pos') }}" class="btn btn-success btn-sm">
            <i class="bi bi-plus-circle me-1"></i>Buka POS
        </a>
        <x-panduan-button slug="riwayat-order" />
    </div>
</div>

{{-- Filter --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <x-search-box placeholder="Nomor / pelanggan / telepon / catatan..." col="col-12 col-sm-6 col-md-3" />
            <div class="col-6 col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua Status</option>
                    @foreach($statuses as $s)
                    <option value="{{ $s->value }}" @selected(request('status') == $s->value)>{{ $s->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <input type="date" name="dari" class="form-control form-control-sm"
                    value="{{ request('dari') }}" placeholder="Dari tanggal">
            </div>
            <div class="col-6 col-md-2">
                <input type="date" name="sampai" class="form-control form-control-sm"
                    value="{{ request('sampai') }}" placeholder="Sampai">
            </div>
            <div class="col-6 col-md-1">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Desktop Table --}}
<div class="card d-none d-md-block">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Nomor Order</th>
                        <th>Tanggal</th>
                        <th>Jam</th>
                        <th>Pelanggan</th>
                        <th class="text-center">Tipe</th>
                        <th class="text-end">Total Bayar</th>
                        <th class="text-center">Pembayaran</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $o)
                    <tr>
                        <td>
                            <a href="{{ route('penjualan.show', $o) }}" class="text-decoration-none fw-semibold">
                                {{ $o->nomor_order }}
                            </a>
                        </td>
                        <td>{{ $o->tanggal_order->format('d/m/Y') }}</td>
                        <td><span class="text-muted small">{{ $o->created_at?->format('H:i') ?? '-' }}</span></td>
                        <td>{{ $o->nama_pelanggan ?? $o->pelanggan?->nama_pelanggan ?? '-' }}</td>
                        <td class="text-center">
                            <span class="badge bg-secondary">
                                {{ $o->tipe_order->label() }}
                            </span>
                        </td>
                        <td class="text-end fw-semibold">Rp {{ number_format($o->total_bayar, 0, ',', '.') }}</td>
                        <td class="text-center">
                            <span class="badge bg-light text-dark">{{ $o->tipe_pembayaran->label() }}</span>
                        </td>
                        <td class="text-center">
                            <span class="badge {{ $o->status->badgeClass() }}">{{ $o->status->label() }}</span>
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('penjualan.show', $o) }}" class="btn btn-outline-primary" title="Detail">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <button type="button" class="btn btn-outline-secondary" title="Cetak Struk 1x"
                                        onclick="openStrukModal({{ $o->id }})">
                                    <i class="bi bi-printer"></i>
                                </button>
                                <button type="button" class="btn btn-outline-secondary" title="Cetak Struk 2x"
                                        onclick="openStrukModal({{ $o->id }}, 2)">
                                    <i class="bi bi-printer-fill"></i><sub style="font-size:8px">2x</sub>
                                </button>
                                @can('order.edit')
                                @if(auth()->user()->role === \App\Enums\RoleUser::Owner || !in_array($o->status, [\App\Enums\StatusOrder::Selesai, \App\Enums\StatusOrder::Dibatalkan]))
                                <a href="{{ route('penjualan.edit', $o) }}" class="btn btn-outline-warning" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                @endif
                                @endcan
                                @can('order.batalkan')
                                @if($o->status === \App\Enums\StatusOrder::Selesai)
                                <button type="button" class="btn btn-outline-danger" title="{{ ($isOwner || $o->created_at->isToday()) ? 'Batalkan (stok & kas dikembalikan)' : 'Order dari hari sebelumnya, hanya Owner yang bisa membatalkan' }}"
                                    @disabled(!($isOwner || $o->created_at->isToday()))
                                    onclick="confirmBatalkan('{{ addslashes($o->nomor_order) }}', '{{ route('penjualan.batalkan', $o->id) }}')">
                                    <i class="bi bi-x-circle"></i>
                                </button>
                                @endif
                                @endcan
                                @can('order.delete')
                                @if(in_array($o->status, [\App\Enums\StatusOrder::Pending, \App\Enums\StatusOrder::Dibatalkan]))
                                <button type="button" class="btn btn-outline-secondary" title="Hapus"
                                    onclick="confirmHapus('{{ addslashes($o->nomor_order) }}', '{{ route('penjualan.destroy', $o->id) }}')">
                                    <i class="bi bi-trash"></i>
                                </button>
                                @endif
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>Tidak ada data penjualan
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Mobile Card View --}}
<div class="d-md-none">
    @forelse($orders as $o)
    <div class="card mb-2">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <a href="{{ route('penjualan.show', $o) }}" class="fw-semibold text-decoration-none">
                        {{ $o->nomor_order }}
                    </a>
                    <div class="text-muted small">{{ $o->tanggal_order->format('d/m/Y') }} &bull; {{ $o->created_at?->format('H:i') ?? '-' }}</div>
                </div>
                <span class="badge {{ $o->status->badgeClass() }}">{{ $o->status->label() }}</span>
            </div>
            <div class="text-muted small mb-2">
                <div><i class="bi bi-person me-1"></i>{{ $o->nama_pelanggan ?? $o->pelanggan?->nama_pelanggan ?? 'Walk-in' }}</div>
                <div><i class="bi bi-cash me-1"></i>Rp {{ number_format($o->total_bayar, 0, ',', '.') }} - {{ $o->tipe_pembayaran->label() }}</div>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('penjualan.show', $o) }}" class="btn btn-sm btn-outline-primary flex-fill">
                    <i class="bi bi-eye"></i> Detail
                </a>
                <button type="button" class="btn btn-sm btn-outline-secondary" title="Cetak Struk 1x"
                        onclick="openStrukModal({{ $o->id }})">
                    <i class="bi bi-printer"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" title="Cetak Struk 2x"
                        onclick="openStrukModal({{ $o->id }}, 2)">
                    <i class="bi bi-printer-fill"></i><sub style="font-size:8px">2x</sub>
                </button>
                @can('order.edit')
                @if(auth()->user()->role === \App\Enums\RoleUser::Owner || !in_array($o->status, [\App\Enums\StatusOrder::Selesai, \App\Enums\StatusOrder::Dibatalkan]))
                <a href="{{ route('penjualan.edit', $o) }}" class="btn btn-sm btn-outline-warning">
                    <i class="bi bi-pencil"></i>
                </a>
                @endif
                @endcan
                @can('order.batalkan')
                @if($o->status === \App\Enums\StatusOrder::Selesai)
                <button type="button" class="btn btn-sm btn-outline-danger" title="Batalkan"
                    @disabled(!($isOwner || $o->created_at->isToday()))
                    onclick="confirmBatalkan('{{ addslashes($o->nomor_order) }}', '{{ route('penjualan.batalkan', $o->id) }}')">
                    <i class="bi bi-x-circle"></i>
                </button>
                @endif
                @endcan
                @can('order.delete')
                @if(in_array($o->status, [\App\Enums\StatusOrder::Pending, \App\Enums\StatusOrder::Dibatalkan]))
                <button type="button" class="btn btn-sm btn-outline-secondary" title="Hapus"
                    onclick="confirmHapus('{{ addslashes($o->nomor_order) }}', '{{ route('penjualan.destroy', $o->id) }}')">
                    <i class="bi bi-trash"></i>
                </button>
                @endif
                @endcan
            </div>
        </div>
    </div>
    @empty
    <div class="text-center text-muted py-5">
        <i class="bi bi-inbox fs-3 d-block mb-2"></i>Tidak ada data penjualan
    </div>
    @endforelse
</div>

<div class="mt-3">{{ $orders->links() }}</div>

@include('components.cascade-delete-modal', ['entity' => 'Order', 'childList' => ['Detail item / produk dalam order ini']])

@can('order.batalkan')
{{-- Modal Batalkan (dipakai semua baris) — beda dari Hapus: stok & kas dikembalikan. --}}
<div class="modal fade" id="modalBatalkanList" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content">
            <form method="POST" id="formBatalkanList" action="">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title mb-0"><i class="bi bi-x-circle me-2 text-danger"></i>Batalkan Order <span id="batalkanListNomor"></span></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @include('penjualan._alasan-batal-fields')
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-x-circle me-1"></i>Ya, Batalkan Order</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

@push('scripts')
<script>
function confirmBatalkan(nomor, url) {
    var form = document.getElementById('formBatalkanList');
    form.reset();
    form.action = url;
    document.getElementById('batalkanListNomor').textContent = nomor;
    new bootstrap.Modal(document.getElementById('modalBatalkanList')).show();
}
function confirmHapus(nama, deleteUrl) {
    document.getElementById('cascadeModalEntityName').textContent = nama;
    document.getElementById('cascadeModalForm').action = deleteUrl;
    new bootstrap.Modal(document.getElementById('cascadeDeleteModal')).show();
}
</script>
@endpush

@endsection
