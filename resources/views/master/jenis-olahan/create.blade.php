@extends('layouts.app')

@section('title', 'Tambah Jenis Menu')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0 fw-bold"><i class="bi bi-plus-circle me-2 text-primary"></i>Tambah Jenis Menu</h4>
    </div>
    <a href="{{ route('master.jenis-olahan.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-md-7 col-lg-5">
        <div class="card">
            <div class="card-body">
                <form action="{{ route('master.jenis-olahan.store') }}" method="POST">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Nama Jenis Menu <span class="text-danger">*</span>
                            <x-tooltip key="master.jenis_olahan" />
                        </label>
                        <input type="text" name="nama"
                               class="form-control @error('nama') is-invalid @enderror"
                               value="{{ old('nama') }}"
                               placeholder="contoh: Nugget, Otak-otak, Kerupuk"
                               maxlength="50" required autofocus>
                        <div class="form-text">
                            Nama akan tampil di dropdown POS. Slug (value tersimpan) di-generate otomatis dari nama ini dan tidak bisa diubah setelah simpan.
                        </div>
                        @error('nama')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active"
                                   value="1" id="isActiveCheck"
                                   {{ old('is_active', '1') ? 'checked' : '' }}>
                            <label class="form-check-label" for="isActiveCheck">
                                Aktif (langsung muncul di POS)
                            </label>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Simpan
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
