@extends('layouts.app')

@section('title', 'Tambah Karyawan')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0 fw-bold">Tambah Karyawan</h4>
        <small class="text-muted">Isi form di bawah untuk menambahkan karyawan baru</small>
    </div>
    <a href="{{ route('karyawan.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>

<form action="{{ route('karyawan.store') }}" method="POST" enctype="multipart/form-data">
    @csrf
    <div class="row g-3">
        {{-- Kolom Kiri --}}
        <div class="col-12 col-md-6">
            <div class="card h-100">
                <div class="card-header">Data Pribadi</div>
                <div class="card-body">
                    {{-- Foto Upload --}}
                    <div class="mb-3 text-center">
                        <img id="fotoPreview" src="{{ asset('images/avatar-default.png') }}"
                             onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22><rect fill=%22%23e2e8f0%22 width=%22100%22 height=%22100%22/><text x=%2250%25%22 y=%2250%25%22 dominant-baseline=%22middle%22 text-anchor=%22middle%22 fill=%22%2394a3b8%22 font-size=%2240%22>&#128100;</text></svg>'"
                             style="width:100px;height:100px;border-radius:50%;object-fit:cover;border:2px solid #e2e8f0" class="mb-2">
                        <div>
                            <label class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-camera me-1"></i>Upload Foto
                                <input type="file" name="foto" id="foto" accept="image/*" class="d-none"
                                       onchange="previewFoto(this)">
                            </label>
                        </div>
                        @error('foto')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="nama_lengkap" class="form-control @error('nama_lengkap') is-invalid @enderror"
                               value="{{ old('nama_lengkap') }}" required>
                        @error('nama_lengkap')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">NIK <small class="text-muted">(kosongkan untuk auto-generate)</small> <x-tooltip key="karyawan.nik" /></label>
                        <input type="text" name="nik" class="form-control @error('nik') is-invalid @enderror"
                               value="{{ old('nik') }}" placeholder="KRY-001">
                        @error('nik')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Jenis Kelamin <span class="text-danger">*</span></label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="jenis_kelamin" value="L"
                                       id="lakiLaki" {{ old('jenis_kelamin') === 'L' ? 'checked' : '' }} required>
                                <label class="form-check-label" for="lakiLaki">Laki-laki</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="jenis_kelamin" value="P"
                                       id="perempuan" {{ old('jenis_kelamin') === 'P' ? 'checked' : '' }}>
                                <label class="form-check-label" for="perempuan">Perempuan</label>
                            </div>
                        </div>
                        @error('jenis_kelamin')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tanggal Lahir</label>
                        <input type="date" name="tanggal_lahir" class="form-control @error('tanggal_lahir') is-invalid @enderror"
                               value="{{ old('tanggal_lahir') }}">
                        @error('tanggal_lahir')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Alamat</label>
                        <textarea name="alamat" class="form-control @error('alamat') is-invalid @enderror"
                                  rows="2">{{ old('alamat') }}</textarea>
                        @error('alamat')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Telepon</label>
                        <input type="text" name="telepon" class="form-control @error('telepon') is-invalid @enderror"
                               value="{{ old('telepon') }}">
                        @error('telepon')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Kolom Kanan --}}
        <div class="col-12 col-md-6">
            <div class="card h-100">
                <div class="card-header">Data Pekerjaan</div>
                <div class="card-body">
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
                        <label class="form-label">Jabatan <span class="text-danger">*</span></label>
                        <input type="text" name="jabatan" class="form-control @error('jabatan') is-invalid @enderror"
                               value="{{ old('jabatan') }}" placeholder="Operator Produksi, Kasir, dll" required>
                        @error('jabatan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tipe Karyawan <span class="text-danger">*</span> <x-tooltip key="karyawan.tipe_karyawan" /></label>
                        <select name="tipe_karyawan" class="form-select @error('tipe_karyawan') is-invalid @enderror" required>
                            <option value="tetap" {{ old('tipe_karyawan') === 'tetap' ? 'selected' : '' }}>Tetap</option>
                            <option value="kontrak" {{ old('tipe_karyawan') === 'kontrak' ? 'selected' : '' }}>Kontrak</option>
                            <option value="harian" {{ old('tipe_karyawan') === 'harian' ? 'selected' : '' }}>Harian</option>
                        </select>
                        @error('tipe_karyawan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tanggal Masuk <span class="text-danger">*</span></label>
                        <input type="date" name="tanggal_masuk" class="form-control @error('tanggal_masuk') is-invalid @enderror"
                               value="{{ old('tanggal_masuk') }}" required>
                        @error('tanggal_masuk')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <x-input-rupiah name="gaji_pokok" label="Gaji Pokok" :value="old('gaji_pokok', 0)" required />
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Atasan Langsung <x-tooltip key="karyawan.atasan_id" /></label>
                        <select name="atasan_id" class="form-select @error('atasan_id') is-invalid @enderror">
                            <option value="">-- Tidak Ada / Pilih Atasan --</option>
                            @foreach($atasanList as $a)
                            <option value="{{ $a->id }}" {{ old('atasan_id') == $a->id ? 'selected' : '' }}>
                                {{ $a->nama_lengkap }} ({{ $a->jabatan }})
                            </option>
                            @endforeach
                        </select>
                        @error('atasan_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">No. Rekening</label>
                            <input type="text" name="no_rekening" class="form-control"
                                   value="{{ old('no_rekening') }}">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Nama Bank</label>
                            <input type="text" name="nama_bank" class="form-control"
                                   value="{{ old('nama_bank') }}" placeholder="BCA, BRI, dll">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Catatan</label>
                        <textarea name="catatan" class="form-control" rows="2">{{ old('catatan') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tunjangan Default (untuk penggajian otomatis) --}}
        <div class="col-12">
            <div class="card">
                <div class="card-header fw-semibold">
                    <i class="bi bi-cash-stack me-1 text-success"></i>Tunjangan & BPJS Default
                    <small class="text-muted fw-normal ms-2">(Digunakan otomatis saat generate slip gaji)</small>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6 col-md-3">
                            <x-input-rupiah name="tunjangan_jabatan" label="Tunjangan Jabatan" :value="old('tunjangan_jabatan', 0)" />
                        </div>
                        <div class="col-6 col-md-3">
                            <x-input-rupiah name="tunjangan_makan" label="Tunjangan Makan" :value="old('tunjangan_makan', 0)" />
                        </div>
                        <div class="col-6 col-md-3">
                            <x-input-rupiah name="tunjangan_transport" label="Tunjangan Transport" :value="old('tunjangan_transport', 0)" />
                        </div>
                        <div class="col-6 col-md-3"></div>
                        <div class="col-6 col-md-3">
                            <label class="form-label">BPJS Kesehatan (%) <x-tooltip key="karyawan.bpjs_kesehatan_persen" /></label>
                            <div class="input-group">
                                <input type="number" name="tunjangan_bpjs_kesehatan_persen" class="form-control"
                                       value="{{ old('tunjangan_bpjs_kesehatan_persen', 1) }}" min="0" max="5" step="0.5">
                                <span class="input-group-text">%</span>
                            </div>
                            <small class="text-muted">Default: 1%</small>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label">BPJS JHT (%) <x-tooltip key="karyawan.bpjs_tk_persen" /></label>
                            <div class="input-group">
                                <input type="number" name="tunjangan_bpjs_tk_persen" class="form-control"
                                       value="{{ old('tunjangan_bpjs_tk_persen', 2) }}" min="0" max="10" step="0.5">
                                <span class="input-group-text">%</span>
                            </div>
                            <small class="text-muted">Default: 2%</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Akun Login (Opsional) --}}
    <div class="card mt-4">
        <div class="card-header fw-semibold">
            <i class="bi bi-person-badge me-1 text-primary"></i>Akun Login (Opsional)
        </div>
        <div class="card-body">
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="buat_akun_login" name="buat_akun_login"
                    value="1" {{ old('buat_akun_login') ? 'checked' : '' }} onchange="toggleAkunSection()">
                <label class="form-check-label" for="buat_akun_login">
                    <strong>Buat akun login untuk karyawan ini</strong> <x-tooltip key="karyawan.buat_akun_login" />
                </label>
                <small class="d-block text-muted">Karyawan dapat login ke sistem untuk akses fitur absensi, penilaian, dan slip gaji.</small>
            </div>
            <div id="akunSection" style="display:none">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label">Email Login <span class="text-danger">*</span></label>
                        <input type="email" name="user_email" class="form-control @error('user_email') is-invalid @enderror"
                            value="{{ old('user_email') }}" placeholder="email@example.com">
                        @error('user_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Role <span class="text-danger">*</span> <x-tooltip key="karyawan.user_role" /></label>
                        <select name="user_role" class="form-select @error('user_role') is-invalid @enderror">
                            <option value="">-- Pilih Role --</option>
                            @foreach(\App\Support\Roles::semua() as $r)
                                @if($r !== \App\Enums\RoleUser::Owner)
                                <option value="{{ $r->value }}" {{ old('user_role') === $r->value ? 'selected' : '' }}>{{ $r->label() }}</option>
                                @endif
                            @endforeach
                        </select>
                        @error('user_role')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="alert alert-info mt-3 mb-0 py-2" style="font-size:.875rem">
                    <i class="bi bi-info-circle me-1"></i>
                    Password default: <strong>password123</strong> — karyawan dapat menggantinya setelah login pertama.
                </div>
            </div>
        </div>
    </div>

    <div class="mt-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan Karyawan</button>
        <a href="{{ route('karyawan.index') }}" class="btn btn-outline-secondary">Batal</a>
    </div>
</form>

@push('scripts')
<script>
function previewFoto(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => document.getElementById('fotoPreview').src = e.target.result;
        reader.readAsDataURL(input.files[0]);
    }
}
function toggleAkunSection() {
    const checked = document.getElementById('buat_akun_login').checked;
    document.getElementById('akunSection').style.display = checked ? 'block' : 'none';
}
document.addEventListener('DOMContentLoaded', toggleAkunSection);
</script>
@endpush
@endsection
