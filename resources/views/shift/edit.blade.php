@extends('layouts.app')

@section('title', 'Edit Shift')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0 fw-bold"><i class="bi bi-pencil me-2 text-primary"></i>Edit Shift</h4>
        <small class="text-muted">{{ $shift->nama_shift }} — {{ $shift->cabang?->nama_cabang }}</small>
    </div>
    <a href="{{ route('shift.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="card">
            <div class="card-body">
                <form action="{{ route('shift.update', $shift) }}" method="POST">
                    @csrf @method('PUT')

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Cabang <span class="text-danger">*</span></label>
                        <select name="cabang_id" class="form-select @error('cabang_id') is-invalid @enderror" required>
                            @foreach($cabangs as $c)
                            <option value="{{ $c->id }}"
                                    {{ (old('cabang_id', $shift->cabang_id) == $c->id) ? 'selected' : '' }}>
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
                               value="{{ old('nama_shift', $shift->nama_shift) }}" required>
                        @error('nama_shift')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Jam Masuk <span class="text-danger">*</span></label>
                            <input type="time" name="jam_masuk"
                                   class="form-control @error('jam_masuk') is-invalid @enderror"
                                   value="{{ old('jam_masuk', substr($shift->jam_masuk, 0, 5)) }}" required>
                            @error('jam_masuk')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Jam Keluar <span class="text-danger">*</span></label>
                            <input type="time" name="jam_keluar"
                                   class="form-control @error('jam_keluar') is-invalid @enderror"
                                   value="{{ old('jam_keluar', substr($shift->jam_keluar, 0, 5)) }}" required>
                            @error('jam_keluar')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Toleransi Telat <span class="text-danger">*</span></label>
                        <div class="input-group" style="max-width:200px">
                            <input type="number" name="toleransi_telat_menit"
                                   class="form-control @error('toleransi_telat_menit') is-invalid @enderror"
                                   value="{{ old('toleransi_telat_menit', $shift->toleransi_telat_menit) }}"
                                   min="0" max="120" required>
                            <span class="input-group-text">menit</span>
                        </div>
                        @error('toleransi_telat_menit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Deskripsi <span class="text-muted fw-normal">(opsional)</span></label>
                        <textarea name="deskripsi" class="form-control @error('deskripsi') is-invalid @enderror"
                                  rows="2">{{ old('deskripsi', $shift->deskripsi) }}</textarea>
                        @error('deskripsi')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active"
                                   id="isActive" value="1"
                                   {{ old('is_active', $shift->is_active) ? 'checked' : '' }}>
                            <label class="form-check-label" for="isActive">Shift aktif</label>
                        </div>
                    </div>

                    @php $jmlKaryawan = $shift->karyawans()->where('status','aktif')->count(); @endphp
                    @if($jmlKaryawan > 0)
                    <div class="alert alert-info py-2 small mb-3">
                        <i class="bi bi-people me-1"></i>
                        Shift ini digunakan oleh <strong>{{ $jmlKaryawan }} karyawan aktif</strong>.
                        Perubahan jam akan langsung berlaku untuk semua karyawan tersebut.
                    </div>
                    @endif

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Perbarui Shift
                        </button>
                        <a href="{{ route('shift.index') }}" class="btn btn-outline-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
