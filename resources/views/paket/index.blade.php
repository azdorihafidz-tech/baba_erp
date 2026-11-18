@extends('layouts.app')

@section('title', 'Paket & Langganan')

@php
    use App\Support\Paket;
    $aktif = $data['modul'];
    $tgl = fn ($c) => $c ? $c->format('d/m/Y') : '—';
    $statusLabel = ['tanpa_batas' => ['success', 'Tanpa batas tanggal'], 'aktif' => ['success', 'Aktif'], 'segera' => ['warning', 'Segera berakhir'],
        'tenggang' => ['danger', 'Masa tenggang'], 'baca_saja' => ['dark', 'Baca-saja (langganan habis)']][$status['kode']];
    $presetJs = collect(Paket::PRESET)->map(fn ($p) => $p[1] ?? array_keys(Paket::MODUL));
    $butuhJs = collect(Paket::MODUL)->map(fn ($m) => $m[2]);
@endphp

@section('content')
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-box2-heart me-2 text-primary"></i>Paket & Langganan</h5>
    <x-panduan-button slug="paket-langganan" />
</div>

@unless($data['segel_valid'])
<div class="alert alert-danger py-2"><i class="bi bi-shield-exclamation me-1"></i><strong>Segel lisensi tidak cocok</strong> — isi paket diubah di luar aplikasi (langsung di database). Aplikasi tetap berjalan memakai isi yang ada. Periksa isinya lalu klik <strong>Simpan</strong> untuk memasang segel baru.</div>
@endunless

<div class="row g-2 mb-3">
    <div class="col-6 col-md-3"><div class="card card-body py-2"><div class="small text-muted">Paket</div><div class="fw-bold">{{ Paket::namaPaket() }}</div><div class="small text-muted">{{ count($aktif) }} dari {{ count(Paket::MODUL) }} modul</div></div></div>
    <div class="col-6 col-md-3"><div class="card card-body py-2"><div class="small text-muted">Status langganan</div><div><span class="badge bg-{{ $statusLabel[0] }}">{{ $statusLabel[1] }}</span></div></div></div>
    <div class="col-6 col-md-3"><div class="card card-body py-2"><div class="small text-muted">Aktif sampai</div><div class="fw-bold">{{ $tgl($status['aktif_sampai']) }}</div></div></div>
    <div class="col-6 col-md-3"><div class="card card-body py-2"><div class="small text-muted">Baca-saja mulai</div><div class="fw-bold">{{ $status['batas_tenggang'] ? $status['batas_tenggang']->copy()->addDay()->format('d/m/Y') : '—' }}</div></div></div>
</div>

<form method="POST" action="{{ route('paket.update') }}" class="card" id="formPaket">
    @csrf
    <div class="card-header py-2 d-flex flex-wrap gap-2 align-items-center">
        <span class="fw-semibold me-auto">Modul aktif <x-tooltip key="paket.modul" /></span>
        @foreach(Paket::PRESET as $kode => [$nama])
        <button type="button" class="btn btn-sm btn-outline-primary btn-preset" data-preset="{{ $kode }}">{{ $nama }}</button>
        @endforeach
    </div>
    <div class="card-body">
        <p class="small text-muted">Tombol paket hanya mengisi centangan cepat — setelah itu modul tetap bisa dinyalakan/dimatikan satu per satu. Modul yang dibutuhkan ikut menyala otomatis. Mematikan modul hanya menyembunyikan menunya; data tetap aman.</p>
        <div class="row g-2">
        @foreach(Paket::MODUL as $kode => [$label, $ket, $butuh])
            <div class="col-12 col-md-6">
                <label class="border rounded p-2 d-flex gap-2 h-100">
                    <input type="checkbox" class="form-check-input mt-1 cek-modul" name="modul[]" value="{{ $kode }}" @checked(in_array($kode, $aktif, true))>
                    <span>
                        <span class="fw-semibold">{{ $label }}</span>
                        <span class="d-block small text-muted">{{ $ket }}</span>
                        @if($butuh)<span class="d-block small text-muted">Butuh: {{ implode(', ', array_map(fn ($b) => Paket::MODUL[$b][0], $butuh)) }}</span>@endif
                    </span>
                </label>
            </div>
        @endforeach
        </div>
        <div class="small text-muted mt-2">Selalu aktif (inti): Dashboard, Cabang & Gudang (Pemegang Stok), Manajemen User, Role & Hak Akses, Pengaturan Umum, Jadwal Otomatis (Kirim RO tetap jalan), Kas & Transaksi + Kelola Kas, Kamus Menu, Panduan, Keamanan, Notifikasi, Tim IT.</div>

        <hr>
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <label class="form-label fw-semibold">Aktif sampai <x-tooltip key="paket.aktif_sampai" /></label>
                <input type="date" name="aktif_sampai" value="{{ old('aktif_sampai', $data['aktif_sampai']?->toDateString()) }}" class="form-control">
                <div class="form-text">Kosong = tanpa batas. Tanggal ini hari terakhir langganan berlaku. Peringatan muncul mulai {{ Paket::HARI_PERINGATAN }} hari sebelumnya.</div>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label fw-semibold">Masa tenggang (hari)</label>
                <input type="number" name="masa_tenggang" min="0" max="60" value="{{ old('masa_tenggang', $data['masa_tenggang']) }}" class="form-control" required>
                <div class="form-text">Setelah tanggal berakhir, aplikasi tetap normal selama masa ini (pita merah), lalu menjadi <strong>baca-saja</strong>.</div>
            </div>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="small text-muted">Terakhir diubah: {{ $data['diubah_at'] ? \Carbon\Carbon::parse($data['diubah_at'])->format('d/m/Y H:i') : '—' }}. Setiap perubahan tercatat di Aktivitas Tim IT.</span>
        <button class="btn btn-primary" onclick="return confirm('Simpan paket & langganan ini?')"><i class="bi bi-save me-1"></i>Simpan</button>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    const PRESET = @json($presetJs);
    const BUTUH = @json($butuhJs);
    const cek = () => [...document.querySelectorAll('.cek-modul')];
    function lengkapi() {
        let ubah = true;
        while (ubah) {
            ubah = false;
            cek().filter(c => c.checked).forEach(c => (BUTUH[c.value] || []).forEach(b => {
                const t = cek().find(x => x.value === b);
                if (t && !t.checked) { t.checked = true; ubah = true; }
            }));
        }
    }
    function lepas(kode) {
        // Mematikan modul → modul yang membutuhkannya ikut mati
        cek().filter(c => c.checked && (BUTUH[c.value] || []).includes(kode)).forEach(c => { c.checked = false; lepas(c.value); });
    }
    cek().forEach(c => c.addEventListener('change', () => { if (c.checked) lengkapi(); else lepas(c.value); }));
    document.querySelectorAll('.btn-preset').forEach(b => b.addEventListener('click', () => {
        const isi = PRESET[b.dataset.preset] || [];
        cek().forEach(c => c.checked = isi.includes(c.value));
        lengkapi();
    }));
})();
</script>
@endpush
