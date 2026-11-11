@extends('laporan.pdf.layout')

@section('content')
<table class="data">
    <thead>
        <tr><th>Tanggal</th><th>Karyawan</th><th>Cabang</th><th class="center">Status</th><th>Jam Masuk</th><th>Jam Keluar</th><th class="num">Lembur</th><th>Keterangan</th></tr>
    </thead>
    <tbody>
        @forelse($absensis as $a)
        <tr>
            <td>{{ $a->tanggal?->format('d/m/Y') ?? '-' }}</td>
            <td>{{ $a->karyawan?->nama_lengkap ?? '-' }}</td>
            <td>{{ $a->cabang?->nama_cabang ?? '-' }}</td>
            <td class="center">{{ ucfirst($a->status) }}</td>
            <td>{{ $a->jam_masuk ?? '-' }}</td>
            <td>{{ $a->jam_keluar ?? '-' }}</td>
            <td class="num">{{ number_format($a->jam_lembur ?? 0, 1) }}</td>
            <td>{{ $a->keterangan ?? '-' }}</td>
        </tr>
        @empty
        <tr><td colspan="8" class="empty-note">Tidak ada data untuk filter yang dipilih.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
