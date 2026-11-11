@extends('layouts.app')

@section('title', 'Detail Order: ' . $order->nomor_order)

@section('content')
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-receipt me-2 text-success"></i>Detail Order</h5>
    <div class="d-flex gap-2 flex-wrap">
        <button type="button" class="btn btn-sm btn-outline-secondary"
                onclick="openStrukModal({{ $order->id }})">
            <i class="bi bi-printer me-1"></i>Cetak Struk
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary"
                onclick="openStrukModal({{ $order->id }}, 2)">
            <i class="bi bi-printer-fill me-1"></i>Cetak 2x
        </button>
        {{-- 4.44 Poin 2 — cetak ulang Label Produk, hanya kalau label aktif di cabang order ini --}}
        @can('label_cup.print')
        @if($order->cabang?->label_cup_aktif)
        <button type="button" class="btn btn-sm btn-outline-warning"
                onclick="window.openCupLabel('{{ route('label-cup.order', $order) }}').then(function (t) { if (!t) alert('Tidak ada item yang perlu label di order ini (cek kategori di Cabang → Edit → Label Produk).'); })">
            <i class="bi bi-tag me-1"></i>Cetak Label
        </button>
        @endif
        @endcan
        @can('order.batalkan')
        @if($order->status === \App\Enums\StatusOrder::Selesai)
        @php $bisaBatalkan = $isOwner || $order->created_at->isToday(); @endphp
        <button type="button" class="btn btn-sm btn-outline-danger"
            @if($bisaBatalkan)
            data-bs-toggle="modal" data-bs-target="#modalBatalkan"
            @else
            disabled title="Order dari hari sebelumnya, hanya Owner yang bisa membatalkan"
            @endif>
            <i class="bi bi-x-circle me-1"></i>Batalkan
        </button>
        @endif
        @if(!$order->parent_order_id && $order->status !== \App\Enums\StatusOrder::Dibatalkan && $penggantiCandidates->isNotEmpty())
        <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalTandaiPengganti">
            <i class="bi bi-arrow-left-right me-1"></i>Tandai sebagai Pengganti
        </button>
        @endif
        @endcan
        @can('order.kembalikan')
        @if($order->status === \App\Enums\StatusOrder::Dibatalkan)
        <form method="POST" action="{{ route('penjualan.kembalikan', $order) }}"
            onsubmit="return confirm('Kembalikan order ini ke Selesai? Stok akan dikurangi kembali.')"
            class="d-inline">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-success">
                <i class="bi bi-arrow-counterclockwise me-1"></i>Kembalikan ke Selesai
            </button>
        </form>
        @endif
        @endcan
        <a href="{{ route('penjualan.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
    </div>
</div>

<div class="row g-3">
    {{-- Antrian Produksi Card (jika antrian aktif) --}}
    @if($order->nomor_antrian)
    <div class="col-12">
        <div class="card border-warning mb-0" style="background:#fffbeb">
            <div class="card-body py-2 px-3 d-flex align-items-center gap-3 flex-wrap">
                <div class="text-center" style="min-width:60px">
                    <div style="font-size:2.5rem;font-weight:900;line-height:1;color:#b45309">
                        {{ str_pad($order->nomor_antrian, 3, '0', STR_PAD_LEFT) }}
                    </div>
                    <div class="small text-muted">No. Antrian</div>
                </div>
                <div class="flex-fill">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="fw-semibold">Status Produksi:</span>
                        @if($order->status_produksi)
                        <span class="badge {{ $order->status_produksi->badgeClass() }}">
                            {{ $order->status_produksi->label() }}
                        </span>
                        @else
                        <span class="text-muted small">–</span>
                        @endif
                    </div>
                    @if($order->dikerjakanOleh)
                    <div class="small text-muted mt-1">
                        <i class="bi bi-person me-1"></i>Dikerjakan oleh: {{ $order->dikerjakanOleh->nama_lengkap }}
                    </div>
                    @endif
                    @if($order->lokasi_rak)
                    <div class="small text-muted">
                        <i class="bi bi-archive me-1"></i>Rak: {{ $order->lokasi_rak }}
                    </div>
                    @endif
                </div>
                @can('antrian.kelola')
                <a href="{{ route('antrian.operator') }}" class="btn btn-sm btn-outline-warning">
                    <i class="bi bi-list-ol me-1"></i>Kelola Antrian
                </a>
                @endcan
            </div>
        </div>
    </div>
    @endif

    {{-- Info Order --}}
    <div class="col-12 col-md-4">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Info Order</span>
                <span class="badge {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr><td class="text-muted">Nomor</td><td class="fw-bold">{{ $order->nomor_order }}</td></tr>
                    <tr><td class="text-muted">Tanggal</td><td>{{ $order->tanggal_order->format('d M Y') }}</td></tr>
                    <tr><td class="text-muted">Tipe</td>
                        <td><span class="badge bg-info text-dark">{{ $order->tipe_order->label() }}</span></td>
                    </tr>
                    <tr><td class="text-muted">Cabang</td><td>{{ $order->cabang?->nama_cabang ?? '-' }}</td></tr>
                    <tr><td class="text-muted">Kasir</td><td>{{ $order->kasir?->name ?? '-' }}</td></tr>
                </table>
                @if($order->parentOrder)
                <div class="alert alert-warning py-2 px-3 mt-2 mb-0 small">
                    <i class="bi bi-arrow-repeat me-1"></i>🔄 <strong>PENGGANTI</strong> dari order
                    <a href="{{ route('penjualan.show', $order->parentOrder) }}" class="alert-link">#{{ $order->parentOrder->nomor_order }}</a>
                </div>
                @endif
                @if($order->childOrders->isNotEmpty())
                <div class="alert alert-danger py-2 px-3 mt-2 mb-0 small">
                    <i class="bi bi-x-circle me-1"></i>❌ <strong>DIBATALKAN</strong> → diganti order
                    <a href="{{ route('penjualan.show', $order->childOrders->first()) }}" class="alert-link">#{{ $order->childOrders->first()->nomor_order }}</a>
                </div>
                @endif
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">Pelanggan</div>
            <div class="card-body">
                @if($order->pelanggan)
                <div class="fw-semibold">{{ $order->pelanggan->nama_pelanggan }}</div>
                <small class="text-muted">{{ $order->pelanggan->telepon ?? '' }}</small>
                @elseif($order->nama_pelanggan)
                <div class="fw-semibold">{{ $order->nama_pelanggan }}</div>
                <small class="text-muted">{{ $order->telepon_pelanggan ?? '' }}</small>
                @else
                <span class="text-muted">Walk-in / Umum</span>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header">Pembayaran</div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <td class="text-muted">Metode</td>
                        <td><span class="badge bg-light text-dark">{{ $order->tipe_pembayaran->label() }}</span></td>
                    </tr>
                    <tr><td class="text-muted">Subtotal</td><td class="text-end">Rp {{ number_format($order->total_harga, 0, ',', '.') }}</td></tr>
                    @if($order->diskon > 0)
                    <tr><td class="text-muted">Diskon</td><td class="text-end text-danger">- Rp {{ number_format($order->diskon, 0, ',', '.') }}</td></tr>
                    @endif
                    <tr class="fw-bold border-top">
                        <td>Total</td>
                        <td class="text-end text-primary fs-6">Rp {{ number_format($order->total_bayar, 0, ',', '.') }}</td>
                    </tr>
                    <tr><td class="text-muted">Dibayar</td><td class="text-end">Rp {{ number_format($order->jumlah_bayar, 0, ',', '.') }}</td></tr>
                    @if($order->kembalian > 0)
                    <tr><td class="text-muted">Kembalian</td><td class="text-end text-success">Rp {{ number_format($order->kembalian, 0, ',', '.') }}</td></tr>
                    @endif
                </table>

                {{-- Bukti Pembayaran --}}
                @if($order->bukti_pembayaran)
                <div class="mt-3 pt-3 border-top">
                    <div class="text-muted small mb-2"><i class="bi bi-image me-1"></i>Bukti Pembayaran</div>
                    <a href="{{ storage_url($order->bukti_pembayaran) }}" target="_blank" data-bs-toggle="modal" data-bs-target="#modalBukti">
                        <img src="{{ storage_url($order->bukti_pembayaran) }}" alt="Bukti Pembayaran"
                             class="img-fluid rounded border"
                             style="max-height:160px;object-fit:contain;cursor:zoom-in;width:100%">
                    </a>
                    <a href="{{ storage_url($order->bukti_pembayaran) }}" download class="btn btn-sm btn-outline-secondary mt-2 w-100">
                        <i class="bi bi-download me-1"></i>Unduh Bukti
                    </a>
                </div>
                @elseif($order->tipe_pembayaran->value !== 'tunai')
                <div class="mt-3 pt-3 border-top text-center text-muted small">
                    <i class="bi bi-image-slash d-block fs-4 mb-1 opacity-50"></i>
                    Belum ada bukti pembayaran
                </div>
                @endif
            </div>
        </div>

        {{-- Riwayat Pembatalan --}}
        @if($riwayatPembatalan->isNotEmpty())
        <div class="accordion mt-3" id="accordionRiwayatBatal">
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed small" type="button"
                        data-bs-toggle="collapse" data-bs-target="#collapseRiwayatBatal">
                        <i class="bi bi-clock-history me-2"></i>Riwayat Pembatalan
                    </button>
                </h2>
                <div id="collapseRiwayatBatal" class="accordion-collapse collapse" data-bs-parent="#accordionRiwayatBatal">
                    <div class="accordion-body small">
                        @foreach($riwayatPembatalan as $log)
                        <div class="mb-2 pb-2 border-bottom">
                            <div>{{ $log->description }}</div>
                            <div class="text-muted">
                                <i class="bi bi-person me-1"></i>{{ $log->causer?->name ?? 'Sistem' }}
                                &middot; {{ $log->created_at->format('d M Y H:i') }}
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Modal Lightbox Bukti --}}
        @if($order->bukti_pembayaran)
        <div class="modal fade" id="modalBukti" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header py-2">
                        <span class="modal-title small fw-semibold">Bukti Pembayaran — {{ $order->nomor_order }}</span>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center p-2">
                        <img src="{{ storage_url($order->bukti_pembayaran) }}" alt="Bukti Pembayaran"
                             class="img-fluid rounded" style="max-height:80vh">
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>

    {{-- Tabel Items --}}
    <div class="col-12 col-md-8">
        <div class="card">
            <div class="card-header">Daftar Item</div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item</th>
                            <th class="text-center">Tipe</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Harga</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->items as $i => $item)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>
                                <div class="fw-semibold">{{ $item->nama_item }}</div>
                                @if($item->berat_daging && strtolower($item->satuan ?? 'kg') === 'kg')
                                <small class="text-muted">Berat: {{ fmt_qty($item->berat_daging) }} kg | {{ ucfirst($item->jenis_olahan ?? '') }}</small>
                                @elseif($item->jenis_olahan)
                                <small class="text-muted">{{ ucfirst($item->jenis_olahan) }}</small>
                                @endif
                                @if($item->catatan)
                                <small class="text-muted d-block">{{ $item->catatan }}</small>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($item->berat_daging)
                                <span class="badge bg-info text-dark">Jasa Giling</span>
                                @else
                                <span class="badge bg-success">Produk</span>
                                @endif
                            </td>
                            <td class="text-end">{{ fmt_qty($item->qty) }} {{ $item->satuan }}</td>
                            <td class="text-end">Rp {{ number_format($item->harga_satuan, 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($item->total_harga, 0, ',', '.') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="fw-bold">
                            <td colspan="5" class="text-end">Total</td>
                            <td class="text-end text-primary">Rp {{ number_format($order->total_bayar, 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        @if($order->catatan)
        <div class="card mt-3">
            <div class="card-body">
                <i class="bi bi-chat-left-text me-2 text-muted"></i>
                <span class="text-muted">{{ $order->catatan }}</span>
            </div>
        </div>
        @endif
    </div>
</div>

@can('order.batalkan')
@if($order->status === \App\Enums\StatusOrder::Selesai)
{{-- Modal Batalkan Order --}}
<div class="modal fade" id="modalBatalkan" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content">
            <form method="POST" action="{{ route('penjualan.batalkan', $order) }}">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title mb-0"><i class="bi bi-x-circle me-2 text-danger"></i>Batalkan Order {{ $order->nomor_order }}</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @if($isOwner && !$order->created_at->isToday())
                    <div class="alert alert-warning small py-2">
                        <i class="bi bi-shield-check me-1"></i>Order ini dari hari sebelumnya. Sebagai Owner, pembatalan tetap diizinkan dan akan dicatat di audit log.
                    </div>
                    @endif
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
@endif

@if(!$order->parent_order_id && $order->status !== \App\Enums\StatusOrder::Dibatalkan && $penggantiCandidates->isNotEmpty())
{{-- Modal Tandai sebagai Pengganti --}}
<div class="modal fade" id="modalTandaiPengganti" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content">
            <form method="POST" action="{{ route('penjualan.tandai-pengganti', $order) }}">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title mb-0"><i class="bi bi-arrow-left-right me-2 text-warning"></i>Tandai sebagai Pengganti</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">Pilih order dibatalkan yang digantikan oleh order {{ $order->nomor_order }} ini:</p>
                    <div class="mb-0">
                        <label class="form-label">Order Dibatalkan <span class="text-danger">*</span></label>
                        <select name="parent_order_id" class="form-select" required>
                            <option value="">-- Pilih Order --</option>
                            @foreach($penggantiCandidates as $cand)
                            <option value="{{ $cand->id }}">
                                #{{ $cand->nomor_order }} — {{ $cand->nama_pelanggan ?? 'Umum' }} — Rp {{ number_format($cand->total_bayar, 0, ',', '.') }} ({{ $cand->created_at->format('H:i') }})
                            </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning btn-sm"><i class="bi bi-check-circle me-1"></i>Tandai sebagai Pengganti</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endcan
@endsection

@if(session('auto_print_struk_order_id'))
@push('scripts')
<script>
waitForBootstrapAndOpenStruk({{ session('auto_print_struk_order_id') }}, {{ session('auto_print_struk_copies', 1) }});
</script>
@endpush
@endif
