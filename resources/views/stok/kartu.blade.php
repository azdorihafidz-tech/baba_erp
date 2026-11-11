@extends('layouts.app')

@section('title', 'Kartu Stok')

@push('styles')
<style>
    .filter-card { border: 1px solid #e2e8f0; border-radius: 12px; background: white; }
    .info-header { background: #f8fafc; border-radius: 10px; border: 1px solid #e2e8f0; padding: 1rem 1.25rem; }
    .movement-badge { font-size: 0.72rem; padding: 0.2rem 0.55rem; border-radius: 20px; font-weight: 600; }
    .badge-masuk { background: #dcfce7; color: #166534; }
    .badge-keluar { background: #fee2e2; color: #991b1b; }
    .badge-transfer { background: #dbeafe; color: #1e40af; }
    .badge-adjustment { background: #fef3c7; color: #92400e; }
    .placeholder-box {
        background: #f8fafc;
        border: 2px dashed #e2e8f0;
        border-radius: 16px;
        padding: 3rem 1.5rem;
        text-align: center;
    }
</style>
@endpush

@section('content')

{{-- PAGE HEADER --}}
<div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-0" style="color:#1e293b">
            <i class="bi bi-journal-text me-2 text-primary"></i>Kartu Stok
        </h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0" style="font-size:0.8rem">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('stok.index') }}" class="text-decoration-none">Stok</a></li>
                <li class="breadcrumb-item active">Kartu Stok</li>
            </ol>
        </nav>
    </div>
</div>

{{-- FORM FILTER --}}
<div class="filter-card p-4 mb-4">
    <form method="GET" action="{{ route('stok.kartu') }}" class="row g-3 align-items-end">
        <div class="col-12 col-md-4 col-lg-3">
            <label class="form-label fw-semibold" style="font-size:0.85rem">
                Item <span class="text-danger">*</span>
            </label>
            <select name="item_id" class="form-select" required>
                <option value="">— Pilih Item —</option>
                @foreach($items as $item)
                <option value="{{ $item->id }}" {{ request('item_id') == $item->id ? 'selected' : '' }}>
                    [{{ $item->kode_item }}] {{ $item->nama_dengan_cabang }}
                </option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-4 col-lg-3">
            <label class="form-label fw-semibold" style="font-size:0.85rem">
                Lokasi <span class="text-danger">*</span>
            </label>
            <select name="lokasi_id" class="form-select" required>
                <option value="">— Pilih Lokasi —</option>
                @foreach($lokasiList as $lok)
                <option value="{{ $lok->id }}" {{ request('lokasi_id') == $lok->id ? 'selected' : '' }}>
                    {{ $lok->nama_cabang }}
                    @if($lok->tipe?->value === 'gudang_pusat') (Gudang Pusat) @endif
                </option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label fw-semibold" style="font-size:0.85rem">Dari Tanggal</label>
            <input type="date" name="dari" class="form-control" value="{{ request('dari') }}">
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label fw-semibold" style="font-size:0.85rem">Sampai Tanggal</label>
            <input type="date" name="sampai" class="form-control" value="{{ request('sampai') }}">
        </div>
        <div class="col-12 col-lg-auto d-flex gap-2">
            <button type="submit" class="btn btn-primary" style="min-height:44px;min-width:120px">
                <i class="bi bi-search me-2"></i>Tampilkan
            </button>
            @if(request()->hasAny(['item_id','lokasi_id','dari','sampai']))
            <a href="{{ route('stok.kartu') }}" class="btn btn-outline-secondary" style="min-height:44px">
                <i class="bi bi-x-lg me-1"></i>Reset
            </a>
            @endif
        </div>
    </form>
</div>

@if(request('item_id') && request('lokasi_id'))

    {{-- INFO HEADER STOK --}}
    @if(isset($selectedItem) && isset($selectedLokasi))
    <div class="info-header mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-md-6">
                <div class="d-flex align-items-start gap-3">
                    <div style="width:44px;height:44px;border-radius:10px;background:#eff6ff;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        <i class="bi bi-box-seam text-primary" style="font-size:1.2rem"></i>
                    </div>
                    <div>
                        <div class="fw-bold" style="color:#1e293b">{{ $selectedItem->nama_item }}</div>
                        <div class="d-flex align-items-center gap-2 mt-1 flex-wrap">
                            <code style="background:#e2e8f0;color:#475569;font-size:0.72rem;padding:0.1rem 0.4rem;border-radius:3px">
                                {{ $selectedItem->kode_item }}
                            </code>
                            <span class="text-muted" style="font-size:0.8rem">{{ $selectedItem->satuan }}</span>
                            <span class="text-muted" style="font-size:0.8rem">
                                <i class="bi bi-geo-alt me-1"></i>{{ $selectedLokasi->nama_cabang }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <div class="row g-2">
                    <div class="col-6">
                        <div style="background:white;border-radius:8px;padding:0.6rem 0.85rem;border:1px solid #e2e8f0;text-align:center">
                            <div class="fw-bold fs-5 lh-1 text-primary">
                                {{ fmt_qty($stokSaat ?? 0) }}
                            </div>
                            <div class="text-muted" style="font-size:0.72rem">Stok Saat Ini ({{ $selectedItem->satuan }})</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div style="background:white;border-radius:8px;padding:0.6rem 0.85rem;border:1px solid #e2e8f0;text-align:center">
                            <div class="fw-bold fs-5 lh-1 text-muted">
                                {{ fmt_qty($minimumLokasi ?? 0) }}
                            </div>
                            <div class="text-muted" style="font-size:0.72rem">Minimum ({{ $selectedItem->satuan }})</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- TABEL MOVEMENTS - Desktop --}}
    <div class="card d-none d-md-block">
        <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between">
            <span class="fw-semibold">
                <i class="bi bi-arrow-left-right me-2 text-primary"></i>Riwayat Pergerakan Stok
            </span>
            @isset($movements)
            <small class="text-muted">{{ $movements->total() }} record</small>
            @endisset
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="px-4">Tanggal</th>
                        <th>Tipe</th>
                        <th>Dari</th>
                        <th>Ke</th>
                        <th class="text-end">Qty</th>
                        <th class="d-none d-lg-table-cell">Referensi</th>
                        <th class="d-none d-xl-table-cell">Catatan</th>
                        <th class="d-none d-lg-table-cell">Dicatat Oleh</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($movements ?? [] as $mv)
                    <tr>
                        <td class="px-4" style="font-size:0.83rem;white-space:nowrap">
                            {{ $mv->created_at?->format('d/m/Y') }}<br>
                            <span class="text-muted" style="font-size:0.75rem">{{ $mv->created_at?->format('H:i') }}</span>
                        </td>
                        <td>
                            @php
                                $tipe = $mv->tipe ?? ($mv->tipe?->value ?? '');
                                $tipeVal = is_object($tipe) ? $tipe->value : $tipe;
                                $movBadge = match($tipeVal) {
                                    'masuk'      => 'badge-masuk',
                                    'keluar'     => 'badge-keluar',
                                    'transfer'   => 'badge-transfer',
                                    'adjustment' => 'badge-adjustment',
                                    default      => 'bg-secondary-subtle text-secondary',
                                };
                                $movLabel = match($tipeVal) {
                                    'masuk'      => 'Masuk',
                                    'keluar'     => 'Keluar',
                                    'transfer'   => 'Transfer',
                                    'adjustment' => 'Adjustment',
                                    default      => ucfirst($tipeVal),
                                };
                            @endphp
                            <span class="movement-badge {{ $movBadge }}">{{ $movLabel }}</span>
                        </td>
                        <td style="font-size:0.83rem">{{ $mv->lokasiAsal?->nama_cabang ?? '—' }}</td>
                        <td style="font-size:0.83rem">{{ $mv->lokasiTujuan?->nama_cabang ?? '—' }}</td>
                        <td class="text-end fw-semibold" style="font-size:0.88rem">
                            {{ fmt_qty($mv->qty) }}
                        </td>
                        <td class="d-none d-lg-table-cell">
                            <span class="text-muted" style="font-size:0.78rem">
                                {{ $mv->referensi_type ? Str::limit($mv->referensi_type, 20) : '—' }}
                                @if($mv->referensi_id) #{{ $mv->referensi_id }} @endif
                            </span>
                        </td>
                        <td class="d-none d-xl-table-cell">
                            <span class="text-muted" style="font-size:0.8rem">
                                {{ $mv->catatan ? Str::limit($mv->catatan, 40) : '—' }}
                            </span>
                        </td>
                        <td class="d-none d-lg-table-cell">
                            <span style="font-size:0.8rem">{{ $mv->user?->name ?? '—' }}</span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-5">
                            <div class="text-muted">
                                <i class="bi bi-journal-x" style="font-size:2.5rem;opacity:0.3"></i>
                                <p class="mt-2 mb-0">Belum ada riwayat pergerakan stok untuk item dan lokasi ini.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if(isset($movements) && $movements->hasPages())
        <div class="card-footer py-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <small class="text-muted">
                Menampilkan {{ $movements->firstItem() }}–{{ $movements->lastItem() }} dari {{ $movements->total() }} data
            </small>
            {{ $movements->links('pagination::bootstrap-5') }}
        </div>
        @endif
    </div>

    {{-- MOBILE CARD VIEW --}}
    <div class="d-md-none">
        @forelse($movements ?? [] as $mv)
        @php
            $tipe = $mv->tipe ?? '';
            $tipeVal = is_object($tipe) ? $tipe->value : $tipe;
            $movBadge = match($tipeVal) {
                'masuk'      => 'badge-masuk',
                'keluar'     => 'badge-keluar',
                'transfer'   => 'badge-transfer',
                'adjustment' => 'badge-adjustment',
                default      => 'bg-secondary-subtle text-secondary',
            };
            $movLabel = match($tipeVal) {
                'masuk'      => 'Masuk',
                'keluar'     => 'Keluar',
                'transfer'   => 'Transfer',
                'adjustment' => 'Adjustment',
                default      => ucfirst($tipeVal),
            };
        @endphp
        <div class="card mb-3" style="border-radius:10px">
            <div class="card-body py-3">
                <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                    <span class="movement-badge {{ $movBadge }}">{{ $movLabel }}</span>
                    <span class="text-muted" style="font-size:0.78rem">{{ $mv->created_at?->format('d/m/Y H:i') }}</span>
                </div>
                <div class="fw-bold" style="font-size:1rem">
                    {{ fmt_qty($mv->qty) }}
                    <span class="text-muted fw-normal" style="font-size:0.8rem">{{ $selectedItem->satuan ?? '' }}</span>
                </div>
                @if($mv->lokasiAsal || $mv->lokasiTujuan)
                <div class="text-muted mt-1" style="font-size:0.8rem">
                    @if($mv->lokasiAsal) Dari: {{ $mv->lokasiAsal->nama_cabang }} @endif
                    @if($mv->lokasiAsal && $mv->lokasiTujuan) → @endif
                    @if($mv->lokasiTujuan) Ke: {{ $mv->lokasiTujuan->nama_cabang }} @endif
                </div>
                @endif
                @if($mv->catatan)
                <div class="text-muted mt-1" style="font-size:0.78rem">
                    <i class="bi bi-chat-left-text me-1"></i>{{ $mv->catatan }}
                </div>
                @endif
                <div class="text-muted mt-1" style="font-size:0.78rem">
                    <i class="bi bi-person me-1"></i>{{ $mv->user?->name ?? '—' }}
                </div>
            </div>
        </div>
        @empty
        <div class="placeholder-box">
            <i class="bi bi-journal-x" style="font-size:2.5rem;opacity:0.3;color:#94a3b8"></i>
            <p class="mt-3 mb-0 text-muted">Belum ada riwayat pergerakan stok.</p>
        </div>
        @endforelse

        @if(isset($movements) && $movements->hasPages())
        <div class="mt-3">
            {{ $movements->links('pagination::bootstrap-5') }}
        </div>
        @endif
    </div>

@else
    {{-- PLACEHOLDER --}}
    <div class="placeholder-box">
        <i class="bi bi-journal-bookmark" style="font-size:3rem;opacity:0.25;color:#94a3b8"></i>
        <h5 class="mt-3 mb-1 fw-semibold" style="color:#64748b">Pilih Item dan Lokasi</h5>
        <p class="text-muted mb-0" style="font-size:0.88rem">
            Pilih item dan lokasi di atas, lalu klik <strong>Tampilkan</strong> untuk melihat riwayat pergerakan stok.
        </p>
    </div>
@endif

@endsection
