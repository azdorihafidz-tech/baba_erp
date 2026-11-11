@extends('layouts.app')

@section('title', 'Edit Kategori Transaksi')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold">
        <i class="bi bi-pencil me-2 text-warning"></i>Edit Kategori Transaksi
    </h5>
    <a href="{{ route('kategori-transaksi.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-lg-6">
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <span>Edit: <strong>{{ $kategoriTransaksi->nama }}</strong></span>
                @if($kategoriTransaksi->is_system)
                <span class="badge bg-warning-subtle text-warning border border-warning ms-auto" style="font-size:0.7rem">KATEGORI SISTEM</span>
                @endif
            </div>
            <div class="card-body">
                @if($kategoriTransaksi->is_system)
                <div class="alert alert-warning py-2 mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    Ini adalah kategori sistem. Anda bisa mengubah nama dan pengaturan, tetapi kode tidak disarankan diubah.
                </div>
                @endif
                <form method="POST" action="{{ route('kategori-transaksi.update', $kategoriTransaksi) }}">
                    @csrf @method('PUT')
                    <div class="row g-3">

                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold">Kode <span class="text-danger">*</span></label>
                            <input type="text" name="kode" id="kodeInput"
                                class="form-control text-uppercase @error('kode') is-invalid @enderror"
                                value="{{ old('kode', $kategoriTransaksi->kode) }}" maxlength="30" required>
                            @error('kode')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 col-md-8">
                            <label class="form-label fw-semibold">Nama Kategori <span class="text-danger">*</span></label>
                            <input type="text" name="nama"
                                class="form-control @error('nama') is-invalid @enderror"
                                value="{{ old('nama', $kategoriTransaksi->nama) }}" maxlength="100" required>
                            @error('nama')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Tipe <span class="text-danger">*</span></label>
                            <select name="tipe" class="form-select @error('tipe') is-invalid @enderror" required>
                                <option value="pemasukan"   @selected(old('tipe', $kategoriTransaksi->tipe) === 'pemasukan')>Pemasukan</option>
                                <option value="pengeluaran" @selected(old('tipe', $kategoriTransaksi->tipe) === 'pengeluaran')>Pengeluaran</option>
                                <option value="keduanya"    @selected(old('tipe', $kategoriTransaksi->tipe) === 'keduanya')>Keduanya</option>
                            </select>
                            @error('tipe')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Induk (Opsional)</label>
                            <select name="parent_id" class="form-select @error('parent_id') is-invalid @enderror">
                                <option value="">-- Tanpa Induk --</option>
                                @foreach($parents as $p)
                                <option value="{{ $p->id }}" @selected(old('parent_id', $kategoriTransaksi->parent_id) == $p->id)>
                                    {{ $p->nama }}
                                </option>
                                @endforeach
                            </select>
                            @error('parent_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-6 col-md-4">
                            <label class="form-label fw-semibold">Urutan</label>
                            <input type="number" name="urutan" class="form-control"
                                value="{{ old('urutan', $kategoriTransaksi->urutan) }}" min="0" max="9999">
                        </div>

                        <div class="col-6 col-md-4 d-flex align-items-center pt-3">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" value="1" class="form-check-input"
                                    id="isActive" @checked(old('is_active', $kategoriTransaksi->is_active))>
                                <label class="form-check-label fw-semibold" for="isActive">Aktif</label>
                            </div>
                        </div>

                        <div class="col-12 col-md-8">
                            <label class="form-label fw-semibold">Kode Akun COA <small class="text-muted fw-normal">(opsional — reklas manual)</small></label>
                            <select name="kode_akun_coa" class="form-select @error('kode_akun_coa') is-invalid @enderror">
                                <option value="">-- Belum dipetakan --</option>
                                @foreach($coaList as $akun)
                                <option value="{{ $akun->kode }}" @selected(old('kode_akun_coa', $kategoriTransaksi->kode_akun_coa) === $akun->kode)>
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
                                <option value="tetap" @selected(old('tipe_biaya', $kategoriTransaksi->tipe_biaya) === 'tetap')>Tetap</option>
                                <option value="variabel" @selected(old('tipe_biaya', $kategoriTransaksi->tipe_biaya) === 'variabel')>Variabel</option>
                            </select>
                            @error('tipe_biaya')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 mt-2">
                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-check-circle me-1"></i>Simpan Perubahan
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
document.getElementById('kodeInput').addEventListener('input', function() {
    this.value = this.value.toUpperCase();
});
</script>
@endpush
