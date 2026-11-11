@extends('layouts.app')

@section('title', 'Edit Cabang - ' . $cabang->nama_cabang)

@push('styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<style>
    #leafletMap { height: 60vh; min-height: 400px; width: 100%; background: #ddd; }
    .user-assign-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.6rem 0.75rem;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
    }
    .user-avatar {
        width: 34px; height: 34px;
        border-radius: 50%;
        background: #3b82f6; color: white;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.75rem; font-weight: 700;
        flex-shrink: 0;
    }
</style>
@endpush

@section('content')

{{-- PAGE HEADER --}}
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-0" style="color:#1e293b">
            <i class="bi bi-pencil-square me-2 text-primary"></i>Edit Cabang
        </h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0" style="font-size:0.8rem">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('cabang.index') }}" class="text-decoration-none">Cabang</a></li>
                <li class="breadcrumb-item active">Edit</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2">
        <x-panduan-button slug="cabang" />
        {{-- Toggle Aktif — form TERPISAH di luar semua form lain --}}
        <form method="POST" action="{{ route('cabang.toggle-aktif', $cabang) }}">
            @csrf @method('PATCH')
            <button type="submit"
                class="btn btn-sm {{ $cabang->is_active ? 'btn-outline-warning' : 'btn-outline-success' }} d-flex align-items-center gap-1"
                onclick="return confirm('{{ $cabang->is_active ? 'Nonaktifkan' : 'Aktifkan' }} cabang ini?')">
                <i class="bi bi-{{ $cabang->is_active ? 'pause-circle' : 'play-circle' }}"></i>
                <span class="d-none d-sm-inline">{{ $cabang->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</span>
            </button>
        </form>
        <a href="{{ route('cabang.index') }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
            <i class="bi bi-arrow-left"></i>
            <span class="d-none d-sm-inline">Kembali</span>
        </a>
    </div>
</div>

{{-- Status banner jika nonaktif --}}
@if(!$cabang->is_active)
<div class="alert alert-warning d-flex align-items-center gap-2 mb-4">
    <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
    <span>Cabang ini sedang <strong>nonaktif</strong>. Data masih tersimpan tapi cabang tidak bisa beroperasi.</span>
</div>
@endif

{{-- ===== FORM EDIT CABANG (hanya field informasi cabang) ===== --}}
<form method="POST" action="{{ route('cabang.update', $cabang) }}" id="formEditCabang">
    @csrf
    @method('PUT')

    <div class="row g-4">
        {{-- Kolom Kiri: field informasi --}}
        <div class="col-12 col-lg-8">
            <div class="card">
                <div class="card-header py-3 px-4">
                    <i class="bi bi-info-circle me-2 text-primary"></i>
                    <span class="fw-semibold">Informasi Cabang</span>
                </div>
                <div class="card-body p-4">

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-7">
                            <label for="nama_cabang" class="form-label fw-medium">
                                Nama Cabang <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="nama_cabang" name="nama_cabang"
                                class="form-control @error('nama_cabang') is-invalid @enderror"
                                value="{{ old('nama_cabang', $cabang->nama_cabang) }}"
                                maxlength="100" required autofocus>
                            @error('nama_cabang')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-5">
                            <label for="kode_cabang" class="form-label fw-medium">
                                Kode Cabang <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="kode_cabang" name="kode_cabang"
                                class="form-control text-uppercase @error('kode_cabang') is-invalid @enderror"
                                value="{{ old('kode_cabang', $cabang->kode_cabang) }}"
                                maxlength="10" required style="letter-spacing:0.1em">
                            @error('kode_cabang')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label for="tipe" class="form-label fw-medium">
                                Tipe <span class="text-danger">*</span>
                            </label>
                            <select id="tipe" name="tipe"
                                class="form-select @error('tipe') is-invalid @enderror">
                                @foreach($tipes as $tipe)
                                    <option value="{{ $tipe->value }}"
                                        {{ old('tipe', $cabang->tipe?->value) === $tipe->value ? 'selected' : '' }}>
                                        {{ $tipe->icon() }} {{ $tipe->label() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('tipe')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="telepon" class="form-label fw-medium">Telepon</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                <input type="text" id="telepon" name="telepon"
                                    class="form-control @error('telepon') is-invalid @enderror"
                                    value="{{ old('telepon', $cabang->telepon) }}"
                                    maxlength="20">
                                @error('telepon')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="alamat" class="form-label fw-medium">Alamat</label>
                        <textarea id="alamat" name="alamat" rows="3"
                            class="form-control @error('alamat') is-invalid @enderror">{{ old('alamat', $cabang->alamat) }}</textarea>
                        @error('alamat')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- ===== LOKASI & GPS ABSENSI ===== --}}
                    <div class="border border-primary-subtle rounded-3 p-3 mb-3" id="gps" style="background:#f0f9ff">
                        <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                            <div class="fw-semibold" style="font-size:0.9rem">
                                <i class="bi bi-geo-alt-fill me-1 text-primary"></i>Lokasi & Pengaturan Absensi
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" id="btnGPS" class="btn btn-outline-primary btn-sm"
                                        onclick="ambilLokasiGPS()">
                                    <i class="bi bi-crosshair me-1"></i>
                                    <span class="d-none d-sm-inline">Lokasi Saya</span>
                                    <span class="d-sm-none">GPS</span>
                                </button>
                                <button type="button" id="btnPeta" class="btn btn-outline-secondary btn-sm"
                                        onclick="bukaPeta()">
                                    <i class="bi bi-map me-1"></i>
                                    <span class="d-none d-sm-inline">Pilih dari Peta</span>
                                    <span class="d-sm-none">Peta</span>
                                </button>
                            </div>
                        </div>
                        <div class="alert alert-info py-2 mb-2" style="font-size:0.78rem;border-radius:6px">
                            <i class="bi bi-lightbulb me-1"></i>
                            <strong>Tip:</strong> Untuk hasil terbaik, gunakan HP/laptop di lokasi cabang saat klik <strong>"Lokasi Saya"</strong>.
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-12 col-sm-6">
                                <label class="form-label small mb-1 fw-medium">Latitude</label>
                                <input type="number" step="0.00000001" min="-90" max="90"
                                       name="latitude" id="lat_input"
                                       class="form-control form-control-sm @error('latitude') is-invalid @enderror"
                                       value="{{ old('latitude', $cabang->latitude) }}"
                                       placeholder="contoh: 3.595200">
                                @error('latitude')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label small mb-1 fw-medium">Longitude</label>
                                <input type="number" step="0.00000001" min="-180" max="180"
                                       name="longitude" id="lng_input"
                                       class="form-control form-control-sm @error('longitude') is-invalid @enderror"
                                       value="{{ old('longitude', $cabang->longitude) }}"
                                       placeholder="contoh: 98.672200">
                                @error('longitude')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-12 col-sm-6">
                                <label class="form-label small mb-1 fw-medium">Radius Absensi (meter)</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="radius_absen_meter" id="radius_input"
                                           min="10" max="5000" class="form-control"
                                           value="{{ old('radius_absen_meter', $cabang->radius_absen_meter ?? 100) }}"
                                           oninput="validateRadius(this)">
                                    <span class="input-group-text">m</span>
                                </div>
                                <div id="radius_warning" class="form-text" style="display:none;font-size:0.75rem"></div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label small mb-1 fw-medium">Jam Masuk Kerja</label>
                                <input type="time" name="jam_masuk" class="form-control form-control-sm"
                                       value="{{ old('jam_masuk', $cabang->jam_masuk ?? '08:00') }}">
                            </div>
                        </div>
                        <div id="koordinatPreview" style="font-size:0.76rem">
                            @if($cabang->latitude && $cabang->longitude)
                            <span class="text-success">
                                <i class="bi bi-geo-alt-fill me-1"></i>GPS tersetting:
                                {{ number_format((float)$cabang->latitude, 6) }}, {{ number_format((float)$cabang->longitude, 6) }}
                            </span>
                            @else
                            <span class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>GPS belum diset. Biarkan kosong untuk menonaktifkan validasi lokasi.
                            </span>
                            @endif
                        </div>
                    </div>

                    {{-- ===== SISTEM ANTRIAN PRODUKSI ===== --}}
                    <div class="border border-warning-subtle rounded-3 p-3 mb-3" style="background:#fffbeb">
                        <div class="fw-semibold mb-2" style="font-size:0.9rem">
                            <i class="bi bi-list-ol me-1 text-warning"></i>Sistem Antrian Produksi
                        </div>
                        <div class="form-check form-switch">
                            <input type="hidden" name="antrian_produksi_aktif" value="0">
                            <input class="form-check-input" type="checkbox" role="switch"
                                id="antrianAktif" name="antrian_produksi_aktif" value="1"
                                {{ old('antrian_produksi_aktif', $cabang->antrian_produksi_aktif ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="antrianAktif">
                                Aktifkan sistem antrian produksi untuk cabang ini
                            </label>
                        </div>
                        <div class="form-text mt-1">
                            Jika aktif, setiap order baru di POS akan mendapat nomor antrian otomatis
                            dan bisa dikelola oleh operator produksi.
                        </div>
                    </div>

                    {{-- ===== TAMPILAN DISPLAY TV ===== --}}
                    <div class="border border-info-subtle rounded-3 p-3 mb-3" style="background:#f0f9ff">
                        <div class="fw-semibold mb-2" style="font-size:0.9rem">
                            <i class="bi bi-tv me-1 text-info"></i>Tampilan Display TV Antrian
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input type="hidden" name="running_text_aktif" value="0">
                            <input class="form-check-input" type="checkbox" role="switch"
                                id="runningTextAktif" name="running_text_aktif" value="1"
                                {{ old('running_text_aktif', $cabang->running_text_aktif ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="runningTextAktif">
                                Aktifkan running text di footer layar antrian
                            </label>
                        </div>
                        <label for="running_text" class="form-label small fw-medium mb-1">
                            Teks Berjalan (Running Text)
                        </label>
                        <textarea id="running_text" name="running_text" rows="2"
                            class="form-control form-control-sm @error('running_text') is-invalid @enderror"
                            maxlength="500"
                            placeholder="Contoh: Promo hari ini diskon 10% untuk order 5kg! Terima kasih sudah menggunakan layanan kami.">{{ old('running_text', $cabang->running_text) }}</textarea>
                        <div class="form-text">Teks akan berjalan (marquee) di bagian bawah layar TV Display antrian cabang ini.</div>
                        @error('running_text')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- ===== FOOTER STRUK ===== --}}
                    <div class="border border-success-subtle rounded-3 p-3 mb-3" style="background:#f0fdf4">
                        <div class="fw-semibold mb-2" style="font-size:0.9rem">
                            <i class="bi bi-receipt me-1 text-success"></i>Struk POS
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input type="hidden" name="izinkan_sembunyi_harga_struk" value="0">
                            <input class="form-check-input" type="checkbox" role="switch"
                                id="izinkanSembunyiHarga" name="izinkan_sembunyi_harga_struk" value="1"
                                {{ old('izinkan_sembunyi_harga_struk', $cabang->izinkan_sembunyi_harga_struk ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="izinkanSembunyiHarga">
                                Izinkan kasir sembunyi harga per item di struk
                                <x-tooltip key="cabang.izinkan_sembunyi_harga_struk" placement="top" />
                            </label>
                        </div>
                        <div class="form-text mt-n1 mb-2">
                            Jika dicentang, kasir bisa cetak struk tanpa harga per item (untuk hindari komplain harga pelanggan) —
                            muncul checkbox opsional di POS, default tidak tampil harga per item. Total tetap selalu tercetak.
                        </div>
                        <label for="footer_struk" class="form-label small fw-medium mb-1">
                            Footer Struk <span class="text-muted fw-normal">(Opsional)</span>
                            <x-tooltip key="cabang.footer_struk" placement="top" />
                        </label>
                        <textarea id="footer_struk" name="footer_struk" rows="3"
                            class="form-control form-control-sm @error('footer_struk') is-invalid @enderror"
                            maxlength="500"
                            placeholder="Contoh:&#10;Terima kasih sudah berbelanja!&#10;{{ $brand->namaSingkat() }} Cabang Pusat Kota">{{ old('footer_struk', $cabang->footer_struk) }}</textarea>
                        <div class="form-text">
                            Pesan ini muncul di bagian bawah struk POS cabang ini.
                            Multi-baris diperbolehkan. Kosongkan untuk pakai teks default.
                        </div>
                        @error('footer_struk')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- ===== LABEL PRODUK (4.44 Poin 2) — bawaan MATI ===== --}}
                    @php $kategoriLabelTerpilih = old('label_cup_kategori', $cabang->kategoriLabelCup()); @endphp
                    <div class="border border-info-subtle rounded-3 p-3 mb-3" style="background:#f0f9ff">
                        <input type="hidden" name="label_cup_form" value="1">
                        <div class="fw-semibold mb-2" style="font-size:0.9rem">
                            <i class="bi bi-tag me-1 text-info"></i>Label Produk (stiker)
                            <x-tooltip key="cabang.label_cup_aktif" placement="top" />
                            <x-panduan-button slug="label-produk" />
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input type="hidden" name="label_cup_aktif" value="0">
                            <input class="form-check-input" type="checkbox" role="switch"
                                id="labelCupAktif" name="label_cup_aktif" value="1"
                                {{ old('label_cup_aktif', $cabang->label_cup_aktif ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="labelCupAktif">Aktifkan cetak label produk di cabang ini</label>
                        </div>
                        <div class="form-label small fw-medium mb-1">Kategori yang dicetak label <x-tooltip key="cabang.label_cup_kategori" placement="top" /></div>
                        <div class="d-flex flex-wrap gap-3 mb-2">
                            @forelse($kategoriLabelOptions as $kat)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="label_cup_kategori[]"
                                    id="labelKat{{ $kat->id }}" value="{{ $kat->kode_kategori }}"
                                    {{ in_array($kat->kode_kategori, (array) $kategoriLabelTerpilih, true) ? 'checked' : '' }}>
                                <label class="form-check-label small" for="labelKat{{ $kat->id }}">{{ $kat->nama_kategori }}</label>
                            </div>
                            @empty
                            <div class="small text-muted">Belum ada kategori yang punya produk jual.</div>
                            @endforelse
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-items-end">
                            <div>
                                <label for="label_cup_lebar_mm" class="form-label small fw-medium mb-1">Lebar stiker (mm)</label>
                                <input type="number" id="label_cup_lebar_mm" name="label_cup_lebar_mm" min="25" max="120"
                                    class="form-control form-control-sm @error('label_cup_lebar_mm') is-invalid @enderror" style="max-width:110px"
                                    value="{{ old('label_cup_lebar_mm', $cabang->label_cup_lebar_mm ?? 40) }}">
                            </div>
                            <div>
                                <label for="label_cup_tinggi_mm" class="form-label small fw-medium mb-1">Tinggi stiker (mm)</label>
                                <input type="number" id="label_cup_tinggi_mm" name="label_cup_tinggi_mm" min="15" max="120"
                                    class="form-control form-control-sm @error('label_cup_tinggi_mm') is-invalid @enderror" style="max-width:110px"
                                    value="{{ old('label_cup_tinggi_mm', $cabang->label_cup_tinggi_mm ?? 30) }}">
                            </div>
                            <x-tooltip key="cabang.label_cup_ukuran" placement="top" />
                        </div>
                        {{-- 4.49 Tahap D — geser posisi label per cabang --}}
                        <div class="d-flex flex-wrap gap-2 align-items-end mt-2">
                            <div>
                                <label for="label_cup_geser_x" class="form-label small fw-medium mb-1">Geser kiri/kanan (mm)</label>
                                <input type="number" step="0.5" min="-10" max="10" id="label_cup_geser_x" name="label_cup_geser_x"
                                    class="form-control form-control-sm @error('label_cup_geser_x') is-invalid @enderror" style="max-width:110px"
                                    value="{{ old('label_cup_geser_x', $cabang->label_cup_geser_x ?? 0) }}">
                            </div>
                            <div>
                                <label for="label_cup_geser_y" class="form-label small fw-medium mb-1">Geser atas/bawah (mm)</label>
                                <input type="number" step="0.5" min="-10" max="10" id="label_cup_geser_y" name="label_cup_geser_y"
                                    class="form-control form-control-sm @error('label_cup_geser_y') is-invalid @enderror" style="max-width:110px"
                                    value="{{ old('label_cup_geser_y', $cabang->label_cup_geser_y ?? 0) }}">
                            </div>
                            <x-tooltip key="cabang.label_cup_geser" placement="top" />
                        </div>
                        <div class="form-text">Isi kalau tulisan label tidak di tengah stiker: <strong>minus = geser ke kiri</strong> (mis. −3,5 kalau kiri terlalu lebar dan kanan terpotong), plus = ke kanan. Berlaku untuk semua tablet/HP cabang ini.</div>
                        <div class="form-text mt-1">1 stiker = 1 qty item (1 box / pack). Label dicetak dari jendela struk setelah bayar, atau dari Detail Order. Saat aktif, POS menampilkan kolom catatan per item yang ikut tercetak di label.</div>
                    </div>

                    {{-- ===== CONFIG POS (Tahap 3 D'mentai) ===== --}}
                    <div class="border border-warning-subtle rounded-3 p-3 mb-3" style="background:#fffbeb">
                        <div class="fw-semibold mb-2" style="font-size:0.9rem">
                            <i class="bi bi-shop me-1 text-warning"></i>Config POS Outlet Ini
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6 col-md-3">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="dine_in_aktif" value="0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="dineInAktif" name="dine_in_aktif" value="1" {{ old('dine_in_aktif', $cabang->dine_in_aktif ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label small" for="dineInAktif">Dine-in</label>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="takeaway_aktif" value="0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="takeawayAktif" name="takeaway_aktif" value="1" {{ old('takeaway_aktif', $cabang->takeaway_aktif ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label small" for="takeawayAktif">Takeaway</label>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="frozen_aktif" value="0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="frozenAktif" name="frozen_aktif" value="1" {{ old('frozen_aktif', $cabang->frozen_aktif ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label small" for="frozenAktif">Frozen</label>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="nomor_meja_aktif" value="0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="nomorMejaAktif" name="nomor_meja_aktif" value="1" {{ old('nomor_meja_aktif', $cabang->nomor_meja_aktif ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label small" for="nomorMejaAktif">Input No. Meja</label>
                                </div>
                            </div>
                        </div>
                        <div class="row g-2 mb-2 align-items-end">
                            <div class="col-12 col-md-6">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="pilih_tipe_transaksi" value="0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="pilihTipeTransaksi" name="pilih_tipe_transaksi" value="1" {{ old('pilih_tipe_transaksi', $cabang->pilih_tipe_transaksi ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label small" for="pilihTipeTransaksi">Tampilkan pilihan Tipe Transaksi di POS <x-tooltip key="cabang.pilih_tipe_transaksi" /></label>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-medium mb-1" for="tipeTransaksiDefault">Tipe bawaan (dipakai saat pilihan disembunyikan)</label>
                                <select name="tipe_transaksi_default" id="tipeTransaksiDefault" class="form-select form-select-sm">
                                    @foreach(\App\Enums\TipeTransaksi::cases() as $t)
                                        <option value="{{ $t->value }}" @selected(old('tipe_transaksi_default', $cabang->tipe_transaksi_default ?? 'takeaway') === $t->value)>{{ $t->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <x-input-rupiah name="take_away_fee" label="Takeaway Fee" :value="old('take_away_fee', $cabang->take_away_fee ?? 0)" />
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-medium mb-1">Service Charge (%)</label>
                                <input type="number" step="0.01" min="0" max="100" class="form-control form-control-sm"
                                    name="service_charge_persen" value="{{ old('service_charge_persen', $cabang->service_charge_persen ?? 0) }}">
                            </div>
                        </div>
                        <div class="row g-2 mt-1">
                            <div class="col-12 small fw-medium">Wajib upload bukti pembayaran di POS <x-tooltip key="cabang.wajib_bukti" /></div>
                            <div class="col-6">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="wajib_bukti_transfer" value="0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="wajibBuktiTransfer" name="wajib_bukti_transfer" value="1" {{ old('wajib_bukti_transfer', $cabang->wajib_bukti_transfer ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label small" for="wajibBuktiTransfer">Transfer</label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="wajib_bukti_qris" value="0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="wajibBuktiQris" name="wajib_bukti_qris" value="1" {{ old('wajib_bukti_qris', $cabang->wajib_bukti_qris ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label small" for="wajibBuktiQris">QRIS</label>
                                </div>
                            </div>
                        </div>
                        {{-- 4.49 — Kirim ke Cabang dari POS (hanya Gudang Pusat), bawaan mati --}}
                        @if(($cabang->tipe?->value ?? $cabang->tipe) === 'gudang_pusat')
                        <div class="form-check form-switch mt-2">
                            <input type="hidden" name="kirim_cabang_aktif" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="kirimCabangAktif" name="kirim_cabang_aktif" value="1" {{ old('kirim_cabang_aktif', $cabang->kirim_cabang_aktif ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label small" for="kirimCabangAktif">Aktifkan <strong>Kirim ke Cabang</strong> di POS <x-tooltip key="cabang.kirim_cabang_aktif" /></label>
                        </div>
                        <div class="form-text">Kalau dinyalakan, POS gudang ini punya pilihan Jenis Pembeli → Kirim ke Cabang. Mati = POS seperti biasa.</div>
                        @endif
                    </div>

                    <div class="mb-0">
                        <label for="kepala_cabang_id" class="form-label fw-medium">
                            Kepala Cabang / Penanggung Jawab
                        </label>
                        <select id="kepala_cabang_id" name="kepala_cabang_id"
                            class="form-select @error('kepala_cabang_id') is-invalid @enderror">
                            <option value="">— Tidak ada —</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}"
                                    {{ old('kepala_cabang_id', $cabang->kepala_cabang_id) == $user->id ? 'selected' : '' }}>
                                    {{ $user->name }} ({{ $user->role?->label() }})
                                </option>
                            @endforeach
                        </select>
                        @error('kepala_cabang_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                </div>
            </div>
        </div>

        {{-- Kolom Kanan: info & tombol simpan --}}
        <div class="col-12 col-lg-4">

            {{-- Info cabang --}}
            <div class="card mb-3">
                <div class="card-header py-3 px-4">
                    <i class="bi bi-clock-history me-2 text-muted"></i>
                    <span class="fw-semibold">Info Cabang</span>
                </div>
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between mb-2" style="font-size:0.8rem">
                        <span class="text-muted">Dibuat</span>
                        <span>{{ $cabang->created_at->format('d M Y') }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2" style="font-size:0.8rem">
                        <span class="text-muted">Diperbarui</span>
                        <span>{{ $cabang->updated_at->format('d M Y H:i') }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2" style="font-size:0.8rem">
                        <span class="text-muted">Total Karyawan</span>
                        <span class="fw-semibold">{{ $cabang->karyawans()->count() }}</span>
                    </div>
                    <div class="d-flex justify-content-between" style="font-size:0.8rem">
                        <span class="text-muted">Status</span>
                        @if($cabang->is_active)
                            <span class="badge bg-success-subtle text-success">Aktif</span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary">Nonaktif</span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Tombol Simpan — menggunakan form="formEditCabang" --}}
            <div class="card">
                <div class="card-body p-4">
                    <button type="submit" form="formEditCabang" class="btn btn-primary w-100 mb-2">
                        <i class="bi bi-check-lg me-2"></i>Simpan Perubahan
                    </button>
                    <a href="{{ route('cabang.index') }}" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-x-lg me-2"></i>Batal
                    </a>
                </div>
            </div>

        </div>
    </div>
    {{-- ===== SHIFT KERJA ===== --}}
    <div class="row g-4 mt-0">
        <div class="col-12">
            <div class="card">
                <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between">
                    <span>
                        <i class="bi bi-clock me-2 text-primary"></i>
                        <span class="fw-semibold">Shift Kerja</span>
                    </span>
                    <span class="badge bg-primary-subtle text-primary">{{ $shifts->count() }} shift</span>
                </div>
                <div class="card-body p-0">
                    @if($shifts->isEmpty())
                    <div class="p-4 text-center text-muted" style="font-size:0.875rem">
                        <i class="bi bi-clock me-2"></i>Belum ada shift untuk cabang ini.
                        Shift akan dibuat otomatis saat pertama kali cabang dibuat.
                    </div>
                    @else
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="px-4" style="min-width:160px">Nama Shift</th>
                                    <th style="min-width:120px">Jam Masuk</th>
                                    <th style="min-width:120px">Jam Keluar</th>
                                    <th style="min-width:130px">Toleransi Telat</th>
                                    <th class="text-center" style="min-width:80px">Aktif</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($shifts as $i => $shift)
                                <tr>
                                    <input type="hidden" name="shifts[{{ $i }}][id]" value="{{ $shift->id }}">
                                    <td class="px-4">
                                        <input type="text" name="shifts[{{ $i }}][nama_shift]"
                                               class="form-control form-control-sm"
                                               value="{{ $shift->nama_shift }}" maxlength="50" required>
                                    </td>
                                    <td>
                                        <input type="time" name="shifts[{{ $i }}][jam_masuk]"
                                               class="form-control form-control-sm"
                                               value="{{ substr($shift->jam_masuk, 0, 5) }}" required>
                                    </td>
                                    <td>
                                        <input type="time" name="shifts[{{ $i }}][jam_keluar]"
                                               class="form-control form-control-sm"
                                               value="{{ substr($shift->jam_keluar, 0, 5) }}" required>
                                    </td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <input type="number" name="shifts[{{ $i }}][toleransi_telat_menit]"
                                                   class="form-control form-control-sm"
                                                   value="{{ $shift->toleransi_telat_menit }}"
                                                   min="0" max="120" style="max-width:70px">
                                            <span class="input-group-text">menit</span>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="form-check d-inline-block m-0">
                                            <input class="form-check-input" type="checkbox"
                                                   name="shifts[{{ $i }}][is_active]" value="1"
                                                   {{ $shift->is_active ? 'checked' : '' }}>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="px-4 py-2 border-top bg-light" style="font-size:0.76rem;color:#64748b">
                        <i class="bi bi-info-circle me-1"></i>
                        Perubahan shift akan disimpan bersama data cabang saat klik <strong>Simpan Perubahan</strong>.
                        Shift yang dinonaktifkan tidak muncul di dropdown karyawan.
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

</form>{{-- akhir formEditCabang --}}

{{-- ===== BARIS BAWAH: User & Danger Zone (TERPISAH dari formEditCabang) ===== --}}
<div class="row g-4 mt-2">

    {{-- Manajemen User --}}
    <div class="col-12 col-lg-8">
        <div class="card">
            <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between">
                <span>
                    <i class="bi bi-people me-2 text-primary"></i>
                    <span class="fw-semibold">User di Cabang Ini</span>
                </span>
                <span class="badge bg-primary-subtle text-primary">{{ $assignedUsers->count() }} user</span>
            </div>
            <div class="card-body p-4">

                {{-- Daftar user yang sudah assign --}}
                @if($assignedUsers->isEmpty())
                    <p class="text-muted text-center py-3 mb-3" style="font-size:0.875rem">
                        <i class="bi bi-people me-2"></i>Belum ada user yang di-assign ke cabang ini.
                    </p>
                @else
                <div class="d-flex flex-column gap-2 mb-4">
                    @foreach($assignedUsers as $user)
                    <div class="user-assign-item">
                        <div class="user-avatar">{{ substr($user->name, 0, 1) }}</div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-medium" style="font-size:0.875rem">{{ $user->name }}</div>
                            <div class="text-muted" style="font-size:0.75rem">
                                {{ $user->role?->label() }}
                                @if($user->pivot->is_default)
                                    <span class="badge bg-success-subtle text-success ms-1" style="font-size:0.65rem">Default</span>
                                @endif
                            </div>
                        </div>
                        {{-- form remove-user: form terpisah, di luar formEditCabang --}}
                        <form method="POST" action="{{ route('cabang.remove-user', $cabang) }}" class="flex-shrink-0">
                            @csrf @method('DELETE')
                            <input type="hidden" name="user_id" value="{{ $user->id }}">
                            <button type="submit" class="btn btn-sm btn-outline-danger px-2 py-1"
                                title="Hapus dari cabang"
                                onclick="return confirm('Hapus {{ addslashes($user->name) }} dari cabang ini?')"
                                @if($user->id === $cabang->kepala_cabang_id) disabled title="Kepala cabang tidak bisa dihapus dari sini" @endif>
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </form>
                    </div>
                    @endforeach
                </div>
                @endif

                {{-- Form tambah user: form terpisah, di luar formEditCabang --}}
                <form method="POST" action="{{ route('cabang.assign-user', $cabang) }}">
                    @csrf
                    <label class="form-label fw-medium" style="font-size:0.875rem">
                        <i class="bi bi-person-plus me-1"></i>Tambah User ke Cabang
                    </label>
                    <div class="d-flex gap-2">
                        <select name="user_id" class="form-select form-select-sm">
                            <option value="">— Pilih user —</option>
                            @foreach($users as $user)
                                @unless($assignedUsers->contains('id', $user->id))
                                <option value="{{ $user->id }}">
                                    {{ $user->name }} ({{ $user->role?->label() }})
                                </option>
                                @endunless
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm px-3 flex-shrink-0">
                            <i class="bi bi-plus-lg me-1"></i>Tambah
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>

    {{-- Danger Zone: form terpisah, di luar formEditCabang --}}
    <div class="col-12 col-lg-4">
        <div class="card border-danger-subtle">
            <div class="card-header py-3 px-4 bg-danger-subtle">
                <i class="bi bi-exclamation-triangle me-2 text-danger"></i>
                <span class="fw-semibold text-danger">Zona Berbahaya</span>
            </div>
            <div class="card-body p-4">
                <p class="text-muted mb-3" style="font-size:0.8rem">
                    Menghapus cabang bersifat permanen. Pastikan tidak ada karyawan aktif atau data transaksi.
                </p>
                @can('cabang.delete')
                <form method="POST" action="{{ route('cabang.destroy', $cabang) }}">
                    @csrf @method('DELETE')
                    <button type="submit"
                        class="btn btn-outline-danger w-100 btn-sm"
                        onclick="return confirm('HAPUS cabang {{ addslashes($cabang->nama_cabang) }}?\n\nTindakan ini TIDAK DAPAT DIBATALKAN.')">
                        <i class="bi bi-trash3 me-2"></i>Hapus Cabang Ini
                    </button>
                </form>
                @endcan
            </div>
        </div>
    </div>

</div>

{{-- ===== MODAL PETA LEAFLET ===== --}}
<div class="modal fade" id="modalPeta" tabindex="-1" aria-labelledby="modalPetaLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content" style="max-height:92vh;display:flex;flex-direction:column">
            <div class="modal-header py-2 px-3">
                <h5 class="modal-title d-flex align-items-center gap-2" id="modalPetaLabel">
                    <i class="bi bi-map text-primary"></i>Pilih Lokasi Cabang
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="px-3 py-2 border-bottom bg-light" style="position:relative">
                <div class="d-flex gap-2">
                    <div class="input-group input-group-sm flex-grow-1">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" id="mapSearch" class="form-control"
                               placeholder="Cari nama tempat, alamat...">
                    </div>
                    <button type="button" class="btn btn-primary btn-sm px-3 flex-shrink-0"
                            onclick="searchLocation()">Cari</button>
                </div>
                <div id="searchResults" class="list-group mt-1 shadow"
                     style="max-height:140px;overflow-y:auto;display:none;position:absolute;z-index:1050;left:1rem;right:1rem;top:100%"></div>
            </div>
            <div>
                <div id="leafletMap"></div>
            </div>
            <div class="px-3 py-2 border-top d-flex align-items-center gap-3 bg-light">
                <i class="bi bi-geo-alt-fill text-primary flex-shrink-0"></i>
                <div style="flex:1;min-width:0">
                    <div style="font-size:0.75rem;color:#64748b">Klik peta atau drag marker untuk pilih lokasi</div>
                    <div id="mapCoordDisplay" class="fw-semibold"
                         style="font-size:0.84rem;font-variant-numeric:tabular-nums">Belum dipilih</div>
                </div>
                <div style="font-size:0.75rem;color:#64748b;flex-shrink:0;text-align:right">
                    Radius:<br><strong id="mapRadiusDisplay">100</strong>m
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">
                    <i class="bi bi-x me-1"></i>Batal
                </button>
                <button type="button" class="btn btn-primary btn-sm" onclick="simpanLokasiPeta()">
                    <i class="bi bi-check-lg me-1"></i>Simpan Lokasi
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
document.getElementById('kode_cabang').addEventListener('input', function() {
    this.value = this.value.toUpperCase();
});

// ─── MAP VARIABLES ─────────────────────────────────────────────────────
let leafletMap = null, mapMarker = null, mapCircle = null;
let tempLat = null, tempLng = null;
const DEFAULT_LAT = 3.5952, DEFAULT_LNG = 98.6722;

// ─── OPEN MAP MODAL ────────────────────────────────────────────────────
// Listener is registered ONCE in DOMContentLoaded — not here — to avoid race condition
function bukaPeta() {
    console.log('[Peta] Opening modal');
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalPeta')).show();
}

function initLeafletMap(lat, lng) {
    console.log('[Peta] Init started lat=' + lat + ' lng=' + lng);
    const hasCoord  = lat !== null && !isNaN(lat);
    const centerLat = hasCoord ? lat : DEFAULT_LAT;
    const centerLng = hasCoord ? lng : DEFAULT_LNG;
    const zoom      = hasCoord ? 15 : 13;
    if (leafletMap) {
        leafletMap.setView([centerLat, centerLng], zoom);
        if (hasCoord) setMapMarker(lat, lng);
        setTimeout(function () {
            leafletMap.invalidateSize();
            console.log('[Peta] Size invalidated (re-open)');
        }, 300);
        return;
    }
    try {
        leafletMap = L.map('leafletMap').setView([centerLat, centerLng], zoom);
        console.log('[Peta] Map object created');
    } catch (e) {
        console.error('[Peta] Map init failed:', e);
        return;
    }
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19,
    }).addTo(leafletMap);
    console.log('[Peta] Tile layer added');
    leafletMap.on('click', function(e) { setMapMarker(e.latlng.lat, e.latlng.lng); });
    if (hasCoord) setMapMarker(lat, lng);
    setTimeout(function () {
        leafletMap.invalidateSize();
        console.log('[Peta] Size invalidated (first init)');
    }, 300);
}

function setMapMarker(lat, lng) {
    tempLat = lat; tempLng = lng;
    if (mapMarker) {
        mapMarker.setLatLng([lat, lng]);
    } else {
        mapMarker = L.marker([lat, lng], { draggable: true }).addTo(leafletMap);
        mapMarker.on('dragend', function() {
            const p = mapMarker.getLatLng();
            setMapMarker(p.lat, p.lng);
        });
    }
    updateMapCircle();
    document.getElementById('mapCoordDisplay').textContent = lat.toFixed(6) + ', ' + lng.toFixed(6);
}

function updateMapCircle() {
    const radius = parseInt(document.getElementById('radius_input')?.value) || 100;
    document.getElementById('mapRadiusDisplay').textContent = radius;
    if (mapCircle) leafletMap.removeLayer(mapCircle);
    if (tempLat !== null) {
        mapCircle = L.circle([tempLat, tempLng], {
            radius, color: '#2563eb', fillColor: '#3b82f6', fillOpacity: 0.12, weight: 2,
        }).addTo(leafletMap);
    }
}

function simpanLokasiPeta() {
    if (tempLat === null) { alert('Klik di peta untuk memilih lokasi terlebih dahulu.'); return; }
    document.getElementById('lat_input').value = tempLat.toFixed(8);
    document.getElementById('lng_input').value = tempLng.toFixed(8);
    updateKoordinatPreview(tempLat, tempLng);
    bootstrap.Modal.getInstance(document.getElementById('modalPeta')).hide();
}

// ─── NOMINATIM SEARCH ──────────────────────────────────────────────────
function searchLocation() {
    const query = document.getElementById('mapSearch').value.trim();
    const results = document.getElementById('searchResults');
    if (!query) return;
    results.innerHTML = '<div class="list-group-item py-2 small text-muted"><i class="bi bi-hourglass-split me-1"></i>Mencari...</div>';
    results.style.display = 'block';
    fetch('https://nominatim.openstreetmap.org/search?format=json&limit=5&accept-language=id&q=' + encodeURIComponent(query))
        .then(r => r.json())
        .then(data => {
            if (!data.length) {
                results.innerHTML = '<div class="list-group-item py-2 small text-muted">Tidak ada hasil.</div>';
                return;
            }
            results.innerHTML = data.map(item =>
                `<button type="button" class="list-group-item list-group-item-action py-2 px-3"
                         style="font-size:0.8rem;text-align:left" onclick="pilihHasilSearch(${item.lat},${item.lon})">
                    <i class="bi bi-geo-alt me-2 text-primary"></i>${item.display_name}
                 </button>`
            ).join('');
        })
        .catch(() => {
            results.innerHTML = '<div class="list-group-item py-2 small text-danger"><i class="bi bi-exclamation-circle me-1"></i>Gagal mencari.</div>';
        });
}

function pilihHasilSearch(lat, lng) {
    lat = parseFloat(lat); lng = parseFloat(lng);
    leafletMap.setView([lat, lng], 16);
    setMapMarker(lat, lng);
    document.getElementById('searchResults').style.display = 'none';
    document.getElementById('mapSearch').value = '';
}

// ─── GPS BROWSER ───────────────────────────────────────────────────────
function ambilLokasiGPS() {
    if (!navigator.geolocation) {
        alert('Browser Anda tidak mendukung geolocation.\n\nGunakan tombol "Pilih dari Peta" sebagai alternatif.');
        return;
    }
    const isSecure = location.protocol === 'https:' ||
                     location.hostname === 'localhost' ||
                     location.hostname === '127.0.0.1';
    if (!isSecure) {
        alert('Geolocation hanya bekerja di HTTPS atau localhost.\n\nAlternatif:\n• Tombol "Pilih dari Peta"\n• Isi koordinat manual dari Google Maps');
        return;
    }
    const btn = document.getElementById('btnGPS');
    const origHtml = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Mendeteksi...';
    btn.disabled  = true;
    navigator.geolocation.getCurrentPosition(
        (pos) => {
            const lat = pos.coords.latitude, lng = pos.coords.longitude;
            document.getElementById('lat_input').value = lat.toFixed(8);
            document.getElementById('lng_input').value = lng.toFixed(8);
            updateKoordinatPreview(lat, lng);
            btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Berhasil!';
            btn.classList.replace('btn-outline-primary', 'btn-success');
            setTimeout(() => {
                btn.innerHTML = origHtml;
                btn.disabled  = false;
                btn.classList.replace('btn-success', 'btn-outline-primary');
            }, 2500);
        },
        (err) => {
            btn.innerHTML = origHtml;
            btn.disabled  = false;
            alert(err.code === 1
                ? 'Izin GPS ditolak.\n\nAlternatif:\n• Klik "Pilih dari Peta"\n• Isi koordinat manual'
                : 'Gagal mendapatkan lokasi GPS: ' + err.message);
        },
        { enableHighAccuracy: true, timeout: 12000, maximumAge: 30000 }
    );
}

// ─── RADIUS VALIDATION ─────────────────────────────────────────────────
function validateRadius(input) {
    const val = parseInt(input.value), w = document.getElementById('radius_warning');
    if (!w) return;
    if (!val || val < 10) {
        w.textContent = '⚠️ Terlalu kecil — karyawan mungkin kesulitan absen.';
        w.style.color = '#dc3545'; w.style.display = 'block';
    } else if (val > 1000) {
        w.textContent = '⚠️ Terlalu besar — area absensi sangat luas, rawan penyalahgunaan.';
        w.style.color = '#d97706'; w.style.display = 'block';
    } else {
        w.style.display = 'none';
    }
    if (leafletMap && tempLat !== null) updateMapCircle();
}

// ─── KOORDINAT PREVIEW ─────────────────────────────────────────────────
function updateKoordinatPreview(lat, lng) {
    const el = document.getElementById('koordinatPreview');
    if (!el) return;
    el.innerHTML = '<span class="text-success"><i class="bi bi-geo-alt-fill me-1"></i>GPS tersetting: ' +
        parseFloat(lat).toFixed(6) + ', ' + parseFloat(lng).toFixed(6) + '</span>';
}

// ─── EVENT LISTENERS ───────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('mapSearch');
    if (searchInput) {
        searchInput.addEventListener('keydown', e => {
            if (e.key === 'Enter') { e.preventDefault(); searchLocation(); }
        });
    }
    document.addEventListener('click', e => {
        if (!e.target.closest('#mapSearch') && !e.target.closest('#searchResults')) {
            const r = document.getElementById('searchResults');
            if (r) r.style.display = 'none';
        }
    });
    const radiusInput = document.getElementById('radius_input');
    if (radiusInput && radiusInput.value) validateRadius(radiusInput);

    // Persistent shown.bs.modal listener — fires AFTER modal animation completes
    document.getElementById('modalPeta').addEventListener('shown.bs.modal', function () {
        console.log('[Peta] Modal shown event fired');
        const lat = parseFloat(document.getElementById('lat_input').value) || null;
        const lng = parseFloat(document.getElementById('lng_input').value) || null;
        // 300ms timeout lets the browser finish layout before Leaflet reads container size
        setTimeout(function () {
            if (!leafletMap) {
                initLeafletMap(lat, lng);
            } else {
                leafletMap.invalidateSize();
                console.log('[Peta] Size invalidated');
            }
        }, 300);
    });
    console.log('[Peta] Map listener registered');
});
</script>
@endpush
