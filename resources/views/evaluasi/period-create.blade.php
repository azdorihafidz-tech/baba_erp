@extends('layouts.app')

@section('title', 'Buat Periode Penilaian')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0 fw-bold">Buat Periode Penilaian</h4>
    <a href="{{ route('evaluasi.periods') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>

<div class="card" style="max-width:550px">
    <div class="card-header">Form Periode Penilaian</div>
    <div class="card-body">
        <form action="{{ route('evaluasi.period-store') }}" method="POST">
            @csrf

            <div class="mb-3">
                <label class="form-label">Nama Periode <span class="text-danger">*</span> <x-tooltip key="evaluasi.nama_periode" /></label>
                <input type="text" name="nama_periode" class="form-control @error('nama_periode') is-invalid @enderror"
                       value="{{ old('nama_periode') }}" placeholder="Contoh: Q1 2026, Triwulan 1 2026" required>
                @error('nama_periode')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label">Cabang <span class="text-danger">*</span></label>
                <select name="cabang_id" class="form-select @error('cabang_id') is-invalid @enderror" required>
                    <option value="">-- Pilih Cabang --</option>
                    @foreach($cabangs as $c)
                    <option value="{{ $c->id }}" {{ old('cabang_id') == $c->id ? 'selected' : '' }}>
                        {{ $c->nama_cabang }}
                    </option>
                    @endforeach
                </select>
                @error('cabang_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="row g-2">
                <div class="col-6">
                    <label class="form-label">Tanggal Mulai <span class="text-danger">*</span></label>
                    <input type="date" name="tanggal_mulai" class="form-control @error('tanggal_mulai') is-invalid @enderror"
                           value="{{ old('tanggal_mulai') }}" required>
                    @error('tanggal_mulai')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-6">
                    <label class="form-label">Tanggal Selesai <span class="text-danger">*</span></label>
                    <input type="date" name="tanggal_selesai" class="form-control @error('tanggal_selesai') is-invalid @enderror"
                           value="{{ old('tanggal_selesai') }}" required>
                    @error('tanggal_selesai')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label">Deadline Pengisian <span class="text-danger">*</span> <x-tooltip key="evaluasi.deadline_pengisian" /></label>
                    <input type="date" name="deadline_pengisian" class="form-control @error('deadline_pengisian') is-invalid @enderror"
                           value="{{ old('deadline_pengisian') }}" required>
                    <small class="text-muted">Batas waktu penilai mengisi form penilaian</small>
                    @error('deadline_pengisian')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="mt-3 p-3 bg-light rounded">
                <small class="text-muted">
                    <strong>Alur:</strong> Setelah periode dibuat (status Draft), Anda perlu menekan tombol
                    <strong>Buka</strong> untuk mengaktifkan penilaian. Sistem akan otomatis membuat record
                    evaluasi untuk semua karyawan aktif di cabang tersebut.
                </small>
            </div>

            <div class="mt-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>Buat Periode
                </button>
                <a href="{{ route('evaluasi.periods') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
