@extends('laporan.pdf.layout')

@section('content')
@php
    $produk = $jenis === 'produk';
    $anakKey = $produk ? 'varian' : 'produk';
    $kolomLabel = 4; // No + nama + kolom ke-3 + Qty
    $jumlahKolom = 7 + ($lihatUntung ? 3 : 0);
    $q = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',');
@endphp

<table class="data" style="margin-bottom:10px">
    <tr>
        <td><strong>{{ $produk ? 'Produk terjual' : 'Kategori' }}:</strong> {{ $baris->count() }}</td>
        <td><strong>Total qty:</strong> {{ $q($total['qty']) }}</td>
        <td><strong>Pendapatan:</strong> {{ fmt_rupiah($total['pendapatan']) }}</td>
        @if($lihatUntung)
        <td><strong>Keuntungan:</strong> {{ fmt_rupiah($total['keuntungan']) }}</td>
        @endif
    </tr>
</table>

<table class="data">
    <thead>
        <tr>
            <th style="width:22px">No</th>
            @if($produk)
            <th>Produk</th>
            <th>Kategori</th>
            <th style="text-align:right">Qty</th>
            @else
            <th>Kategori</th>
            <th style="text-align:right">Produk</th>
            <th style="text-align:right">Qty</th>
            @endif
            <th style="text-align:right">Penjualan</th>
            <th style="text-align:right">Diskon</th>
            <th style="text-align:right">Pendapatan</th>
            @if($lihatUntung)
            <th style="text-align:right">Modal (HPP)</th>
            <th style="text-align:right">Keuntungan</th>
            <th style="text-align:right">Margin</th>
            @endif
        </tr>
    </thead>
    <tbody>
        @forelse($baris as $i => $r)
        <tr>
            <td class="center">{{ $i + 1 }}</td>
            @if($produk)
            <td><strong>{{ $r['nama'] }}</strong>@if($r['kode'])<br><span style="color:#94a3b8;font-size:8px">{{ $r['kode'] }}</span>@endif</td>
            <td>{{ $r['kategori'] }}</td>
            <td class="num">{{ $q($r['qty']) }} {{ $r['satuan'] }}</td>
            @else
            <td><strong>{{ $r['kategori'] }}</strong></td>
            <td class="num">{{ $r['jumlah_produk'] }}</td>
            <td class="num">{{ $q($r['qty']) }}</td>
            @endif
            <td class="num">{{ fmt_rupiah($r['penjualan']) }}</td>
            <td class="num">{{ $r['diskon'] > 0 ? '− ' . fmt_rupiah($r['diskon']) : '-' }}</td>
            <td class="num"><strong>{{ fmt_rupiah($r['pendapatan']) }}</strong></td>
            @if($lihatUntung)
            <td class="num">{{ fmt_rupiah($r['hpp']) }}</td>
            <td class="num">{{ fmt_rupiah($r['keuntungan']) }}</td>
            <td class="num">{{ $r['margin'] !== null ? $r['margin'] . '%' : '-' }}</td>
            @endif
        </tr>
        @foreach(($r[$anakKey] ?? collect()) as $anak)
        <tr style="color:#64748b;font-size:9px">
            <td></td>
            @if($produk)
            <td style="padding-left:14px">• {{ $anak['nama'] }}</td>
            <td></td>
            <td class="num">{{ $q($anak['qty']) }}</td>
            @else
            <td style="padding-left:14px">• {{ $anak['nama'] }}</td>
            <td></td>
            <td class="num">{{ $q($anak['qty']) }}</td>
            @endif
            <td class="num">{{ fmt_rupiah($anak['penjualan']) }}</td>
            <td class="num">{{ $anak['diskon'] > 0 ? '− ' . fmt_rupiah($anak['diskon']) : '-' }}</td>
            <td class="num">{{ fmt_rupiah($anak['pendapatan']) }}</td>
            @if($lihatUntung)
            <td class="num">{{ fmt_rupiah($anak['hpp']) }}</td>
            <td class="num">{{ fmt_rupiah($anak['keuntungan']) }}</td>
            <td class="num">{{ $anak['margin'] !== null ? $anak['margin'] . '%' : '-' }}</td>
            @endif
        </tr>
        @endforeach
        @empty
        <tr><td colspan="{{ $jumlahKolom }}" class="empty-note">Belum ada penjualan di periode ini.</td></tr>
        @endforelse
        @if($baris->isNotEmpty())
        <tr class="grand">
            <td colspan="{{ $produk ? 3 : 2 }}">TOTAL</td>
            @unless($produk)<td></td>@endunless
            <td class="num">{{ $q($total['qty']) }}</td>
            <td class="num">{{ fmt_rupiah($total['penjualan']) }}</td>
            <td class="num">{{ $total['diskon'] > 0 ? '− ' . fmt_rupiah($total['diskon']) : '-' }}</td>
            <td class="num">{{ fmt_rupiah($total['pendapatan']) }}</td>
            @if($lihatUntung)
            <td class="num">{{ fmt_rupiah($total['hpp']) }}</td>
            <td class="num">{{ fmt_rupiah($total['keuntungan']) }}</td>
            <td class="num">{{ $total['pendapatan'] > 0 ? round($total['keuntungan'] / $total['pendapatan'] * 100, 1) . '%' : '-' }}</td>
            @endif
        </tr>
        @if($biayaTambahan > 0)
        <tr>
            <td colspan="{{ $kolomLabel + 2 }}">Service charge &amp; take away fee (bukan milik produk mana pun)</td>
            <td class="num">{{ fmt_rupiah($biayaTambahan) }}</td>
            @if($lihatUntung)<td colspan="3"></td>@endif
        </tr>
        <tr class="subtotal">
            <td colspan="{{ $kolomLabel + 2 }}">Total pendapatan (sama dengan Laporan Transaksi)</td>
            <td class="num">{{ fmt_rupiah($total['pendapatan'] + $biayaTambahan) }}</td>
            @if($lihatUntung)<td colspan="3"></td>@endif
        </tr>
        @endif
        @endif
    </tbody>
</table>

<p style="font-size:9px;color:#64748b">
    Penjualan = harga × qty di keranjang. Diskon = diskon order dibagi ke setiap produk sesuai porsi harganya.
    Pendapatan = penjualan − diskon. Transaksi dibatalkan, dihapus, atau bill tersimpan yang belum dibayar tidak dihitung.
</p>
@endsection
