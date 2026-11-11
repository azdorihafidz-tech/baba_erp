@extends('layouts.app')

@section('title', 'Edit Order: ' . $order->nomor_order)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h5 class="mb-0 fw-bold">
        <i class="bi bi-pencil me-2 text-warning"></i>Edit Order
    </h5>
    <a href="{{ route('penjualan.show', $order) }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali ke Detail
    </a>
</div>

@if($order->status === \App\Enums\StatusOrder::Selesai)
<div class="alert alert-warning d-flex gap-2 mb-3" style="font-size:.85rem">
    <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
    <span>
        <strong>Order ini sudah Selesai.</strong>
        Perubahan akan tercatat di audit log dan akan mempengaruhi jumlah di Transaksi Keuangan terkait.
        Pastikan perubahan sudah dikonfirmasi sebelum disimpan.
    </span>
</div>
@endif
<div class="alert alert-info d-flex gap-2 mb-3" style="font-size:.85rem">
    <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
    <span>
        Hanya data pelanggan, diskon, tipe pembayaran, dan catatan yang bisa diubah di sini.
        <strong>Item dan kuantitas tidak bisa diubah</strong> — batalkan order dan buat order baru jika perlu mengganti item.
    </span>
</div>

<div class="row g-3">

    {{-- Info Order (read-only) --}}
    <div class="col-12 col-md-4">
        <div class="card h-100">
            <div class="card-header fw-semibold">
                <i class="bi bi-receipt me-1"></i>Info Order
            </div>
            <div class="card-body small">
                <div class="mb-2">
                    <div class="text-muted">Nomor Order</div>
                    <div class="fw-bold">{{ $order->nomor_order }}</div>
                </div>
                <div class="mb-2">
                    <div class="text-muted">Tanggal</div>
                    <div>{{ $order->tanggal_order->format('d/m/Y') }}</div>
                </div>
                <div class="mb-2">
                    <div class="text-muted">Tipe</div>
                    <div>{{ $order->tipe_order?->label() ?? '-' }}</div>
                </div>
                <div class="mb-2">
                    <div class="text-muted">Status</div>
                    <span class="badge {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span>
                </div>
                <hr class="my-2">
                <div class="mb-1">
                    <div class="text-muted">Total Harga (sebelum diskon)</div>
                    <div class="fw-semibold">Rp {{ number_format($order->total_harga, 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Item order (read-only) --}}
    <div class="col-12 col-md-8">
        <div class="card mb-3">
            <div class="card-header fw-semibold">
                <i class="bi bi-list-ul me-1"></i>Item Order <span class="text-muted fw-normal">(tidak bisa diubah)</span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0 small">
                    <thead class="table-light text-muted">
                        <tr>
                            <th>Item</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Harga</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->items as $item)
                        <tr>
                            <td>{{ $item->item?->nama_item ?? $item->nama_item ?? '-' }}</td>
                            <td class="text-center">{{ $item->qty }} {{ $item->item?->satuan ?? '' }}</td>
                            <td class="text-end">Rp {{ number_format($item->harga_satuan, 0, ',', '.') }}</td>
                            <td class="text-end fw-semibold">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="3" class="text-end fw-semibold">Total Harga:</td>
                            <td class="text-end fw-bold">Rp {{ number_format($order->total_harga, 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- Form edit --}}
        <div class="card">
            <div class="card-header fw-semibold">
                <i class="bi bi-pencil-square me-1"></i>Data yang Bisa Diubah
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('penjualan.update', $order) }}" id="editOrderForm">
                    @csrf
                    @method('PUT')
                    <div class="row g-3">

                        <div class="col-12 col-sm-6">
                            <label class="form-label fw-semibold">Nama Pelanggan</label>
                            <input type="text" name="nama_pelanggan" class="form-control @error('nama_pelanggan') is-invalid @enderror"
                                value="{{ old('nama_pelanggan', $order->nama_pelanggan) }}"
                                placeholder="Nama pelanggan / walk-in">
                            @error('nama_pelanggan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 col-sm-6">
                            <label class="form-label fw-semibold">Telepon</label>
                            <input type="text" name="telepon_pelanggan" class="form-control @error('telepon_pelanggan') is-invalid @enderror"
                                value="{{ old('telepon_pelanggan', $order->telepon_pelanggan) }}"
                                placeholder="No. telepon">
                            @error('telepon_pelanggan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 col-sm-6">
                            <label class="form-label fw-semibold">Tipe Pembayaran <span class="text-danger">*</span></label>
                            <select name="tipe_pembayaran" class="form-select @error('tipe_pembayaran') is-invalid @enderror" required>
                                @foreach($tipesPembayaran as $tp)
                                <option value="{{ $tp->value }}" @selected(old('tipe_pembayaran', $order->tipe_pembayaran->value) === $tp->value)>
                                    {{ $tp->label() }}
                                </option>
                                @endforeach
                            </select>
                            @error('tipe_pembayaran')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 col-sm-6">
                            <x-input-rupiah name="diskon" label="Diskon" :value="old('diskon', (int) $order->diskon)" id="diskonInput" />
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Catatan</label>
                            <textarea name="catatan" class="form-control" rows="2"
                                placeholder="Catatan tambahan...">{{ old('catatan', $order->catatan) }}</textarea>
                        </div>

                        {{-- Preview total_bayar --}}
                        <div class="col-12">
                            <div class="p-3 rounded bg-light border">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted">Total Harga:</span>
                                    <span>Rp {{ number_format($order->total_harga, 0, ',', '.') }}</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted">Diskon:</span>
                                    <span class="text-danger" id="previewDiskon">- Rp {{ number_format($order->diskon, 0, ',', '.') }}</span>
                                </div>
                                <hr class="my-2">
                                <div class="d-flex justify-content-between align-items-center fw-bold fs-6">
                                    <span>Total Bayar:</span>
                                    <span class="text-success" id="previewTotalBayar">Rp {{ number_format($order->total_bayar, 0, ',', '.') }}</span>
                                </div>
                                <div class="text-muted small mt-1">* TransaksiKeuangan akan diperbarui otomatis setelah simpan.</div>
                            </div>
                        </div>

                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-check-circle me-1"></i>Simpan Perubahan
                            </button>
                            <a href="{{ route('penjualan.show', $order) }}" class="btn btn-outline-secondary">Batal</a>
                        </div>

                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
(function () {
    const totalHarga = {{ (float) $order->total_harga }};

    function updatePreview() {
        const hidden = document.querySelector('input[type="hidden"][name="diskon"]');
        const diskon = parseFloat(hidden?.value || 0) || 0;
        const total  = Math.max(0, totalHarga - diskon);

        document.getElementById('previewDiskon').textContent =
            '- Rp ' + diskon.toLocaleString('id-ID');
        document.getElementById('previewTotalBayar').textContent =
            'Rp ' + total.toLocaleString('id-ID');
    }

    document.addEventListener('rupiah:change', function (e) {
        if (e.target.dataset.rupiahFor === 'diskon') updatePreview();
    });

    document.addEventListener('DOMContentLoaded', updatePreview);
})();
</script>
@endpush
