@extends('layouts.app')

@section('title', 'Tambah Shift')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2 text-primary"></i>Tambah Shift Baru</h4>
    </div>
    <a href="{{ route('shift.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="card">
            <div class="card-body">
                <form action="{{ route('shift.store') }}" method="POST">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Cabang <span class="text-danger">*</span></label>
                        <select name="cabang_id" class="form-select @error('cabang_id') is-invalid @enderror" required>
                            <option value="">-- Pilih Cabang --</option>
                            @foreach($cabangs as $c)
                            <option value="{{ $c->id }}"
                                    {{ (old('cabang_id', $defaultCabang) == $c->id) ? 'selected' : '' }}>
                                {{ $c->nama_cabang }}
                            </option>
                            @endforeach
                        </select>
                        @error('cabang_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Shift <span class="text-danger">*</span></label>
                        <input type="text" name="nama_shift"
                               class="form-control @error('nama_shift') is-invalid @enderror"
                               value="{{ old('nama_shift') }}"
                               placeholder="contoh: Shift 1 Pagi" required>
                        <div class="form-text">Nama harus unik per cabang.</div>
                        @error('nama_shift')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Jam Masuk <span class="text-danger">*</span></label>
                            <input type="time" name="jam_masuk"
                                   class="form-control @error('jam_masuk') is-invalid @enderror"
                                   value="{{ old('jam_masuk', '08:00') }}" required>
                            @error('jam_masuk')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Jam Keluar <span class="text-danger">*</span></label>
                            <input type="time" name="jam_keluar"
                                   class="form-control @error('jam_keluar') is-invalid @enderror"
                                   value="{{ old('jam_keluar', '16:00') }}" required>
                            @error('jam_keluar')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Toleransi Telat <span class="text-danger">*</span> <x-tooltip key="shift.toleransi_telat_menit" /></label>
                        <div class="input-group" style="max-width:200px">
                            <input type="number" name="toleransi_telat_menit"
                                   class="form-control @error('toleransi_telat_menit') is-invalid @enderror"
                                   value="{{ old('toleransi_telat_menit', 15) }}"
                                   min="0" max="120" required>
                            <span class="input-group-text">menit</span>
                        </div>
                        <div class="form-text">Karyawan dianggap telat jika clock in lebih dari N menit setelah jam masuk.</div>
                        @error('toleransi_telat_menit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Deskripsi <span class="text-muted fw-normal">(opsional)</span></label>
                        <textarea name="deskripsi" class="form-control @error('deskripsi') is-invalid @enderror"
                                  rows="2" placeholder="Catatan tambahan untuk shift ini...">{{ old('deskripsi') }}</textarea>
                        @error('deskripsi')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active"
                                   id="isActive" value="1"
                                   {{ old('is_active', '1') ? 'checked' : '' }}>
                            <label class="form-check-label" for="isActive">Shift aktif</label>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Simpan Shift
                        </button>
                        <a href="{{ route('shift.index') }}" class="btn btn-outline-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
