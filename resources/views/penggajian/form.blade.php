@extends('layouts.app')

@section('title', $isEdit ? 'Edit Slip Gaji' : 'Buat Slip Gaji')

@section('content')
@php
    // Helper: ambil nilai dari penggajian (edit), preview (create+karyawan terpilih), atau default
    $pg = $penggajian;
    $pv = $preview ?? [];
    $fv = function($field, $default = 0) use ($pg, $pv) {
        return old($field, $pg ? $pg->{$field} : ($pv[$field] ?? $default));
    };

    // Selalu ambil tarif dari PengaturanGaji (bukan formula) agar edit-mode pun pakai nilai yang benar
    $_tarifLemburSetting = \App\Models\PengaturanGaji::getSetting('tarif_lembur_per_jam', 0);
    $_tarifAlphaSetting  = \App\Models\PengaturanGaji::getSetting('potongan_alpa_per_hari', 0);
    $_gajiPokokFallback  = $pg ? (float) $pg->gaji_pokok : 0;
    $tarifLemburPreview  = isset($pv['tarifLembur'])
        ? (float) $pv['tarifLembur']
        : ($_tarifLemburSetting > 0 ? $_tarifLemburSetting : round($_gajiPokokFallback / 173 * 1.5));
    $tarifAlphaPreview   = isset($pv['tarifAlpha'])
        ? (float) $pv['tarifAlpha']
        : $_tarifAlphaSetting;
    $jamLemburPreview   = (float) $fv('jam_lembur_total', 0);
    $alphaPreview       = (int)   $fv('jumlah_alpha', 0);
    $uangLemburAuto     = round($jamLemburPreview * $tarifLemburPreview);
    $potonganAbsensiAuto= round($tarifAlphaPreview * $alphaPreview);
    $uangLemburManual   = $pg ? (bool) $pg->uang_lembur_manual   : false;
    $potonganAlpaManual = $pg ? (bool) $pg->potongan_alpa_manual : false;
@endphp

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0 fw-bold">
            <i class="bi bi-cash-coin me-2 text-primary"></i>
            {{ $isEdit ? 'Edit Slip Gaji' : 'Buat Slip Gaji' }}
        </h4>
        <small class="text-muted">
            {{ $isEdit ? 'Ubah komponen gaji — hanya draft yang bisa diedit' : 'Input manual per karyawan dengan semua komponen lengkap' }}
        </small>
    </div>
    <a href="{{ route('penggajian.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>

@if(session('info'))
<div class="alert alert-info alert-dismissible fade show">{{ session('info') }} <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

{{-- Form --}}
<form action="{{ $isEdit ? route('penggajian.update', $penggajian) : route('penggajian.store') }}"
      method="POST" id="formGaji">
    @csrf
    @if($isEdit) @method('PUT') @endif

    {{-- ======= BAGIAN ATAS: PILIH KARYAWAN + PERIODE ======= --}}
    <div class="card mb-3">
        <div class="card-header fw-semibold"><i class="bi bi-person-badge me-1"></i>Data Karyawan & Periode</div>
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-5">
                    <label class="form-label fw-semibold">Karyawan <span class="text-danger">*</span></label>
                    @if($isEdit)
                        <input type="hidden" name="karyawan_id" value="{{ $penggajian->karyawan_id }}">
                        <input type="text" class="form-control" value="{{ $penggajian->karyawan?->nama_lengkap }} ({{ $penggajian->karyawan?->nik ?? '-' }})" readonly>
                    @else
                        <select name="karyawan_id" id="karyawanSelect" class="form-select @error('karyawan_id') is-invalid @enderror" required
                                onchange="onKaryawanChange()">
                            <option value="">-- Pilih Karyawan --</option>
                            @foreach($karyawans as $k)
                            <option value="{{ $k->id }}"
                                    data-jabatan="{{ $k->jabatan }}"
                                    data-gaji="{{ $k->gaji_pokok }}"
                                    data-nik="{{ $k->nik ?? '-' }}"
                                    {{ (old('karyawan_id', $selectedKaryawan?->id) == $k->id) ? 'selected' : '' }}>
                                {{ $k->nama_lengkap }} — {{ $k->jabatan }}
                            </option>
                            @endforeach
                        </select>
                        @error('karyawan_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @endif
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label fw-semibold">Periode <span class="text-danger">*</span></label>
                    @if($isEdit)
                        <input type="hidden" name="periode" value="{{ $penggajian->periode }}">
                        <input type="text" class="form-control" value="{{ $penggajian->periode }}" readonly>
                    @else
                        <input type="month" name="periode" id="periodeInput" class="form-control @error('periode') is-invalid @enderror"
                               value="{{ old('periode', $periodeDefault) }}" required onchange="onKaryawanChange()">
                        @error('periode')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @endif
                </div>
                @if(!$isEdit)
                <div class="col-12 col-md-4">
                    <button type="button" class="btn btn-warning w-100" onclick="hitungOtomatis()">
                        <i class="bi bi-calculator me-1"></i>Hitung Otomatis dari Absensi
                    </button>
                    <small class="text-muted d-block mt-1">Pilih karyawan + periode lalu klik untuk auto-fill</small>
                </div>
                @endif
            </div>

            {{-- Info Karyawan --}}
            @if($selectedKaryawan || $isEdit)
            @php $k = $selectedKaryawan ?? $penggajian->karyawan; @endphp
            <div class="row g-2 mt-2">
                <div class="col-6 col-md-3">
                    <small class="text-muted">NIK</small>
                    <div class="fw-semibold">{{ $k?->nik ?? '-' }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <small class="text-muted">Jabatan</small>
                    <div class="fw-semibold">{{ $k?->jabatan }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <small class="text-muted">Tipe</small>
                    <div class="fw-semibold">{{ ucfirst($k?->tipe_karyawan) }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <small class="text-muted">Cabang</small>
                    <div class="fw-semibold">{{ $k?->cabang?->nama_cabang ?? '-' }}</div>
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- ======= REKAP ABSENSI ======= --}}
    @if($pg || $preview)
    <div class="card mb-3" id="rekapAbsensiCard">
        <div class="card-header fw-semibold d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span><i class="bi bi-calendar-check me-1"></i>Rekap Absensi</span>
            @if(!$isEdit)
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnRefreshRekap" onclick="refreshRekapAbsensi()">
                <i class="bi bi-arrow-repeat me-1"></i>Refresh Data Absensi
            </button>
            @endif
        </div>
        <div class="card-body">
            <div class="row g-2 text-center" id="rekapAbsensiRow">
                <div class="col-6 col-md" id="cardHariKerja">
                    <div class="p-2 bg-light rounded">
                        <div class="fw-bold h5 mb-0" id="valHariKerja">{{ $fv('jumlah_hari_kerja', 0) }}</div>
                        <small class="text-muted">Hari Kerja</small>
                    </div>
                </div>
                <div class="col-6 col-md">
                    <div class="p-2 bg-success bg-opacity-10 rounded">
                        <div class="fw-bold h5 mb-0 text-success" id="valHadir">{{ $fv('jumlah_hadir', 0) }}</div>
                        <small class="text-muted">Hadir</small>
                    </div>
                </div>
                <div class="col-6 col-md">
                    <div class="p-2 bg-danger bg-opacity-10 rounded">
                        <div class="fw-bold h5 mb-0 text-danger" id="valAlpha">{{ $fv('jumlah_alpha', 0) }}</div>
                        <small class="text-muted">Alpha</small>
                    </div>
                </div>
                <div class="col-6 col-md">
                    <div class="p-2 bg-warning bg-opacity-10 rounded">
                        <div class="fw-bold h5 mb-0 text-warning" id="valTelat">{{ $fv('jumlah_telat', 0) }}</div>
                        <small class="text-muted">Terlambat</small>
                    </div>
                </div>
                <div class="col-6 col-md">
                    <div class="p-2 bg-primary bg-opacity-10 rounded">
                        <div class="fw-bold h5 mb-0 text-primary" id="valLembur">{{ number_format($fv('jam_lembur_total', 0), 1) }}</div>
                        <small class="text-muted">Jam Lembur</small>
                    </div>
                </div>
            </div>
            {{-- Ringkasan teks --}}
            <div class="mt-2 small text-muted" id="rekapRingkasan">
                📊 Rekap Absensi: Hadir <strong id="rsHadir">{{ $fv('jumlah_hadir', 0) }}</strong> hari,
                Lembur <strong id="rsLembur">{{ number_format($fv('jam_lembur_total', 0), 1) }}</strong> jam,
                Telat <strong id="rsTelat">{{ $fv('jumlah_telat', 0) }}</strong> hari
            </div>
            {{-- Hidden fields absensi --}}
            <input type="hidden" id="hHariKerja" value="{{ $fv('jumlah_hari_kerja', 0) }}">
            <input type="hidden" id="hJumlahAlpha" value="{{ $fv('jumlah_alpha', 0) }}">
            <input type="hidden" id="hJamLembur" value="{{ $fv('jam_lembur_total', 0) }}">
        </div>
    </div>
    @else
    {{-- Placeholder rekap saat belum ada karyawan/preview --}}
    <div class="card mb-3 d-none" id="rekapAbsensiCard">
        <div class="card-header fw-semibold d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span><i class="bi bi-calendar-check me-1"></i>Rekap Absensi</span>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnRefreshRekap" onclick="refreshRekapAbsensi()">
                <i class="bi bi-arrow-repeat me-1"></i>Refresh Data Absensi
            </button>
        </div>
        <div class="card-body">
            <div class="row g-2 text-center" id="rekapAbsensiRow">
                <div class="col-6 col-md">
                    <div class="p-2 bg-light rounded">
                        <div class="fw-bold h5 mb-0" id="valHariKerja">0</div>
                        <small class="text-muted">Hari Kerja</small>
                    </div>
                </div>
                <div class="col-6 col-md">
                    <div class="p-2 bg-success bg-opacity-10 rounded">
                        <div class="fw-bold h5 mb-0 text-success" id="valHadir">0</div>
                        <small class="text-muted">Hadir</small>
                    </div>
                </div>
                <div class="col-6 col-md">
                    <div class="p-2 bg-danger bg-opacity-10 rounded">
                        <div class="fw-bold h5 mb-0 text-danger" id="valAlpha">0</div>
                        <small class="text-muted">Alpha</small>
                    </div>
                </div>
                <div class="col-6 col-md">
                    <div class="p-2 bg-warning bg-opacity-10 rounded">
                        <div class="fw-bold h5 mb-0 text-warning" id="valTelat">0</div>
                        <small class="text-muted">Terlambat</small>
                    </div>
                </div>
                <div class="col-6 col-md">
                    <div class="p-2 bg-primary bg-opacity-10 rounded">
                        <div class="fw-bold h5 mb-0 text-primary" id="valLembur">0</div>
                        <small class="text-muted">Jam Lembur</small>
                    </div>
                </div>
            </div>
            <div class="mt-2 small text-muted" id="rekapRingkasan">
                📊 Rekap Absensi: Hadir <strong id="rsHadir">0</strong> hari,
                Lembur <strong id="rsLembur">0</strong> jam,
                Telat <strong id="rsTelat">0</strong> hari
            </div>
            <input type="hidden" id="hHariKerja" value="0">
            <input type="hidden" id="hJumlahAlpha" value="0">
            <input type="hidden" id="hJamLembur" value="0">
        </div>
    </div>
    @endif

    {{-- ======= KOMPONEN GAJI ======= --}}
    <div class="row g-3">

        {{-- PENAMBAHAN --}}
        <div class="col-12 col-lg-6">
            <div class="card h-100 border-success">
                <div class="card-header bg-success bg-opacity-10 d-flex justify-content-between">
                    <span class="fw-semibold text-success"><i class="bi bi-plus-circle me-1"></i>Penambahan</span>
                    <span class="text-success fw-bold" id="totalPenambahanBadge">Rp 0</span>
                </div>
                <div class="card-body">

                    {{-- Gaji Pokok --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Gaji Pokok <span class="badge bg-secondary ms-1" style="font-size:.65rem">Dari Data Karyawan</span></label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" inputmode="numeric" data-rupiah id="gajiPokok" class="form-control bg-light"
                                   value="{{ $fv('gaji_pokok', 0) }}" readonly tabindex="-1">
                        </div>
                    </div>

                    {{-- Tunjangan Jabatan --}}
                    <div class="mb-3">
                        <label class="form-label">Tunjangan Jabatan</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" inputmode="numeric" data-rupiah name="tunjangan_jabatan" id="tunjangan_jabatan"
                                   class="form-control"
                                   value="{{ $fv('tunjangan_jabatan', 0) }}"
                                   oninput="recalculate()">
                        </div>
                    </div>

                    {{-- Tunjangan Makan --}}
                    <div class="mb-3">
                        <label class="form-label">Tunjangan Makan</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" inputmode="numeric" data-rupiah name="tunjangan_makan" id="tunjangan_makan"
                                   class="form-control"
                                   value="{{ $fv('tunjangan_makan', 0) }}"
                                   oninput="recalculate()">
                        </div>
                    </div>

                    {{-- Tunjangan Transport --}}
                    <div class="mb-3">
                        <label class="form-label">Tunjangan Transport</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" inputmode="numeric" data-rupiah name="tunjangan_transport" id="tunjangan_transport"
                                   class="form-control"
                                   value="{{ $fv('tunjangan_transport', 0) }}"
                                   oninput="recalculate()">
                        </div>
                    </div>

                    {{-- Tunjangan Kehadiran --}}
                    <div class="mb-3">
                        <label class="form-label">Premi / Tunjangan Kehadiran
                            <small class="text-muted">(dibayar jika hadir ≥80%)</small>
                            <x-tooltip key="penggajian.tunjangan_kehadiran" />
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" inputmode="numeric" data-rupiah name="tunjangan_kehadiran" id="tunjangan_kehadiran"
                                   class="form-control"
                                   value="{{ $fv('tunjangan_kehadiran', 0) }}"
                                   oninput="recalculate()">
                        </div>
                    </div>

                    {{-- Uang Lembur --}}
                    <div class="mb-3">
                        <label class="form-label d-flex align-items-center gap-2 flex-wrap">
                            <span>Uang Lembur</span>
                            <x-tooltip key="penggajian.uang_lembur" />
                            <span id="badgeLembur"
                                  class="badge {{ $uangLemburManual ? 'bg-warning text-dark' : 'bg-secondary' }}"
                                  style="font-size:.65rem">
                                {{ $uangLemburManual ? '🟡 Manual override' : 'Auto-Hitung' }}
                            </span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" inputmode="numeric" data-rupiah name="uang_lembur" id="uangLembur"
                                   class="form-control"
                                   value="{{ $fv('uang_lembur', 0) }}"
                                   oninput="onUangLemburChange()">
                            <button type="button" class="btn btn-outline-secondary btn-sm px-2"
                                    id="btnResetLembur" onclick="resetUangLembur()"
                                    title="Reset ke nilai auto"
                                    {{ !$uangLemburManual ? 'style=display:none' : '' }}>
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </button>
                        </div>
                        <small class="text-muted" id="captionLembur">
                            Auto: {{ number_format($jamLemburPreview, 1) }} jam × Rp {{ number_format($tarifLemburPreview, 0, ',', '.') }}/jam
                            = Rp {{ number_format($uangLemburAuto, 0, ',', '.') }}
                            (<a href="{{ route('pengaturan.penggajian') }}" class="text-decoration-none" target="_blank">⚙️ Pengaturan</a>)
                        </small>
                        <input type="hidden" name="uang_lembur_manual" id="hUangLemburManual"
                               value="{{ $uangLemburManual ? '1' : '0' }}">
                    </div>

                    {{-- Bonus --}}
                    <div class="mb-3">
                        <label class="form-label">Bonus</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" inputmode="numeric" data-rupiah name="bonus" id="bonus"
                                   class="form-control"
                                   value="{{ $fv('bonus', 0) }}"
                                   oninput="recalculate()">
                        </div>
                    </div>

                    {{-- Insentif --}}
                    <div class="mb-3">
                        <label class="form-label">Insentif / Komisi Penjualan <x-tooltip key="penggajian.insentif" /></label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" inputmode="numeric" data-rupiah name="insentif" id="insentif"
                                   class="form-control"
                                   value="{{ $fv('insentif', 0) }}"
                                   oninput="recalculate()">
                        </div>
                    </div>

                    {{-- THR --}}
                    <div class="mb-3">
                        <label class="form-label">THR (Tunjangan Hari Raya)</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" inputmode="numeric" data-rupiah name="thr" id="thr"
                                   class="form-control"
                                   value="{{ $fv('thr', 0) }}"
                                   oninput="recalculate()">
                        </div>
                    </div>

                    {{-- Komisi --}}
                    <div class="mb-3">
                        <label class="form-label">Komisi</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" inputmode="numeric" data-rupiah name="komisi" id="komisi"
                                   class="form-control"
                                   value="{{ $fv('komisi', 0) }}"
                                   oninput="recalculate()">
                        </div>
                    </div>

                    {{-- Tunjangan Lain --}}
                    <div class="mb-0">
                        <label class="form-label">Tunjangan Lain-lain</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" inputmode="numeric" data-rupiah name="tunjangan" id="tunjangan"
                                   class="form-control"
                                   value="{{ $fv('tunjangan', 0) }}"
                                   oninput="recalculate()">
                        </div>
                    </div>

                </div>
                <div class="card-footer bg-success bg-opacity-10">
                    <div class="d-flex justify-content-between fw-bold">
                        <span>Total Penambahan</span>
                        <span class="text-success" id="totalPenambahan">Rp 0</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- PENGURANGAN --}}
        <div class="col-12 col-lg-6">
            <div class="card h-100 border-danger">
                <div class="card-header bg-danger bg-opacity-10 d-flex justify-content-between">
                    <span class="fw-semibold text-danger"><i class="bi bi-dash-circle me-1"></i>Pengurangan / Potongan</span>
                    <span class="text-danger fw-bold" id="totalPotonganBadge">Rp 0</span>
                </div>
                <div class="card-body">

                    {{-- Potongan Absensi --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold d-flex align-items-center gap-2 flex-wrap">
                            <span>Potongan Alpha / Absensi</span>
                            <x-tooltip key="penggajian.potongan_absensi" />
                            <span id="badgeAlpha"
                                  class="badge {{ $potonganAlpaManual ? 'bg-warning text-dark' : 'bg-secondary' }}"
                                  style="font-size:.65rem">
                                {{ $potonganAlpaManual ? '🟡 Manual override' : 'Auto-Hitung' }}
                            </span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" inputmode="numeric" data-rupiah name="potongan_absensi" id="potonganAbsensi"
                                   class="form-control"
                                   value="{{ $fv('potongan_absensi', 0) }}"
                                   oninput="onPotonganAbsensiChange()">
                            <button type="button" class="btn btn-outline-secondary btn-sm px-2"
                                    id="btnResetAlpha" onclick="resetPotonganAbsensi()"
                                    title="Reset ke nilai auto"
                                    {{ !$potonganAlpaManual ? 'style=display:none' : '' }}>
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </button>
                        </div>
                        <small class="text-muted" id="captionAlpha">
                            Auto: {{ $alphaPreview }} hari alpha × Rp {{ number_format($tarifAlphaPreview, 0, ',', '.') }}/hari
                            = Rp {{ number_format($potonganAbsensiAuto, 0, ',', '.') }}
                            (<a href="{{ route('pengaturan.penggajian') }}" class="text-decoration-none" target="_blank">⚙️ Pengaturan</a>)
                        </small>
                        <input type="hidden" name="potongan_alpa_manual" id="hPotonganAlpaManual"
                               value="{{ $potonganAlpaManual ? '1' : '0' }}">
                    </div>

                    {{-- BPJS Kesehatan --}}
                    <div class="mb-3">
                        <label class="form-label">BPJS Kesehatan (Ditanggung Karyawan)
                            <span class="badge bg-info text-dark ms-1" style="font-size:.65rem">1% Gaji Pokok</span>
                            <x-tooltip key="penggajian.bpjs_kesehatan" />
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" inputmode="numeric" data-rupiah name="bpjs_kesehatan" id="bpjs_kesehatan"
                                   class="form-control"
                                   value="{{ $fv('bpjs_kesehatan', 0) }}"
                                   oninput="recalculate()">
                        </div>
                        <small class="text-muted">Tarif default sesuai data karyawan. Ubah jika perlu.</small>
                    </div>

                    {{-- BPJS TK (JHT) --}}
                    <div class="mb-3">
                        <label class="form-label">BPJS Ketenagakerjaan JHT (Ditanggung Karyawan)
                            <span class="badge bg-info text-dark ms-1" style="font-size:.65rem">2% Gaji Pokok</span>
                            <x-tooltip key="penggajian.bpjs_ketenagakerjaan" />
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" inputmode="numeric" data-rupiah name="bpjs_ketenagakerjaan" id="bpjs_ketenagakerjaan"
                                   class="form-control"
                                   value="{{ $fv('bpjs_ketenagakerjaan', 0) }}"
                                   oninput="recalculate()">
                        </div>
                        <small class="text-muted">Tarif default sesuai data karyawan. Ubah jika perlu.</small>
                    </div>

                    {{-- PPh21 --}}
                    <div class="mb-3">
                        <label class="form-label">PPh21 (Pajak Penghasilan)
                            <span class="badge bg-warning text-dark ms-1" style="font-size:.65rem">Progresif</span>
                            <x-tooltip key="penggajian.pph21" />
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" inputmode="numeric" data-rupiah name="pph21" id="pph21"
                                   class="form-control"
                                   value="{{ $fv('pph21', 0) }}"
                                   oninput="recalculate()">
                        </div>
                        <small class="text-muted">
                            Dihitung otomatis dari total penghasilan bruto setahun.
                            PTKP TK/0 = Rp 54jt. Tarif: 5%/15%/25%/30%.
                        </small>
                    </div>

                    {{-- Kasbon --}}
                    <div class="mb-3">
                        <label class="form-label">Kasbon / Pinjaman Karyawan</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" inputmode="numeric" data-rupiah name="kasbon" id="kasbon"
                                   class="form-control"
                                   value="{{ $fv('kasbon', 0) }}"
                                   oninput="recalculate()">
                        </div>
                    </div>

                    {{-- Potongan Lain --}}
                    <div class="mb-0">
                        <label class="form-label">Potongan Lain-lain</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" inputmode="numeric" data-rupiah name="potongan_lain" id="potongan_lain"
                                   class="form-control"
                                   value="{{ $fv('potongan_lain', 0) }}"
                                   oninput="recalculate()">
                        </div>
                    </div>

                </div>
                <div class="card-footer bg-danger bg-opacity-10">
                    <div class="d-flex justify-content-between fw-bold">
                        <span>Total Potongan</span>
                        <span class="text-danger" id="totalPotongan">Rp 0</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ======= CATATAN + TOTAL GAJI ======= --}}
    <div class="card mt-3">
        <div class="card-body">
            <div class="row g-3 align-items-center">
                <div class="col-12 col-md-6">
                    <label class="form-label">Catatan</label>
                    <textarea name="catatan" class="form-control" rows="2"
                              placeholder="Catatan tambahan untuk slip gaji ini...">{{ old('catatan', $pg?->catatan) }}</textarea>
                </div>
                <div class="col-12 col-md-6">
                    <div class="p-3 bg-primary bg-opacity-10 rounded text-center">
                        <div class="text-muted mb-1">Total Gaji Bersih</div>
                        <div class="fw-bold text-primary" id="totalGaji" style="font-size:1.8rem">Rp 0</div>
                        <div class="d-flex justify-content-between mt-2 small text-muted">
                            <span>Penambahan: <span class="text-success fw-semibold" id="totalPenambahanInfo">Rp 0</span></span>
                            <span>Potongan: <span class="text-danger fw-semibold" id="totalPotonganInfo">Rp 0</span></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg me-1"></i>
                {{ $isEdit ? 'Perbarui Slip Gaji' : 'Simpan Slip Gaji' }}
            </button>
            <a href="{{ route('penggajian.index') }}" class="btn btn-outline-secondary">Batal</a>
        </div>
    </div>

</form>

@push('scripts')
<script>
// Format angka ke rupiah
function fRp(n) {
    return 'Rp ' + Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function getVal(id) {
    return rupiahParse(document.getElementById(id)?.value);
}

// ── Auto-hitung & override tracking ──────────────────────────────────────────

// Nilai auto dihitung server-side, simpan di JS untuk reset
let _autoUangLembur     = {{ $uangLemburAuto }};
let _autoPotonganAbsensi= {{ $potonganAbsensiAuto }};
let _tarifLembur        = {{ $tarifLemburPreview }};
let _tarifAlpha         = {{ $tarifAlphaPreview }};
let _jamLembur          = {{ $jamLemburPreview }};
let _jumlahAlpha        = {{ $alphaPreview }};

function onUangLemburChange() {
    const current = rupiahParse(document.getElementById('uangLembur').value);
    const isManual = Math.round(current) !== Math.round(_autoUangLembur);
    document.getElementById('hUangLemburManual').value = isManual ? '1' : '0';
    const badge = document.getElementById('badgeLembur');
    const btn   = document.getElementById('btnResetLembur');
    if (isManual) {
        badge.className = 'badge bg-warning text-dark';
        badge.textContent = '🟡 Manual override (Auto: Rp ' + rupiahFmt(Math.round(_autoUangLembur)) + ')';
        btn.style.display = '';
    } else {
        badge.className = 'badge bg-secondary';
        badge.textContent = 'Auto-Hitung';
        btn.style.display = 'none';
    }
    recalculate();
}

function resetUangLembur() {
    document.getElementById('uangLembur').value = rupiahFmt(Math.round(_autoUangLembur));
    onUangLemburChange();
}

function onPotonganAbsensiChange() {
    const current = rupiahParse(document.getElementById('potonganAbsensi').value);
    const isManual = Math.round(current) !== Math.round(_autoPotonganAbsensi);
    document.getElementById('hPotonganAlpaManual').value = isManual ? '1' : '0';
    const badge = document.getElementById('badgeAlpha');
    const btn   = document.getElementById('btnResetAlpha');
    if (isManual) {
        badge.className = 'badge bg-warning text-dark';
        badge.textContent = '🟡 Manual override (Auto: Rp ' + rupiahFmt(Math.round(_autoPotonganAbsensi)) + ')';
        btn.style.display = '';
    } else {
        badge.className = 'badge bg-secondary';
        badge.textContent = 'Auto-Hitung';
        btn.style.display = 'none';
    }
    recalculate();
}

function resetPotonganAbsensi() {
    document.getElementById('potonganAbsensi').value = rupiahFmt(Math.round(_autoPotonganAbsensi));
    onPotonganAbsensiChange();
}

function recalculate() {
    const gajiPokok         = getVal('gajiPokok');
    const tunjanganJabatan  = getVal('tunjangan_jabatan');
    const tunjanganMakan    = getVal('tunjangan_makan');
    const tunjanganTransport= getVal('tunjangan_transport');
    const tunjanganKehadiran= getVal('tunjangan_kehadiran');
    const uangLembur        = getVal('uangLembur');
    const bonus             = getVal('bonus');
    const insentif          = getVal('insentif');
    const thr               = getVal('thr');
    const komisi            = getVal('komisi');
    const tunjangan         = getVal('tunjangan');

    const potonganAbsensi     = getVal('potonganAbsensi');
    const bpjsKesehatan       = getVal('bpjs_kesehatan');
    const bpjsTk              = getVal('bpjs_ketenagakerjaan');
    const pph21               = getVal('pph21');
    const kasbon              = getVal('kasbon');
    const potonganLain        = getVal('potongan_lain');

    const totalPenambahan = gajiPokok + tunjanganJabatan + tunjanganMakan + tunjanganTransport
        + tunjanganKehadiran + uangLembur + bonus + insentif + thr + komisi + tunjangan;

    const totalPotongan = potonganAbsensi + bpjsKesehatan + bpjsTk + pph21 + kasbon + potonganLain;

    const totalGaji = Math.max(0, totalPenambahan - totalPotongan);

    document.getElementById('totalPenambahan').textContent      = fRp(totalPenambahan);
    document.getElementById('totalPenambahanBadge').textContent = fRp(totalPenambahan);
    document.getElementById('totalPenambahanInfo').textContent  = fRp(totalPenambahan);
    document.getElementById('totalPotongan').textContent        = fRp(totalPotongan);
    document.getElementById('totalPotonganBadge').textContent   = fRp(totalPotongan);
    document.getElementById('totalPotonganInfo').textContent    = fRp(totalPotongan);
    document.getElementById('totalGaji').textContent            = fRp(totalGaji);
}

// Redirect dengan karyawan_id + periode untuk auto-fill dari server
function hitungOtomatis() {
    const karyawanId = document.getElementById('karyawanSelect')?.value;
    const periode    = document.getElementById('periodeInput')?.value;
    if (!karyawanId || !periode) {
        alert('Pilih karyawan dan periode terlebih dahulu.');
        return;
    }
    window.location = '{{ route('penggajian.create') }}?karyawan_id=' + karyawanId + '&periode=' + periode;
}

// Refresh rekap absensi via AJAX tanpa reload halaman
function refreshRekapAbsensi() {
    const karyawanId = document.getElementById('karyawanSelect')?.value;
    const periode    = document.getElementById('periodeInput')?.value;
    if (!karyawanId || !periode) {
        alert('Pilih karyawan dan periode terlebih dahulu.');
        return;
    }
    const btn = document.getElementById('btnRefreshRekap');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Memuat...';

    fetch('{{ route('penggajian.rekap-absensi') }}?karyawan_id=' + karyawanId + '&periode=' + periode, {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) throw new Error('Gagal mengambil data');
        const r = data.rekap;
        // Update tampilan kartu
        document.getElementById('valHariKerja').textContent = r.hari_kerja;
        document.getElementById('valHadir').textContent     = r.hari_hadir;
        document.getElementById('valAlpha').textContent     = r.hari_alpha;
        document.getElementById('valTelat').textContent     = r.hari_telat;
        document.getElementById('valLembur').textContent    = parseFloat(r.total_jam_lembur).toFixed(1);
        // Update ringkasan teks
        document.getElementById('rsHadir').textContent  = r.hari_hadir;
        document.getElementById('rsLembur').textContent = parseFloat(r.total_jam_lembur).toFixed(1);
        document.getElementById('rsTelat').textContent  = r.hari_telat;
        // Update hidden fields yang dipakai recalculate()
        document.getElementById('hHariKerja').value   = r.hari_kerja;
        document.getElementById('hJumlahAlpha').value = r.hari_alpha;
        document.getElementById('hJamLembur').value   = r.total_jam_lembur;
        // Update auto-hitung values dari rekap + tarif baru
        _jamLembur    = parseFloat(r.total_jam_lembur) || 0;
        _jumlahAlpha  = parseInt(r.hari_alpha) || 0;
        if (data.tarif_lembur) { _tarifLembur = parseFloat(data.tarif_lembur); }
        if (data.tarif_alpha)  { _tarifAlpha  = parseFloat(data.tarif_alpha);  }
        _autoUangLembur      = Math.round(_jamLembur   * _tarifLembur);
        _autoPotonganAbsensi = Math.round(_jumlahAlpha * _tarifAlpha);

        // Update caption lembur
        const captionL = document.getElementById('captionLembur');
        if (captionL) {
            captionL.innerHTML = 'Auto: ' + parseFloat(r.total_jam_lembur).toFixed(1) + ' jam × Rp '
                + rupiahFmt(Math.round(_tarifLembur)) + '/jam = Rp ' + rupiahFmt(Math.round(_autoUangLembur))
                + ' (<a href="{{ route('pengaturan.penggajian') }}" class="text-decoration-none" target="_blank">⚙️ Pengaturan</a>)';
        }
        const captionA = document.getElementById('captionAlpha');
        if (captionA) {
            captionA.innerHTML = 'Auto: ' + r.hari_alpha + ' hari alpha × Rp '
                + rupiahFmt(Math.round(_tarifAlpha)) + '/hari = Rp ' + rupiahFmt(Math.round(_autoPotonganAbsensi))
                + ' (<a href="{{ route('pengaturan.penggajian') }}" class="text-decoration-none" target="_blank">⚙️ Pengaturan</a>)';
        }

        // Jika belum manual, langsung set nilai auto ke input
        if (document.getElementById('hUangLemburManual')?.value !== '1') {
            document.getElementById('uangLembur').value = rupiahFmt(Math.round(_autoUangLembur));
        }
        if (document.getElementById('hPotonganAlpaManual')?.value !== '1') {
            document.getElementById('potonganAbsensi').value = rupiahFmt(Math.round(_autoPotonganAbsensi));
        }

        // Tampilkan card jika tersembunyi
        document.getElementById('rekapAbsensiCard')?.classList.remove('d-none');
        // Hitung ulang gaji
        recalculate();
    })
    .catch(() => alert('Gagal mengambil rekap absensi. Silakan coba lagi.'))
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Refresh Data Absensi';
    });
}

function onKaryawanChange() {
    // Hanya update gaji pokok saat karyawan berubah (tanpa reload)
    const sel = document.getElementById('karyawanSelect');
    const opt = sel?.options[sel.selectedIndex];
    if (opt && opt.value) {
        const gaji = parseInt(opt.dataset.gaji) || 0;
        if (document.getElementById('gajiPokok')) {
            document.getElementById('gajiPokok').value = rupiahFmt(gaji);
            recalculate();
        }
    }
}

// Inisialisasi saat halaman load
document.addEventListener('DOMContentLoaded', function () {
    recalculate();
});
</script>
@endpush
@endsection
