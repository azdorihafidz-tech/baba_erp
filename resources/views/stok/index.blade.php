@extends('layouts.app')

@section('title', 'Manajemen Stok')

@push('styles')
<style>
    .stat-mini {
        background: #f8fafc;
        border-radius: 10px;
        padding: 0.75rem 1rem;
        border: 1px solid #e2e8f0;
    }
    .stok-card {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        background: white;
        transition: box-shadow 0.15s;
    }
    .stok-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.07); }
    .qty-danger { color: #dc2626 !important; font-weight: 700; }
    .qty-normal { color: #1e293b; font-weight: 600; }
</style>
@endpush

@section('content')

{{-- PAGE HEADER --}}
<div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-0" style="color:#1e293b">
            <i class="bi bi-archive me-2 text-primary"></i>Manajemen Stok
        </h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0" style="font-size:0.8rem">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active">Stok</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @can('stok.adjustment')
        <a href="{{ route('stok.adjustment') }}" class="btn btn-outline-warning d-flex align-items-center gap-2" style="min-height:40px">
            <i class="bi bi-sliders"></i>
            <span class="d-none d-sm-inline">Adjustment Stok</span>
        </a>
        @endcan
        <a href="{{ route('stok.kartu') }}" class="btn btn-outline-info d-flex align-items-center gap-2" style="min-height:40px">
            <i class="bi bi-journal-text"></i>
            <span class="d-none d-sm-inline">Kartu Stok</span>
        </a>
        <x-panduan-button slug="stok-barang" />
    </div>
</div>

{{-- STAT CARDS --}}
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-4">
        <div class="stat-mini d-flex align-items-center gap-3">
            <div style="width:44px;height:44px;border-radius:10px;background:#eff6ff;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="bi bi-archive text-primary" style="font-size:1.2rem"></i>
            </div>
            <div>
                <div class="fw-bold fs-5 lh-1">{{ $statTotal }}</div>
                <div class="text-muted" style="font-size:0.75rem">Item Berstock</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-4">
        <div class="stat-mini d-flex align-items-center gap-3" style="{{ $statBelowMin > 0 ? 'border-color:#fecaca;background:#fff5f5' : '' }}">
            <div style="width:44px;height:44px;border-radius:10px;background:{{ $statBelowMin > 0 ? '#fef2f2' : '#f0fdf4' }};display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="bi bi-{{ $statBelowMin > 0 ? 'exclamation-triangle text-danger' : 'check-circle text-success' }}" style="font-size:1.2rem"></i>
            </div>
            <div>
                <div class="fw-bold fs-5 lh-1 {{ $statBelowMin > 0 ? 'text-danger' : '' }}">
                    {{ $statBelowMin }}
                    @if($statBelowMin > 0)
                        <span class="badge bg-danger ms-1" style="font-size:0.65rem;vertical-align:middle">!</span>
                    @endif
                </div>
                <div class="text-muted" style="font-size:0.75rem">Di Bawah Minimum</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-4">
        <div class="stat-mini d-flex align-items-center gap-3">
            <div style="width:44px;height:44px;border-radius:10px;background:#f0fdf4;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="bi bi-currency-dollar text-success" style="font-size:1.2rem"></i>
            </div>
            <div>
                <div class="fw-bold lh-1" style="font-size:1rem">
                    Rp {{ number_format($statNilai, 0, ',', '.') }}
                </div>
                <div class="text-muted" style="font-size:0.75rem">Nilai Stok</div>
            </div>
        </div>
    </div>
</div>

{{-- FILTER BAR --}}
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('stok.index') }}" class="row g-2 align-items-end">
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label form-label-sm mb-1">Cari Item</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control"
                        placeholder="Nama atau kode item..."
                        value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-6 col-sm-4 col-md-2">
                <label class="form-label form-label-sm mb-1">Kategori</label>
                <select name="kategori" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ request('kategori') == $cat->id ? 'selected' : '' }}>
                        {{ $cat->nama_kategori }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-sm-4 col-md-2">
                <label class="form-label form-label-sm mb-1">Tipe</label>
                <select name="tipe" class="form-select form-select-sm">
                    <option value="">Semua Tipe</option>
                    <option value="bahan_baku" {{ request('tipe') === 'bahan_baku' ? 'selected' : '' }}>Bahan Baku</option>
                    <option value="kemasan" {{ request('tipe') === 'kemasan' ? 'selected' : '' }}>Kemasan</option>
                    <option value="tambahan_gratis" {{ request('tipe') === 'tambahan_gratis' ? 'selected' : '' }}>Tambahan Gratis</option>
                </select>
            </div>
            @if($authUser->canAccessAllBranches())
            <div class="col-6 col-sm-4 col-md-2">
                <label class="form-label form-label-sm mb-1">Lokasi</label>
                <select name="lokasi" class="form-select form-select-sm">
                    <option value="">Semua Lokasi</option>
                    @foreach($lokasiList as $lok)
                    <option value="{{ $lok->id }}" {{ request('lokasi') == $lok->id ? 'selected' : '' }}>
                        {{ $lok->nama_cabang }}
                    </option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-6 col-sm-4 col-md-2">
                <label class="form-label form-label-sm mb-1">Stok Kritis</label>
                <select name="kritis" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="1" {{ request('kritis') === '1' ? 'selected' : '' }}>Di Bawah Minimum</option>
                </select>
            </div>
            <div class="col-12 col-sm-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm px-3">
                    <i class="bi bi-filter me-1"></i>Filter
                </button>
                @if(request()->hasAny(['search','kategori','tipe','lokasi','kritis']))
                <a href="{{ route('stok.index') }}" class="btn btn-outline-secondary btn-sm px-3">
                    <i class="bi bi-x-lg me-1"></i>Reset
                </a>
                @endif
            </div>
        </form>
    </div>
</div>

@if($statBelowMin > 0)
<div class="alert alert-warning d-flex align-items-center gap-2 mb-4" role="alert">
    <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
    <div style="font-size:0.88rem">
        <strong>Perhatian!</strong> Terdapat <strong>{{ $statBelowMin }} item</strong> dengan stok di bawah batas minimum.
        <a href="{{ route('stok.index', ['kritis'=>'1']) }}" class="alert-link">Lihat item kritis</a>
    </div>
</div>
@endif

{{-- TABEL - Desktop & Tablet --}}
<div class="card d-none d-md-block">
    <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between">
        <span class="fw-semibold">
            <i class="bi bi-table me-2 text-primary"></i>Data Stok
        </span>
        <small class="text-muted">{{ $stocks->total() }} data stok</small>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="px-4">Lokasi</th>
                    <th>Item</th>
                    <th class="d-none d-lg-table-cell">Kategori</th>
                    <th class="d-none d-lg-table-cell">Tipe</th>
                    <th class="text-center d-none d-lg-table-cell">Satuan</th>
                    <th class="text-end d-none d-xl-table-cell">Qty Minimum</th>
                    <th class="text-end">Qty Stok</th>
                    <th class="text-center px-4" style="width:100px">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($stocks as $stock)
                @php $isBelowMin = $stock->qty <= $stock->qty_minimum && $stock->qty_minimum > 0; @endphp
                <tr class="{{ $isBelowMin ? 'table-danger' : '' }}">
                    <td class="px-4">
                        @php
                            $lokasiTipe = $stock->lokasi?->tipe?->value ?? 'cabang';
                            $lokasiClass = $lokasiTipe === 'gudang_pusat' ? 'bg-warning-subtle text-warning' : 'bg-primary-subtle text-primary';
                            $lokasiLabel = $lokasiTipe === 'gudang_pusat' ? 'Gudang Pusat' : 'Cabang';
                        @endphp
                        <div class="fw-semibold" style="font-size:0.88rem">{{ $stock->lokasi?->nama_cabang ?? '—' }}</div>
                        <span class="badge {{ $lokasiClass }}" style="font-size:0.68rem">{{ $lokasiLabel }}</span>
                    </td>
                    <td>
                        <div class="fw-semibold" style="color:#1e293b;font-size:0.88rem">{{ $stock->item?->nama_item ?? '—' }}</div>
                        <code style="background:#f1f5f9;color:#475569;font-size:0.72rem;padding:0.1rem 0.4rem;border-radius:3px">
                            {{ $stock->item?->kode_item ?? '—' }}
                        </code>
                    </td>
                    <td class="d-none d-lg-table-cell">
                        <span class="text-muted" style="font-size:0.83rem">{{ $stock->item?->category?->nama_kategori ?? '—' }}</span>
                    </td>
                    <td class="d-none d-lg-table-cell">
                        @if($stock->item?->tipe)
                        @php
                            $tipeBadge = match($stock->item->tipe) {
                                'bahan_baku'      => 'bg-warning-subtle text-warning',
                                'kemasan'         => 'bg-info-subtle text-info',
                                'tambahan_gratis' => 'bg-primary-subtle text-primary',
                                default           => 'bg-secondary-subtle text-secondary',
                            };
                            $tipeLabel = match($stock->item->tipe) {
                                'bahan_baku'      => 'Bahan Baku',
                                'kemasan'         => 'Kemasan',
                                'tambahan_gratis' => 'Tambahan Gratis',
                                default           => 'Lainnya',
                            };
                        @endphp
                        <span class="badge {{ $tipeBadge }}" style="font-size:0.7rem">{{ $tipeLabel }}</span>
                        @endif
                    </td>
                    <td class="text-center d-none d-lg-table-cell">
                        <span class="text-muted" style="font-size:0.83rem">{{ $stock->item?->satuan ?? '—' }}</span>
                    </td>
                    <td class="text-end d-none d-xl-table-cell">
                        <span class="text-muted" style="font-size:0.83rem">
                            {{ fmt_qty($stock->qty_minimum ?? 0) }}
                        </span>
                    </td>
                    <td class="text-end">
                        <span class="{{ $isBelowMin ? 'qty-danger' : 'qty-normal' }}" style="font-size:0.92rem">
                            @if($isBelowMin)
                                <i class="bi bi-exclamation-triangle-fill me-1" style="font-size:0.75rem"></i>
                            @endif
                            {{ fmt_qty($stock->qty) }}
                        </span>
                        <div class="text-muted" style="font-size:0.72rem">{{ $stock->item?->satuan ?? '' }}</div>
                    </td>
                    <td class="text-center px-4">
                        <div class="d-flex align-items-center justify-content-center gap-1">
                            <a href="{{ route('stok.kartu', ['item_id' => $stock->item_id, 'lokasi_id' => $stock->lokasi_id]) }}"
                                class="btn btn-sm btn-outline-info px-2 py-1"
                                title="Kartu Stok" style="min-width:32px;min-height:32px">
                                <i class="bi bi-journal-text"></i>
                            </a>
                            <a href="{{ route('stok.adjustment', ['item_id' => $stock->item_id, 'lokasi_id' => $stock->lokasi_id]) }}"
                                class="btn btn-sm btn-outline-warning px-2 py-1"
                                title="Adjustment" style="min-width:32px;min-height:32px">
                                <i class="bi bi-sliders"></i>
                            </a>
                            @can('stok.minimum.set')
                            <button type="button" class="btn btn-sm btn-outline-secondary px-2 py-1"
                                title="Set Minimum" style="min-width:32px;min-height:32px"
                                onclick="bukaModalSetMinimum('{{ route('stok.set-minimum', $stock) }}', '{{ addslashes($stock->item?->nama_item ?? '') }}', '{{ addslashes($stock->lokasi?->nama_cabang ?? '') }}', {{ (float) ($stock->qty_minimum ?? 0) }})">
                                <i class="bi bi-bullseye"></i>
                            </button>
                            @endcan
                            @can('stok.hapus.reset')
                            <button type="button" class="btn btn-sm btn-outline-danger px-2 py-1"
                                title="Hapus Stok (Reset ke 0)" style="min-width:32px;min-height:32px"
                                onclick="bukaModalResetStok('{{ route('stok.reset', $stock) }}', '{{ addslashes($stock->item?->nama_item ?? '') }}', '{{ addslashes($stock->lokasi?->nama_cabang ?? '') }}')">
                                <i class="bi bi-trash"></i>
                            </button>
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-5">
                        <div class="text-muted">
                            <i class="bi bi-inbox" style="font-size:2.5rem;opacity:0.3"></i>
                            <p class="mt-2 mb-0">Tidak ada data stok ditemukan.</p>
                            @if(request()->hasAny(['search','kategori','tipe','lokasi','kritis']))
                                <a href="{{ route('stok.index') }}" class="btn btn-sm btn-outline-primary mt-2">Reset Filter</a>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($stocks->hasPages())
    <div class="card-footer py-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <small class="text-muted">
            Menampilkan {{ $stocks->firstItem() }}–{{ $stocks->lastItem() }} dari {{ $stocks->total() }} data
        </small>
        {{ $stocks->links('pagination::bootstrap-5') }}
    </div>
    @endif
</div>

{{-- MOBILE CARD VIEW --}}
<div class="d-md-none">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <small class="text-muted">{{ $stocks->total() }} data stok</small>
    </div>

    @forelse($stocks as $stock)
    @php $isBelowMin = $stock->qty <= $stock->qty_minimum && $stock->qty_minimum > 0; @endphp
    <div class="stok-card p-3 mb-3" style="{{ $isBelowMin ? 'border-color:#fecaca;background:#fff8f8' : '' }}">
        <div class="d-flex align-items-start justify-content-between gap-2">
            <div class="flex-grow-1 min-w-0">
                <div class="fw-semibold mb-1" style="color:#1e293b">{{ $stock->item?->nama_item ?? '—' }}</div>
                <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                    <code style="background:#f1f5f9;color:#475569;font-size:0.72rem;padding:0.1rem 0.35rem;border-radius:3px">
                        {{ $stock->item?->kode_item ?? '—' }}
                    </code>
                    <small class="text-muted">{{ $stock->lokasi?->nama_cabang ?? '—' }}</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="{{ $isBelowMin ? 'text-danger fw-bold' : 'fw-semibold text-success' }}" style="font-size:1.05rem">
                        @if($isBelowMin)
                            <i class="bi bi-exclamation-triangle-fill me-1" style="font-size:0.8rem"></i>
                        @endif
                        {{ fmt_qty($stock->qty) }} {{ $stock->item?->satuan ?? '' }}
                    </span>
                    @if($isBelowMin)
                        <span class="badge bg-danger-subtle text-danger" style="font-size:0.68rem">Stok Kritis</span>
                    @endif
                </div>
            </div>
            <div class="dropdown flex-shrink-0">
                <button class="btn btn-sm btn-outline-secondary rounded-circle"
                    style="width:36px;height:36px;padding:0"
                    data-bs-toggle="dropdown">
                    <i class="bi bi-three-dots-vertical" style="font-size:0.9rem"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li>
                        <a href="{{ route('stok.kartu', ['item_id' => $stock->item_id, 'lokasi_id' => $stock->lokasi_id]) }}"
                            class="dropdown-item">
                            <i class="bi bi-journal-text me-2 text-info"></i>Kartu Stok
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('stok.adjustment', ['item_id' => $stock->item_id, 'lokasi_id' => $stock->lokasi_id]) }}"
                            class="dropdown-item">
                            <i class="bi bi-sliders me-2 text-warning"></i>Adjustment
                        </a>
                    </li>
                    @can('stok.minimum.set')
                    <li>
                        <a href="#" class="dropdown-item"
                            onclick="bukaModalSetMinimum('{{ route('stok.set-minimum', $stock) }}', '{{ addslashes($stock->item?->nama_item ?? '') }}', '{{ addslashes($stock->lokasi?->nama_cabang ?? '') }}', {{ (float) ($stock->qty_minimum ?? 0) }}); return false;">
                            <i class="bi bi-bullseye me-2 text-secondary"></i>Set Minimum
                        </a>
                    </li>
                    @endcan
                    @can('stok.hapus.reset')
                    <li>
                        <a href="#" class="dropdown-item text-danger"
                            onclick="bukaModalResetStok('{{ route('stok.reset', $stock) }}', '{{ addslashes($stock->item?->nama_item ?? '') }}', '{{ addslashes($stock->lokasi?->nama_cabang ?? '') }}'); return false;">
                            <i class="bi bi-trash me-2"></i>Hapus Stok (Reset ke 0)
                        </a>
                    </li>
                    @endcan
                </ul>
            </div>
        </div>
    </div>
    @empty
    <div class="text-center py-5 text-muted">
        <i class="bi bi-inbox" style="font-size:2.5rem;opacity:0.3"></i>
        <p class="mt-2">Tidak ada data stok ditemukan.</p>
    </div>
    @endforelse

    @if($stocks->hasPages())
    <div class="mt-3">
        {{ $stocks->links('pagination::bootstrap-5') }}
    </div>
    @endif
</div>

@can('stok.minimum.set')
{{-- Modal Set Minimum — 1 modal dipakai bareng semua baris (desktop + mobile) --}}
<div class="modal fade" id="modalSetMinimum" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formSetMinimum" method="POST">
                @csrf
                @method('PATCH')
                <div class="modal-header">
                    <h6 class="modal-title mb-0"><i class="bi bi-bullseye me-2 text-secondary"></i>Set Stok Minimum</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        <span id="setMinNamaItem" class="fw-semibold text-dark"></span> — <span id="setMinNamaCabang"></span>
                    </p>
                    <label class="form-label fw-semibold">Qty Minimum</label>
                    <input type="number" step="0.001" min="0" name="qty_minimum" id="setMinInput"
                        class="form-control" required>
                    <div class="form-text">Alert &amp; notifikasi stok minimum akan aktif kalau qty stok ≤ nilai ini.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="button" id="btnSimpanSetMinimum" class="btn btn-primary btn-sm">
                        <i class="bi bi-check-circle me-1"></i>Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

@can('stok.hapus.reset')
{{-- Modal Reset Stok — 1 modal dipakai bareng semua baris (desktop + mobile).
     Aksi destruktif: qty di-set 0, batch di-soft-delete, TIDAK ada transaksi
     keuangan. Konfirmasi wajib ketik ulang nama item persis. --}}
<div class="modal fade" id="modalResetStok" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-danger">
            <form id="formResetStok" method="POST">
                @csrf
                @method('DELETE')
                <div class="modal-header bg-danger-subtle">
                    <h6 class="modal-title mb-0 text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>AKSI DESTRUKTIF — Hapus Stok</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small mb-3">
                        <span id="resetStokNamaItem" class="fw-semibold text-dark"></span> — <span id="resetStokNamaCabang"></span>
                    </p>
                    <div class="alert alert-danger py-2 small mb-3">
                        <i class="bi bi-info-circle me-1"></i>
                        Aksi ini akan <strong>reset qty ke 0</strong>, <strong>hapus semua batch stok</strong> item ini di lokasi ini,
                        dan <strong>TIDAK</strong> membuat transaksi keuangan apapun. Riwayat pergerakan stok lama tetap tersimpan (tidak dihapus).
                        Batch yang dihapus masih bisa dipulihkan lewat menu <strong>Data Terhapus</strong>, tapi qty stok yang sudah di-reset TIDAK otomatis kembali.
                    </div>
                    <label class="form-label fw-semibold small">
                        Ketik nama item untuk konfirmasi: <span id="resetStokNamaKonfirmasi" class="text-danger"></span>
                    </label>
                    <input type="text" name="konfirmasi_nama" id="resetStokInput" class="form-control" autocomplete="off" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" id="btnKonfirmasiResetStok" class="btn btn-danger btn-sm" disabled>
                        <i class="bi bi-trash me-1"></i>Konfirmasi Hapus
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

@endsection

@push('scripts')
<script>
function bukaModalSetMinimum(actionUrl, namaItem, namaCabang, currentMin) {
    const form = document.getElementById('formSetMinimum');
    if (!form) return;
    form.action = actionUrl;
    document.getElementById('setMinNamaItem').textContent = namaItem;
    document.getElementById('setMinNamaCabang').textContent = namaCabang;
    document.getElementById('setMinInput').value = currentMin;
    new bootstrap.Modal(document.getElementById('modalSetMinimum')).show();
}

const btnSimpanSetMinimum = document.getElementById('btnSimpanSetMinimum');
if (btnSimpanSetMinimum) {
    btnSimpanSetMinimum.addEventListener('click', async function () {
        const form       = document.getElementById('formSetMinimum');
        const nilai       = document.getElementById('setMinInput').value;
        const namaItem   = document.getElementById('setMinNamaItem').textContent;
        const namaCabang = document.getElementById('setMinNamaCabang').textContent;
        const pesan = `Set minimum untuk ${namaItem} di ${namaCabang} ke ${nilai}?`;

        let ok = true;
        if (typeof window.showConfirm === 'function') {
            const res = await window.showConfirm('Konfirmasi', pesan, { icon: 'question', confirmText: 'Ya, Simpan', cancelText: 'Batal' });
            ok = res.isConfirmed;
        } else {
            ok = window.confirm(pesan);
        }

        if (ok) form.submit();
    });
}

// ── Reset Stok — aksi destruktif, konfirmasi ketik ulang nama item ──
let resetStokNamaAsli = '';

function bukaModalResetStok(actionUrl, namaItem, namaCabang) {
    const form = document.getElementById('formResetStok');
    if (!form) return;
    resetStokNamaAsli = namaItem;
    form.action = actionUrl;
    document.getElementById('resetStokNamaItem').textContent = namaItem;
    document.getElementById('resetStokNamaCabang').textContent = namaCabang;
    document.getElementById('resetStokNamaKonfirmasi').textContent = namaItem;
    const input = document.getElementById('resetStokInput');
    input.value = '';
    document.getElementById('btnKonfirmasiResetStok').disabled = true;
    new bootstrap.Modal(document.getElementById('modalResetStok')).show();
    setTimeout(() => input.focus(), 300);
}

const resetStokInput = document.getElementById('resetStokInput');
if (resetStokInput) {
    resetStokInput.addEventListener('input', function () {
        document.getElementById('btnKonfirmasiResetStok').disabled = (this.value !== resetStokNamaAsli);
    });
}

const formResetStok = document.getElementById('formResetStok');
if (formResetStok) {
    formResetStok.addEventListener('submit', async function (e) {
        if (resetStokInput.value !== resetStokNamaAsli) {
            e.preventDefault();
            return;
        }
        e.preventDefault();
        const pesan = `Yakin reset stok "${resetStokNamaAsli}" ke 0? Aksi ini TIDAK bisa dibatalkan lewat UI.`;
        let ok = true;
        if (typeof window.showConfirm === 'function') {
            const res = await window.showConfirm('Konfirmasi Terakhir', pesan, { icon: 'warning', confirmText: 'Ya, Reset Sekarang', cancelText: 'Batal' });
            ok = res.isConfirmed;
        } else {
            ok = window.confirm(pesan);
        }
        if (ok) this.submit();
    });
}
</script>
@endpush
