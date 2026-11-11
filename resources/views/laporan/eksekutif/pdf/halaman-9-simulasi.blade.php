@include('laporan.eksekutif.pdf._page-header', ['judulHalaman' => 'Halaman 9 — Simulasi 5 Skenario Balik Modal'])

@php $s = $simulasi; @endphp

<div class="kesimpulan-item kesimpulan-perhatian" style="margin-bottom:8px">
    <strong>Catatan penting:</strong> Bisnis masih baru (data produksi ~1,5 minggu, belum menjalankan marketing aktif) — volume "Volume Sedang" dan "Volume Optimis" di bawah adalah <strong>ASUMSI/TARGET yang bisa Anda sesuaikan</strong> (default: 1x dan 2x volume harian BEP), BUKAN proyeksi dari data historis. 3 skenario lain (Tren Aktual, Volume BEP, Kombinasi Efisiensi) sepenuhnya data-driven.
</div>

<table class="data" style="margin-bottom:8px">
    <tr><th colspan="2">PARAMETER DASAR (dari BEP Otomatis bulan berjalan)</th></tr>
    <tr><td>Modal Awal (Owner)</td><td class="num">Rp {{ number_format($s['modal_awal'], 0, ',', '.') }}</td></tr>
    <tr><td>Harga Jual / kg</td><td class="num">Rp {{ number_format($s['harga_jual_per_kg'], 2, ',', '.') }}</td></tr>
    <tr><td>Biaya Variabel / kg</td><td class="num">Rp {{ number_format($s['biaya_variabel_per_kg'], 2, ',', '.') }}</td></tr>
    <tr><td>Biaya Tetap Bulanan</td><td class="num">Rp {{ number_format($s['biaya_tetap_bulanan_asal'], 0, ',', '.') }}</td></tr>
</table>

<table class="data">
    <tr>
        <th>Skenario</th>
        <th class="num">Volume/Hari (kg)</th>
        <th class="num">Omzet Bulanan</th>
        <th class="num">Laba/Rugi Bulanan</th>
        <th>Proyeksi</th>
    </tr>
    @foreach($s['skenario'] as $sk)
    <tr>
        <td><strong>{{ $sk['nama'] }}</strong><br><span style="font-size:8px;color:#94a3b8">{{ $sk['deskripsi'] }}</span></td>
        <td class="num">{{ number_format($sk['volume_harian_kg'], 2, ',', '.') }}</td>
        <td class="num">Rp {{ number_format($sk['omzet_bulanan'], 0, ',', '.') }}</td>
        <td class="num" style="{{ $sk['laba_bulanan'] >= 0 ? '' : 'color:#b91c1c' }}">Rp {{ number_format($sk['laba_bulanan'], 0, ',', '.') }}</td>
        <td>
            @if($sk['waktu_balik_modal_bulan'] !== null)
                Balik modal ~{{ number_format($sk['waktu_balik_modal_bulan'], 1, ',', '.') }} bulan
            @elseif($sk['modal_habis_dalam_bulan'] !== null)
                Modal habis ~{{ number_format($sk['modal_habis_dalam_bulan'], 1, ',', '.') }} bulan
            @else
                Impas (laba ~Rp0)
            @endif
        </td>
    </tr>
    @endforeach
</table>

<div class="footer-note">
    Formula: Omzet Bulanan = Volume/Hari x 26 hari kerja x Harga Jual/kg. Laba Bulanan = Omzet - HPP - Biaya Tetap. Waktu Balik Modal = Modal Awal / Laba Bulanan (kalau untung). Modal Habis Dalam = Modal Awal / |Rugi Bulanan| (kalau rugi terus-menerus, asumsi kondisi konstan — bukan jaminan, murni proyeksi kasar).
    "Kombinasi Efisiensi" = volume Tren Aktual + pangkas {{ number_format($s['pangkas_beban_persen'], 0, ',', '.') }}% dari total Biaya Tetap.
</div>

<div class="footer-note" style="margin-top:6px">Untuk eksperimen interaktif dengan parameter custom, gunakan Menu Simulator BEP.</div>
