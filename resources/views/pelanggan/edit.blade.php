@extends('layouts.app')

@section('title', 'Edit Pelanggan')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-person-gear me-2 text-warning"></i>Edit Pelanggan</h5>
    <a href="{{ route('pelanggan.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('pelanggan.update', $pelanggan) }}">
            @csrf @method('PUT')
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold">Kode Pelanggan <span class="text-danger">*</span></label>
                    <input type="text" name="kode_pelanggan" class="form-control @error('kode_pelanggan') is-invalid @enderror"
                        value="{{ old('kode_pelanggan', $pelanggan->kode_pelanggan) }}">
                    @error('kode_pelanggan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-8">
                    <label class="form-label fw-semibold">Nama Pelanggan <span class="text-danger">*</span></label>
                    <input type="text" name="nama_pelanggan" class="form-control @error('nama_pelanggan') is-invalid @enderror"
                        value="{{ old('nama_pelanggan', $pelanggan->nama_pelanggan) }}">
                    @error('nama_pelanggan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold">Telepon</label>
                    <input type="text" name="telepon" class="form-control @error('telepon') is-invalid @enderror"
                        value="{{ old('telepon', $pelanggan->telepon) }}">
                    @error('telepon')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold">Email</label>
                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                        value="{{ old('email', $pelanggan->email) }}">
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold">Kota</label>
                    <input type="text" name="kota" class="form-control @error('kota') is-invalid @enderror"
                        value="{{ old('kota', $pelanggan->kota) }}">
                    @error('kota')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold">Alamat</label>
                    <textarea name="alamat" class="form-control @error('alamat') is-invalid @enderror"
                        rows="2">{{ old('alamat', $pelanggan->alamat) }}</textarea>
                    @error('alamat')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold">Catatan</label>
                    <textarea name="catatan" class="form-control" rows="2">{{ old('catatan', $pelanggan->catatan) }}</textarea>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-warning">
                    <i class="bi bi-check-circle me-1"></i>Perbarui Pelanggan
                </button>
                <a href="{{ route('pelanggan.index') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
