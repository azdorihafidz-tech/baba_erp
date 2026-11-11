@extends('laporan.pdf.layout')

@section('content')
<table class="data">
    <thead>
        <tr><th>Parent Kategori</th><th>Kategori</th><th>Tipe</th><th class="num">Jumlah Transaksi</th><th class="num">Total</th></tr>
    </thead>
    <tbody>
        @forelse($rows as $r)
        <tr>
            <td>{{ $r->parent_nama ?? '-' }}</td>
            <td>{{ $r->kategori_nama ?? 'Lainnya' }}</td>
            <td>{{ ucfirst($r->tipe) }}</td>
            <td class="num">{{ $r->jumlah_transaksi }}</td>
            <td class="num">Rp {{ number_format($r->total, 0, ',', '.') }}</td>
        </tr>
        @empty
        <tr><td colspan="5" class="empty-note">Tidak ada data untuk filter yang dipilih.</td></tr>
        @endforelse
    </tbody>
    @if($rows->isNotEmpty())
    <tfoot>
        <tr class="grand">
            <td colspan="4">Total Pemasukan: Rp {{ number_format($totalPemasukan, 0, ',', '.') }} — Total Pengeluaran: Rp {{ number_format($totalPengeluaran, 0, ',', '.') }}</td>
            <td></td>
        </tr>
    </tfoot>
    @endif
</table>
@endsection
