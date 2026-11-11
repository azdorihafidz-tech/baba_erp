@extends('layouts.app')

@section('title', 'Tambah Pelanggan')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-person-plus me-2 text-success"></i>Tambah Pelanggan</h5>
    <a href="{{ route('pelanggan.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('pelanggan.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold">Kode Pelanggan <span class="text-danger">*</span></label>
                    <input type="text" name="kode_pelanggan" class="form-control @error('kode_pelanggan') is-invalid @enderror"
                        value="{{ old('kode_pelanggan', $kodeHint) }}" placeholder="{{ $kodeHint }}">
                    <div class="form-text text-muted">Auto-generate: {{ $kodeHint }}</div>
                    @error('kode_pelanggan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-8">
                    <label class="form-label fw-semibold">Nama Pelanggan <span class="text-danger">*</span></label>
                    <input type="text" name="nama_pelanggan" class="form-control @error('nama_pelanggan') is-invalid @enderror"
                        value="{{ old('nama_pelanggan') }}" placeholder="Nama lengkap / nama toko">
                    @error('nama_pelanggan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold">Telepon</label>
                    <input type="text" name="telepon" class="form-control @error('telepon') is-invalid @enderror"
                        value="{{ old('telepon') }}" placeholder="08xx-xxxx-xxxx">
                    @error('telepon')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold">Email</label>
                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                        value="{{ old('email') }}" placeholder="email@example.com">
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold">Kota</label>
                    <input type="text" name="kota" class="form-control @error('kota') is-invalid @enderror"
                        value="{{ old('kota') }}" placeholder="Surabaya">
                    @error('kota')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold">Alamat</label>
                    <textarea name="alamat" class="form-control @error('alamat') is-invalid @enderror"
                        rows="2" placeholder="Alamat lengkap...">{{ old('alamat') }}</textarea>
                    @error('alamat')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold">Catatan</label>
                    <textarea name="catatan" class="form-control" rows="2" placeholder="Catatan tambahan...">{{ old('catatan') }}</textarea>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-circle me-1"></i>Simpan Pelanggan
                </button>
                <a href="{{ route('pelanggan.index') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
