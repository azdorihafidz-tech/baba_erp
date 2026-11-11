@extends('layouts.app')

@section('title', 'Tambah Cabang')

@push('styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<style>
    #leafletMap { height: 60vh; min-height: 400px; width: 100%; background: #ddd; }
</style>
@endpush

@section('content')

{{-- PAGE HEADER --}}
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-0" style="color:#1e293b">
            <i class="bi bi-plus-circle me-2 text-primary"></i>Tambah Cabang
        </h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0" style="font-size:0.8rem">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('cabang.index') }}" class="text-decoration-none">Cabang</a></li>
                <li class="breadcrumb-item active">Tambah</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2">
        <x-panduan-button slug="cabang" />
        <a href="{{ route('cabang.index') }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
            <i class="bi bi-arrow-left"></i>
            <span class="d-none d-sm-inline">Kembali</span>
        </a>
    </div>
</div>

<form method="POST" action="{{ route('cabang.store') }}" id="formCabang">
    @csrf

    <div class="row g-4">
        {{-- ===== KOLOM KIRI (form utama) ===== --}}
        <div class="col-12 col-lg-8">
            <div class="card">
                <div class="card-header py-3 px-4">
                    <i class="bi bi-info-circle me-2 text-primary"></i>
                    <span class="fw-semibold">Informasi Cabang</span>
                </div>
                <div class="card-body p-4">

                    {{-- Nama & Kode - 2 kolom di md+ --}}
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-7">
                            <label for="nama_cabang" class="form-label fw-medium">
                                Nama Cabang <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="nama_cabang" name="nama_cabang"
                                class="form-control @error('nama_cabang') is-invalid @enderror"
                                value="{{ old('nama_cabang') }}"
                                placeholder="contoh: Cabang Pusat Kota"
                                maxlength="100" autofocus>
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
                                value="{{ old('kode_cabang') }}"
                                placeholder="cth: CA001" maxlength="10"
                                style="letter-spacing:0.1em">
                            @error('kode_cabang')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Maksimal 10 karakter, huruf dan angka saja.</div>
                        </div>
                    </div>

                    {{-- Tipe & Telepon --}}
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label for="tipe" class="form-label fw-medium">
                                Tipe <span class="text-danger">*</span>
                            </label>
                            <select id="tipe" name="tipe"
                                class="form-select @error('tipe') is-invalid @enderror">
                                <option value="">— Pilih Tipe —</option>
                                @foreach($tipes as $tipe)
                                    <option value="{{ $tipe->value }}"
                                        {{ old('tipe') === $tipe->value ? 'selected' : '' }}>
                                        {{ $tipe->icon() }} {{ $tipe->label() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('tipe')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            {{-- BABA Tahap 3: jenis toko untuk cabang penjualan --}}
                            <label for="jenis_toko" class="form-label fw-medium mt-2">Jenis toko <x-tooltip key="cabang.jenis_toko" /></label>
                            <select id="jenis_toko" name="jenis_toko" class="form-select @error('jenis_toko') is-invalid @enderror">
                                <option value="">— bukan toko (gudang / kantor) —</option>
                                @foreach(\App\Models\Cabang::JENIS_TOKO as $k => $v)
                                    <option value="{{ $k }}" @selected(old('jenis_toko') === $k)>{{ $v }}</option>
                                @endforeach
                            </select>
                            @error('jenis_toko')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="telepon" class="form-label fw-medium">Telepon</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                <input type="text" id="telepon" name="telepon"
                                    class="form-control @error('telepon') is-invalid @enderror"
                                    value="{{ old('telepon') }}"
                                    placeholder="021-XXXXXXX" maxlength="20">
                                @error('telepon')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Alamat --}}
                    <div class="mb-3">
                        <label for="alamat" class="form-label fw-medium">Alamat</label>
                        <textarea id="alamat" name="alamat" rows="3"
                            class="form-control @error('alamat') is-invalid @enderror"
                            placeholder="Jl. Contoh No. 1, Kota...">{{ old('alamat') }}</textarea>
                        @error('alamat')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
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
                                {{ old('antrian_produksi_aktif', '0') ? 'checked' : '' }}>
                            <label class="form-check-label" for="antrianAktif">
                                Aktifkan sistem antrian produksi untuk cabang ini
                            </label>
                        </div>
                        <div class="form-text mt-1">
                            Jika aktif, setiap order baru di POS akan mendapat nomor antrian otomatis.
                            Default: aktif. Bisa dimatikan per cabang jika tidak diperlukan.
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
                                {{ old('running_text_aktif') ? 'checked' : '' }}>
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
                            placeholder="Contoh: Promo hari ini diskon 10% untuk order 5kg!">{{ old('running_text') }}</textarea>
                        <div class="form-text">Teks akan berjalan di bagian bawah layar TV Display antrian.</div>
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
                                {{ old('izinkan_sembunyi_harga_struk') ? 'checked' : '' }}>
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
                            placeholder="Contoh:&#10;Terima kasih sudah berbelanja!&#10;{{ $brand->namaSingkat() }} Cabang Pusat Kota">{{ old('footer_struk') }}</textarea>
                        <div class="form-text">
                            Pesan ini muncul di bagian bawah struk POS cabang ini.
                            Multi-baris diperbolehkan. Kosongkan untuk pakai teks default.
                        </div>
                        @error('footer_struk')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
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
                                    <input class="form-check-input" type="checkbox" role="switch" id="dineInAktif" name="dine_in_aktif" value="1" {{ old('dine_in_aktif', '1') ? 'checked' : '' }}>
                                    <label class="form-check-label small" for="dineInAktif">Dine-in</label>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="takeaway_aktif" value="0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="takeawayAktif" name="takeaway_aktif" value="1" {{ old('takeaway_aktif', '1') ? 'checked' : '' }}>
                                    <label class="form-check-label small" for="takeawayAktif">Takeaway</label>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="frozen_aktif" value="0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="frozenAktif" name="frozen_aktif" value="1" {{ old('frozen_aktif', '1') ? 'checked' : '' }}>
                                    <label class="form-check-label small" for="frozenAktif">Frozen</label>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="nomor_meja_aktif" value="0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="nomorMejaAktif" name="nomor_meja_aktif" value="1" {{ old('nomor_meja_aktif', '1') ? 'checked' : '' }}>
                                    <label class="form-check-label small" for="nomorMejaAktif">Input No. Meja</label>
                                </div>
                            </div>
                        </div>
                        <div class="row g-2 mb-2 align-items-end">
                            <div class="col-12 col-md-6">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="pilih_tipe_transaksi" value="0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="pilihTipeTransaksi" name="pilih_tipe_transaksi" value="1" {{ old('pilih_tipe_transaksi', '1') ? 'checked' : '' }}>
                                    <label class="form-check-label small" for="pilihTipeTransaksi">Tampilkan pilihan Tipe Transaksi di POS <x-tooltip key="cabang.pilih_tipe_transaksi" /></label>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-medium mb-1" for="tipeTransaksiDefault">Tipe bawaan (dipakai saat pilihan disembunyikan)</label>
                                <select name="tipe_transaksi_default" id="tipeTransaksiDefault" class="form-select form-select-sm">
                                    @foreach(\App\Enums\TipeTransaksi::cases() as $t)
                                        <option value="{{ $t->value }}" @selected(old('tipe_transaksi_default', 'takeaway') === $t->value)>{{ $t->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <x-input-rupiah name="take_away_fee" label="Takeaway Fee" :value="old('take_away_fee', 0)" />
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-medium mb-1">Service Charge (%)</label>
                                <input type="number" step="0.01" min="0" max="100" class="form-control form-control-sm"
                                    name="service_charge_persen" value="{{ old('service_charge_persen', 0) }}">
                            </div>
                        </div>
                        <div class="row g-2 mt-1">
                            <div class="col-12 small fw-medium">Wajib upload bukti pembayaran di POS <x-tooltip key="cabang.wajib_bukti" /></div>
                            <div class="col-6">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="wajib_bukti_transfer" value="0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="wajibBuktiTransfer" name="wajib_bukti_transfer" value="1" {{ old('wajib_bukti_transfer', true) ? 'checked' : '' }}>
                                    <label class="form-check-label small" for="wajibBuktiTransfer">Transfer</label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="wajib_bukti_qris" value="0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="wajibBuktiQris" name="wajib_bukti_qris" value="1" {{ old('wajib_bukti_qris', true) ? 'checked' : '' }}>
                                    <label class="form-check-label small" for="wajibBuktiQris">QRIS</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 4.38-F — salin harga & pengaturan barang dari cabang lain --}}
                    <div class="mb-3">
                        <label for="salin_dari_cabang_id" class="form-label fw-medium">
                            Salin Harga &amp; Pengaturan Barang dari <x-tooltip key="cabang.salin_pengaturan" />
                        </label>
                        <select id="salin_dari_cabang_id" name="salin_dari_cabang_id" class="form-select @error('salin_dari_cabang_id') is-invalid @enderror">
                            <option value="">— Tidak usah (mulai kosong) —</option>
                            @foreach($cabangSumber as $cs)
                                <option value="{{ $cs->id }}" @selected(old('salin_dari_cabang_id') == $cs->id)>{{ $cs->nama_cabang }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Semua bahan &amp; kemasan, produk jual (harga, resep, varian), Bumbu Pusat, dan Qty Minimum cabang itu ikut disalin ke cabang baru. Stok &amp; transaksi tidak ikut disalin.</div>
                        @error('salin_dari_cabang_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    {{-- Kepala Cabang --}}
                    <div class="mb-0">
                        <label for="kepala_cabang_id" class="form-label fw-medium">
                            Kepala Cabang / Penanggung Jawab
                        </label>
                        <select id="kepala_cabang_id" name="kepala_cabang_id"
                            class="form-select @error('kepala_cabang_id') is-invalid @enderror">
                            <option value="">— Pilih User (opsional) —</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}"
                                    {{ old('kepala_cabang_id') == $user->id ? 'selected' : '' }}>
                                    {{ $user->name }} ({{ $user->role?->label() }})
                                </option>
                            @endforeach
                        </select>
                        @error('kepala_cabang_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">User yang dipilih akan otomatis di-assign ke cabang ini.</div>
                    </div>

                    {{-- ===== LOKASI & GPS ABSENSI ===== --}}
                    <div class="border border-primary-subtle rounded-3 p-3 mt-3" id="gps" style="background:#f0f9ff">
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
                                       value="{{ old('latitude') }}" placeholder="contoh: 3.595200">
                                @error('latitude')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label small mb-1 fw-medium">Longitude</label>
                                <input type="number" step="0.00000001" min="-180" max="180"
                                       name="longitude" id="lng_input"
                                       class="form-control form-control-sm @error('longitude') is-invalid @enderror"
                                       value="{{ old('longitude') }}" placeholder="contoh: 98.672200">
                                @error('longitude')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-12 col-sm-6">
                                <label class="form-label small mb-1 fw-medium">Radius Absensi (meter)</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="radius_absen_meter" id="radius_input"
                                           min="10" max="5000" class="form-control"
                                           value="{{ old('radius_absen_meter', 100) }}"
                                           oninput="validateRadius(this)">
                                    <span class="input-group-text">m</span>
                                </div>
                                <div id="radius_warning" class="form-text" style="display:none;font-size:0.75rem"></div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label small mb-1 fw-medium">Jam Masuk Kerja</label>
                                <input type="time" name="jam_masuk" class="form-control form-control-sm"
                                       value="{{ old('jam_masuk', '08:00') }}">
                            </div>
                        </div>
                        <div id="koordinatPreview" style="font-size:0.76rem">
                            <span class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>GPS belum diset. Biarkan kosong untuk menonaktifkan validasi lokasi.
                            </span>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        {{-- ===== KOLOM KANAN (info & tombol) ===== --}}
        <div class="col-12 col-lg-4">

            {{-- Panduan tipe --}}
            <div class="card mb-3">
                <div class="card-header py-3 px-4">
                    <i class="bi bi-question-circle me-2 text-info"></i>
                    <span class="fw-semibold">Panduan Tipe Lokasi</span>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3 pb-3 border-bottom">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="bi bi-building text-warning"></i>
                            <span class="fw-semibold" style="font-size:0.875rem">Gudang Pusat</span>
                        </div>
                        <p class="text-muted mb-0" style="font-size:0.8rem">
                            Lokasi penyimpanan utama. Semua pembelian dari vendor masuk ke sini, lalu didistribusikan ke cabang.
                        </p>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="bi bi-shop text-primary"></i>
                            <span class="fw-semibold" style="font-size:0.875rem">Cabang</span>
                        </div>
                        <p class="text-muted mb-0" style="font-size:0.8rem">
                            Toko/outlet penjualan. Menerima stok dari gudang pusat dan melayani pelanggan langsung.
                        </p>
                    </div>
                </div>
            </div>

            {{-- Info shift otomatis --}}
            <div class="card mb-3">
                <div class="card-header py-3 px-4">
                    <i class="bi bi-clock me-2 text-primary"></i>
                    <span class="fw-semibold">Shift Kerja</span>
                </div>
                <div class="card-body p-4">
                    <p class="text-muted mb-2" style="font-size:0.8rem">
                        Setelah cabang disimpan, sistem akan otomatis membuat <strong>3 shift default</strong>:
                    </p>
                    <div class="d-flex flex-column gap-1" style="font-size:0.8rem">
                        <div class="d-flex justify-content-between">
                            <span><i class="bi bi-sun me-1 text-warning"></i>Shift 1 (Pagi)</span>
                            <span class="text-muted">08:00 – 16:00</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span><i class="bi bi-sunset me-1 text-orange" style="color:#f97316"></i>Shift 2 (Sore)</span>
                            <span class="text-muted">16:00 – 00:00</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span><i class="bi bi-moon me-1 text-primary"></i>Shift 3 (Malam)</span>
                            <span class="text-muted">00:00 – 08:00</span>
                        </div>
                    </div>
                    <small class="text-muted d-block mt-2">
                        Shift bisa diubah di halaman edit cabang setelah disimpan.
                    </small>
                </div>
            </div>

            {{-- Tombol aksi --}}
            <div class="card">
                <div class="card-body p-4">
                    <button type="submit" class="btn btn-primary w-100 mb-2">
                        <i class="bi bi-check-lg me-2"></i>Simpan Cabang
                    </button>
                    <a href="{{ route('cabang.index') }}" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-x-lg me-2"></i>Batal
                    </a>
                </div>
            </div>

        </div>
    </div>

</form>

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

    // Persistent shown.bs.modal listener — fires AFTER modal animation completes
    document.getElementById('modalPeta').addEventListener('shown.bs.modal', function () {
        console.log('[Peta] Modal shown event fired');
        const lat = parseFloat(document.getElementById('lat_input').value) || null;
        const lng = parseFloat(document.getElementById('lng_input').value) || null;
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
