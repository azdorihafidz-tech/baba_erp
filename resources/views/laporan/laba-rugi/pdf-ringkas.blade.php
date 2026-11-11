@extends('laporan.pdf.layout')

@section('content')
<table class="data">
    <tbody>
        <tr class="section-head"><td colspan="2">RINGKASAN</td></tr>
        <tr><td>Total Order</td><td class="num">{{ number_format($ringkasan['total_order'], 0, ',', '.') }}</td></tr>
        <tr><td>Total Omzet</td><td class="num">Rp {{ number_format($ringkasan['total_omzet'], 0, ',', '.') }}</td></tr>
        <tr><td>Total HPP</td><td class="num">Rp {{ number_format($ringkasan['total_hpp'], 0, ',', '.') }}</td></tr>
        <tr class="subtotal">
            <td>Laba Kotor (Untung)</td>
            <td class="num">Rp {{ number_format($ringkasan['total_untung'], 0, ',', '.') }}</td>
        </tr>
        <tr class="grand">
            <td>Margin</td>
            <td class="num">{{ $ringkasan['margin'] !== null ? $ringkasan['margin'] . '%' : '-' }}</td>
        </tr>
    </tbody>
</table>

<div style="font-size:9px;color:#94a3b8;margin-top:10px;">
    Catatan: laporan ini murni profit produksi (Omzet vs HPP bahan dari transaksi POS), BUKAN cash flow —
    biaya operasional (gaji, sewa, dll) TIDAK termasuk di sini, lihat Laporan Keuangan utk itu.
    Untuk breakdown per produk/transaksi, lihat versi Detail (landscape).
</div>
@endsection
