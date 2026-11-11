@extends('layouts.app')

@section('title', 'Edit Supplier')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-pencil-square me-2 text-warning"></i>Edit Supplier</h5>
    <a href="{{ route('supplier.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('supplier.update', $supplier) }}">
            @csrf @method('PUT')
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold">Kode Supplier <span class="text-danger">*</span></label>
                    <input type="text" name="kode_supplier" class="form-control @error('kode_supplier') is-invalid @enderror"
                        value="{{ old('kode_supplier', $supplier->kode_supplier) }}">
                    @error('kode_supplier')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-8">
                    <label class="form-label fw-semibold">Nama Supplier <span class="text-danger">*</span></label>
                    <input type="text" name="nama_supplier" class="form-control @error('nama_supplier') is-invalid @enderror"
                        value="{{ old('nama_supplier', $supplier->nama_supplier) }}">
                    @error('nama_supplier')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold">Kontak Person</label>
                    <input type="text" name="kontak_person" class="form-control @error('kontak_person') is-invalid @enderror"
                        value="{{ old('kontak_person', $supplier->kontak_person) }}">
                    @error('kontak_person')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold">Telepon</label>
                    <input type="text" name="telepon" class="form-control @error('telepon') is-invalid @enderror"
                        value="{{ old('telepon', $supplier->telepon) }}">
                    @error('telepon')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold">Email</label>
                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                        value="{{ old('email', $supplier->email) }}">
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold">Kota</label>
                    <input type="text" name="kota" class="form-control @error('kota') is-invalid @enderror"
                        value="{{ old('kota', $supplier->kota) }}">
                    @error('kota')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold">Supplier Internal (lokasi kita) <x-tooltip key="supplier.cabang_id" /></label>
                    <select name="cabang_id" class="form-select @error('cabang_id') is-invalid @enderror">
                        <option value="">— Bukan, supplier luar —</option>
                        @foreach(\App\Models\Cabang::aktif()->orderBy('nama_cabang')->get() as $c)
                        <option value="{{ $c->id }}" @selected((string) old('cabang_id', $supplier->cabang_id) === (string) $c->id)>{{ $c->nama_cabang }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">Pilih lokasi (mis. Gudang Pusat) kalau supplier ini adalah lokasi kita sendiri. PO cabang ke supplier ini akan tampil di POS lokasi tersebut.</div>
                    @error('cabang_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold">Alamat</label>
                    <textarea name="alamat" class="form-control @error('alamat') is-invalid @enderror"
                        rows="2">{{ old('alamat', $supplier->alamat) }}</textarea>
                    @error('alamat')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold">Catatan</label>
                    <textarea name="catatan" class="form-control @error('catatan') is-invalid @enderror"
                        rows="2">{{ old('catatan', $supplier->catatan) }}</textarea>
                    @error('catatan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1"
                            id="isActive" @checked(old('is_active', $supplier->is_active))>
                        <label class="form-check-label fw-semibold" for="isActive">Supplier Aktif</label>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-warning">
                    <i class="bi bi-check-circle me-1"></i>Perbarui Supplier
                </button>
                <a href="{{ route('supplier.index') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
