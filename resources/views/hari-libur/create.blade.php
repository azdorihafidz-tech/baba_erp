@extends('layouts.app')

@section('title', 'Tambah Hari Libur')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0 fw-bold"><i class="bi bi-calendar-plus me-2 text-primary"></i>Tambah Hari Libur</h4>
    <a href="{{ route('hari-libur.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-md-7 col-lg-5">
        <div class="card">
            <div class="card-body">
                <form action="{{ route('hari-libur.store') }}" method="POST">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tanggal <span class="text-danger">*</span></label>
                        <input type="date" name="tanggal"
                               class="form-control @error('tanggal') is-invalid @enderror"
                               value="{{ old('tanggal') }}" required>
                        @error('tanggal')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Hari Libur <span class="text-danger">*</span></label>
                        <input type="text" name="nama"
                               class="form-control @error('nama') is-invalid @enderror"
                               value="{{ old('nama') }}"
                               placeholder="cth: Hari Raya Idul Fitri" required>
                        @error('nama')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tipe <span class="text-danger">*</span> <x-tooltip key="hari-libur.tipe" /></label>
                        <select name="tipe" id="selectTipe"
                                class="form-select @error('tipe') is-invalid @enderror" required>
                            <option value="nasional" {{ old('tipe','nasional') === 'nasional' ? 'selected' : '' }}>Nasional (berlaku semua cabang)</option>
                            <option value="cabang" {{ old('tipe') === 'cabang' ? 'selected' : '' }}>Khusus Cabang</option>
                        </select>
                        @error('tipe')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-4" id="divCabang" style="{{ old('tipe') === 'cabang' ? '' : 'display:none' }}">
                        <label class="form-label fw-semibold">Cabang <span class="text-danger">*</span></label>
                        <select name="cabang_id" class="form-select @error('cabang_id') is-invalid @enderror">
                            <option value="">— Pilih Cabang —</option>
                            @foreach($cabangs as $c)
                            <option value="{{ $c->id }}" {{ old('cabang_id') == $c->id ? 'selected' : '' }}>
                                {{ $c->nama_cabang }}
                            </option>
                            @endforeach
                        </select>
                        @error('cabang_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Simpan
                        </button>
                        <a href="{{ route('hari-libur.index') }}" class="btn btn-outline-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.getElementById('selectTipe').addEventListener('change', function() {
    document.getElementById('divCabang').style.display = this.value === 'cabang' ? '' : 'none';
});
</script>
@endpush
@endsection
