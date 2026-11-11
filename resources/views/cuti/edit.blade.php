@extends('layouts.app')

@section('title', 'Edit Pengajuan Cuti')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0 fw-bold">Edit Pengajuan Cuti</h4>
    <a href="{{ route('cuti.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>

<div class="card" style="max-width:550px">
    <div class="card-header">Form Edit Cuti</div>
    <div class="card-body">
        <form action="{{ route('cuti.update', $cuti) }}" method="POST">
            @csrf @method('PUT')

            <div class="mb-3">
                <label class="form-label">Karyawan <span class="text-danger">*</span></label>
                <select name="karyawan_id" class="form-select" required>
                    @foreach($karyawans as $k)
                    <option value="{{ $k->id }}" {{ old('karyawan_id', $cuti->karyawan_id) == $k->id ? 'selected' : '' }}>
                        {{ $k->nama_lengkap }} ({{ $k->jabatan }})
                    </option>
                    @endforeach
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Tipe Cuti <span class="text-danger">*</span></label>
                <select name="tipe" class="form-select" required>
                    <option value="cuti_tahunan" {{ old('tipe', $cuti->tipe) === 'cuti_tahunan' ? 'selected' : '' }}>Cuti Tahunan</option>
                    <option value="izin" {{ old('tipe', $cuti->tipe) === 'izin' ? 'selected' : '' }}>Izin</option>
                    <option value="sakit" {{ old('tipe', $cuti->tipe) === 'sakit' ? 'selected' : '' }}>Sakit</option>
                    <option value="cuti_khusus" {{ old('tipe', $cuti->tipe) === 'cuti_khusus' ? 'selected' : '' }}>Cuti Khusus</option>
                </select>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label">Tanggal Mulai <span class="text-danger">*</span></label>
                    <input type="date" name="tanggal_mulai" class="form-control"
                           value="{{ old('tanggal_mulai', $cuti->tanggal_mulai?->format('Y-m-d')) }}"
                           required onchange="hitungHari()">
                </div>
                <div class="col-6">
                    <label class="form-label">Tanggal Selesai <span class="text-danger">*</span></label>
                    <input type="date" name="tanggal_selesai" class="form-control"
                           value="{{ old('tanggal_selesai', $cuti->tanggal_selesai?->format('Y-m-d')) }}"
                           required onchange="hitungHari()">
                </div>
            </div>

            <div class="mb-3 p-2 bg-light rounded text-center" id="infoHari">
                <span id="jumlahHari">{{ $cuti->jumlah_hari }}</span> hari
            </div>

            <div class="mb-3">
                <label class="form-label">Alasan <span class="text-danger">*</span></label>
                <textarea name="alasan" class="form-control" rows="3" required>{{ old('alasan', $cuti->alasan) }}</textarea>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>Simpan Perubahan
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
</script>
@endpush
@endsection
