@extends('laporan.pdf.layout')

@section('content')
<table class="data">
    <thead>
        <tr>
            <th>Tanggal</th>
            <th>Cabang</th>
            <th class="num">Total Order</th>
            <th class="num">Total Pemasukan</th>
            <th class="num">Total Pengeluaran</th>
            <th class="num">Kas Bersih</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $row)
        <tr class="{{ $row->kas_bersih < 0 ? 'highlight-danger' : '' }}">
            <td>{{ \Carbon\Carbon::parse($row->tanggal)->format('d/m/Y') }}</td>
            <td>{{ $row->cabang_nama }}</td>
            <td class="num">{{ $row->jumlah_order }}</td>
            <td class="num">Rp {{ number_format($row->total_pemasukan, 0, ',', '.') }}</td>
            <td class="num">Rp {{ number_format($row->total_pengeluaran, 0, ',', '.') }}</td>
            <td class="num">Rp {{ number_format($row->kas_bersih, 0, ',', '.') }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="6" class="empty-note">Tidak ada data untuk filter yang dipilih.</td>
        </tr>
        @endforelse
    </tbody>
    @if($rows->isNotEmpty())
    <tfoot>
        <tr class="grand">
            <td colspan="2">Total {{ $rows->count() }} baris</td>
            <td class="num">{{ $rows->sum('jumlah_order') }}</td>
            <td class="num">Rp {{ number_format($rows->sum('total_pemasukan'), 0, ',', '.') }}</td>
            <td class="num">Rp {{ number_format($rows->sum('total_pengeluaran'), 0, ',', '.') }}</td>
            <td class="num">Rp {{ number_format($rows->sum('kas_bersih'), 0, ',', '.') }}</td>
        </tr>
    </tfoot>
    @endif
</table>
@endsection
