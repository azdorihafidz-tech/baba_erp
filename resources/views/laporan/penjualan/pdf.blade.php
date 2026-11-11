@extends('laporan.pdf.layout')

@section('content')
<table class="data">
    <thead>
        <tr>
            <th>No. Order</th>
            <th>Tanggal</th>
            <th>Cabang</th>
            <th>Pelanggan</th>
            <th>Tipe</th>
            <th class="num">Total Bayar</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        @forelse($orders as $order)
        <tr>
            <td>{{ $order->nomor_order }}</td>
            <td>{{ $order->tanggal_order?->format('d/m/Y') ?? '-' }}</td>
            <td>{{ $order->cabang?->nama_cabang ?? '-' }}</td>
            <td>{{ $order->nama_pelanggan ?? $order->pelanggan?->nama ?? 'Umum' }}</td>
            <td>{{ $order->tipe_order?->label() ?? '-' }}</td>
            <td class="num">Rp {{ number_format($order->total_bayar, 0, ',', '.') }}</td>
            <td>{{ $order->status?->label() ?? '-' }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="7" class="empty-note">Tidak ada data pada periode ini.</td>
        </tr>
        @endforelse
    </tbody>
    @if($orders->isNotEmpty())
    <tfoot>
        <tr class="grand">
            <td colspan="4">Total {{ $orders->count() }} transaksi</td>
            <td colspan="1"></td>
            <td class="num">Rp {{ number_format($totalOmzet, 0, ',', '.') }}</td>
            <td></td>
        </tr>
    </tfoot>
    @endif
</table>
@endsection
