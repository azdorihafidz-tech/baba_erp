@extends('laporan.pdf.layout')

@section('content')
<table class="data">
    <thead>
        <tr><th>Karyawan</th><th>Cabang</th><th class="num">Skor Akhir</th><th class="center">Predikat</th></tr>
    </thead>
    <tbody>
        @forelse($evaluations as $e)
        <tr>
            <td>{{ $e->karyawan?->nama_lengkap ?? '-' }}</td>
            <td>{{ $e->cabang?->nama_cabang ?? '-' }}</td>
            <td class="num">{{ number_format($e->skor_akhir, 2) }}</td>
            <td class="center">{{ $e->predikat?->label() ?? '-' }}</td>
        </tr>
        @empty
        <tr><td colspan="4" class="empty-note">Tidak ada data untuk filter yang dipilih.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
