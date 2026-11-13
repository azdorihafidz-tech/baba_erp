@extends('layouts.app')

@section('title', 'Jadwal Otomatis')

@push('styles')
<style>
.jo-baris { border-bottom: 1px solid #eee; padding: .6rem .25rem; }
.jo-kode { font-family: ui-monospace, monospace; font-size: .78rem; word-break: break-all; background: #f8f9fa; border: 1px solid #e5e7eb; border-radius: 6px; padding: .5rem; }
</style>
@endpush

@section('content')
@php $ukuran = fn ($b) => $b >= 1048576 ? number_format($b / 1048576, 1, ',', '.') . ' MB' : number_format($b / 1024, 0, ',', '.') . ' KB'; @endphp
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2 text-primary"></i>Jadwal Otomatis</h5>
    <x-panduan-button slug="jadwal-otomatis" />
</div>

{{-- 1. Status cron --}}
<div class="card mb-3 border-{{ $cronHidup ? 'success' : 'danger' }}" data-uji="status-cron"><div class="card-body p-3">
    @if($cronHidup)
    <div class="fw-semibold text-success"><i class="bi bi-check-circle-fill me-1"></i>Cron berjalan</div>
    <div class="small text-muted">Terakhir {{ $cronTerakhir->locale('id')->diffForHumans() }} ({{ $cronTerakhir->format('d/m/Y H:i:s') }}).</div>
    @else
    <div class="fw-semibold text-danger"><i class="bi bi-exclamation-octagon-fill me-1"></i>Cron {{ $cronTerakhir ? 'berhenti' : 'belum terpasang' }}</div>
    <div class="small">@if($cronTerakhir)Terakhir berjalan {{ $cronTerakhir->locale('id')->diffForHumans() }}.@else Belum pernah berjalan.@endif
        Tanpa cron, tugas di bawah <strong>tidak berjalan</strong> (RO belanja online tidak terkirim ke aplikasi member).</div>
    <div class="small mt-2">Pasang di cPanel › <strong>Cron Jobs</strong> › Common Settings <strong>Once Per Minute (* * * * *)</strong> › Command:</div>
    @endif
    <div class="jo-kode mt-1" data-uji="baris-jadwal">{{ $baris['jadwal'] }}</div>
    <button type="button" class="btn btn-sm btn-outline-secondary mt-1 jo-salin" data-teks="{{ $baris['jadwal'] }}"><i class="bi bi-clipboard me-1"></i>Salin</button>
</div></div>

{{-- 2. Tugas --}}
<form method="POST" action="{{ route('pengaturan.jadwal.simpan') }}" class="card mb-3" id="formJadwal">@csrf @method('PUT')
<div class="card-header py-2 fw-semibold small">Tugas otomatis</div>
<div class="card-body p-2">
@foreach(\App\Services\JadwalOtomatisService::TUGAS as $kode => $t)
@php $r = $tugas[$kode]; @endphp
<div class="jo-baris" data-uji="tugas-{{ $kode }}">
    <div class="d-flex align-items-start gap-2">
        <div class="form-check form-switch mt-1">
            <input class="form-check-input" type="checkbox" name="aktif[{{ $kode }}]" value="1" id="aktif_{{ $kode }}" @checked($t['wajib'] || $r->aktif) @disabled($t['wajib']) data-uji="saklar-{{ $kode }}">
        </div>
        <div class="flex-grow-1 min-w-0">
            <label class="fw-semibold" for="aktif_{{ $kode }}">{{ $t['label'] }}</label>
            @if($t['wajib'])<span class="badge bg-secondary ms-1">selalu hidup</span>@endif
            <div class="small text-muted">{{ $t['ket'] }}</div>
            <div class="small mt-1 d-flex flex-wrap align-items-center gap-2">
                <span><i class="bi bi-calendar-event me-1"></i>{{ $t['waktu'] }}@if($t['jam'] && ! $t['ubah_jam']) jam {{ $t['jam'] }}@endif</span>
                @if($t['ubah_jam'])
                <span>jam <input type="time" name="jam[{{ $kode }}]" value="{{ old("jam.$kode", $r->jam) }}" class="form-control form-control-sm d-inline-block" style="width:110px" data-uji="jam-{{ $kode }}"></span>
                @endif
                @if($kode === 'stok_opname_pengingat')
                <span>mulai <select name="hari_sebelum" class="form-select form-select-sm d-inline-block" style="width:auto" data-uji="hari-sebelum">
                    @for($h = 1; $h <= 10; $h++)<option value="{{ $h }}" @selected((int) old('hari_sebelum', $hariPengingat) === $h)>H-{{ $h }}</option>@endfor</select> akhir bulan</span>
                @endif
            </div>
            <div class="small mt-1" data-uji="terakhir-{{ $kode }}">
                @if($r->terakhir_selesai)
                <span class="badge bg-{{ $r->terakhir_status === 'berhasil' ? 'success' : 'danger' }}">{{ $r->terakhir_status === 'berhasil' ? 'Berhasil' : 'Gagal' }}</span>
                <span class="text-muted">{{ $r->terakhir_selesai->locale('id')->diffForHumans() }} ({{ $r->terakhir_oleh === 'manual' ? 'dijalankan manual' : 'terjadwal' }})</span>
                @if($r->terakhir_pesan)<div class="text-muted text-truncate" title="{{ $r->terakhir_pesan }}">{{ $r->terakhir_pesan }}</div>@endif
                @else
                <span class="text-muted">Belum pernah berjalan.</span>
                @endif
            </div>
            <button type="submit" form="jalankan_{{ $kode }}" class="btn btn-sm btn-outline-primary text-nowrap mt-1" data-uji="jalankan-{{ $kode }}"><i class="bi bi-play-fill"></i> Jalankan sekarang</button>
        </div>
    </div>
</div>
@endforeach
</div>
<div class="card-footer py-2 d-flex justify-content-between align-items-center gap-2 flex-wrap">
    <span class="small text-muted">Tugas yang dimatikan tidak berjalan otomatis, tetapi tetap bisa dijalankan manual.</span>
    <button class="btn btn-primary btn-sm" data-uji="simpan-jadwal"><i class="bi bi-save me-1"></i>Simpan</button>
</div>
</form>
@foreach(array_keys(\App\Services\JadwalOtomatisService::TUGAS) as $kode)
<form method="POST" action="{{ route('pengaturan.jadwal.jalankan', $kode) }}" id="jalankan_{{ $kode }}" class="d-none">@csrf</form>
@endforeach

{{-- 3. Cadangan database --}}
<div class="card mb-3 border-{{ $cadanganSehat ? 'success' : 'warning' }}" data-uji="cadangan"><div class="card-body p-3">
    <div class="fw-semibold mb-1"><i class="bi bi-database-check me-1"></i>Cadangan database
        @if($cadanganSehat)<span class="badge bg-success ms-1">Aman</span>@else<span class="badge bg-warning text-dark ms-1">{{ $cadangan->isEmpty() ? 'Belum ada' : 'Perlu dicek' }}</span>@endif</div>
    <div class="small text-muted mb-2">Dibuat setiap hari oleh cron cPanel tersendiri dan disimpan {{ \App\Services\JadwalOtomatisService::SIMPAN_CADANGAN_HARI }} hari. Simpan juga salinannya di luar server sesekali (unduh di bawah).</div>
    @forelse($cadangan->take(7) as $c)
    <div class="d-flex justify-content-between align-items-center border-bottom py-1 small" data-uji="file-cadangan">
        <span>{{ $c['waktu']->format('d/m/Y H:i') }} · {{ $ukuran($c['ukuran']) }}</span>
        <a href="{{ route('pengaturan.jadwal.cadangan', $c['nama']) }}" class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-download"></i> Unduh</a>
    </div>
    @empty
    <div class="small text-danger mb-1">Belum ada file cadangan.</div>
    @endforelse
    <details class="mt-2" @if(! $cadanganSehat) open @endif>
        <summary class="small fw-semibold">Cara memasang cadangan otomatis (sekali saja)</summary>
        <ol class="small mt-2 ps-3">
            <li>Di cPanel › <strong>Terminal</strong>, jalankan sekali (membuat berkas kunci database dari .env, kata sandi tidak tampil):
                <div class="jo-kode mt-1">{{ $baris['kunci'] }}</div>
                <button type="button" class="btn btn-sm btn-outline-secondary mt-1 jo-salin" data-teks="{{ $baris['kunci'] }}"><i class="bi bi-clipboard me-1"></i>Salin</button></li>
            <li class="mt-2">cPanel › <strong>Cron Jobs</strong> › Common Settings <strong>Once Per Day (0 0 * * *)</strong>, lalu ubah kolom Hour menjadi <strong>2</strong> (jam 02.00) › Command:
                <div class="jo-kode mt-1" data-uji="baris-cadangan">{{ $baris['cadangan'] }}</div>
                <button type="button" class="btn btn-sm btn-outline-secondary mt-1 jo-salin" data-teks="{{ $baris['cadangan'] }}"><i class="bi bi-clipboard me-1"></i>Salin</button></li>
        </ol>
    </details>
</div></div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.jo-salin').forEach((b) => b.addEventListener('click', async () => {
    try { await navigator.clipboard.writeText(b.dataset.teks); b.innerHTML = '<i class="bi bi-check2 me-1"></i>Tersalin'; }
    catch (e) { const t = document.createElement('textarea'); t.value = b.dataset.teks; document.body.appendChild(t); t.select(); document.execCommand('copy'); t.remove(); b.textContent = 'Tersalin'; }
    setTimeout(() => { b.innerHTML = '<i class="bi bi-clipboard me-1"></i>Salin'; }, 2000);
}));
</script>
@endpush
