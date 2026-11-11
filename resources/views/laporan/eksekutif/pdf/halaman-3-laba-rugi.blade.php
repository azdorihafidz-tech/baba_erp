@include('laporan.eksekutif.pdf._page-header', ['judulHalaman' => 'Halaman 3 — Kinerja Keuangan (Laba Rugi Bulan Berjalan)'])

@php $lr = $labaRugi; @endphp

<table class="summary-table">
    <tr>
        <td><div class="val">Rp {{ number_format($lr['pendapatan']['total'], 0, ',', '.') }}</div><div class="lbl">Total Pendapatan</div></td>
        <td><div class="val">Rp {{ number_format($lr['laba_kotor'], 0, ',', '.') }}</div><div class="lbl">Laba Kotor</div></td>
        <td><div class="val">Rp {{ number_format($lr['laba_usaha'], 0, ',', '.') }}</div><div class="lbl">Laba Usaha</div></td>
        <td><div class="val">Rp {{ number_format($lr['laba_bersih_setelah_pajak'], 0, ',', '.') }}</div><div class="lbl">Laba Bersih Setelah Pajak</div></td>
    </tr>
</table>

<table class="data">
    <tr class="section-head"><td colspan="2">PENDAPATAN</td></tr>
    @foreach($lr['pendapatan']['detail'] as $d)
    @if($d['jumlah'] > 0)
    <tr><td class="indent">{{ $d['kode'] }} — {{ $d['nama'] }}</td><td class="num">Rp {{ number_format($d['jumlah'], 0, ',', '.') }}</td></tr>
    @endif
    @endforeach
    <tr class="subtotal"><td>Total Pendapatan</td><td class="num">Rp {{ number_format($lr['pendapatan']['total'], 0, ',', '.') }}</td></tr>

    <tr class="section-head"><td colspan="2">HARGA POKOK PENJUALAN (HPP)</td></tr>
    @foreach($lr['hpp']['detail'] as $d)
    @if($d['jumlah'] > 0)
    <tr><td class="indent">{{ $d['kode'] }} — {{ $d['nama'] }}</td><td class="num">Rp {{ number_format($d['jumlah'], 0, ',', '.') }}</td></tr>
    @endif
    @endforeach
    <tr class="subtotal"><td>Total HPP</td><td class="num">(Rp {{ number_format($lr['hpp']['total'], 0, ',', '.') }})</td></tr>
    <tr class="grand"><td>LABA KOTOR</td><td class="num">Rp {{ number_format($lr['laba_kotor'], 0, ',', '.') }}</td></tr>

    <tr class="section-head"><td colspan="2">BEBAN OPERASIONAL</td></tr>
    @foreach($lr['beban_operasional']['detail'] as $d)
    @if($d['jumlah'] > 0)
    <tr><td class="indent">{{ $d['kode'] }} — {{ $d['nama'] }}</td><td class="num">Rp {{ number_format($d['jumlah'], 0, ',', '.') }}</td></tr>
    @endif
    @endforeach
    <tr class="subtotal"><td>Total Beban Operasional</td><td class="num">(Rp {{ number_format($lr['beban_operasional']['total'], 0, ',', '.') }})</td></tr>
    <tr class="grand"><td>LABA USAHA</td><td class="num">Rp {{ number_format($lr['laba_usaha'], 0, ',', '.') }}</td></tr>

    @if($lr['pendapatan_lain']['total'] > 0 || $lr['beban_lain']['total'] > 0)
    <tr class="section-head"><td colspan="2">PENDAPATAN &amp; BEBAN LAIN-LAIN</td></tr>
    <tr><td class="indent">Pendapatan Lain-lain</td><td class="num">Rp {{ number_format($lr['pendapatan_lain']['total'], 0, ',', '.') }}</td></tr>
    <tr><td class="indent">Beban Lain-lain</td><td class="num">(Rp {{ number_format($lr['beban_lain']['total'], 0, ',', '.') }})</td></tr>
    @endif

    <tr class="grand"><td>LABA BERSIH SEBELUM PAJAK</td><td class="num">Rp {{ number_format($lr['laba_bersih_sebelum_pajak'], 0, ',', '.') }}</td></tr>
    <tr><td class="indent">Pajak Penghasilan</td><td class="num">(Rp {{ number_format($lr['pajak_penghasilan'], 0, ',', '.') }})</td></tr>
    <tr class="grand"><td>LABA BERSIH SETELAH PAJAK</td><td class="num">Rp {{ number_format($lr['laba_bersih_setelah_pajak'], 0, ',', '.') }}</td></tr>
</table>

<div class="footer-note">Dikelompokkan per Chart of Accounts (SAK ETAP), periode {{ $mulaiBulan->format('d/m/Y') }} s.d {{ $akhirTanggal->format('d/m/Y') }}. Detail: <a href="{{ route('laporan.laba-rugi-formal.index') }}">Menu Laba Rugi Formal</a></div>
