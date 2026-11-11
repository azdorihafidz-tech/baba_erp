@extends('laporan.pdf.layout')

@section('content')
@php
    $isTransaksi = $level === 'transaksi';
    $namaKolom = match ($level) { 'tahun' => 'Tahun', 'bulan' => 'Bulan', 'hari' => 'Tanggal', default => 'Jam' };
    $kolom = ($isTransaksi ? ($semuaCabang ? 6 : 5) : 2) + 1 + ($lihatUntung ? 2 : 0);
@endphp

<table class="data" style="margin-bottom:10px">
    <tr>
        <td><strong>Jumlah Transaksi:</strong> {{ number_format($total['transaksi'], 0, ',', '.') }}</td>
        <td><strong>Pendapatan:</strong> {{ fmt_rupiah($total['pendapatan']) }}</td>
        @if($lihatUntung)
        <td><strong>Keuntungan:</strong> {{ fmt_rupiah($total['keuntungan']) }}</td>
        @endif
    </tr>
</table>

<table class="data">
    <thead>
        <tr>
            @if($isTransaksi)
            <th>Jam</th>
            <th>No. Order</th>
            @if($semuaCabang)<th>Cabang</th>@endif
            <th>Pelanggan</th>
            <th>Tipe</th>
            <th>Metode</th>
            @else
            <th>{{ $namaKolom }}</th>
            <th style="text-align:right">Transaksi</th>
            @endif
            <th style="text-align:right">Pendapatan</th>
            @if($lihatUntung)
            <th style="text-align:right">Modal (HPP)</th>
            <th style="text-align:right">Keuntungan</th>
            @endif
        </tr>
    </thead>
    <tbody>
        @forelse($baris as $r)
        <tr>
            @if($isTransaksi)
            <td>{{ $r['jam'] }}</td>
            <td>{{ $r['nomor'] }}</td>
            @if($semuaCabang)<td>{{ $r['cabang'] }}</td>@endif
            <td>{{ $r['pelanggan'] }}</td>
            <td>{{ $r['tipe'] }}</td>
            <td>{{ $r['metode'] }}</td>
            @else
            <td>{{ $r['label'] }}</td>
            <td class="num">{{ number_format($r['transaksi'], 0, ',', '.') }}</td>
            @endif
            <td class="num">{{ fmt_rupiah($r['pendapatan']) }}</td>
            @if($lihatUntung)
            <td class="num">{{ fmt_rupiah($r['hpp']) }}</td>
            <td class="num">{{ fmt_rupiah($r['keuntungan']) }}</td>
            @endif
        </tr>
        @empty
        <tr><td colspan="{{ $kolom }}" class="empty-note">Belum ada transaksi.</td></tr>
        @endforelse
        @if($baris->isNotEmpty())
        <tr class="grand">
            @if($isTransaksi)
            <td colspan="{{ $semuaCabang ? 6 : 5 }}">TOTAL ({{ number_format($total['transaksi'], 0, ',', '.') }} transaksi)</td>
            @else
            <td>TOTAL</td>
            <td class="num">{{ number_format($total['transaksi'], 0, ',', '.') }}</td>
            @endif
            <td class="num">{{ fmt_rupiah($total['pendapatan']) }}</td>
            @if($lihatUntung)
            <td class="num">{{ fmt_rupiah($total['hpp']) }}</td>
            <td class="num">{{ fmt_rupiah($total['keuntungan']) }}</td>
            @endif
        </tr>
        @endif
    </tbody>
</table>

<p style="font-size:9px;color:#64748b">
    Pendapatan = uang yang dibayar pelanggan (setelah diskon, termasuk service charge &amp; take away fee).
    Transaksi dibatalkan, dihapus, atau bill tersimpan yang belum dibayar tidak dihitung.
</p>
@endsection
