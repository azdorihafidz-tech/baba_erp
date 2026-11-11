@extends('laporan.pdf.layout')

@section('content')
<table class="data">
    <thead>
        <tr><th>#</th><th>Cabang</th><th class="num">Pemasukan</th><th class="num">Pengeluaran</th><th class="num">Setoran Keluar</th><th class="num">Setoran Masuk</th><th class="num">Net</th></tr>
    </thead>
    <tbody>
        @forelse($ranking as $i => $r)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $r['nama_cabang'] }}</td>
            <td class="num">Rp {{ number_format($r['pemasukan'], 0, ',', '.') }}</td>
            <td class="num">Rp {{ number_format($r['pengeluaran'], 0, ',', '.') }}</td>
            <td class="num">Rp {{ number_format($r['setoran_keluar'], 0, ',', '.') }}</td>
            <td class="num">Rp {{ number_format($r['setoran_masuk'], 0, ',', '.') }}</td>
            <td class="num">Rp {{ number_format($r['net'], 0, ',', '.') }}</td>
        </tr>
        @empty
        <tr><td colspan="7" class="empty-note">Tidak ada data untuk filter yang dipilih.</td></tr>
        @endforelse
    </tbody>
    @if($ranking->isNotEmpty())
    <tfoot>
        <tr class="grand">
            <td colspan="2">TOTAL</td>
            <td class="num">Rp {{ number_format($totalPemasukan, 0, ',', '.') }}</td>
            <td class="num">Rp {{ number_format($totalPengeluaran, 0, ',', '.') }}</td>
            <td colspan="2"></td>
            <td class="num">Rp {{ number_format($totalNet, 0, ',', '.') }}</td>
        </tr>
    </tfoot>
    @endif
</table>
@endsection
