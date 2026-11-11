@include('laporan.eksekutif.pdf._page-header', ['judulHalaman' => 'Halaman 6 — Drill-Down & Verifikasi Data'])

@php
    $alertBadge = ['sehat' => 'badge-sehat', 'perhatian' => 'badge-perhatian', 'kritis' => 'badge-kritis', 'tidak_ada_data' => ''];
@endphp

<div class="page-heading" style="font-size:10.5px">Verifikasi Konsistensi Antar Laporan</div>
<table class="data">
    <tr><th>Item</th><th>Sumber A</th><th class="num">Nilai A</th><th>Sumber B</th><th class="num">Nilai B</th><th class="num">Selisih</th><th>Status</th></tr>
    @foreach($crossCheck['checks'] as $c)
    <tr>
        <td>{{ $c['label'] }}</td>
        <td>{{ $c['label_a'] }}</td>
        <td class="num">Rp {{ number_format($c['nilai_a'], 2, ',', '.') }}</td>
        <td>{{ $c['label_b'] }}</td>
        <td class="num">Rp {{ number_format($c['nilai_b'], 2, ',', '.') }}</td>
        <td class="num">Rp {{ number_format($c['selisih'], 2, ',', '.') }}</td>
        <td><span class="badge {{ $c['status'] === 'MATCH' ? 'badge-match' : 'badge-mismatch' }}">{{ $c['status'] }}</span></td>
    </tr>
    @endforeach
</table>
<div class="footer-note">
    Toleransi selisih &lt; Rp0,01. "Total Beban (Operasional + Lain-lain, di luar HPP)" adalah verifikasi 2 jalur agregasi kode independen (Laba Rugi Formal vs akumulasi Buku Besar) — HPP sengaja dikecualikan dari cek ini karena bersumber dari order_items.hpp (FIFO cost barang terjual) yang tidak pernah tercatat sebagai baris Buku Besar, bukan bug. 3 check lain memvalidasi konsistensi parameter antar titik pemanggilan service yang sama (bukan re-derivasi independen) — MISMATCH di sini nyaris pasti bug integrasi.
    {{ $crossCheck['semua_match'] ? 'Semua check MATCH — data konsisten di seluruh laporan.' : 'ADA MISMATCH — perlu investigasi sebelum laporan ini dipakai untuk keputusan.' }}
</div>

<div class="page-heading" style="font-size:10.5px; margin-top:10px">Breakdown Semua Kategori Beban</div>
<table class="data">
    <tr><th>Kode Akun</th><th>Nama</th><th class="num">Jumlah</th><th class="num">% dari Total Beban</th><th class="num">Rasio vs Pendapatan</th><th>Alert</th></tr>
    @forelse($bebanRingkasan['kategori'] as $k)
    <tr>
        <td>{{ $k['kode_akun'] }}</td>
        <td>{{ $k['nama'] }}</td>
        <td class="num">Rp {{ number_format($k['jumlah'], 0, ',', '.') }}</td>
        <td class="num">{{ number_format($k['persen_dari_total_beban'], 1, ',', '.') }}%</td>
        <td class="num">{{ $k['rasio_vs_pendapatan'] !== null ? number_format($k['rasio_vs_pendapatan'], 1, ',', '.') . '%' : '-' }}</td>
        <td><span class="badge {{ $alertBadge[$k['alert']] ?? '' }}">{{ strtoupper($k['alert']) }}</span></td>
    </tr>
    @empty
    <tr><td colspan="6">Tidak ada beban tercatat di periode ini.</td></tr>
    @endforelse
    <tr class="grand"><td colspan="2">Total Beban</td><td class="num">Rp {{ number_format($bebanRingkasan['total_beban'], 0, ',', '.') }}</td><td colspan="3"></td></tr>
</table>
<div class="footer-note">Alert "KRITIS"/"PERHATIAN" pakai threshold sederhana (rasio beban terhadap pendapatan >=50% / >=30%) — bukan standar baku industri, murni heuristik internal. Detail transaksi per akun: <a href="{{ route('laporan.buku-besar.index') }}">Menu Buku Besar</a></div>

<div class="footer-note" style="margin-top:10px">Laporan Eksekutif ini murni compose dari Laporan Neraca, Laba Rugi Formal, BEP Otomatis, dan Buku Besar yang sudah ada di sistem — dapat diverifikasi ulang kapan saja melalui menu masing-masing.</div>
