@extends('layouts.app')

@section('title', 'Edit Resep Bumbu: ' . $resepBumbu->nama)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h5 class="mb-0 fw-bold"><i class="bi bi-pencil me-2 text-warning"></i>Edit Resep — {{ $resepBumbu->nama }}</h5>
    <div class="d-flex gap-2 align-items-center">
        <x-panduan-button slug="resep-bumbu" />
        <a href="{{ route('master.resep-bumbu.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="row g-3">
    {{-- Info Resep --}}
    <div class="col-12 col-lg-5">
        <div class="card">
            <div class="card-header">Info Resep</div>
            <div class="card-body">
                <x-peringatan-beda-cabang :cabang-id="$resepBumbu->cabang_id" />
                <form method="POST" action="{{ route('master.resep-bumbu.update', $resepBumbu) }}">
                    @csrf @method('PUT')
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Nama Resep <span class="text-danger">*</span></label>
                            <input type="text" name="nama" class="form-control @error('nama') is-invalid @enderror"
                                value="{{ old('nama', $resepBumbu->nama) }}" required>
                            @error('nama')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Kode <span class="text-danger">*</span></label>
                            <input type="text" name="kode" class="form-control @error('kode') is-invalid @enderror"
                                value="{{ old('kode', $resepBumbu->kode) }}" required maxlength="20">
                            @error('kode')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Jenis Menu Induk</label>
                            <select name="jenis_olahan_id" class="form-select @error('jenis_olahan_id') is-invalid @enderror">
                                <option value="">-- Tidak Dikaitkan --</option>
                                @foreach($jenisOlahans as $jo)
                                <option value="{{ $jo->id }}" @selected(old('jenis_olahan_id', $resepBumbu->jenis_olahan_id) == $jo->id)>{{ $jo->nama }}</option>
                                @endforeach
                            </select>
                            @error('jenis_olahan_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Catatan</label>
                            <textarea name="catatan" class="form-control" rows="2">{{ old('catatan', $resepBumbu->catatan) }}</textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActiveSwitch"
                                    {{ old('is_active', $resepBumbu->is_active) ? 'checked' : '' }}>
                                <label class="form-check-label" for="isActiveSwitch">Aktif (tampil di POS)</label>
                            </div>
                        </div>
                        <div class="col-12 mt-2">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-check-circle me-1"></i>Simpan Perubahan
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Daftar Bahan --}}
    <div class="col-12 col-lg-7">
        <div class="card mb-3">
            <div class="card-header">Daftar Bahan (per 1 unit produksi — porsi/pcs)</div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Bahan</th>
                            <th class="text-end">Takaran</th>
                            <th class="text-end">Harga (FIFO)</th>
                            <th class="text-end">Subtotal</th>
                            <th class="text-center" style="width:50px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($resepBumbu->items as $item)
                        <tr>
                            <td class="small">{{ $item->item?->nama_item ?? '(item terhapus)' }}</td>
                            <td class="text-end small">{{ rtrim(rtrim(number_format($item->qty_per_unit, 3, ',', '.'), '0'), ',') }} {{ $item->satuan }}</td>
                            @php
                                $h = $item->item ? ($hargaBahan[$item->item->id] ?? null) : null;
                                $subtotalBaris = $h ? $item->qty_per_unit_dalam_kg * $h['harga'] : 0;
                                $totalBumbu = ($totalBumbu ?? 0) + $subtotalBaris;
                            @endphp
                            <td class="text-end small">
                                @if($h && $h['harga'] > 0)
                                    <span class="{{ $h['habis'] ? 'text-danger' : '' }}">Rp {{ number_format($h['harga'], 0, ',', '.') }}</span>
                                    <div class="{{ $h['habis'] ? 'text-danger' : 'text-muted' }}" style="font-size:0.7rem">{{ $h['info'] }}</div>
                                @else
                                    <span class="text-muted">Belum ada harga</span>
                                @endif
                            </td>
                            <td class="text-end small fw-semibold">
                                Rp {{ number_format($subtotalBaris, 0, ',', '.') }}
                            </td>
                            <td class="text-center">
                                <form method="POST" action="{{ route('master.resep-bumbu.items.destroy', [$resepBumbu, $item]) }}"
                                    onsubmit="return confirm('Hapus bahan ini dari resep?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger px-2 py-0" title="Hapus">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-3">Belum ada bahan. Tambahkan di form bawah.</td>
                        </tr>
                        @endforelse
                    </tbody>
                    @if($resepBumbu->items->isNotEmpty())
                    <tfoot>
                        <tr class="table-light fw-bold">
                            <td colspan="3" class="text-end">Total Bahan per 1 unit produksi (porsi/pcs):</td>
                            <td class="text-end">Rp {{ number_format($totalBumbu ?? 0, 0, ',', '.') }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
            <div class="card-body py-2 border-top">
                <small class="text-muted"><i class="bi bi-info-circle me-1"></i>Harga = harga PO dari stok yang masih ada di cabang ini (<strong>FIFO</strong>, batch tertua terpakai duluan). Stok habis → harga PO terakhir (merah). Semua bahan selalu dihitung ke HPP.</small>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Tambah Bahan</div>
            <div class="card-body">
                <form method="POST" action="{{ route('master.resep-bumbu.items.store', $resepBumbu) }}">
                    @csrf
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-md-4">
                            <label class="form-label small">Bahan <span class="text-danger">*</span></label>
                            <select name="item_id" id="itemIdSelect" class="form-select form-select-sm" required>
                                <option value="">-- Pilih Bahan --</option>
                                @foreach($bahanBakuItems as $b)
                                <option value="{{ $b->id }}" data-harga-beli="{{ $hargaBahan[$b->id]['harga'] ?? 0 }}" data-satuan="{{ $b->satuan }}">{{ $b->nama_item }} ({{ $b->kode_item }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small">Takaran <span class="text-danger">*</span></label>
                            <input type="number" name="qty_per_unit" id="qtyPerUnitInput" class="form-control form-control-sm" step="0.001" min="0.001" required>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small">Satuan <span class="text-danger">*</span></label>
                            <select name="satuan" id="satuanSelect" class="form-select form-select-sm" required>
                                <option value="kg">kg</option>
                                <option value="g">gram</option>
                                <option value="ons">ons</option>
                                <option value="pcs">pcs</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <small class="text-muted" id="previewTotalBahan">Perkiraan total: Rp 0</small>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-plus-lg me-1"></i>Tambah Bahan
                            </button>
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
// Preview harga (murni tampilan, TIDAK dikirim ke server) — update live saat
// bahan/takaran/satuan di form "Tambah Bahan" diubah.
(function () {
    const itemSelect  = document.getElementById('itemIdSelect');
    const qtyInput    = document.getElementById('qtyPerUnitInput');
    const satuanEl    = document.getElementById('satuanSelect');
    const previewEl   = document.getElementById('previewTotalBahan');
    if (!itemSelect || !qtyInput || !satuanEl || !previewEl) return;

    function konversiKeKg(qty, satuan) {
        if (satuan === 'g') return qty / 1000;
        if (satuan === 'ons') return qty / 10;
        return qty; // 'kg'
    }

    // Auto-isi Satuan sesuai satuan asli bahan (Master Bahan Baku/Kemasan)
    // begitu bahan dipilih -- dropdown Satuan cuma punya 4 opsi baku
    // (kg/g/ons/pcs), sedangkan Item.satuan bebas teks ("gram", "ml", "buah",
    // dst), jadi dinormalisasi dulu ke salah satu dari 4 opsi itu. Kalau
    // satuan bahan tidak dikenali (mis. "liter"/"ml" -- belum ada opsi
    // volume di sini), dropdown DIBIARKAN apa adanya supaya user pilih
    // manual sendiri, bukan dipaksa ke nilai yang salah.
    function normalisasiSatuan(raw) {
        const s = (raw || '').toLowerCase().trim();
        if (['kg', 'kilogram'].includes(s)) return 'kg';
        if (['g', 'gr', 'gram'].includes(s)) return 'g';
        if (['ons'].includes(s)) return 'ons';
        if (['pcs', 'pc', 'buah', 'porsi', 'biji', 'lembar'].includes(s)) return 'pcs';
        return null;
    }

    function autoisiSatuan() {
        const opt = itemSelect.selectedOptions[0];
        if (!opt) return;
        const satuanNormal = normalisasiSatuan(opt.dataset.satuan);
        if (satuanNormal) satuanEl.value = satuanNormal;
    }
    itemSelect.addEventListener('change', autoisiSatuan);

    function updatePreview() {
        const opt = itemSelect.selectedOptions[0];
        const hargaBeli = opt ? (parseFloat(opt.dataset.hargaBeli) || 0) : 0;
        const qty = parseFloat(qtyInput.value) || 0;
        const qtyKg = konversiKeKg(qty, satuanEl.value);
        const total = qtyKg * hargaBeli;
        previewEl.textContent = 'Perkiraan total: Rp ' + total.toLocaleString('id-ID', { maximumFractionDigits: 0 })
            + (hargaBeli === 0 ? ' (bahan ini belum punya harga)' : ' (harga FIFO stok cabang)');
    }

    [itemSelect, qtyInput, satuanEl].forEach(el => {
        el.addEventListener('input', updatePreview);
        el.addEventListener('change', updatePreview);
    });

    updatePreview();
})();
</script>
@endpush
