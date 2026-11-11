@extends('layouts.app')

@section('title', 'Detail Permintaan — ' . $stockRequest->nomor_request)

@push('styles')
<style>
    .info-card { border: 1px solid #e2e8f0; border-radius: 12px; background: white; }
    .info-row { display: flex; justify-content: space-between; align-items: flex-start; padding: 0.5rem 0; border-bottom: 1px solid #f1f5f9; font-size: 0.85rem; gap: 1rem; }
    .info-row:last-child { border-bottom: none; }
    .info-label { color: #64748b; flex-shrink: 0; min-width: 110px; }
    .approval-card { border: 1.5px solid #3b82f6; border-radius: 12px; background: #eff6ff; }
</style>
@endpush

@section('content')

@php $statusVal = is_object($stockRequest->status) ? $stockRequest->status->value : $stockRequest->status; @endphp

{{-- PAGE HEADER --}}
<div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-0" style="color:#1e293b">
            <i class="bi bi-file-earmark-text me-2 text-primary"></i>Detail Permintaan
        </h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0" style="font-size:0.8rem">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('stock-request.index') }}" class="text-decoration-none">Permintaan Stok</a></li>
                <li class="breadcrumb-item active">{{ $stockRequest->nomor_request }}</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @canany(['stok.request', 'stok.transfer'])
        @if(in_array($statusVal, ['pending','disetujui']))
        <form method="POST" action="{{ route('stock-request.batalkan', $stockRequest) }}">
            @csrf
            <button type="submit" class="btn btn-outline-warning" style="min-height:40px"
                onclick="return confirm('Batalkan permintaan {{ $stockRequest->nomor_request }}?')">
                <i class="bi bi-x-circle me-2"></i>Batalkan
            </button>
        </form>
        @endif
        @endcanany
        @if(in_array($statusVal, ['ditolak','dibatalkan']) && $authUser->canAccessAllBranches())
        <form method="POST" action="{{ route('stock-request.destroy', $stockRequest) }}">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-outline-danger" style="min-height:40px"
                onclick="return confirm('Hapus permanen permintaan ini? Data tidak bisa dikembalikan.')">
                <i class="bi bi-trash3 me-2"></i>Hapus
            </button>
        </form>
        @endif
        <a href="{{ route('stock-request.index') }}" class="btn btn-outline-secondary" style="min-height:40px">
            <i class="bi bi-arrow-left me-2"></i>Kembali
        </a>
    </div>
</div>

<div class="row g-4">
    {{-- INFO HEADER --}}
    <div class="col-12 col-lg-5 col-xl-4">
        <div class="info-card p-4 mb-4">
            <h6 class="fw-bold mb-3" style="color:#1e293b;font-size:0.9rem">
                <i class="bi bi-info-circle me-2 text-primary"></i>Informasi Permintaan
            </h6>
            <div class="info-row">
                <span class="info-label">No. Request</span>
                <code style="background:#f1f5f9;color:#475569;font-size:0.8rem;padding:0.15rem 0.45rem;border-radius:4px">
                    {{ $stockRequest->nomor_request }}
                </code>
            </div>
            <div class="info-row">
                <span class="info-label">Status</span>
                @php
                    $statusBadge = match($statusVal) {
                        'pending'   => 'bg-warning-subtle text-warning',
                        'disetujui' => 'bg-primary-subtle text-primary',
                        'ditolak'   => 'bg-danger-subtle text-danger',
                        'dikirim'   => 'bg-info-subtle text-info',
                        'diterima'  => 'bg-success-subtle text-success',
                        default     => 'bg-secondary-subtle text-secondary',
                    };
                    $statusLabel = match($statusVal) {
                        'pending'   => 'Pending',
                        'disetujui' => 'Disetujui',
                        'ditolak'   => 'Ditolak',
                        'dikirim'   => 'Dikirim',
                        'diterima'  => 'Diterima',
                        default     => ucfirst($statusVal ?? '—'),
                    };
                @endphp
                <span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Cabang</span>
                <span class="fw-semibold">{{ $stockRequest->cabang?->nama_cabang ?? '—' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Tanggal</span>
                <span>{{ $stockRequest->tanggal_request?->format('d/m/Y') ?? '—' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Dibuat Oleh</span>
                <span>{{ $stockRequest->createdBy?->name ?? '—' }}</span>
            </div>
            @if($stockRequest->approved_by)
            <div class="info-row">
                <span class="info-label">Diproses Oleh</span>
                <span>{{ $stockRequest->approvedBy?->name ?? '—' }}</span>
            </div>
            @endif
            @if($stockRequest->catatan)
            <div class="info-row">
                <span class="info-label">Catatan</span>
                <span class="text-muted" style="font-size:0.82rem">{{ $stockRequest->catatan }}</span>
            </div>
            @endif
        </div>
    </div>

    {{-- TABEL ITEM + APPROVAL --}}
    <div class="col-12 col-lg-7 col-xl-8">

        {{-- TABEL ITEM --}}
        <div class="info-card mb-4">
            <div class="p-4 pb-0">
                <h6 class="fw-bold mb-0" style="color:#1e293b;font-size:0.9rem">
                    <i class="bi bi-list-check me-2 text-primary"></i>Item yang Diminta
                </h6>
            </div>
            <div class="table-responsive mt-3">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-4">Item</th>
                            <th class="text-end">Qty Diminta</th>
                            <th class="text-end">Qty Disetujui</th>
                            <th class="text-end">Qty Diterima</th>
                            <th class="d-none d-md-table-cell">Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($stockRequest->items ?? [] as $sri)
                        <tr>
                            <td class="px-4">
                                <div class="fw-semibold" style="font-size:0.88rem">{{ $sri->item?->nama_item ?? '—' }}</div>
                                <div class="text-muted" style="font-size:0.75rem">{{ $sri->item?->satuan ?? '' }}</div>
                            </td>
                            <td class="text-end fw-semibold" style="font-size:0.88rem">
                                {{ fmt_qty($sri->qty_diminta) }}
                            </td>
                            <td class="text-end" style="font-size:0.88rem">
                                @if($sri->qty_disetujui !== null)
                                    <span class="{{ $sri->qty_disetujui < $sri->qty_diminta ? 'text-warning' : 'text-success' }} fw-semibold">
                                        {{ fmt_qty($sri->qty_disetujui) }}
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-end" style="font-size:0.88rem">
                                @if($sri->qty_diterima !== null)
                                    <span class="fw-semibold">{{ fmt_qty($sri->qty_diterima) }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="d-none d-md-table-cell">
                                <span class="text-muted" style="font-size:0.8rem">{{ $sri->catatan ?? '—' }}</span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">
                                <i class="bi bi-inbox me-2" style="opacity:0.4"></i>Tidak ada item.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- BUAT TRANSFER (status disetujui, belum ada transfer) --}}
        @if($statusVal === 'disetujui' && $authUser->can('stok.transfer'))
        @php $sudahAdaTransfer = $stockRequest->transfers()->whereNotIn('status',['dibatalkan'])->exists(); @endphp
        @if(!$sudahAdaTransfer)
        <div class="p-4" style="border:1.5px solid #22c55e;border-radius:12px;background:#f0fdf4">
            <h6 class="fw-bold mb-2" style="color:#166534;font-size:0.9rem">
                <i class="bi bi-truck me-2"></i>Buat Transfer Pengiriman
            </h6>
            <p class="text-muted mb-3" style="font-size:0.8rem">
                Permintaan ini sudah disetujui. Buat surat jalan / transfer stok untuk mengirim barang ke
                <strong>{{ $stockRequest->cabang?->nama_cabang }}</strong>.
            </p>
            <a href="{{ route('stock-transfer.create', ['dari_request' => $stockRequest->id]) }}"
                class="btn btn-success fw-semibold w-100" style="min-height:44px">
                <i class="bi bi-truck me-2"></i>Buat Transfer dari Request Ini
            </a>
        </div>
        @else
        <div class="p-4" style="border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc">
            <p class="text-muted mb-2" style="font-size:0.83rem">
                <i class="bi bi-check-circle text-success me-2"></i>Transfer pengiriman sudah dibuat.
            </p>
            <a href="{{ route('stock-transfer.index') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-arrow-right me-1"></i>Lihat Transfer
            </a>
        </div>
        @endif
        @endif

        {{-- FORM APPROVAL (hanya jika status pending DAN user punya akses semua cabang) --}}
        @if($statusVal === 'pending' && $authUser->can('stok.transfer'))
        <div class="approval-card p-4">
            <h6 class="fw-bold mb-3" style="color:#1e40af;font-size:0.9rem">
                <i class="bi bi-shield-check me-2"></i>Proses Permintaan
            </h6>

            <form method="POST" action="{{ route('stock-request.approve', $stockRequest) }}" id="formApproval">
            @csrf

                {{-- Pilihan: setujui / tolak --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold" style="font-size:0.85rem">Keputusan</label>
                    <div class="d-flex gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="aksi" id="keputusanSetujui"
                                value="disetujui" {{ old('aksi') === 'ditolak' ? '' : 'checked' }}
                                onchange="toggleApprovalDetail(this.value)">
                            <label class="form-check-label" for="keputusanSetujui" style="font-size:0.85rem">
                                <i class="bi bi-check-circle text-success me-1"></i>Setujui
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="aksi" id="keputusanTolak"
                                value="ditolak" {{ old('aksi') === 'ditolak' ? 'checked' : '' }}
                                onchange="toggleApprovalDetail(this.value)">
                            <label class="form-check-label" for="keputusanTolak" style="font-size:0.85rem">
                                <i class="bi bi-x-circle text-danger me-1"></i>Tolak
                            </label>
                        </div>
                    </div>
                </div>

                {{-- Detail qty disetujui (hanya tampil saat setujui) --}}
                <div id="approvalDetail">
                    <div class="mb-3">
                        <label class="form-label fw-semibold" style="font-size:0.85rem">Qty Disetujui per Item</label>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0" style="font-size:0.83rem">
                                <thead class="table-light">
                                    <tr>
                                        <th>Item</th>
                                        <th class="text-end">Diminta</th>
                                        <th class="text-end" style="width:140px">Disetujui</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($stockRequest->items ?? [] as $sri)
                                    <tr>
                                        <td>
                                            {{ $sri->item?->nama_item ?? '—' }}
                                            <small class="text-muted d-block">{{ $sri->item?->satuan }}</small>
                                        </td>
                                        <td class="text-end">{{ fmt_qty($sri->qty_diminta) }}</td>
                                        <td class="text-end">
                                            <input type="number"
                                                name="qty_disetujui[{{ $sri->id }}]"
                                                class="form-control form-control-sm text-end"
                                                value="{{ old('qty_disetujui.'.$sri->id, $sri->qty_diminta) }}"
                                                min="0" step="0.001">
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Catatan gudang --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold" style="font-size:0.85rem">Catatan Gudang (Opsional)</label>
                    <textarea name="catatan_gudang" rows="2"
                        class="form-control form-control-sm @error('catatan_gudang') is-invalid @enderror"
                        placeholder="cth: Stok tersedia sebagian, sisanya akan dikirim pekan depan...">{{ old('catatan_gudang') }}</textarea>
                    @error('catatan_gudang')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary fw-semibold" style="min-height:44px;min-width:140px"
                        onclick="return confirm('Proses permintaan ini sesuai keputusan yang dipilih?')">
                        <i class="bi bi-check-lg me-2"></i>Proses
                    </button>
                </div>

            </form>
        </div>
        @endif

    </div>
</div>

@endsection

@push('scripts')
<script>
    function toggleApprovalDetail(val) {
        const detail = document.getElementById('approvalDetail');
        if (detail) {
            detail.style.display = val === 'disetujui' ? 'block' : 'none';
        }
    }
    // Init
    const keputusan = document.querySelector('input[name="aksi"]:checked');
    if (keputusan) toggleApprovalDetail(keputusan.value);
</script>
@endpush
