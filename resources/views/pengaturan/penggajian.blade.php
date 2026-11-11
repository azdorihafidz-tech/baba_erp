@extends('layouts.app')

@section('title', 'Pengaturan Penggajian')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-sliders me-2 text-primary"></i>Pengaturan Penggajian</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0" style="font-size:.8rem">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active">Pengaturan Penggajian</li>
            </ol>
        </nav>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="card">
            <div class="card-header fw-semibold">
                <i class="bi bi-gear me-1"></i>Tarif & Parameter Global Penggajian
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Pengaturan ini berlaku untuk semua cabang sebagai nilai default.
                    Nilai dapat di-override secara manual per slip gaji.
                </p>

                <form action="{{ route('pengaturan.penggajian.update') }}" method="POST" id="formPengaturanGaji">
                    @csrf @method('PUT')

                    {{-- Tarif Lembur Per Jam --}}
                    <div class="mb-4">
                        <label class="form-label fw-semibold" for="disp_tarif_lembur">
                            Tarif Lembur Per Jam
                            <span class="text-muted fw-normal">(Rp/jam)</span>
                            <x-tooltip key="pengaturan-gaji.tarif_lembur_per_jam" />
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" inputmode="numeric" id="disp_tarif_lembur"
                                   class="form-control @error('tarif_lembur_per_jam') is-invalid @enderror"
                                   value="{{ number_format((int) old('tarif_lembur_per_jam', $setting->tarif_lembur_per_jam), 0, ',', '.') }}"
                                   oninput="syncRupiah(this,'tarif_lembur_per_jam')"
                                   placeholder="contoh: 15.000"
                                   autocomplete="off">
                            <input type="hidden" name="tarif_lembur_per_jam" id="tarif_lembur_per_jam"
                                   value="{{ (int) old('tarif_lembur_per_jam', $setting->tarif_lembur_per_jam) }}">
                        </div>
                        <div class="form-text">
                            Default = Rp 15.000/jam. Sistem juga bisa menghitung otomatis dari
                            gaji pokok ÷ 173 × 1,5 (klik Hitung Otomatis di form penggajian).
                        </div>
                        @error('tarif_lembur_per_jam')
                        <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Potongan Alpha Per Hari --}}
                    <div class="mb-4">
                        <label class="form-label fw-semibold" for="disp_potongan_alpa">
                            Potongan Alpha Per Hari
                            <span class="text-muted fw-normal">(Rp/hari)</span>
                            <x-tooltip key="pengaturan-gaji.potongan_alpa_per_hari" />
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" inputmode="numeric" id="disp_potongan_alpa"
                                   class="form-control @error('potongan_alpa_per_hari') is-invalid @enderror"
                                   value="{{ number_format((int) old('potongan_alpa_per_hari', $setting->potongan_alpa_per_hari), 0, ',', '.') }}"
                                   oninput="syncRupiah(this,'potongan_alpa_per_hari')"
                                   placeholder="contoh: 50.000"
                                   autocomplete="off">
                            <input type="hidden" name="potongan_alpa_per_hari" id="potongan_alpa_per_hari"
                                   value="{{ (int) old('potongan_alpa_per_hari', $setting->potongan_alpa_per_hari) }}">
                        </div>
                        <div class="form-text">
                            Isi 0 (nol) → potongan alpha dihitung proporsional dari gaji pokok ÷ hari kerja.
                            Isi nilai → potongan tetap per hari alpha.
                        </div>
                        @error('potongan_alpa_per_hari')
                        <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Potongan Telat Per Menit --}}
                    <div class="mb-4">
                        <label class="form-label fw-semibold" for="disp_potongan_telat">
                            Potongan Telat Per Menit
                            <span class="text-muted fw-normal">(Rp/menit)</span>
                            <x-tooltip key="pengaturan-gaji.potongan_telat_per_menit" />
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" inputmode="numeric" id="disp_potongan_telat"
                                   class="form-control @error('potongan_telat_per_menit') is-invalid @enderror"
                                   value="{{ number_format((int) old('potongan_telat_per_menit', $setting->potongan_telat_per_menit), 0, ',', '.') }}"
                                   oninput="syncRupiah(this,'potongan_telat_per_menit')"
                                   placeholder="contoh: 500"
                                   autocomplete="off">
                            <input type="hidden" name="potongan_telat_per_menit" id="potongan_telat_per_menit"
                                   value="{{ (int) old('potongan_telat_per_menit', $setting->potongan_telat_per_menit) }}">
                        </div>
                        <div class="form-text">
                            Isi 0 (nol) untuk menonaktifkan potongan keterlambatan.
                        </div>
                        @error('potongan_telat_per_menit')
                        <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Hari Kerja Per Minggu <x-tooltip key="pengaturan-gaji.hari_kerja_per_minggu" /></label>
                        <input type="number" name="hari_kerja_per_minggu" min="1" max="7"
                               class="form-control @error('hari_kerja_per_minggu') is-invalid @enderror"
                               value="{{ old('hari_kerja_per_minggu', $setting->hari_kerja_per_minggu) }}"
                               style="max-width:120px">
                        <div class="form-text">
                            Digunakan sebagai acuan dalam menghitung hari kerja sebulan.
                            Default = 6 hari (Senin–Sabtu).
                        </div>
                        @error('hari_kerja_per_minggu')
                        <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Simpan Pengaturan
                        </button>
                        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">Batal</a>
                    </div>
                </form>
            </div>
            <div class="card-footer bg-light">
                <div class="row g-3 text-center small">
                    <div class="col-6 col-md-3">
                        <div class="text-muted">Tarif Lembur</div>
                        <div class="fw-bold">Rp {{ number_format($setting->tarif_lembur_per_jam, 0, ',', '.') }}/jam</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-muted">Potongan Alpha</div>
                        <div class="fw-bold">
                            @if($setting->potongan_alpa_per_hari > 0)
                            Rp {{ number_format($setting->potongan_alpa_per_hari, 0, ',', '.') }}/hari
                            @else
                            Proporsional
                            @endif
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-muted">Potongan Telat</div>
                        <div class="fw-bold">
                            @if($setting->potongan_telat_per_menit > 0)
                            Rp {{ number_format($setting->potongan_telat_per_menit, 0, ',', '.') }}/menit
                            @else
                            Nonaktif
                            @endif
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-muted">Hari Kerja</div>
                        <div class="fw-bold">{{ $setting->hari_kerja_per_minggu }} hari/minggu</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@push('scripts')
<script>
/**
 * Format display input sebagai "50.000" dan simpan raw integer ke hidden field.
 * Dipanggil setiap oninput pada display input.
 */
function syncRupiah(displayEl, hiddenId) {
    // Hapus semua non-digit
    const raw = displayEl.value.replace(/\D/g, '');
    const num  = raw ? parseInt(raw, 10) : 0;
    // Update hidden field dengan nilai raw (integer)
    document.getElementById(hiddenId).value = num;
    // Format display dengan titik ribuan (id-ID: 50000 → "50.000")
    displayEl.value = raw ? num.toLocaleString('id-ID') : '';
}
</script>
@endpush
@endsection
