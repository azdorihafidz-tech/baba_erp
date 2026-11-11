@include('laporan.eksekutif.pdf._page-header', ['judulHalaman' => 'Halaman 2 — Posisi Keuangan (Neraca)'])

@php $n = $neraca; @endphp

@if($n['balance_check'])
<div class="kesimpulan-item kesimpulan-positif">Neraca BALANCE — Total Aset Rp {{ number_format($n['aset']['total_aset'], 0, ',', '.') }} = Total Kewajiban + Modal Rp {{ number_format($n['total_kewajiban_modal'], 0, ',', '.') }}</div>
@else
<div class="kesimpulan-item kesimpulan-perhatian">Neraca BELUM BALANCE — Selisih Rp {{ number_format($n['selisih'], 0, ',', '.') }}. Wajar untuk data historis pra-COA (lihat CLAUDE.md Rule #46) — Modal Owner adalah figure yang bisa di-adjust manual Owner.</div>
@endif

<table class="layout">
<tr>
<td style="width:50%; padding-right:6px; vertical-align:top;">
    <table class="data">
        <tr><th colspan="2">ASET</th></tr>
        <tr class="section-head"><td colspan="2">Aset Lancar</td></tr>
        <tr><td class="indent">Kas &amp; Setara Kas</td><td class="num">Rp {{ number_format($n['aset']['lancar']['kas_setara'], 0, ',', '.') }}</td></tr>
        <tr><td class="indent">Piutang Usaha</td><td class="num">Rp {{ number_format($n['aset']['lancar']['piutang'], 0, ',', '.') }}</td></tr>
        <tr><td class="indent">Persediaan Bahan Baku</td><td class="num">Rp {{ number_format($n['aset']['lancar']['persediaan_bahan_baku'], 0, ',', '.') }}</td></tr>
        <tr><td class="indent">Persediaan Barang Jadi</td><td class="num">Rp {{ number_format($n['aset']['lancar']['persediaan_barang_jadi'], 0, ',', '.') }}</td></tr>
        <tr><td class="indent">Persediaan Kemasan</td><td class="num">Rp {{ number_format($n['aset']['lancar']['persediaan_kemasan'], 0, ',', '.') }}</td></tr>
        <tr class="subtotal"><td>Total Aset Lancar</td><td class="num">Rp {{ number_format($n['aset']['lancar']['total'], 0, ',', '.') }}</td></tr>

        <tr class="section-head"><td colspan="2">Aset Tetap</td></tr>
        <tr><td class="indent">Aset Bruto</td><td class="num">Rp {{ number_format($n['aset']['tetap']['aset_bruto'], 0, ',', '.') }}</td></tr>
        <tr><td class="indent">Akumulasi Depresiasi</td><td class="num">(Rp {{ number_format($n['aset']['tetap']['akumulasi_depresiasi'], 0, ',', '.') }})</td></tr>
        <tr class="subtotal"><td>Nilai Buku Aset Tetap</td><td class="num">Rp {{ number_format($n['aset']['tetap']['nilai_buku'], 0, ',', '.') }}</td></tr>

        <tr class="grand"><td>TOTAL ASET</td><td class="num">Rp {{ number_format($n['aset']['total_aset'], 0, ',', '.') }}</td></tr>
    </table>
</td>
<td style="width:50%; padding-left:6px; vertical-align:top;">
    <table class="data">
        <tr><th colspan="2">KEWAJIBAN</th></tr>
        <tr><td>Hutang Usaha</td><td class="num">Rp {{ number_format($n['kewajiban']['jangka_pendek']['hutang_usaha'], 0, ',', '.') }}</td></tr>
        <tr><td>Hutang Pajak</td><td class="num">Rp {{ number_format($n['kewajiban']['jangka_pendek']['hutang_pajak'], 0, ',', '.') }}</td></tr>
        <tr><td>Hutang Bank (Jk. Panjang)</td><td class="num">Rp {{ number_format($n['kewajiban']['jangka_panjang']['hutang_bank'], 0, ',', '.') }}</td></tr>
        <tr class="grand"><td>TOTAL KEWAJIBAN</td><td class="num">Rp {{ number_format($n['kewajiban']['total_kewajiban'], 0, ',', '.') }}</td></tr>
    </table>

    <table class="data">
        <tr><th colspan="2">MODAL</th></tr>
        <tr><td>Modal Owner</td><td class="num">Rp {{ number_format($n['modal']['modal_owner'], 0, ',', '.') }}</td></tr>
        <tr><td>Laba Ditahan</td><td class="num">Rp {{ number_format($n['modal']['laba_ditahan'], 0, ',', '.') }}</td></tr>
        <tr class="grand"><td>TOTAL MODAL</td><td class="num">Rp {{ number_format($n['modal']['total_modal'], 0, ',', '.') }}</td></tr>
    </table>

    <table class="data">
        <tr class="grand"><td>TOTAL KEWAJIBAN + MODAL</td><td class="num">Rp {{ number_format($n['total_kewajiban_modal'], 0, ',', '.') }}</td></tr>
    </table>
</td>
</tr>
</table>

<div class="footer-note">Kas, Persediaan, dan Nilai Buku Aset mencerminkan kondisi terkini sistem — hanya Laba Ditahan yang menghormati tanggal cutoff. Detail: <a href="{{ route('laporan.neraca.index') }}">Menu Neraca</a></div>
