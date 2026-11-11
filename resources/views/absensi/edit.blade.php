@extends('layouts.app')

@section('title', 'Edit Absensi')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0 fw-bold">Edit Absensi</h4>
    <a href="{{ route('absensi.index', ['tanggal' => $absensi->tanggal->format('Y-m-d')]) }}"
       class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>

<div class="card" style="max-width:540px">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>
            {{ $absensi->karyawan->nama_lengkap }} &mdash;
            {{ $absensi->tanggal->translatedFormat('d F Y') }}
        </span>
        @if($absensi->dicatat_oleh)
        <span class="badge bg-info-subtle text-info border border-info-subtle">Manual</span>
        @else
        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Face Recognition</span>
        @endif
    </div>
    <div class="card-body">
        <form action="{{ route('absensi.update', $absensi) }}" method="POST">
            @csrf @method('PUT')

            <div class="mb-3">
                <label class="form-label">Status <x-tooltip key="absensi.status" /></label>
                <select name="status" class="form-select" required>
                    @foreach(['hadir','izin','sakit','alpha','libur','cuti'] as $s)
                    <option value="{{ $s }}" {{ old('status', $absensi->status) === $s ? 'selected' : '' }}>
                        {{ ucfirst($s) }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div class="row g-2">
                <div class="col-6">
                    <label class="form-label">Jam Masuk</label>
                    <input type="time" name="jam_masuk" class="form-control"
                           value="{{ old('jam_masuk', $absensi->jam_masuk?->format('H:i')) }}">
                </div>
                <div class="col-6">
                    <label class="form-label">Jam Keluar</label>
                    <input type="time" name="jam_keluar" class="form-control"
                           value="{{ old('jam_keluar', $absensi->jam_keluar?->format('H:i')) }}">
                </div>
            </div>

            <div class="row g-2 mt-0 mb-3">
                <div class="col-6">
                    <label class="form-label">Lembur Masuk</label>
                    <input type="time" name="jam_lembur_masuk" class="form-control"
                           value="{{ old('jam_lembur_masuk', $absensi->jam_lembur_masuk?->format('H:i')) }}">
                </div>
                <div class="col-6">
                    <label class="form-label">Lembur Keluar</label>
                    <input type="time" name="jam_lembur_keluar" class="form-control"
                           value="{{ old('jam_lembur_keluar', $absensi->jam_lembur_keluar?->format('H:i')) }}">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Keterangan</label>
                <textarea name="keterangan" class="form-control" rows="2">{{ old('keterangan', $absensi->keterangan) }}</textarea>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan</button>
                <a href="{{ route('absensi.index', ['tanggal' => $absensi->tanggal->format('Y-m-d')]) }}"
                   class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
