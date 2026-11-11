@extends('layouts.app')

@section('title', 'Tambah Kategori Transaksi')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold">
        <i class="bi bi-plus-circle me-2 text-primary"></i>Tambah Kategori Transaksi
    </h5>
    <a href="{{ route('kategori-transaksi.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-lg-6">
        <div class="card">
            <div class="card-header">Form Kategori Baru</div>
            <div class="card-body">
                <form method="POST" action="{{ route('kategori-transaksi.store') }}">
                    @csrf
                    <div class="row g-3">

                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold">Kode <span class="text-danger">*</span>
                                <small class="text-muted fw-normal">(unik, max 30 karakter)</small>
                            </label>
                            <input type="text" name="kode" id="kodeInput"
                                class="form-control text-uppercase @error('kode') is-invalid @enderror"
                                value="{{ old('kode') }}" placeholder="contoh: TRANSP" maxlength="30" required>
                            @error('kode')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <small class="text-muted">Otomatis diubah ke huruf kapital</small>
                        </div>

                        <div class="col-12 col-md-8">
                            <label class="form-label fw-semibold">Nama Kategori <span class="text-danger">*</span></label>
                            <input type="text" name="nama" id="namaInput"
                                class="form-control @error('nama') is-invalid @enderror"
                                value="{{ old('nama') }}" placeholder="Biaya Transportasi" maxlength="100" required>
                            @error('nama')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Tipe <span class="text-danger">*</span></label>
                            <select name="tipe" class="form-select @error('tipe') is-invalid @enderror" required>
                                <option value="">-- Pilih Tipe --</option>
                                <option value="pemasukan"   @selected(old('tipe') === 'pemasukan')>Pemasukan</option>
                                <option value="pengeluaran" @selected(old('tipe') === 'pengeluaran')>Pengeluaran</option>
                                <option value="keduanya"    @selected(old('tipe') === 'keduanya')>Keduanya</option>
                            </select>
                            @error('tipe')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Induk (Opsional)</label>
                            <select name="parent_id" class="form-select @error('parent_id') is-invalid @enderror">
                                <option value="">-- Tanpa Induk (kategori utama) --</option>
                                @foreach($parents as $p)
                                <option value="{{ $p->id }}" @selected(old('parent_id') == $p->id)>
                                    {{ $p->nama }} ({{ strtoupper($p->tipe) }})
                                </option>
                                @endforeach
                            </select>
                            @error('parent_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-6 col-md-4">
                            <label class="form-label fw-semibold">Urutan</label>
                            <input type="number" name="urutan" class="form-control @error('urutan') is-invalid @enderror"
                                value="{{ old('urutan', 50) }}" min="0" max="9999">
                            @error('urutan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <small class="text-muted">Angka kecil tampil lebih awal</small>
                        </div>

                        <div class="col-6 col-md-4 d-flex align-items-center pt-3">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" value="1" class="form-check-input"
                                    id="isActive" @checked(old('is_active', true))>
                                <label class="form-check-label fw-semibold" for="isActive">Aktif</label>
                            </div>
                        </div>

                        <div class="col-12 col-md-8">
                            <label class="form-label fw-semibold">Kode Akun COA <small class="text-muted fw-normal">(opsional)</small></label>
                            <select name="kode_akun_coa" class="form-select @error('kode_akun_coa') is-invalid @enderror">
                                <option value="">-- Belum dipetakan --</option>
                                @foreach($coaList as $akun)
                                <option value="{{ $akun->kode }}" @selected(old('kode_akun_coa') === $akun->kode)>
                                    {{ $akun->kode }} — {{ $akun->nama }}
                                </option>
                                @endforeach
                            </select>
                            @error('kode_akun_coa')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold">Tipe Biaya <small class="text-muted fw-normal">(opsional)</small></label>
                            <select name="tipe_biaya" class="form-select @error('tipe_biaya') is-invalid @enderror">
                                <option value="">-- Tidak diset --</option>
                                <option value="tetap" @selected(old('tipe_biaya') === 'tetap')>Tetap</option>
                                <option value="variabel" @selected(old('tipe_biaya') === 'variabel')>Variabel</option>
                            </select>
                            @error('tipe_biaya')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 mt-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-1"></i>Simpan
                            </button>
                            <a href="{{ route('kategori-transaksi.index') }}" class="btn btn-outline-secondary ms-2">Batal</a>
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
// Auto-suggest kode dari nama
document.getElementById('namaInput').addEventListener('blur', function() {
    const kodeInput = document.getElementById('kodeInput');
    if (!kodeInput.value && this.value) {
        kodeInput.value = this.value.toUpperCase()
            .replace(/\s+/g, '-')
            .replace(/[^A-Z0-9-]/g, '')
            .substring(0, 20);
    }
});
document.getElementById('kodeInput').addEventListener('input', function() {
    this.value = this.value.toUpperCase();
});
</script>
@endpush
