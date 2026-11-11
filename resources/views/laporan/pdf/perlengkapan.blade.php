@extends('laporan.pdf.layout')

@section('content')
<table class="data" style="margin-bottom:14px">
    <tbody>
        <tr><td style="width:40%"><strong>Total Nilai</strong></td><td>Rp {{ number_format($ringkasan['total_nilai'], 0, ',', '.') }}</td></tr>
        <tr><td><strong>Jumlah Kejadian</strong></td><td>{{ $ringkasan['jumlah_kejadian'] }}</td></tr>
        <tr><td><strong>Item Berbeda Terpakai</strong></td><td>{{ $ringkasan['jumlah_item'] }}</td></tr>
    </tbody>
</table>

<table class="data">
    <thead>
        <tr>
            <th>Tanggal</th>
            <th>Item</th>
            <th>Cabang</th>
            <th class="text-end">Qty</th>
            <th class="text-end">Nilai</th>
            <th>Keterangan</th>
        </tr>
    </thead>
    <tbody>
        @forelse($detail as $d)
        <tr>
            <td>{{ \Carbon\Carbon::parse($d->tanggal_pemakaian)->format('d/m/Y') }}</td>
            <td>{{ $d->nama_item }}</td>
            <td>{{ $d->nama_cabang }}</td>
            <td class="text-end">{{ number_format($d->qty, 3, ',', '.') }} {{ $d->satuan }}</td>
            <td class="text-end">Rp {{ number_format($d->nilai, 0, ',', '.') }}</td>
            <td>{{ $d->keterangan ?: '-' }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="6" class="empty-note">Belum ada data untuk periode ini.</td>
        </tr>
        @endforelse
    </tbody>
</table>
@endsection
