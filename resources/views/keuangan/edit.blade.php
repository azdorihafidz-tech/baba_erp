@extends('layouts.app')

@section('title', 'Edit Transaksi Keuangan')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold">
        <i class="bi bi-pencil me-2 text-warning"></i>Edit Transaksi Keuangan
    </h5>
    <a href="{{ route('keuangan.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Edit Transaksi — <strong>{{ $transaksi->nomor_transaksi }}</strong></span>
                <span class="badge bg-secondary">Manual</span>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('keuangan.update', $transaksi) }}" enctype="multipart/form-data">
                    @csrf @method('PUT')
                    <div class="row g-3">

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Tanggal <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal_transaksi" class="form-control @error('tanggal_transaksi') is-invalid @enderror"
                                value="{{ old('tanggal_transaksi', $transaksi->tanggal_transaksi->format('Y-m-d')) }}" required>
                            @error('tanggal_transaksi')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Tipe <span class="text-danger">*</span></label>
                            <select name="tipe" id="tipeSelect" class="form-select @error('tipe') is-invalid @enderror" required>
                                <option value="">-- Pilih Tipe --</option>
                                @foreach($tipes as $t)
                                <option value="{{ $t->value }}" @selected(old('tipe', $transaksi->tipe->value) === $t->value)>{{ $t->label() }}</option>
                                @endforeach
                            </select>
                            @error('tipe')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">
                                Kategori <span class="text-danger">*</span>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1" style="font-size:0.7rem;line-height:1.4"
                                    data-bs-toggle="modal" data-bs-target="#coaCheatSheetModal" title="Cheat sheet kode akun COA">?</button>
                            </label>
                            <select name="kategori_id" id="kategoriSelect" class="form-select @error('kategori_id') is-invalid @enderror" required>
                                @php $selectedKatId = old('kategori_id', $transaksi->kategori_id); @endphp
                                <option value="">-- Pilih Kategori --</option>
                                @foreach($kategoris as $k)
                                <option value="{{ $k->id }}"
                                    data-tipe="{{ $k->tipe }}"
                                    @selected($selectedKatId == $k->id)>
                                    {{ $k->parent ? $k->parent->nama . ' › ' : '' }}{{ $k->nama }}
                                </option>
                                @endforeach
                            </select>
                            @error('kategori_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 col-md-6" id="kategoriPengeluaranWrap" style="display:none">
                            <label class="form-label fw-semibold">Kategori Pengeluaran <span class="text-danger">*</span></label>
                            @php $selectedKatPengeluaran = old('kategori_pengeluaran', $transaksi->kategori_pengeluaran?->value ?? 'lain_lain'); @endphp
                            <select name="kategori_pengeluaran" id="kategoriPengeluaranSelect" class="form-select @error('kategori_pengeluaran') is-invalid @enderror">
                                <option value="">-- Pilih Kategori Pengeluaran --</option>
                                @foreach($kategoriPengeluarans as $kp)
                                <option value="{{ $kp->value }}" @selected($selectedKatPengeluaran === $kp->value)>{{ $kp->label() }}</option>
                                @endforeach
                            </select>
                            @error('kategori_pengeluaran')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Untuk breakdown Laporan Setoran Harian.</div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Kas <span class="text-danger">*</span></label>
                            <select name="kas_id" class="form-select @error('kas_id') is-invalid @enderror" required>
                                <option value="">-- Pilih Kas --</option>
                                @foreach($kass as $kas)
                                <option value="{{ $kas->id }}" @selected(old('kas_id', $transaksi->kas_id) == $kas->id)>
                                    {{ $kas->nama_kas }} (Saldo: Rp {{ number_format($kas->saldo_sekarang, 0, ',', '.') }})
                                </option>
                                @endforeach
                            </select>
                            @error('kas_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Kas wajib dipilih supaya saldo kas tetap akurat.</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Keterangan <span class="text-danger">*</span></label>
                            <input type="text" name="keterangan" class="form-control @error('keterangan') is-invalid @enderror"
                                value="{{ old('keterangan', $transaksi->keterangan) }}" placeholder="Deskripsi singkat transaksi..." required>
                            @error('keterangan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <x-input-rupiah name="jumlah" label="Jumlah" :value="old('jumlah', (int) $transaksi->jumlah)" required />
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">
                                Bukti / Foto
                                <small class="text-muted fw-normal">(ganti bukti, opsional)</small>
                            </label>
                            @if($transaksi->bukti_path)
                            <div class="mb-2">
                                <a href="/img/{{ $transaksi->bukti_path }}" target="_blank" class="btn btn-sm btn-outline-info">
                                    <i class="bi bi-paperclip me-1"></i>Lihat Bukti Saat Ini
                                </a>
                            </div>
                            @endif
                            <input type="file" name="bukti" class="form-control @error('bukti') is-invalid @enderror"
                                accept=".jpg,.jpeg,.png,.pdf">
                            @error('bukti')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Catatan</label>
                            <textarea name="catatan" class="form-control" rows="1" placeholder="Catatan tambahan...">{{ old('catatan', $transaksi->catatan) }}</textarea>
                        </div>

                        <div class="col-12 mt-2">
                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-check-circle me-1"></i>Simpan Perubahan
                            </button>
                            <a href="{{ route('keuangan.index') }}" class="btn btn-outline-secondary ms-2">Batal</a>
                        </div>

                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<x-coa-cheat-sheet-modal />
@endsection

@push('scripts')
<script>
const tipeSelect     = document.getElementById('tipeSelect');
const kategoriSelect = document.getElementById('kategoriSelect');
const allOptions     = Array.from(kategoriSelect.options);

function filterKategori() {
    const tipe = tipeSelect.value;
    const current = kategoriSelect.value;
    kategoriSelect.innerHTML = '<option value="">-- Pilih Kategori --</option>';
    allOptions.forEach(opt => {
        if (!opt.value) return;
        const optTipe = opt.dataset.tipe;
        if (!tipe || optTipe === 'keduanya' || optTipe === tipe) {
            const clone = opt.cloneNode(true);
            if (clone.value == current) clone.selected = true;
            kategoriSelect.appendChild(clone);
        }
    });
}

tipeSelect.addEventListener('change', filterKategori);
if (tipeSelect.value) filterKategori();

// Toggle Kategori Pengeluaran — cuma wajib & tampil untuk tipe Pengeluaran
const kategoriPengeluaranWrap   = document.getElementById('kategoriPengeluaranWrap');
const kategoriPengeluaranSelect = document.getElementById('kategoriPengeluaranSelect');

function toggleKategoriPengeluaran() {
    const isPengeluaran = tipeSelect.value === 'pengeluaran';
    kategoriPengeluaranWrap.style.display = isPengeluaran ? '' : 'none';
    kategoriPengeluaranSelect.required = isPengeluaran;
    if (!isPengeluaran) kategoriPengeluaranSelect.value = '';
}

tipeSelect.addEventListener('change', toggleKategoriPengeluaran);
toggleKategoriPengeluaran();
</script>
@endpush
