@extends('layouts.app')

@section('title', 'Pengaturan Umum')

@section('content')

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-0" style="color:#1e293b">
            <i class="bi bi-building me-2 text-primary"></i>Pengaturan Umum
        </h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0" style="font-size:0.8rem">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active">Pengaturan Umum</li>
            </ol>
        </nav>
    </div>
    <x-panduan-button slug="pengaturan-umum" />
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
    <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<form method="POST" action="{{ route('pengaturan.umum.update') }}" enctype="multipart/form-data" id="formPengaturanUmum">
    @csrf @method('PUT')

    <div class="row g-4">
        {{-- Kolom Kiri --}}
        <div class="col-12 col-lg-8">
            <div class="card mb-4">
                <div class="card-header py-3 px-4">
                    <i class="bi bi-info-circle me-2 text-primary"></i>
                    <span class="fw-semibold">Identitas Usaha</span>
                </div>
                <div class="card-body p-4">

                    <div class="mb-3">
                        <label for="nama_perusahaan" class="form-label fw-medium">
                            Nama Usaha <span class="text-danger">*</span>
                        </label>
                        <input type="text" id="nama_perusahaan" name="nama_perusahaan"
                            class="form-control @error('nama_perusahaan') is-invalid @enderror"
                            value="{{ old('nama_perusahaan', $setting->nama_perusahaan) }}"
                            maxlength="100" required>
                        <div class="form-text">Nama lengkap usaha — tampil di TV Display Antrian dan aplikasi kasir.</div>
                        @error('nama_perusahaan')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-5">
                            <label for="nama_singkat" class="form-label fw-medium">
                                Nama Singkat <x-tooltip key="pengaturan_umum.nama_singkat" placement="top" />
                            </label>
                            <input type="text" id="nama_singkat" name="nama_singkat"
                                class="form-control @error('nama_singkat') is-invalid @enderror"
                                value="{{ old('nama_singkat', $setting->nama_singkat) }}"
                                maxlength="30" placeholder="Kosong = pakai Nama Usaha">
                            @error('nama_singkat')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-7">
                            <label for="tagline" class="form-label fw-medium">
                                Tagline <x-tooltip key="pengaturan_umum.tagline" placement="top" />
                            </label>
                            <input type="text" id="tagline" name="tagline"
                                class="form-control @error('tagline') is-invalid @enderror"
                                value="{{ old('tagline', $setting->tagline) }}"
                                maxlength="150" placeholder="Contoh: Dimsum & Gyoza">
                            @error('tagline')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="alamat_perusahaan" class="form-label fw-medium">Alamat</label>
                        <textarea id="alamat_perusahaan" name="alamat_perusahaan" rows="3"
                            class="form-control @error('alamat_perusahaan') is-invalid @enderror"
                            maxlength="1000"
                            placeholder="Jl. Contoh No. 1, Kota...">{{ old('alamat_perusahaan', $setting->alamat_perusahaan) }}</textarea>
                        @error('alamat_perusahaan')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-0">
                        <label for="telepon" class="form-label fw-medium">No. Telepon / WhatsApp</label>
                        <input type="text" id="telepon" name="telepon"
                            class="form-control @error('telepon') is-invalid @enderror"
                            value="{{ old('telepon', $setting->telepon) }}"
                            maxlength="30" placeholder="Contoh: 0812-3456-7890">
                        <div class="form-text">Tampil di header laporan PDF (kosong = tidak ditampilkan).</div>
                        @error('telepon')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mt-3 mb-0">
                        <label for="tanggal_mulai_periode" class="form-label fw-medium">
                            Tanggal Mulai Periode Bulanan (Closing)
                            <x-tooltip key="pengaturan_umum.tanggal_mulai_periode" />
                        </label>
                        @php $tglMulai = (int) old('tanggal_mulai_periode', $setting->tanggal_mulai_periode ?? 1); @endphp
                        <select id="tanggal_mulai_periode" name="tanggal_mulai_periode"
                            class="form-select @error('tanggal_mulai_periode') is-invalid @enderror" style="max-width:220px">
                            @for($d = 1; $d <= 28; $d++)
                            <option value="{{ $d }}" @selected($tglMulai === $d)>Tanggal {{ $d }}</option>
                            @endfor
                        </select>
                        <div class="form-text">
                            Dipakai menu Closing / Tutup Buku. Contoh: pilih <strong>26</strong> = periode "Oktober" adalah
                            26 September s/d 25 Oktober. Pilih <strong>1</strong> = bulan kalender biasa.
                        </div>
                        @error('tanggal_mulai_periode')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                </div>
            </div>

            <div class="card">
                <div class="card-header py-3 px-4">
                    <i class="bi bi-image me-2 text-primary"></i>
                    <span class="fw-semibold">Logo Usaha</span>
                    <x-tooltip key="pengaturan_umum.logo" placement="top" />
                </div>
                <div class="card-body p-4">

                    <div class="mb-3 p-3 border rounded-3 bg-light d-flex align-items-center gap-3 flex-wrap">
                        <img src="{{ $brand->logoUrl() }}" alt="Logo saat ini" id="logoPreview"
                             style="max-height:80px;max-width:200px;object-fit:contain;">
                        <img src="{{ $brand->faviconUrl() }}" alt="Ikon aplikasi"
                             style="width:48px;height:48px;object-fit:contain;border-radius:10px;background:#fff;border:1px solid #e2e8f0">
                        <div>
                            @if($brand->adaLogoUpload())
                                <div class="fw-medium" style="font-size:0.875rem">Logo aktif</div>
                                <div class="text-muted" style="font-size:0.75rem">Kiri: logo · Kanan: ikon tab browser &amp; aplikasi</div>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="hapus_logo" id="hapusLogo" value="1">
                                    <label class="form-check-label text-danger" for="hapusLogo" style="font-size:0.8rem">
                                        Hapus logo ini (kembali ke logo bawaan)
                                    </label>
                                </div>
                            @else
                                <div class="fw-medium" style="font-size:0.875rem">Logo bawaan program</div>
                                <div class="text-muted" style="font-size:0.75rem">Upload logo usaha Anda supaya tampil di semua halaman.</div>
                            @endif
                        </div>
                    </div>

                    <div>
                        <label for="logo" class="form-label fw-medium">
                            {{ $brand->adaLogoUpload() ? 'Ganti Logo' : 'Upload Logo' }}
                        </label>
                        <input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp"
                            class="form-control @error('logo') is-invalid @enderror"
                            onchange="previewLogo(this)">
                        <div class="form-text">Format: PNG, JPG, WebP. Maksimal 2MB. Paling bagus: PNG latar transparan, bentuk persegi atau bulat. Ikon tab browser &amp; aplikasi dibuat otomatis dari logo ini.</div>
                        @error('logo')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                </div>
            </div>

            {{-- 4.48 — Warna Aplikasi --}}
            @php
                $warnaForm = [
                    'utama'   => ['label' => 'Warna Utama', 'ket' => 'Menu aktif, sorotan, grafik, judul laporan PDF'],
                    'sidebar' => ['label' => 'Warna Sidebar', 'ket' => 'Latar menu di kiri & halaman login'],
                    'latar'   => ['label' => 'Warna Latar Halaman', 'ket' => 'Latar di belakang kartu & header tabel Excel/PDF (harus warna terang)'],
                ];
            @endphp
            <div class="card mt-4" id="kartuWarna">
                <div class="card-header py-3 px-4 d-flex align-items-center flex-wrap gap-2">
                    <i class="bi bi-palette me-1 text-primary"></i>
                    <span class="fw-semibold">Warna Aplikasi</span>
                    <x-tooltip key="pengaturan_umum.warna" placement="top" />
                    <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" id="btnWarnaBawaan" onclick="pakaiTema('bawaan')">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Kembalikan ke warna bawaan
                    </button>
                </div>
                <div class="card-body p-4">
                    <div class="fw-medium mb-2" style="font-size:.875rem">Tema siap pakai</div>
                    <div class="d-flex flex-wrap gap-2 mb-4" id="pilihanTema">
                        @foreach(\App\Support\Branding::TEMA as $kunci => $tema)
                        <button type="button" class="btn btn-sm btn-light border d-flex align-items-center gap-2 tema-btn"
                                data-tema="{{ $kunci }}" onclick="pakaiTema('{{ $kunci }}')">
                            <span class="d-inline-flex rounded overflow-hidden border" style="width:42px;height:18px">
                                <span style="flex:1;background:{{ $tema['sidebar'] }}"></span>
                                <span style="flex:1;background:{{ $tema['utama'] }}"></span>
                                <span style="flex:1;background:{{ $tema['latar'] }}"></span>
                            </span>
                            {{ $tema['label'] }}
                        </button>
                        @endforeach
                    </div>

                    <div class="row g-3 align-items-start">
                        <div class="col-md-7">
                            @foreach($warnaForm as $jenis => $w)
                            @php $nilai = old('warna_' . $jenis, $brand->warna($jenis)); @endphp
                            <div class="mb-3">
                                <label for="warna_{{ $jenis }}" class="form-label fw-medium mb-1">{{ $w['label'] }}</label>
                                <div class="d-flex align-items-center gap-2">
                                    <input type="color" class="form-control form-control-color warna-pilih" id="warna_{{ $jenis }}_pilih"
                                           data-jenis="{{ $jenis }}" value="{{ strtolower($nilai) }}" title="Pilih warna">
                                    <input type="text" id="warna_{{ $jenis }}" name="warna_{{ $jenis }}" maxlength="7"
                                           class="form-control warna-hex @error('warna_' . $jenis) is-invalid @enderror" style="max-width:120px"
                                           data-jenis="{{ $jenis }}" value="{{ strtoupper($nilai) }}" placeholder="#RRGGBB">
                                </div>
                                <div class="form-text">{{ $w['ket'] }}</div>
                                @error('warna_' . $jenis)
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                            @endforeach
                        </div>
                        <div class="col-md-5">
                            <div class="fw-medium mb-2" style="font-size:.875rem">Pratinjau</div>
                            <div class="border rounded-3 overflow-hidden d-flex" id="pratinjauWarna" style="height:170px;font-size:.72rem">
                                <div class="pw-sidebar d-flex flex-column py-2" style="width:42%">
                                    <div class="px-2 mb-2 fw-bold pw-teks-sidebar">{{ $brand->namaSingkat() }}</div>
                                    <div class="px-2 py-1 pw-teks-sidebar" style="opacity:.7">Dashboard</div>
                                    <div class="px-2 py-1 pw-aktif">POS - Kasir</div>
                                    <div class="px-2 py-1 pw-teks-sidebar" style="opacity:.7">Laporan</div>
                                </div>
                                <div class="pw-latar flex-grow-1 p-2">
                                    <div class="bg-white rounded border p-2 mb-2">
                                        <div class="text-muted">Pendapatan</div>
                                        <div class="fw-bold pw-teks-utama" style="font-size:.95rem">Rp 2.042.000</div>
                                    </div>
                                    <div class="d-flex align-items-end gap-1" style="height:46px">
                                        @foreach([40, 70, 55, 90, 65] as $t)
                                        <div class="pw-batang rounded-top" style="flex:1;height:{{ $t }}%"></div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            <div class="form-text">Tampilan seluruh aplikasi ikut berubah setelah <strong>Simpan Pengaturan</strong>. Warna tombol (Simpan, Hapus, dll) tidak berubah.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Kolom Kanan --}}
        <div class="col-12 col-lg-4">

            <div class="card mb-3">
                <div class="card-header py-3 px-4">
                    <i class="bi bi-info-circle me-2 text-muted"></i>
                    <span class="fw-semibold">Dipakai di mana?</span>
                </div>
                <div class="card-body p-4" style="font-size:0.8rem">
                    <p class="text-muted mb-2">
                        <i class="bi bi-box-arrow-in-right me-1 text-primary"></i>
                        <strong>Login, sidebar &amp; judul tab</strong> — logo, nama singkat &amp; tagline.
                    </p>
                    <p class="text-muted mb-2">
                        <i class="bi bi-printer me-1 text-primary"></i>
                        <strong>Struk &amp; laporan PDF</strong> — nama singkat &amp; tagline (footer struk per cabang diatur di Cabang → Edit).
                    </p>
                    <p class="text-muted mb-2">
                        <i class="bi bi-tv me-1 text-primary"></i>
                        <strong>TV Display Antrian</strong> — logo &amp; nama usaha.
                    </p>
                    <p class="text-muted mb-2">
                        <i class="bi bi-palette me-1 text-primary"></i>
                        <strong>Warna Aplikasi</strong> — sidebar, menu aktif, latar halaman, login, grafik, judul &amp; header tabel laporan PDF/Excel. Berlaku untuk semua cabang.
                    </p>
                    <p class="text-muted mb-0">
                        <i class="bi bi-phone me-1 text-primary"></i>
                        <strong>Ikon aplikasi (PWA) &amp; aplikasi kasir</strong> — ikon dibuat otomatis dari logo.
                    </p>
                </div>
            </div>

            <div class="card">
                <div class="card-body p-4">
                    <button type="submit" class="btn btn-primary w-100 mb-2">
                        <i class="bi bi-check-lg me-2"></i>Simpan Pengaturan
                    </button>
                    <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-x-lg me-2"></i>Batal
                    </a>
                </div>
            </div>

        </div>
    </div>

</form>

@endsection

@push('scripts')
<script>
// 4.48 — Warna Aplikasi: tema, pemilih warna, pratinjau (aturan teks sama dgn App\Support\Branding)
const TEMA_WARNA = @json(\App\Support\Branding::TEMA);
function hexValid(v) { v = String(v || '').trim().toUpperCase(); if (/^[0-9A-F]{6}$/.test(v)) v = '#' + v; return /^#[0-9A-F]{6}$/.test(v) ? v : null; }
function luminansi(hex) {
    return [1, 3, 5].map(i => parseInt(hex.substr(i, 2), 16) / 255)
        .map(c => c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4))
        .reduce((t, c, i) => t + c * [0.2126, 0.7152, 0.0722][i], 0);
}
function teksDiAtas(hex) { return 1.05 / (luminansi(hex) + 0.05) >= 2.5 ? '#FFFFFF' : '#1A1A1A'; }
function nilaiWarna(jenis) { return hexValid(document.getElementById('warna_' + jenis).value); }
function setWarna(jenis, hex) {
    document.getElementById('warna_' + jenis).value = hex;
    document.getElementById('warna_' + jenis + '_pilih').value = hex.toLowerCase();
}
function perbaruiPratinjau() {
    const u = nilaiWarna('utama'), s = nilaiWarna('sidebar'), l = nilaiWarna('latar');
    const kotak = document.getElementById('pratinjauWarna');
    if (!kotak) return;
    if (s) { kotak.querySelector('.pw-sidebar').style.background = s; kotak.querySelectorAll('.pw-teks-sidebar').forEach(e => e.style.color = teksDiAtas(s)); }
    if (u) {
        const aktif = kotak.querySelector('.pw-aktif'); aktif.style.background = u; aktif.style.color = teksDiAtas(u);
        kotak.querySelectorAll('.pw-teks-utama').forEach(e => e.style.color = u);
        kotak.querySelectorAll('.pw-batang').forEach(e => e.style.background = u);
    }
    if (l) kotak.querySelector('.pw-latar').style.background = l;
    const peringatan = l && teksDiAtas(l) === '#FFFFFF';
    document.getElementById('warna_latar').classList.toggle('is-invalid', !!peringatan);
    document.querySelectorAll('.tema-btn').forEach(b => {
        const t = TEMA_WARNA[b.dataset.tema];
        b.classList.toggle('border-primary', t.utama === u && t.sidebar === s && t.latar === l);
    });
}
function pakaiTema(kunci) {
    const t = TEMA_WARNA[kunci];
    if (!t) return;
    ['utama', 'sidebar', 'latar'].forEach(j => setWarna(j, t[j]));
    perbaruiPratinjau();
}
document.querySelectorAll('.warna-pilih').forEach(el => el.addEventListener('input', function () {
    document.getElementById('warna_' + this.dataset.jenis).value = this.value.toUpperCase();
    perbaruiPratinjau();
}));
document.querySelectorAll('.warna-hex').forEach(el => el.addEventListener('input', function () {
    const hex = hexValid(this.value);
    if (hex) document.getElementById('warna_' + this.dataset.jenis + '_pilih').value = hex.toLowerCase();
    perbaruiPratinjau();
}));
perbaruiPratinjau();

function previewLogo(input) {
    const preview = document.getElementById('logoPreview');
    if (input.files && input.files[0] && preview) {
        const reader = new FileReader();
        reader.onload = function (e) { preview.src = e.target.result; };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
@endpush
