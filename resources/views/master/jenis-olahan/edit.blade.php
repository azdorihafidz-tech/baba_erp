@extends('layouts.app')

@section('title', 'Edit Jenis Menu')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0 fw-bold"><i class="bi bi-pencil-square me-2 text-primary"></i>Edit Jenis Menu</h4>
    </div>
    <a href="{{ route('master.jenis-olahan.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-md-7 col-lg-5">
        <div class="card">
            <div class="card-body">
                <form action="{{ route('master.jenis-olahan.update', $jenisOlahan) }}" method="POST">
                    @csrf @method('PUT')

                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Nama Jenis Menu <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="nama"
                               class="form-control @error('nama') is-invalid @enderror"
                               value="{{ old('nama', $jenisOlahan->nama) }}"
                               maxlength="50" required autofocus>
                        @error('nama')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    {{-- Slug: read-only, untuk informasi saja --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-muted">Slug (tidak bisa diubah)</label>
                        <input type="text" class="form-control form-control-sm bg-light text-muted"
                               value="{{ $jenisOlahan->slug }}" disabled>
                        <div class="form-text">
                            Slug adalah value yang tersimpan di data transaksi. Tidak bisa diubah agar data lama tetap valid.
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active"
                                   value="1" id="isActiveCheck"
                                   {{ old('is_active', $jenisOlahan->is_active) ? 'checked' : '' }}>
                            <label class="form-check-label" for="isActiveCheck">
                                Aktif (muncul di dropdown POS)
                            </label>
                        </div>
                        @if(!$jenisOlahan->is_active)
                        <small class="text-warning d-block mt-1">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            Saat ini nonaktif — tidak muncul di POS, tapi transaksi lama tetap aman.
                        </small>
                        @endif
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Simpan Perubahan
                        </button>
                        <a href="{{ route('master.jenis-olahan.index') }}" class="btn btn-outline-secondary">
                            Batal
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
