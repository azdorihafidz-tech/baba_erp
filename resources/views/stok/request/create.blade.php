@extends('layouts.app')

@section('title', 'Buat Permintaan Stok')

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
</style>
@endpush

@section('content')

{{-- PAGE HEADER --}}
<div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-0" style="color:#1e293b">
            <i class="bi bi-cart-plus me-2 text-primary"></i>Buat Permintaan Stok
        </h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0" style="font-size:0.8rem">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('stock-request.index') }}" class="text-decoration-none">Permintaan Stok</a></li>
                <li class="breadcrumb-item active">Buat Baru</li>
            </ol>
        </nav>
    </div>
</div>

<div class="alert alert-info d-flex align-items-start gap-2 mb-4" role="alert">
    <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
    <div style="font-size:0.87rem">
        Permintaan ini akan dikirim ke <strong>Gudang Pusat</strong> untuk diproses.
        Gudang Pusat akan menyetujui permintaan dan mengirimkan stok yang diminta ke cabang Anda.
    </div>
</div>

<form method="POST" action="{{ route('stock-request.store') }}" id="formRequest">
@csrf

<div class="row g-4">
    <div class="col-12 col-lg-8">

        {{-- CATATAN --}}
        <div class="form-card p-4 mb-4">
            <div class="section-title"><i class="bi bi-chat-left-text me-1"></i>Informasi Permintaan</div>
            <div class="mb-0">
                <label class="form-label fw-semibold" style="font-size:0.85rem">Catatan (Opsional)</label>
                <textarea name="catatan" rows="2"
                    class="form-control @error('catatan') is-invalid @enderror"
                    placeholder="cth: Permintaan mendesak untuk produksi minggu ini...">{{ old('catatan') }}</textarea>
                @error('catatan')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        {{-- DAFTAR ITEM --}}
        <div class="form-card p-4">
            <div class="section-title d-flex align-items-center justify-content-between">
                <span><i class="bi bi-list-check me-1"></i>Daftar Item yang Diminta</span>
                <button type="button" id="btnTambahItem"
                    class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1"
                    style="font-size:0.8rem;padding:0.3rem 0.75rem">
                    <i class="bi bi-plus-lg"></i> Tambah Item
                </button>
            </div>

            <div id="itemContainer">
                {{-- Baris item diisi via JS atau dari old() --}}
            </div>

            @error('items')
                <div class="alert alert-danger py-2 mt-2" style="font-size:0.83rem">{{ $message }}</div>
            @enderror
            @error('items.*.item_id')
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
                    <i class="bi bi-send me-2"></i>Kirim Permintaan
                </button>
                <a href="{{ route('stock-request.index') }}" class="btn btn-outline-secondary" style="min-height:44px">
                    <i class="bi bi-arrow-left me-2"></i>Batal
                </a>
            </div>
            <div class="mt-3 p-3 rounded" style="background:#f8fafc;font-size:0.78rem;color:#64748b">
                <p class="mb-1"><i class="bi bi-check-circle text-success me-1"></i>Permintaan akan dikirim ke Gudang Pusat</p>
                <p class="mb-1"><i class="bi bi-clock text-warning me-1"></i>Tunggu persetujuan dari Gudang Pusat</p>
                <p class="mb-0"><i class="bi bi-truck text-info me-1"></i>Barang dikirim setelah disetujui</p>
            </div>
        </div>
    </div>
</div>

</form>

{{-- Template baris item (disembunyikan, di-clone oleh JS) --}}
<template id="itemRowTemplate">
    <div class="item-row" data-index="__IDX__">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-sm-5 col-md-5">
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
            <div class="col-5 col-sm-3 col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem">
                    Qty <span class="text-danger">*</span>
                </label>
                <input type="number" name="items[__IDX__][qty_diminta]"
                    class="form-control form-control-sm"
                    placeholder="0" min="0.001" step="0.001" required>
            </div>
            <div class="col-3 col-sm-2 col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem">Satuan</label>
                <input type="text" class="form-control form-control-sm satuan-display"
                    placeholder="—" readonly style="background:#f8fafc">
            </div>
            <div class="col-12 col-sm-8 col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.78rem">Catatan</label>
                <input type="text" name="items[__IDX__][catatan]"
                    class="form-control form-control-sm"
                    placeholder="opsional...">
            </div>
            <div class="col-auto ms-auto">
                <button type="button" class="btn btn-outline-danger btn-sm btn-hapus-row" title="Hapus baris ini">
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
    const container   = document.getElementById('itemContainer');
    const emptyWarn   = document.getElementById('emptyItemWarning');
    const btnTambah   = document.getElementById('btnTambahItem');
    const template    = document.getElementById('itemRowTemplate');

    // Old values dari validasi error
    const oldItems = @json(old('items', []));

    function createRow(idx, oldData = null) {
        const tplContent = template.innerHTML.replace(/__IDX__/g, idx);
        const div = document.createElement('div');
        div.innerHTML = tplContent;
        const row = div.firstElementChild;

        // Pre-fill old data
        if (oldData) {
            const sel = row.querySelector('.select-item');
            if (sel && oldData.item_id) {
                sel.value = oldData.item_id;
                // Update satuan
                const opt = sel.options[sel.selectedIndex];
                if (opt) {
                    const satuanInp = row.querySelector('.satuan-display');
                    if (satuanInp) satuanInp.value = opt.getAttribute('data-satuan') || '—';
                }
            }
            const qtyInp = row.querySelector('input[name$="[qty_diminta]"]');
            if (qtyInp && oldData.qty_diminta) qtyInp.value = oldData.qty_diminta;
            const catInp = row.querySelector('input[name$="[catatan]"]');
            if (catInp && oldData.catatan) catInp.value = oldData.catatan;
        }

        // Event: pilih item → update satuan
        row.querySelector('.select-item').addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            const satuanEl = this.closest('.item-row').querySelector('.satuan-display');
            satuanEl.value = opt ? (opt.getAttribute('data-satuan') || '—') : '—';
        });

        // Event: hapus baris
        row.querySelector('.btn-hapus-row').addEventListener('click', function() {
            row.remove();
            toggleEmpty();
        });

        container.appendChild(row);
        toggleEmpty();
    }

    function toggleEmpty() {
        const rows = container.querySelectorAll('.item-row');
        emptyItemWarning.style.display = rows.length === 0 ? 'block' : 'none';
    }

    btnTambah.addEventListener('click', function() {
        createRow(rowIndex++);
    });

    // Restore dari old() saat validasi error
    if (oldItems && oldItems.length > 0) {
        oldItems.forEach((item, i) => createRow(i, item));
        rowIndex = oldItems.length;
    } else {
        // Tampilkan 1 baris kosong secara default
        createRow(rowIndex++);
    }

    // Validasi: harus ada minimal 1 item sebelum submit
    document.getElementById('formRequest').addEventListener('submit', function(e) {
        const rows = container.querySelectorAll('.item-row');
        if (rows.length === 0) {
            e.preventDefault();
            alert('Tambahkan minimal 1 item yang diminta.');
        }
    });
</script>
@endpush
