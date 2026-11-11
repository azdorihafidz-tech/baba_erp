@extends('layouts.app')

@section('title', 'Tambah Resep Bumbu')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h5 class="mb-0 fw-bold"><i class="bi bi-plus-circle me-2 text-primary"></i>Tambah Resep Bumbu</h5>
    <div class="d-flex gap-2 align-items-center">
        <x-panduan-button slug="resep-bumbu" />
        <a href="{{ route('master.resep-bumbu.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-lg-7">
        <div class="card">
            <div class="card-header">Info Resep</div>
            <div class="card-body">
                <form method="POST" action="{{ route('master.resep-bumbu.store') }}">
                    @csrf
                    {{-- 4.39 — Bumbu Pusat milik 1 cabang --}}
                    <div class="small text-muted mb-3">
                        <i class="bi bi-geo-alt-fill text-primary"></i>
                        @if($cabangAktif)
                            Milik cabang: <strong class="text-body">{{ $cabangAktif->nama_cabang }}</strong> (cabang yang dipilih di pojok kanan atas)
                        @else
                            Milik cabang:
                            <select name="cabang_id" class="form-select form-select-sm d-inline-block w-auto">
                                @foreach($pilihanCabang as $c)
                                    <option value="{{ $c->id }}" @selected(old('cabang_id', $pilihanCabang->firstWhere('tipe', 'gudang_pusat')?->id) == $c->id)>{{ $c->nama_cabang }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                    <div class="row g-3">
                        <div class="col-12 col-md-8">
                            <label class="form-label fw-semibold">Nama Resep <span class="text-danger">*</span></label>
                            <input type="text" name="nama" class="form-control @error('nama') is-invalid @enderror"
                                value="{{ old('nama') }}" placeholder="mis. Bakso Kojek" required>
                            @error('nama')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold">Kode <span class="text-danger">*</span></label>
                            <input type="text" name="kode" class="form-control @error('kode') is-invalid @enderror"
                                value="{{ old('kode') }}" placeholder="mis. BKS-KJK" required maxlength="20">
                            @error('kode')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">
                                Jenis Menu Induk
                                <small class="text-muted fw-normal">(opsional — kategorisasi referensi, tidak mempengaruhi tampilan POS)</small>
                            </label>
                            <select name="jenis_olahan_id" class="form-select @error('jenis_olahan_id') is-invalid @enderror">
                                <option value="">-- Tidak Dikaitkan --</option>
                                @foreach($jenisOlahans as $jo)
                                <option value="{{ $jo->id }}" @selected(old('jenis_olahan_id') == $jo->id)>{{ $jo->nama }}</option>
                                @endforeach
                            </select>
                            @error('jenis_olahan_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Catatan</label>
                            <textarea name="catatan" class="form-control" rows="2" placeholder="Catatan tambahan (opsional)...">{{ old('catatan') }}</textarea>
                        </div>
                        <div class="col-12 mt-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-1"></i>Simpan &amp; Lanjut Isi Bahan
                            </button>
                            <a href="{{ route('master.resep-bumbu.index') }}" class="btn btn-outline-secondary ms-2">Batal</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <div class="alert alert-info small mt-3">
            <i class="bi bi-info-circle me-1"></i>Setelah disimpan, kamu akan diarahkan ke halaman edit untuk menambahkan daftar bahan (bumbu) resep ini.
        </div>
    </div>
</div>
@endsection
