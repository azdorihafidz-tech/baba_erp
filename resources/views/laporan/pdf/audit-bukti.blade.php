@extends('laporan.pdf.layout')

@section('content')
<table class="data">
    <thead>
        <tr>
            <th>Tanggal</th>
            <th>No. Transaksi</th>
            <th>Keterangan</th>
            <th>Kategori</th>
            <th>Cabang</th>
            <th class="text-end">Jumlah</th>
            <th>Status Bukti</th>
        </tr>
    </thead>
    <tbody>
        @forelse($transaksis as $r)
        <tr class="{{ $r->bukti_path ? '' : 'highlight-danger' }}">
            <td>{{ optional($r->tanggal_transaksi)->format('d/m/Y') }}</td>
            <td>{{ $r->nomor_transaksi }}</td>
            <td>{{ $r->keterangan }}</td>
            <td>{{ $r->kategoriDinamis?->nama ?? '-' }}</td>
            <td>{{ $r->cabang?->nama_cabang ?? '-' }}</td>
            <td class="text-end">Rp {{ number_format($r->jumlah, 0, ',', '.') }}</td>
            <td>{{ $r->bukti_path ? 'Sudah Upload' : 'BELUM UPLOAD' }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="7" class="empty-note">Tidak ada transaksi di atas threshold pada periode ini.</td>
        </tr>
        @endforelse
    </tbody>
</table>
@endsection
