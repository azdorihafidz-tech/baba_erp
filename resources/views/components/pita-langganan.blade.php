{{-- Pita status langganan & segel lisensi (porting Dimsum 4.53 (_rancangan/RANCANGAN-KAMUS-TIMIT-PAKET.md) bagian 5–6) --}}
@php
    $u = auth()->user();
    $st = \App\Support\Paket::statusLangganan();
    $segelRusak = $u?->isTeknisi() && ! \App\Support\Paket::data()['segel_valid'];
    $tgl = fn ($c) => $c?->format('d/m/Y');
@endphp
@if($u)
    @if($segelRusak)
    <div class="alert alert-danger py-2 mb-3 small"><i class="bi bi-shield-exclamation me-1"></i><strong>Tim IT:</strong> segel lisensi tidak cocok — isi paket diubah di luar aplikasi. <a href="{{ route('paket.index') }}">Periksa & simpan ulang</a>.</div>
    @endif
    @if($st['kode'] === 'segera' && $u->aksesPenuh())
    <div class="alert alert-warning py-2 mb-3 small"><i class="bi bi-calendar-event me-1"></i>Langganan aplikasi berakhir <strong>{{ $tgl($st['aktif_sampai']) }}</strong>{{ $st['sisa_hari'] > 0 ? ' (' . $st['sisa_hari'] . ' hari lagi)' : ' (hari ini)' }}. Hubungi tim IT penyedia aplikasi untuk memperpanjang.</div>
    @elseif($st['kode'] === 'tenggang')
    <div class="alert alert-danger py-2 mb-3 small"><i class="bi bi-exclamation-triangle me-1"></i>Langganan aplikasi berakhir <strong>{{ $tgl($st['aktif_sampai']) }}</strong>. Masa tenggang sampai <strong>{{ $tgl($st['batas_tenggang']) }}</strong>; setelah itu aplikasi hanya bisa dilihat (baca-saja). Hubungi tim IT penyedia aplikasi.</div>
    @elseif($st['kode'] === 'baca_saja')
    <div class="alert alert-dark py-2 mb-3 small"><i class="bi bi-lock me-1"></i><strong>Mode baca-saja</strong> — langganan berakhir {{ $tgl($st['aktif_sampai']) }}. Data dan laporan tetap bisa dilihat & diunduh, tetapi tidak bisa menyimpan atau mengubah data. Hubungi tim IT penyedia aplikasi.
        @if($u->isTeknisi())<br><em>Anda Tim IT: tidak terkena mode baca-saja.</em> <a href="{{ route('paket.index') }}">Perpanjang di Paket & Langganan</a>.@endif
    </div>
    @endif
@endif
