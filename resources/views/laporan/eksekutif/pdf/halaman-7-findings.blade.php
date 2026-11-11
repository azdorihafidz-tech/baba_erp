@include('laporan.eksekutif.pdf._page-header', ['judulHalaman' => 'Halaman 7 — Evidence-Based Findings'])

@php
    $severityBadge = ['kritis' => 'badge-kritis', 'perhatian' => 'badge-perhatian', 'positif' => 'badge-positif', 'info' => 'badge-kunci'];
    $routeMap = [
        'laporan.buku-besar.index' => 'Buku Besar',
        'laporan.penjualan' => 'Laporan Penjualan',
        'pembelian.po-dashboard.index' => 'Dashboard PO',
        'laporan.keuangan.arus-kas' => 'Laporan Keuangan → Arus Kas',
    ];
@endphp

<div class="footer-note" style="margin-bottom:8px">Setiap temuan di bawah adalah fakta konkret dari data real periode ini (nominal, nama, tanggal) — bukan kalimat generik.</div>

@foreach($findings as $f)
<table class="data" style="margin-bottom:8px">
    <tr class="section-head">
        <td style="width:70%">{{ $f['judul'] }}</td>
        <td style="text-align:right"><span class="badge {{ $severityBadge[$f['severity']] ?? '' }}">{{ strtoupper($f['severity']) }}</span></td>
    </tr>
    <tr>
        <td colspan="2">
            {{ $f['fakta'] }}
            @if(isset($f['link_route']) && isset($routeMap[$f['link_route']]))
            <br><span class="footer-link">Verifikasi: {{ $routeMap[$f['link_route']] }}</span>
            @endif
        </td>
    </tr>
</table>
@endforeach

<div class="footer-note">Sumber: BebanBreakdownService, PoDashboardService (Dashboard PO), dan query agregat langsung dari data transaksi/order/kasir periode berjalan.</div>
