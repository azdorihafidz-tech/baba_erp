@extends('layouts.app')

@section('title', 'Buat Transfer Stok')

@push('styles')
<style>
    .form-card { border: 1px solid #e2e8f0; border-radius: 12px; background: white; }
    .section-title {
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #64748b;
        border-bottom: 1px solid #f1f5f9;
        padding-bottom: 0.6rem;
        margin-bottom: 1rem;
    }
    .item-row { background: #fafafa; border-radius: 8px; border: 1px solid #e9ecef; padding: 0.75rem; margin-bottom: 0.5rem; }
    .btn-hapus-row {
        min-width: 36px; min-height: 36px;
        display: flex; align-items: center; justify-content: center;
        padding: 0;
    }
    .stok-tersedia-badge {
        font-size: 0.72rem;
        background: #dcfce7;
        color: #166534;
        padding: 0.15rem 0.45rem;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
    }
</style>
@endpush

@section('content')

{{-- PAGE HEADER --}}
<div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-0" style="color:#1e293b">
            <i class="bi bi-arrow-left-right me-2 text-primary"></i>Buat Transfer Stok
        </h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0" style="font-size:0.8rem">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('stock-transfer.index') }}" class="text-decoration-none">Transfer Stok</a></li>
                <li class="breadcrumb-item active">Buat Baru</li>
            </ol>
        </nav>
    </div>
</div>

{{-- Alert dari Stock Request --}}
@if(isset($stockRequest) && $stockRequest)
<div class="alert alert-info d-flex align-items-start gap-2 mb-4" role="alert">
    <i class="bi bi-link-45deg flex-shrink-0 mt-1" style="font-size:1.1rem"></i>
    <div style="font-size:0.87rem">
        Transfer ini dibuat berdasarkan <strong>Permintaan Stok
        <a href="{{ route('stock-request.show', $stockRequest) }}" class="alert-link">
            {{ $stockRequest->nomor_request }}
        </a></strong> dari cabang <strong>{{ $stockRequest->cabang?->nama_cabang }}</strong>.
        Item dari permintaan telah di-pre-fill di bawah.
    </div>
</div>
@endif

<form method="POST" action="{{ route('stock-transfer.store') }}" id="formTransfer">
@csrf

@if(isset($stockRequest) && $stockRequest)
<input type="hidden" name="stock_request_id" value="{{ $stockRequest->id }}">
@endif

<div class="row g-4">
    <div class="col-12 col-lg-8">

        {{-- INFO TRANSFER --}}
        <div class="form-card p-4 mb-4">
            <div class="section-title"><i class="bi bi-info-circle me-1"></i>Informasi Transfer</div>

            <div class="row g-3 mb-3">
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold" style="font-size:0.85rem">
                        Dari Lokasi <span class="text-danger">*</span> <x-tooltip key="stock-transfer.dari_lokasi_id" />
                    </label>
                    <select name="dari_lokasi_id" id="dari_lokasi_id"
                        class="form-select @error('dari_lokasi_id') is-invalid @enderror" required>
                        <option value="">— Pilih Lokasi Asal —</option>
                        @foreach($lokasiList as $lok)
                        <option value="{{ $lok->id }}"
                            {{ old('dari_lokasi_id', $defaultDariLokasiId) == $lok->id ? 'selected' : '' }}>
                            {{ $lok->nama_cabang }}
                            @if($lok->tipe?->value === 'gudang_pusat') (Gudang Pusat) @endif
                        </option>
                        @endforeach
                    </select>
                    @error('dari_lokasi_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold" style="font-size:0.85rem">
                        Ke Lokasi <span class="text-danger">*</span> <x-tooltip key="stock-transfer.ke_lokasi_id" />
                    </label>
                    <select name="ke_lokasi_id" id="ke_lokasi_id"
                        class="form-select @error('ke_lokasi_id') is-invalid @enderror" required>
                        <option value="">— Pilih Lokasi Tujuan —</option>
                        @foreach($lokasiList as $lok)
                        <option value="{{ $lok->id }}"
                            {{ old('ke_lokasi_id', $stockRequest?->cabang_id) == $lok->id ? 'selected' : '' }}>
                            {{ $lok->nama_cabang }}
                            @if($lok->tipe?->value === 'gudang_pusat') (Gudang Pusat) @endif
                        </option>
                        @endforeach
                    </select>
                    @error('ke_lokasi_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold" style="font-size:0.85rem">
                        Tanggal Kirim <span class="text-danger">*</span>
                    </label>
                    <input type="date" name="tanggal_kirim"
                        class="form-control @error('tanggal_kirim') is-invalid @enderror"
                        value="{{ old('tanggal_kirim', date('Y-m-d')) }}"
                        required>
                    @error('tanggal_kirim')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold" style="font-size:0.85rem">Catatan (Opsional)</label>
                    <textarea name="catatan" rows="2"
                        class="form-control @error('catatan') is-invalid @enderror"
                        placeholder="cth: Pengiriman rutin mingguan ke Cabang Selatan...">{{ old('catatan') }}</textarea>
                    @error('catatan')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            {{-- Stock Request optional select (jika tidak dari request) --}}
            @if(!isset($stockRequest) || !$stockRequest)
            <div class="mt-3">
                <label class="form-label fw-semibold" style="font-size:0.85rem">
                    Berdasarkan Permintaan (Opsional) <x-tooltip key="stock-transfer.stock_request_id" />
                </label>
                <select name="stock_request_id"
                    class="form-select @error('stock_request_id') is-invalid @enderror">
                    <option value="">— Tidak terkait permintaan —</option>
                    @foreach($pendingRequests ?? [] as $sr)
                    <option value="{{ $sr->id }}" {{ old('stock_request_id') == $sr->id ? 'selected' : '' }}>
                        {{ $sr->nomor_request }} — {{ $sr->cabang?->nama_cabang }}
                        ({{ $sr->tanggal_request?->format('d/m/Y') }})
                    </option>
                    @endforeach
                </select>
                @error('stock_request_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            @endif
        </div>

        {{-- DAFTAR ITEM --}}
        <div class="form-card p-4">
            <div class="section-title d-flex align-items-center justify-content-between">
                <span><i class="bi bi-list-check me-1"></i>Daftar Item Transfer</span>
                <button type="button" id="btnTambahItem"
                    class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1"
                    style="font-size:0.8rem;padding:0.3rem 0.75rem">
                    <i class="bi bi-plus-lg"></i> Tambah Item
                </button>
            </div>

            <div id="itemContainer"></div>

            @error('items')
                <div class="alert alert-danger py-2 mt-2" style="font-size:0.83rem">{{ $message }}</div>
            @enderror

            <div id="emptyItemWarning" class="text-center py-4 text-muted" style="display:none">
                <i class="bi bi-inbox" style="font-size:2rem;opacity:0.3"></i>
                <p class="mt-2 mb-0" style="font-size:0.85rem">Belum ada item. Klik "Tambah Item" untuk menambahkan.</p>
            </div>
        </div>

    </div>

    {{-- KOLOM KANAN --}}
    <div class="col-12 col-lg-4">
        <div class="form-card p-4">
            <div class="section-title"><i class="bi bi-lightning me-1"></i>Aksi</div>
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary fw-semibold" style="min-height:44px">
                    <i class="bi bi-check-lg me-2"></i>Simpan Transfer
                </button>
                <a href="{{ route('stock-transfer.index') }}" class="btn btn-outline-secondary" style="min-height:44px">
                    <i class="bi bi-arrow-left me-2"></i>Batal
                </a>
            </div>
            <div class="mt-3 p-3 rounded" style="background:#f8fafc;font-size:0.78rem;color:#64748b">
                <p class="mb-1"><i class="bi bi-info-circle text-primary me-1"></i>Transfer disimpan dengan status <strong>Draft</strong></p>
                <p class="mb-1"><i class="bi bi-truck text-info me-1"></i>Ubah ke <strong>Dikirim</strong> saat barang sudah diberangkatkan</p>
                <p class="mb-0"><i class="bi bi-box-seam text-success me-1"></i>Stok lokasi tujuan bertambah saat status <strong>Diterima</strong></p>
            </div>
        </div>
    </div>
</div>

</form>

{{-- Template baris item --}}
<template id="itemRowTemplate">
    <div class="item-row" data-index="__IDX__">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-sm-5 col-md-4">
                <label class="form-label fw-semibold" style="font-size:0.78rem">
                    Item <span class="text-danger">*</span>
                </label>
                <select name="items[__IDX__][item_id]" class="form-select form-select-sm select-item" required>
                    <option value="">— Pilih Item —</option>
                    @foreach($items as $it)
                    <option value="{{ $it->id }}" data-satuan="{{ $it->satuan }}">
                        [{{ $it->kode_item }}] {{ $it->nama_dengan_cabang }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-sm-5 col-md-3">
                <label class="form-label fw-semibold" style="font-size:0.78rem">Stok Tersedia</label>
                <div class="stok-tersedia-badge stok-info-display" style="height:31px">
                    <i class="bi bi-dash me-1"></i><span class="stok-val">—</span>
                </div>
            </div>
            <div class="col-5 col-sm-4 col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem">
                    Qty Kirim <span class="text-danger">*</span>
                </label>
                <input type="number" name="items[__IDX__][qty_kirim]"
                    class="form-control form-control-sm"
                    placeholder="0" min="0.001" step="0.001" required>
            </div>
            <div class="col-3 col-sm-2 col-md-1">
                <label class="form-label fw-semibold" style="font-size:0.78rem">Satuan</label>
                <input type="text" class="form-control form-control-sm satuan-display"
                    placeholder="—" readonly style="background:#f8fafc">
            </div>
            <div class="col-12 col-sm-6 col-md-2 d-none d-md-block">
                <label class="form-label fw-semibold" style="font-size:0.78rem">Catatan</label>
                <input type="text" name="items[__IDX__][catatan]"
                    class="form-control form-control-sm" placeholder="opsional">
            </div>
            <div class="col-auto ms-auto">
                <button type="button" class="btn btn-outline-danger btn-sm btn-hapus-row" title="Hapus baris">
                    <i class="bi bi-trash3" style="font-size:0.85rem"></i>
                </button>
            </div>
        </div>
    </div>
</template>

@endsection

@push('scripts')
<script>
    let rowIndex = 0;
    const container  = document.getElementById('itemContainer');
    const emptyWarn  = document.getElementById('emptyItemWarning');
    const btnTambah  = document.getElementById('btnTambahItem');
    const template   = document.getElementById('itemRowTemplate');

    // Pre-filled dari stock request
    const prefillItems = @json($prefillItems ?? []);
    // Old values dari validasi error
    const oldItems = @json(old('items', []));

    function fmtQty(qty) {
        const parts = parseFloat(qty).toFixed(3).split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        const dec = parts[1].replace(/0+$/, '');
        return dec ? parts[0] + ',' + dec : parts[0];
    }

    function getStokTersedia(itemId, lokasiId, callback) {
        if (!itemId || !lokasiId) { callback(null); return; }
        fetch(`{{ route('api.stok.qty') }}?item_id=${itemId}&lokasi_id=${lokasiId}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => callback(parseFloat(data.qty ?? 0)))
        .catch(() => callback(null));
    }

    function updateStokDisplay(row) {
        const itemId   = row.querySelector('.select-item').value;
        const lokasiId = document.getElementById('dari_lokasi_id').value;
        const stokEl   = row.querySelector('.stok-val');
        const satuanEl = row.querySelector('.satuan-display');
        const selOpt   = row.querySelector('.select-item').options[row.querySelector('.select-item').selectedIndex];
        const satuan   = selOpt ? (selOpt.getAttribute('data-satuan') || '—') : '—';
        satuanEl.value = satuan;

        if (!itemId || !lokasiId) { stokEl.textContent = '—'; return; }
        stokEl.textContent = '...';
        getStokTersedia(itemId, lokasiId, function(qty) {
            stokEl.textContent = qty !== null ? fmtQty(qty) + ' ' + satuan : '—';
        });
    }

    function createRow(idx, data = null) {
        const tplContent = template.innerHTML.replace(/__IDX__/g, idx);
        const div = document.createElement('div');
        div.innerHTML = tplContent;
        const row = div.firstElementChild;

        // Pre-fill data
        if (data) {
            const sel = row.querySelector('.select-item');
            if (sel && data.item_id) sel.value = data.item_id;
            const qtyInp = row.querySelector('input[name$="[qty_kirim]"]');
            if (qtyInp && data.qty_kirim) qtyInp.value = data.qty_kirim;
            const catInp = row.querySelector('input[name$="[catatan]"]');
            if (catInp && data.catatan) catInp.value = data.catatan;
            const satuanEl = row.querySelector('.satuan-display');
            if (satuanEl && data.satuan) satuanEl.value = data.satuan;
        }

        // Event: pilih item
        row.querySelector('.select-item').addEventListener('change', function() {
            updateStokDisplay(row);
        });

        // Event: hapus baris
        row.querySelector('.btn-hapus-row').addEventListener('click', function() {
            row.remove();
            toggleEmpty();
        });

        container.appendChild(row);
        toggleEmpty();

        // Update stok display jika ada data
        if (data && data.item_id) updateStokDisplay(row);
    }

    function toggleEmpty() {
        const rows = container.querySelectorAll('.item-row');
        emptyItemWarning.style.display = rows.length === 0 ? 'block' : 'none';
    }

    btnTambah.addEventListener('click', function() {
        createRow(rowIndex++);
    });

    // Update semua stok display saat lokasi asal berubah
    document.getElementById('dari_lokasi_id').addEventListener('change', function() {
        container.querySelectorAll('.item-row').forEach(row => updateStokDisplay(row));
    });

    // Init: dari prefill, old(), atau 1 baris kosong
    if (oldItems && oldItems.length > 0) {
        oldItems.forEach((item, i) => createRow(i, item));
        rowIndex = oldItems.length;
    } else if (prefillItems && prefillItems.length > 0) {
        prefillItems.forEach((item, i) => createRow(i, item));
        rowIndex = prefillItems.length;
    } else {
        createRow(rowIndex++);
    }

    // Validasi: minimal 1 item
    document.getElementById('formTransfer').addEventListener('submit', function(e) {
        const rows = container.querySelectorAll('.item-row');
        if (rows.length === 0) {
            e.preventDefault();
            alert('Tambahkan minimal 1 item untuk ditransfer.');
        }
    });
</script>
@endpush
