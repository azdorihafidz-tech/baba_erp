@extends('layouts.app')

@section('title', 'Detail Transfer — ' . $transfer->nomor_transfer)

@push('styles')
<style>
    .info-card { border: 1px solid #e2e8f0; border-radius: 12px; background: white; }
    .info-row { display: flex; justify-content: space-between; align-items: flex-start; padding: 0.5rem 0; border-bottom: 1px solid #f1f5f9; font-size: 0.85rem; gap: 1rem; }
    .info-row:last-child { border-bottom: none; }
    .info-label { color: #64748b; flex-shrink: 0; min-width: 110px; }
    .action-card { border-radius: 12px; }
    .action-card-kirim { background: #eff6ff; border: 1.5px solid #3b82f6; }
    .action-card-terima { background: #f0fdf4; border: 1.5px solid #22c55e; }
    .action-card-batal { background: #fff5f5; border: 1.5px solid #ef4444; }
</style>
@endpush

@section('content')

@php
    $statusVal = is_object($transfer->status) ? $transfer->status->value : $transfer->status;
    $statusBadge = match($statusVal) {
        'draft'             => 'bg-secondary-subtle text-secondary',
        'dikirim'           => 'bg-info-subtle text-info',
        'diterima_sebagian' => 'bg-warning-subtle text-warning',
        'diterima'          => 'bg-success-subtle text-success',
        'dibatalkan'        => 'bg-danger-subtle text-danger',
        default             => 'bg-secondary-subtle text-secondary',
    };
    $statusLabel = match($statusVal) {
        'draft'             => 'Draft',
        'dikirim'           => 'Dikirim',
        'diterima_sebagian' => 'Diterima Sebagian',
        'diterima'          => 'Diterima',
        'dibatalkan'        => 'Dibatalkan',
        default             => ucfirst($statusVal ?? '—'),
    };
@endphp

{{-- PAGE HEADER --}}
<div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-0" style="color:#1e293b">
            <i class="bi bi-file-earmark-arrow-right me-2 text-primary"></i>Detail Transfer
        </h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0" style="font-size:0.8rem">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('stock-transfer.index') }}" class="text-decoration-none">Transfer Stok</a></li>
                <li class="breadcrumb-item active">{{ $transfer->nomor_transfer }}</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @if($statusVal === 'dibatalkan' && $authUser->canAccessAllBranches())
        <form method="POST" action="{{ route('stock-transfer.destroy', $transfer) }}">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-outline-danger" style="min-height:40px"
                onclick="return confirm('Hapus transfer {{ $transfer->nomor_transfer }} permanen?')">
                <i class="bi bi-trash3 me-2"></i>Hapus
            </button>
        </form>
        @endif
        <a href="{{ route('stock-transfer.index') }}" class="btn btn-outline-secondary" style="min-height:40px">
            <i class="bi bi-arrow-left me-2"></i>Kembali
        </a>
    </div>
</div>

<div class="row g-4">

    {{-- KOLOM KIRI: Info + Aksi --}}
    <div class="col-12 col-lg-4">

        {{-- INFO --}}
        <div class="info-card p-4 mb-4">
            <h6 class="fw-bold mb-3" style="color:#1e293b;font-size:0.9rem">
                <i class="bi bi-info-circle me-2 text-primary"></i>Informasi Transfer
            </h6>
            <div class="info-row">
                <span class="info-label">No. Transfer</span>
                <code style="background:#f1f5f9;color:#475569;font-size:0.78rem;padding:0.15rem 0.45rem;border-radius:4px">
                    {{ $transfer->nomor_transfer }}
                </code>
            </div>
            <div class="info-row">
                <span class="info-label">Status</span>
                <span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Dari</span>
                <span class="fw-semibold">{{ $transfer->dariLokasi?->nama_cabang ?? '—' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Ke</span>
                <span class="fw-semibold">{{ $transfer->keLokasi?->nama_cabang ?? '—' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Tgl Kirim</span>
                <span>{{ $transfer->tanggal_kirim?->format('d/m/Y') ?? '—' }}</span>
            </div>
            @if($transfer->tanggal_terima)
            <div class="info-row">
                <span class="info-label">Tgl Terima</span>
                <span>{{ $transfer->tanggal_terima?->format('d/m/Y') ?? '—' }}</span>
            </div>
            @endif
            <div class="info-row">
                <span class="info-label">Dibuat Oleh</span>
                <span>{{ $transfer->createdBy?->name ?? '—' }}</span>
            </div>
            @if($transfer->receivedBy)
            <div class="info-row">
                <span class="info-label">Diterima Oleh</span>
                <span>{{ $transfer->receivedBy?->name ?? '—' }}</span>
            </div>
            @endif
            @if($transfer->stockRequest)
            <div class="info-row">
                <span class="info-label">Dari Request</span>
                <a href="{{ route('stock-request.show', $transfer->stock_request_id) }}"
                    class="text-primary text-decoration-none" style="font-size:0.82rem">
                    <i class="bi bi-link me-1"></i>{{ $transfer->stockRequest->nomor_request }}
                </a>
            </div>
            @endif
            @if($transfer->catatan)
            <div class="info-row">
                <span class="info-label">Catatan</span>
                <span class="text-muted" style="font-size:0.82rem">{{ $transfer->catatan }}</span>
            </div>
            @endif
        </div>

        {{-- AKSI: Status DRAFT --}}
        @if($statusVal === 'draft' && $authUser->can('stok.transfer'))
        <div class="action-card action-card-kirim p-4 mb-3">
            <h6 class="fw-bold mb-2" style="color:#1e40af;font-size:0.88rem">
                <i class="bi bi-truck me-2"></i>Kirimkan Transfer
            </h6>
            <p class="text-muted mb-3" style="font-size:0.8rem">
                Ubah status menjadi "Dikirim" untuk menandai barang telah diberangkatkan.
                Stok lokasi asal akan berkurang.
            </p>
            <form method="POST" action="{{ route('stock-transfer.kirim', $transfer) }}" id="formKirim">
                @csrf
                <button type="submit"
                    class="btn btn-primary w-100" style="min-height:44px"
                    onclick="return confirm('Konfirmasi pengiriman transfer ini? Stok lokasi asal akan berkurang.')">
                    <i class="bi bi-truck me-2"></i>Tandai Dikirim
                </button>
            </form>
        </div>

        <div class="action-card action-card-batal p-4">
            <h6 class="fw-bold mb-2" style="color:#991b1b;font-size:0.88rem">
                <i class="bi bi-x-circle me-2"></i>Batalkan Transfer
            </h6>
            <form method="POST" action="{{ route('stock-transfer.batalkan', $transfer) }}" id="formBatalkan">
                @csrf
                <button type="submit"
                    class="btn btn-outline-danger w-100" style="min-height:44px"
                    onclick="return confirm('Batalkan transfer ini? Tindakan tidak dapat diundur.')">
                    <i class="bi bi-x-circle me-2"></i>Batalkan
                </button>
            </form>
        </div>
        @endif

        {{-- Jika diterima atau dibatalkan: hanya kembali --}}
        @if(in_array($statusVal, ['diterima', 'dibatalkan']))
        <div class="info-card p-4 text-center">
            <p class="text-muted mb-3" style="font-size:0.85rem">
                Transfer ini sudah <strong>{{ $statusLabel }}</strong> dan tidak dapat diubah.
            </p>
            <a href="{{ route('stock-transfer.index') }}" class="btn btn-outline-secondary w-100" style="min-height:44px">
                <i class="bi bi-arrow-left me-2"></i>Kembali ke Daftar
            </a>
        </div>
        @endif
    </div>

    {{-- KOLOM KANAN: Tabel Item + Form Terima --}}
    <div class="col-12 col-lg-8">

        {{-- TABEL ITEM --}}
        <div class="info-card mb-4">
            <div class="p-4 pb-0">
                <h6 class="fw-bold mb-0" style="color:#1e293b;font-size:0.9rem">
                    <i class="bi bi-list-check me-2 text-primary"></i>Daftar Item Transfer
                </h6>
            </div>
            <div class="table-responsive mt-3">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-4">Item</th>
                            <th class="text-center">Satuan</th>
                            <th class="text-end">Qty Kirim</th>
                            <th class="text-end">Qty Diterima</th>
                            <th class="d-none d-md-table-cell">Catatan</th>
                            @if(in_array($statusVal, ['dikirim', 'diterima_sebagian']))
                            <th class="text-end" style="width:140px">Konfirmasi Terima</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transfer->items ?? [] as $sti)
                        <tr>
                            <td class="px-4">
                                <div class="fw-semibold" style="font-size:0.88rem">{{ $sti->item?->nama_item ?? '—' }}</div>
                                <div class="text-muted" style="font-size:0.75rem">{{ $sti->item?->kode_item }}</div>
                            </td>
                            <td class="text-center">
                                <span class="text-muted" style="font-size:0.83rem">{{ $sti->item?->satuan ?? '—' }}</span>
                            </td>
                            <td class="text-end fw-semibold" style="font-size:0.88rem">
                                {{ fmt_qty($sti->qty_kirim) }}
                            </td>
                            <td class="text-end" style="font-size:0.88rem">
                                @if($sti->qty_terima !== null)
                                    <span class="{{ $sti->qty_terima < $sti->qty_kirim ? 'text-warning' : 'text-success' }} fw-semibold">
                                        {{ fmt_qty($sti->qty_terima) }}
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="d-none d-md-table-cell">
                                <span class="text-muted" style="font-size:0.8rem">{{ $sti->catatan ?? '—' }}</span>
                            </td>
                            @if(in_array($statusVal, ['dikirim', 'diterima_sebagian']))
                            <td class="text-end">
                                <input type="number"
                                    form="formTerima"
                                    name="qty_terima[{{ $sti->id }}]"
                                    class="form-control form-control-sm text-end"
                                    style="width:110px;display:inline-block"
                                    value="{{ old('qty_terima.'.$sti->id, $sti->qty_kirim) }}"
                                    min="0" step="0.001"
                                    max="{{ $sti->qty_kirim }}">
                            </td>
                            @endif
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ in_array($statusVal, ['dikirim', 'diterima_sebagian']) ? 6 : 5 }}" class="text-center py-4 text-muted">
                                <i class="bi bi-inbox me-2" style="opacity:0.4"></i>Tidak ada item.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- FORM KONFIRMASI TERIMA (status Dikirim / Diterima Sebagian) --}}
        @canany(['stok.request', 'stok.transfer'])
        @if(in_array($statusVal, ['dikirim', 'diterima_sebagian']))
        <div class="action-card action-card-terima p-4">
            <h6 class="fw-bold mb-2" style="color:#166534;font-size:0.88rem">
                <i class="bi bi-box-seam me-2"></i>Konfirmasi Penerimaan
            </h6>
            <p class="text-muted mb-3" style="font-size:0.8rem">
                Masukkan jumlah yang benar-benar diterima per item, lalu klik <strong>Konfirmasi Terima</strong>.
                Stok lokasi tujuan akan bertambah sesuai qty yang diterima.
            </p>
            {{-- Form terima — ID form digunakan oleh input di tabel atas --}}
            <form method="POST" action="{{ route('stock-transfer.terima', $transfer) }}" id="formTerima">
                @csrf
                <button type="submit"
                    class="btn btn-success fw-semibold" style="min-height:44px;min-width:180px"
                    onclick="return confirm('Konfirmasi penerimaan stok ini? Stok lokasi tujuan akan bertambah.')">
                    <i class="bi bi-check-lg me-2"></i>Konfirmasi Terima
                </button>
            </form>
        </div>
        @endif
        @endcanany

    </div>
</div>

@endsection
