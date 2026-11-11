@include('laporan.eksekutif.pdf._page-header', ['judulHalaman' => 'Halaman 4 — Analisa Break Even Point (BEP)'])

@php $b = $bep; @endphp

<table class="summary-table">
    <tr>
        <td><div class="val">{{ number_format($b['bep_unit'] ?? 0, 2, ',', '.') }} kg</div><div class="lbl">BEP Unit</div></td>
        <td><div class="val">Rp {{ number_format($b['bep_rupiah'] ?? 0, 0, ',', '.') }}</div><div class="lbl">BEP Rupiah</div></td>
        <td><div class="val">{{ number_format($b['volume_aktual'], 2, ',', '.') }} kg</div><div class="lbl">Volume Aktual</div></td>
        <td><div class="val">{{ $b['persentase_tercapai'] !== null ? number_format($b['persentase_tercapai'], 1, ',', '.') . '%' : '-' }}</div><div class="lbl">% Tercapai</div></td>
    </tr>
</table>

@if(!$b['bisa_bep'])
<div class="kesimpulan-item kesimpulan-perhatian">BEP tidak bisa dihitung — Harga Jual per kg &lt;= Biaya Variabel per kg (margin kontribusi negatif/nol).</div>
@endif

<table class="data">
    <tr><th colspan="2">KOMPONEN PERHITUNGAN</th></tr>
    <tr><td>Biaya Tetap (bulan berjalan)</td><td class="num">Rp {{ number_format($b['biaya_tetap'], 0, ',', '.') }}</td></tr>
    <tr><td>Biaya Variabel / kg (HPP jasa giling)</td><td class="num">Rp {{ number_format($b['biaya_variabel_per_unit'], 2, ',', '.') }}</td></tr>
    <tr><td>Harga Jual rata-rata / kg</td><td class="num">Rp {{ number_format($b['harga_jual_per_unit'], 2, ',', '.') }}</td></tr>
    <tr class="subtotal"><td>Margin Kontribusi / kg</td><td class="num">Rp {{ number_format($b['margin_kontribusi_per_unit'], 2, ',', '.') }}</td></tr>
    <tr><td>Margin of Safety</td><td class="num">{{ $b['margin_of_safety_persen'] !== null ? number_format($b['margin_of_safety_persen'], 1, ',', '.') . '%' : '-' }}</td></tr>
    <tr class="grand"><td>Estimasi Laba pada Volume Aktual</td><td class="num">Rp {{ number_format($b['estimasi_laba'], 0, ',', '.') }}</td></tr>
</table>

@if($b['kekurangan_kg'] !== null && $b['kekurangan_kg'] > 0)
<div class="kesimpulan-item kesimpulan-perhatian">
    Kekurangan volume untuk BEP: <strong>{{ number_format($b['kekurangan_kg'], 2, ',', '.') }} kg</strong>.
    @if($b['estimasi_hari_menuju_bep'] !== null)
        Estimasi {{ number_format($b['estimasi_hari_menuju_bep'], 0, ',', '.') }} hari lagi jika rata-rata produksi harian saat ini konsisten.
    @endif
</div>
@elseif($b['bisa_bep'])
<div class="kesimpulan-item kesimpulan-positif">BEP sudah tercapai pada volume aktual saat ini.</div>
@endif

<table class="data">
    <tr><th colspan="2">BREAKDOWN BIAYA TETAP PER KATEGORI</th></tr>
    @forelse($b['biaya_tetap_detail'] as $d)
    <tr><td>{{ $d['nama_kategori'] }}</td><td class="num">Rp {{ number_format($d['jumlah'], 0, ',', '.') }}</td></tr>
    @empty
    <tr><td colspan="2">Tidak ada transaksi biaya tetap di periode ini.</td></tr>
    @endforelse
    <tr class="grand"><td>Total</td><td class="num">Rp {{ number_format($b['biaya_tetap'], 0, ',', '.') }}</td></tr>
</table>

<div class="footer-note">Fokus lini Jasa Giling, periode {{ $mulaiBulan->format('d/m/Y') }} s.d {{ $akhirTanggal->format('d/m/Y') }}. Detail &amp; chart interaktif: <a href="{{ route('laporan.bep-otomatis.index') }}">Menu BEP Otomatis</a></div>
