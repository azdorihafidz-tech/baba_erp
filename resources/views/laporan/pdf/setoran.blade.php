@extends('laporan.pdf.layout')

@section('content')
<table class="data">
    <thead>
        <tr><th>Tanggal</th><th>No. Transaksi</th><th>Cabang Asal</th><th>Kas Asal</th><th class="num">Jumlah</th><th class="center">Status</th><th>Keterangan</th></tr>
    </thead>
    <tbody>
        @forelse($rows as $r)
        @php $status = !is_null($r->deleted_at) ? ($r->status_setoran ?? 'dihapus') : ($r->status_setoran ?? '-'); @endphp
        <tr>
            <td>{{ optional($r->tanggal_transaksi)->format('d/m/Y') ?? '-' }}</td>
            <td>{{ $r->nomor_transaksi }}</td>
            <td>{{ $r->cabang?->nama_cabang ?? '-' }}</td>
            <td>{{ $r->kas?->nama_kas ?? '-' }}</td>
            <td class="num">Rp {{ number_format($r->jumlah, 0, ',', '.') }}</td>
            <td class="center">{{ ucfirst(str_replace('_',' ',$status)) }}</td>
            <td>{{ $r->keterangan }}</td>
        </tr>
        @empty
        <tr><td colspan="7" class="empty-note">Tidak ada data untuk filter yang dipilih.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
