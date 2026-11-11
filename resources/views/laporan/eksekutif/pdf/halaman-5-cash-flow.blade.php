@include('laporan.eksekutif.pdf._page-header', ['judulHalaman' => 'Halaman 5 — Arus Kas'])

@php
    $ak = $arusKas;
    $trend = $overview['trend_6_bulan'];
@endphp

<table class="summary-table">
    <tr>
        <td><div class="val">Rp {{ number_format($ak['total_masuk'], 0, ',', '.') }}</div><div class="lbl">Total Kas Masuk</div></td>
        <td><div class="val">Rp {{ number_format($ak['total_keluar'], 0, ',', '.') }}</div><div class="lbl">Total Kas Keluar</div></td>
        <td><div class="val {{ $ak['net_kas'] >= 0 ? '' : '' }}">Rp {{ number_format($ak['net_kas'], 0, ',', '.') }}</div><div class="lbl">Net Arus Kas</div></td>
        <td><div class="val">{{ $overview['kas_summary']['cash_runway_bulan'] !== null ? number_format($overview['kas_summary']['cash_runway_bulan'], 1, ',', '.') . ' bulan' : '-' }}</div><div class="lbl">Estimasi Cash Runway</div></td>
    </tr>
</table>

<div class="footer-note">
    Total Masuk/Keluar di atas termasuk entri NON-TUNAI (mis. beban penyusutan aset yang tidak benar-benar memindahkan uang) — konsisten dengan Menu Laporan Keuangan -&gt; Arus Kas. Untuk gambaran kas TUNAI murni, lihat tabel "Arus Kas Tunai Saja" di bawah.
</div>

<table class="data" style="margin-top:8px">
    <tr><th colspan="2">ARUS KAS TUNAI SAJA (EXCLUDE ENTRI NON-TUNAI)</th></tr>
    <tr><td>Kas Masuk (Tunai)</td><td class="num">Rp {{ number_format($ak['tunai_only']['total_masuk'], 0, ',', '.') }}</td></tr>
    <tr><td>Kas Keluar (Tunai)</td><td class="num">Rp {{ number_format($ak['tunai_only']['total_keluar'], 0, ',', '.') }}</td></tr>
    <tr class="grand"><td>Net Kas Tunai</td><td class="num">Rp {{ number_format($ak['tunai_only']['net_kas'], 0, ',', '.') }}</td></tr>
</table>

<table class="data" style="margin-top:8px">
    <tr><th>Bulan</th><th class="num">Pendapatan</th><th class="num">Beban</th><th class="num">Selisih</th></tr>
    @foreach($trend['labels'] as $i => $label)
    @php $selisih = $trend['pendapatan'][$i] - $trend['beban'][$i]; @endphp
    <tr>
        <td>{{ $label }}</td>
        <td class="num">Rp {{ number_format($trend['pendapatan'][$i], 0, ',', '.') }}</td>
        <td class="num">Rp {{ number_format($trend['beban'][$i], 0, ',', '.') }}</td>
        <td class="num">Rp {{ number_format($selisih, 0, ',', '.') }}</td>
    </tr>
    @endforeach
</table>

<div class="footer-note">Posisi Kas saat ini: Rp {{ number_format($overview['kas_summary']['posisi_kas'], 0, ',', '.') }} — Rata-rata beban bulanan (6 bulan): Rp {{ number_format($overview['kas_summary']['rata_rata_beban_bulanan'], 0, ',', '.') }}. Detail: <a href="{{ route('laporan.keuangan.arus-kas') }}">Menu Laporan Keuangan -&gt; Arus Kas</a></div>
