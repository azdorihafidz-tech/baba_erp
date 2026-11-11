@extends('layouts.app')

@section('title', 'Generate Gaji Bulanan')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0 fw-bold">Generate Gaji Bulanan</h4>
        <small class="text-muted">Buat slip gaji otomatis berdasarkan absensi</small>
    </div>
    <a href="{{ route('penggajian.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>

<div class="card" style="max-width:550px">
    <div class="card-header">Form Generate Gaji</div>
    <div class="card-body">
        <form action="{{ route('penggajian.proses-generate') }}" method="POST">
            @csrf

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

            <div class="mb-3">
                <label class="form-label">Periode (Bulan/Tahun) <span class="text-danger">*</span></label>
                <input type="month" name="periode" class="form-control @error('periode') is-invalid @enderror"
                       value="{{ old('periode', $periodeDefault) }}" required>
                <small class="text-muted">Gaji dihitung berdasarkan absensi bulan tersebut</small>
                @error('periode')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <hr>
            <p class="text-muted small mb-3">Override komponen gaji (opsional, berlaku untuk semua karyawan di cabang ini):</p>

            <div class="row g-2">
                <div class="col-12">
                    <x-input-rupiah name="tunjangan" label="Tunjangan Default" :value="old('tunjangan', 0)" />
                </div>
                <div class="col-12">
                    <x-input-rupiah name="bonus" label="Bonus" :value="old('bonus', 0)" />
                </div>
                <div class="col-12">
                    <x-input-rupiah name="potongan_lain" label="Potongan Lain" :value="old('potongan_lain', 0)" />
                </div>
            </div>

            <div class="mt-3 p-3 bg-light rounded">
                <small class="text-muted">
                    <strong>Catatan:</strong> Sistem akan otomatis menghitung:
                    <ul class="mt-1 mb-0">
                        <li>Hari kerja (Senin–Sabtu)</li>
                        <li>Potongan alpha: gaji pokok / hari kerja × jumlah alpha</li>
                        <li>Uang lembur: jam lembur × (gaji pokok / 173 × 1.5)</li>
                    </ul>
                </small>
            </div>

            <div class="mt-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-gear me-1"></i>Generate Semua Karyawan Aktif
                </button>
                <a href="{{ route('penggajian.index') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
