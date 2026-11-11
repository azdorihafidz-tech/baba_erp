@extends('layouts.app')

@section('title', 'Ajukan Cuti')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0 fw-bold">Ajukan Cuti / Izin</h4>
    <a href="{{ route('cuti.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>

<div class="card" style="max-width:550px">
    <div class="card-header">Form Pengajuan Cuti</div>
    <div class="card-body">
        <form action="{{ route('cuti.store') }}" method="POST">
            @csrf

            <div class="mb-3">
                <label class="form-label">Karyawan <span class="text-danger">*</span></label>
                <select name="karyawan_id" class="form-select @error('karyawan_id') is-invalid @enderror" required>
                    <option value="">-- Pilih Karyawan --</option>
                    @foreach($karyawans as $k)
                    <option value="{{ $k->id }}" {{ old('karyawan_id') == $k->id ? 'selected' : '' }}>
                        {{ $k->nama_lengkap }} ({{ $k->jabatan }})
                    </option>
                    @endforeach
                </select>
                @error('karyawan_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label">Tipe Cuti <span class="text-danger">*</span> <x-tooltip key="cuti.tipe" /></label>
                <select name="tipe" class="form-select @error('tipe') is-invalid @enderror" required>
                    <option value="cuti_tahunan" {{ old('tipe') === 'cuti_tahunan' ? 'selected' : '' }}>Cuti Tahunan</option>
                    <option value="izin" {{ old('tipe') === 'izin' ? 'selected' : '' }}>Izin</option>
                    <option value="sakit" {{ old('tipe') === 'sakit' ? 'selected' : '' }}>Sakit</option>
                    <option value="cuti_khusus" {{ old('tipe') === 'cuti_khusus' ? 'selected' : '' }}>Cuti Khusus</option>
                </select>
                @error('tipe')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label">Tanggal Mulai <span class="text-danger">*</span></label>
                    <input type="date" name="tanggal_mulai" class="form-control @error('tanggal_mulai') is-invalid @enderror"
                           value="{{ old('tanggal_mulai', today()->format('Y-m-d')) }}" required
                           onchange="hitungHari()">
                    @error('tanggal_mulai')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-6">
                    <label class="form-label">Tanggal Selesai <span class="text-danger">*</span></label>
                    <input type="date" name="tanggal_selesai" class="form-control @error('tanggal_selesai') is-invalid @enderror"
                           value="{{ old('tanggal_selesai', today()->format('Y-m-d')) }}" required
                           onchange="hitungHari()">
                    @error('tanggal_selesai')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="mb-3 p-2 bg-light rounded text-center" id="infoHari">
                <span id="jumlahHari">1</span> hari
            </div>

            <div class="mb-3">
                <label class="form-label">Alasan <span class="text-danger">*</span></label>
                <textarea name="alasan" class="form-control @error('alasan') is-invalid @enderror"
                          rows="3" required>{{ old('alasan') }}</textarea>
                @error('alasan')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-send me-1"></i>Ajukan Cuti
                </button>
                <a href="{{ route('cuti.index') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function hitungHari() {
    const mulai = document.querySelector('[name="tanggal_mulai"]').value;
    const selesai = document.querySelector('[name="tanggal_selesai"]').value;
    if (mulai && selesai) {
        const d1 = new Date(mulai), d2 = new Date(selesai);
        const diff = Math.floor((d2 - d1) / (1000 * 60 * 60 * 24)) + 1;
        document.getElementById('jumlahHari').textContent = diff > 0 ? diff : 1;
    }
}
hitungHari();
</script>
@endpush
@endsection
